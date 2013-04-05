<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    elis
 * @subpackage core
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/elis/core/lib/setup.php');

/**
 * Base class for filters.
 */
abstract class data_filter {
    /**
     * Returns an SQL WHERE and/or JOIN clause, with parameters.
     *
     * @param bool $use_join whether or not a JOIN query should be generated
     * (if relevant)
     * @param string $tablename the name or alias of the base table
     * @param int $paramtype The type of SQL parameter to use.  Only
     * SQL_PARAMS_NAMED and SQL_PARAMS_QM are supported.  SQL_PARAMS_DOLLAR is
     * NOT supported.
     * @param moodle_database $db the database that the query will be executed
     * on
     *
     * @return array where the item at key 'join' (if present) is the JOIN
     * clause with parameter placeholders, the item at key 'join_parameters'
     * (if present) is an array of parameters for the JOIN clause, the item at
     * key 'where' (if present) is the WHERE clause with parameter
     * placeholders, and the item at key 'where_parameters' (if present) is an
     * array of parameters.  If the array has the 'join' key, then it will also
     * have the 'join_parameters' key (even if it is empty), and if it has the
     * 'where' key, then it will also have the 'where_parameters' key.  If the
     * array is empty (it does not have a 'join' or 'where' key), then it
     * should be interpreted as no filtering performed (that is, all records
     * are returned).
     */
    abstract public function get_sql($use_join=false, $tablename=null, $paramtype=SQL_PARAMS_QM, moodle_database $db=null);

    /**
     * This is public so that unit tests can have predictable table aliases.
     * Do not access this variable directly.
     */
    public static $_prefix_num = 0;

    /**
     * Returns a unique name.  Useful for table aliases for creating join
     * conditions.  The name is guaranteed to be unique across a single run of
     * a script.  The name is produced by concatenating a (configurable) prefix
     * with a counter.
     *
     * @param string $prefix the table prefix to use
     */
    protected static function _get_unique_name($prefix='table') {
        self::$_prefix_num = self::$_prefix_num + 1;
        $num = self::$_prefix_num;
        return "{$prefix}_{$num}";
    }
}

/**
 * Combine filters using AND (i.e. all filters must be satisfied).
 */
class AND_filter extends data_filter {
    /**
     * @param array $filters array of filters
     */
    public function __construct(array $filters) {
        $this->filters = $filters;
    }

    public function get_sql($use_join=false, $tablename=null, $paramtype=SQL_PARAMS_QM, moodle_database $db=null) {
        $where_clauses = array();
        $where_parameters = array();
        $join_clauses = array();
        $join_parameters = array();

        foreach ($this->filters as $filter) {
            $result = $filter->get_sql($use_join, $tablename, $paramtype, $db);
            if (isset($result['where'])) {
                $where_clauses[] = $result['where'];
                $where_parameters = array_merge($where_parameters, $result['where_parameters']);
            }
            if (isset($result['join'])) {
                $join_clauses[] = $result['join'];
                $join_parameters = array_merge($join_parameters, $result['join_parameters']);
            }
        }

        $rv = array();
        if (!empty($where_clauses)) {
            $rv['where'] = '(' . implode(")\n AND (", $where_clauses) . ')';
            $rv['where_parameters'] = $where_parameters;
        }
        if (!empty($join_clauses)) {
            $rv['join'] = implode("\n", $join_clauses);
            $rv['join_parameters'] = $join_parameters;
        }
        return $rv;
    }

    /**
     * Returns an SQL WHERE and/or JOIN clause for an array of filters.  The
     * filters are ANDed together
     *
     * @param array $filters array of filters
     * @param bool $use_join whether or not a JOIN query should be generated
     * (if relevant)
     * @param string $tablename the name or alias of the base table
     * @param moodle_database $db the database that the query will be executed
     * on
     *
     * @return array same as the get_sql method
     */
    public static function get_combined_sql(array $filters=array(), $use_join=false, $tablename=null, $paramtype=SQL_PARAMS_QM, moodle_database $db=null) {
        $filter = new AND_filter($filters);
        return $filter->get_sql($use_join, $tablename, $paramtype, $db);
    }
}
/**
 * Combine filters using OR (i.e. any filter can be satisfied).
 */
class OR_filter extends data_filter {
    /**
     * @param array $filters array of filters
     */
    public function __construct(array $filters) {
        $this->filters = $filters;
    }

    public function get_sql($use_join=false, $tablename=null, $paramtype=SQL_PARAMS_QM, moodle_database $db=null) {
        $filters = $this->filters;
        if (count($filters) == 0) {
            // no filters -- return nothing
            return array();
        }
        if (count($filters) == 1) {
            // only one filter -- just return its result
            $filter = current($filters);
            return $filter->get_sql($use_join, $tablename, $paramtype, $db);
        }
        /* otherwise, get each condition (WHERE clause only), and join with an
         * OR */
        $where_clauses = array();
        $where_parameters = array();
        foreach ($this->filters as $filter) {
            $result = $filter->get_sql(false, $tablename, $paramtype, $db);
            if (isset($result['where'])) {
                $where_clauses[] = $result['where'];
                $where_parameters = array_merge($where_parameters, $result['where_parameters']);
            } else {
                /* Filter returned empty result, which we interpret as "always
                 * true".  Since result is ORed, the result is "always true". */
                return array();
            }
        }

        $rv = array();
        if (!empty($where_clauses)) {
            $rv['where'] = '((' . implode(")\n OR (", $where_clauses) . '))';
            $rv['where_parameters'] = $where_parameters;
        }
        return $rv;
    }

    /**
     * Returns an SQL WHERE and/or JOIN clause for an array of filters.  The
     * filters are ORed together
     *
     * @param array $filters array of filters
     * @param bool $use_join whether or not a JOIN query should be generated
     * (if relevant)
     * @param string $tablename the name or alias of the base table
     * @param moodle_database $db the database that the query will be executed
     * on
     *
     * @return array same as the get_sql method
     */
    public static function get_combined_sql(array $filters=array(), $use_join=false, $tablename=null, $paramtype=SQL_PARAMS_QM, moodle_database $db=null) {
        $filter = new OR_filter($filters);
        return $filter->get_sql($use_join, $tablename, $paramtype, $db);
    }
}

/**
 * Filtering on a static WHERE clause.
 */
class select_filter extends data_filter {
    /**
     * @param string $select the WHERE clause
     * @param array $params the parameters
     */
    public function __construct($select, array $params = array()) {
        $this->select = $select;
        $this->params = $params;
    }

    public function get_sql($use_join=false, $tablename=null, $paramtype=SQL_PARAMS_QM, moodle_database $db=null) {
        return array('where' => $this->select,
                     'where_parameters' => $this->params);
    }
}

/**
 * Filtering on a simple field value.
 */
class field_filter extends data_filter {
    const EQ = '=';
    const NEQ = '!=';
    const LT = '<';
    const GT = '>';
    const LE = '<=';
    const GE = '>=';
    const LIKE = 'LIKE';
    const NOTLIKE = 'NOT LIKE';

    /**
     * @param string $name the name of the field
     * @param string $value the value of the field to match
     * @param string $comparison the comparison operator to use
     */
    public function __construct($name, $value, $comparison=self::EQ) {
        $this->name = $name;
        $this->value = $value;
        $this->comparison = $comparison;
    }

    public function get_sql($use_join=false, $tablename=null, $paramtype=SQL_PARAMS_QM, moodle_database $db=null) {
        global $DB;
        if ($tablename) {
            $name = "{$tablename}.{$this->name}";
        } else {
            $name = $this->name;
        }

        if ($this->value === null) {
            // do the Right Thing with null values (i.e. use IS (NOT) NULL)
            if ($this->comparison == self::NEQ) {
                return array('where' => "{$name} IS NOT NULL",
                             'where_parameters' => array());
            } else {
                return array('where' => "{$name} IS NULL",
                             'where_parameters' => array());
            }
        }

        if ($paramtype == SQL_PARAMS_NAMED) {
            $paramindex = data_filter::_get_unique_name('param');
            $paramname = ":{$paramindex}";
        } else {
            $paramname = '?';
            $paramindex = 0;
        }
        if ($this->comparison === self::LIKE) {
            if ($db === null) {
                $db = $DB;
            }
            return array('where' => $db->sql_like($name, $paramname, false),
                         'where_parameters' => array($paramindex => $this->value));
        } else if ($this->comparison === self::NOTLIKE) {
            if ($db === null) {
                $db = $DB;
            }
            return array('where' => $db->sql_like($name, $paramname, false, true, true),
                         'where_parameters' => array($paramindex => $this->value));
        } else {
            return array('where' => "{$name} {$this->comparison} {$paramname}",
                         'where_parameters' => array($paramindex => $this->value));
        }
    }
}

/**
 * Filtering on a field value being in a list of values
 */
class in_list_filter extends data_filter {
    /**
     * @param string $local_field the name of the field
     * @param array $list the values to compare against
     * @param bool $not_in whether to return records that do not match
     */
    public function __construct($local_field, array $list, $not_in = false) {
        $this->local_field = $local_field;
        $this->list = $list;
        $this->not_in = $not_in;
    }

    public function get_sql($use_join=false, $tablename=null, $paramtype=SQL_PARAMS_QM, moodle_database $db=null) {
        global $DB;
        if ($tablename) {
            $name = "{$tablename}.{$this->local_field}";
        } else {
            $name = $this->local_field;
        }
        if ($db === null) {
            $db = $DB;
        }
        if (empty($this->list)) {
            return array('where' => 'FALSE',
                         'where_parameters' => array());
        }
        list($sql, $params) = $db->get_in_or_equal($this->list, $paramtype, data_filter::_get_unique_name('in_param').'_', !$this->not_in);
        return array('where' => "{$name} {$sql}",
                     'where_parameters' => $params);
    }
}

/**
 * Filtering on a joined table
 */
class join_filter extends data_filter {
    /**
     * @param string $local_field the name of the field (from the local table)
     * to join
     * @param string $foreign_table the name of the table to join with
     * @param string $foreign_filter the name of the field (from the foreign
     * table) to join with
     * @param data_filter $filter how to filter the joined table
     * @param bool $not_exist whether to return records for which no
     * association exists
     * @param bool $unique whether each $foreign_field value is unique (within
     * the filter).  This determines whether a join can be used, or whether
     * joining may result in duplicate records
     */
    public function __construct($local_field, $foreign_table, $foreign_field, data_filter $filter=null, $not_exist = false, $unique = true) {
        $this->local_field = $local_field;
        $this->foreign_table = $foreign_table;
        $this->foreign_field = $foreign_field;
        $this->filter = $filter;
        $this->not_exist = $not_exist;
        $this->unique = $unique;
    }

    public function get_sql($use_join=false, $tablename=null, $paramtype=SQL_PARAMS_QM, moodle_database $db=null) {
        $rv = array();
        if ($tablename) {
            $local_field = "{$tablename}.{$this->local_field}";
        } else {
            $local_field = $this->local_field;
        }
        $jointablename = data_filter::_get_unique_name();
        if ($use_join && $this->unique) {
            if ($this->not_exist) {
                // get the filter SQL to tack on to the JOIN condition
                $filter_sql = $this->filter ? $this->filter->get_sql(false, $jointablename, $paramtype, $db) : array();
                $add_filter = empty($filter_sql) ? '' : "AND ({$filter_sql['where']})";

                // and create the join
                $rv['join'] = "LEFT JOIN {{$this->foreign_table}} {$jointablename}
                               ON {$jointablename}.{$this->foreign_field} = {$local_field} {$add_filter}";
                $rv['join_parameters'] = isset($filter_sql['where_parameters']) ? $filter_sql['where_parameters'] : array();
                $rv['where'] = "{$jointablename}.id IS NULL";
                $rv['where_parameters'] = array();
            } else {
                // get the sql from the filter
                if ($this->filter) {
                    $filter_sql = $this->filter->get_sql(true, $jointablename, $paramtype, $db);
                } else {
                    $filter_sql = array();
                }

                if (isset($filter_sql['where'])) {
                    $rv['where'] = $filter_sql['where'];
                    $rv['where_parameters'] = $filter_sql['where_parameters'];
                }

                // and create the join
                $rv['join'] = "JOIN {{$this->foreign_table}} {$jointablename} ON {$jointablename}.{$this->foreign_field} = {$local_field}"
                    . (isset($filter_sql['join']) ? (' ' . $filter_sql['join']) : '');
                $rv['join_parameters'] = array();
                if (isset($filter_sql['join'])) {
                    $rv['join_parameters'] = $rv['join_parameters'] + $filter_sql['join_parameters'];
                }
            }
        } else {
            $filter_sql = $this->filter ? $this->filter->get_sql(true, $jointablename, $paramtype, $db) : array();
            if ($tablename) {
                // if the table name is specified, we can use the more
                // efficient EXISTS instead of IN
                $exists = $this->not_exist ? 'NOT EXISTS' : 'EXISTS';
                $params = array();
                $sql = "$exists (SELECT 'x'
                                   FROM {{$this->foreign_table}} {$jointablename} ";
                if (isset($filter_sql['join'])) {
                    $sql .= $filter_sql['join'];
                    $params = $filter_sql['join_parameters'];
                }
                $sql .= " WHERE {$jointablename}.{$this->foreign_field} = {$local_field}";
                if (isset($filter_sql['where'])) {
                    $sql .= " AND {$filter_sql['where']}";
                    $params += $filter_sql['where_parameters'];
                }
                $sql .= ')';
                $rv['where'] = $sql;
                $rv['where_parameters'] = $params;
            } else {
                $filter_sql = $this->filter ? $this->filter->get_sql(true, $jointablename, $paramtype, $db) : array();
                $in = $this->not_exist ? 'NOT IN' : 'IN';
                $params = array();
                $sql = "{$local_field} $in (SELECT {$jointablename}.{$this->foreign_field}
                                              FROM {{$this->foreign_table}} {$jointablename} ";
                if (isset($filter_sql['join'])) {
                    $sql .= $filter_sql['join'];
                    $params = $filter_sql['join_parameters'];
                }
                if (isset($filter_sql['where'])) {
                    $sql .= " WHERE {$filter_sql['where']}";
                    $params += $filter_sql['where_parameters'];
                }
                $sql .= ')';
                $rv['where'] = $sql;
                $rv['where_parameters'] = $params;
            }
        }

        return $rv;
    }
}
