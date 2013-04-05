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
 * Represents a database record as an object.  Fields are identified as
 * protected members with the name '_dbfield_<name>', where <name> is the database
 * field name.
 */
class elis_data_object {
    /**
     * Name of the database table.
     */
    const TABLE = '';

    /**
     * Associated records.
     * @todo how is this layed out/used?
     * - should handle both one-to-many and many-to-many relationships
     * - should be extendable with plugins
     * @var array
     * - keys are "fake" field names
     * - values are arrays with the following keys:
     *   - class: the name of the foreign class that represents the association
     *   - idfield: field in this object's record that points to the remote ID
     *     (possbly empty)
     *   - foreignidfield: field in the foreign table that points back to this field
     *   - filtermethod: method from "class" to call to get a filter object
     *     (possibly empty).  The method must take this data record as its only
     *     argument.
     *   - listmethod: method from "class" to call to get an iteration of the
     *     associated records (possibly empty).  The method must take two
     *     arguments: this data record (required) and an array of filters
     *     (optional).
     *   - countmethod: method from "class" to call to get a count of
     *     associated records.  The method must take two arguments: this data
     *     record (required) and an array of filters (optional).
     * - exactly one of idfield, foreignidfield, filtermethod, or listmethod
     *   must be defined.  If listmethod is defined, countmethod must also be
     *   defined.
     * e.g. for crlm_class class:
     * $associations = array('tracks' =>
     *                           array('class' => 'track_class',
     *                                 'foreignidfield' => 'classid'),
     *                       'course' =>
     *                           array('class' => 'course',
     *                                 'idfield' => 'courseid'),
     *                       ...);
     * This allows access to the associated course via $this->course, and to
     * the tracks via
     * - $this->tracks (gets all associated tracks)
     * - $this->get_tracks(filters) (get associated tracks subject to filters)
     * - $this->count_tracks(filters) (count associated tracks subject to
     *   filters)
     */
    public static $associations = array();

    /**
     * Cache of objects retrieved for associations.  These objects are loaded
     * on-demand.
     */
    protected $_associated_objects = array();

    /**
     * Whether deleting a record requires extra steps.
     */
    protected static $delete_is_complex = false;

    /**
     * Functions to use for validating data.  Each validation function must
     * either be the name of a method (taking no arguments), or a PHP callback
     * (taking one argument: $this).
     */
    public static $validation_rules = array();

    /**
     * Validation rules to ignore.  These entries should be the keys for the
     * $validation_rules array that should not be checked.
     */
    public $validation_overrides = array();

    /**
     * The database object to use.
     */
    protected $_db;

    /**
     * Whether missing fields should be loaded from the database.
     */
    protected $_is_loaded = false;

    /**
     * Whether the data has not been changed since loading from the database.
     */
    protected $_is_saved = false;

    /**
     * Magic constant for marking a field as not set.
     */
    protected static $_unset;

    /**
     * Extra data that is associated with the record, but is not part of the
     * database record (e.g. counts for related records).
     */
    private $_extradata = array();

    const FIELD_PREFIX = '_dbfield_';

    /**
     * Autoincrement ID field
     * @var    integer
     * @length 10
     */
    protected $_dbfield_id;

    /***************************************************************************
     * High-level methods
     **************************************************************************/

    /**
     * Construct a data object.
     * @param mixed $src record source.  It can be
     * - false: an empty object is created
     * - an integer: loads the record that has record id equal to $src
     * - an object: creates an object with field data taken from the members
     *   of $src
     * - an array: creates an object with the field data taken from the
     *   elements of $src
     * @param mixed $field_map mapping for field names from $src.  If it is a
     * string, then it will be treated as a prefix for field names.  If it is
     * an array, then it is a mapping of destination field names to source
     * field names.
     * @param array $associations pre-fetched associated objects (to avoid
     * needing to re-fetch)
     * @param boolean $from_db whether or not the record source object/array
     * comes from the database
     * @param array $extradatafields extra data from the $src object/array
     * associated with the record that should be kept in the data object (such
     * as counts of related records)
     * @param moodle_database $database database object to use (null for the
     * default database)
     */
    public function __construct($src=false, $field_map=null, array $associations=array(),
                                $from_db=false, array $extradatafields=array(),
                                moodle_database $database=null) {
        global $DB;

        if (!isset(self::$_unset)) {
            self::$_unset = new stdClass;
        }

        // mark all the fields as unset
        $reflect = new ReflectionClass(get_class($this));
        $prefix_len = strlen(self::FIELD_PREFIX);
        foreach ($reflect->getProperties() as $prop) {
            if (strncmp($prop->getName(), self::FIELD_PREFIX, $prefix_len) === 0) {
                $field_name = $prop->getName();
                $this->$field_name = self::$_unset;
            }
        }

        if ($database === null) {
            $this->_db = $DB;
        } else {
            $this->_db = $database;
        }

        // initialize the object fields
        if ($src === false) {
            $this->_setup_extradata((object)array(), $extradatafields);
        } else if (is_numeric($src)) {
            $this->_dbfield_id = $src;
            $this->_setup_extradata((object)array(), $extradatafields);
        } else if (is_object($src)) {
            $this->_load_data_from_record($src, false, $field_map, $from_db, $extradatafields);
        } else if (is_array($src)) {
            $this->_load_data_from_record((object)$src, false, $field_map, $from_db, $extradatafields);
        } else {
            throw new data_object_exception('data_object_construct_invalid_source', 'elis_core');
        }

        $this->_associated_objects = $associations;
    }

    /**
     * Delete the record from the database.
     */
    public function delete() {
        if ($this->_dbfield_id !== self::$_unset) {
            $this->_db->delete_records(static::TABLE, array('id' => $this->_dbfield_id));
        }
    }

    /**
     * Force loading the record from the database.
     */
    public function load($overwrite=true) {
        if (!$this->_is_loaded && $this->_dbfield_id !== self::$_unset) {
            //this will throw an exception if fetching the record fails
            $record = $this->_db->get_record(static::TABLE,
                                             array('id' => $this->_dbfield_id), '*', MUST_EXIST);
            $this->_load_data_from_record($record, $overwrite, null, true);
        }
    }

    /**
     * Save the record to the database.  This method is used to both create a
     * new record, and to update an existing record.
     */
    public function save() {
        // don't bother saving if nothing has changed
        if (!$this->_is_saved) {
            $this->validate();
            // create a dumb object for Moodle
            $record = $this->to_object();
            if ($this->_dbfield_id !== self::$_unset && !empty($this->_dbfield_id)) {
                $this->_db->update_record(static::TABLE, $record);
            } else {
                $this->_dbfield_id = $this->_db->insert_record(static::TABLE, $record);
            }
            $this->_is_saved = true;
        }
    }

    /**
     * Create a duplicate copy of the object.
     * FIXME: finish docs
     */
    public function duplicate(array $options) {
        $objs = array('_errors' => array());
        $this->load();
        $classname = get_class($this);
        $clone = new $classname($this);
        $clone->_dbfield_id = self::$_unset;
        $clone->save();
        if (!$clone->id) {
            $objs['_errors'][] = get_string('failed_duplicate', 'elis_cm', $this);
            return $objs;
        }

        $objs[$classname] = array($this->_dbfield_id => $clone->_dbfield_id);
        return $objs;
    }

    /**
     * Validate the record before saving.  By default, run all the validation
     * rules except for the ones that are overridden.  Each validation rule
     * should throw an exception if the data fails validation.
     */
    public function validate() {
        $validation_rules = static::$validation_rules;
        foreach ($validation_rules as $name => $function) {
            if (!in_array($name, $this->validation_overrides)) {
                // The validation function can either be a method name or some
                // other PHP callback.  Give preference to the method if it
                // exists.
                if (is_string($function) && method_exists($this, $function)) {
                    call_user_func(array($this, $function));
                } else {
                    call_user_func($function, $this);
                }
            }
        }
    }

    /**
     * Load the records corresponding to some criteria.
     *
     * @param mixed $filter a filter object, or an array of filter objects.  If
     * omitted, all records will be loaded.
     * @param array $sort sort order for the records.  This is an array of
     * fields, where the array key is the field to sort by, and the value is
     * the direction (ASC or DESC) of the sort.  (If the value is neither ASC
     * nor DESC, then ASC is assumed.)
     * @param moodle_database $db database object to use
     * @return data_collection a collection
     */
    public static function find($filter=null, array $sort=array(), $limitfrom=0, $limitnum=0, moodle_database $db=null) {
        require_once(elis::lib('data/data_filter.class.php'));
        global $DB;

        $tablename = static::TABLE;
        if ($db === null) {
            $db = $DB;
        }

        $sortclause = array();
        foreach ($sort as $field => $order) {
            if ($order !== 'DESC') {
                $order = 'ASC';
            }
            $sortclause[] = "$field $order";
        }
        $sortclause = implode(', ', $sortclause);

        if ($filter === null) {
            $sql_clauses = array();
        } else if (is_object($filter)) {
            $sql_clauses = $filter->get_sql(true, "{{$tablename}}", SQL_PARAMS_NAMED, $db);
        } else {
            $sql_clauses = AND_filter::get_combined_sql($filter, true, "{{$tablename}}", SQL_PARAMS_NAMED, $db);
        }
        if (isset($sql_clauses['join'])) {
            $sql = "SELECT {{$tablename}}.*
                      FROM {{$tablename}}
                           {$sql_clauses['join']}";
            $parameters = $sql_clauses['join_parameters'];
            if (isset($sql_clauses['where'])) {
                $sql = "$sql WHERE {$sql_clauses['where']}";
                $parameters = array_merge($parameters, $sql_clauses['where_parameters']);
            }
            if (!empty($sortclause)) {
                $sql = "$sql ORDER BY $sortclause";
            }
            $rs = $db->get_recordset_sql($sql, $parameters, $limitfrom, $limitnum);
        } else {
            if ($filter === null) {
                // nothing
            } else if (is_object($filter)) {
                $sql_clauses = $filter->get_sql(false, "{{$tablename}}", SQL_PARAMS_NAMED, $db);
            } else {
                $sql_clauses = AND_filter::get_combined_sql($filter, false, null, SQL_PARAMS_NAMED, $db);
            }
            if (!isset($sql_clauses['where'])) {
                $sql_clauses['where'] = '';
                $sql_clauses['where_parameters'] = array();
            }
            $rs = $db->get_recordset_select($tablename,
                                            $sql_clauses['where'],
                                            $sql_clauses['where_parameters'],
                                            $sortclause, '*', $limitfrom, $limitnum);
        }
        return new data_collection($rs, get_called_class(), null, array(), true, array(), $db);
    }

    /**
     * Count the records corresponding to some criteria.
     *
     * @param mixed $filter a filter object or an array of filter objects.  If
     * omitted, all records will be counted.
     * @param moodle_database $db database object to use
     * @return integer
     */
    public static function count($filter=null, moodle_database $db=null) {
        require_once(elis::lib('data/data_filter.class.php'));
        global $DB;

        $tablename = static::TABLE;
        if ($db === null) {
            $db = $DB;
        }

        if ($filter === null) {
            $sql_clauses = array();
        } else if (is_object($filter)) {
            $sql_clauses = $filter->get_sql(true, "{{$tablename}}", SQL_PARAMS_NAMED, $db);
        } else {
            $sql_clauses = AND_filter::get_combined_sql($filter, true, "{{$tablename}}", SQL_PARAMS_NAMED, $db);
        }
        if (isset($sql_clauses['join'])) {
            $sql = "SELECT COUNT(DISTINCT {{$tablename}}.id)
                      FROM {{$tablename}}
                           {$sql_clauses['join']}";
            $parameters = $sql_clauses['join_parameters'];
            if (isset($sql_clauses['where'])) {
                $sql = "$sql WHERE {$sql_clauses['where']}";
                $parameters = array_merge($parameters, $sql_clauses['where_parameters']);
            }
            return $db->count_records_sql($sql, $parameters);
        } else {
            if ($filter === null) {
            } else if (is_object($filter)) {
                $sql_clauses = $filter->get_sql(false, "{{$tablename}}", SQL_PARAMS_NAMED, $db);
            } else {
                $sql_clauses = AND_filter::get_combined_sql($filter, false, null, SQL_PARAMS_NAMED, $db);
            }
            if (!isset($sql_clauses['where'])) {
                $sql_clauses['where'] = '';
                $sql_clauses['where_parameters'] = null;
            }
            return $db->count_records_select($tablename,
                                             $sql_clauses['where'],
                                             $sql_clauses['where_parameters']);
        }
    }

    /**
     * Test whether records satisfying the given filters exist
     *
     * @param mixed $filter a filter object or an array of filter objects.  If
     * omitted, all records will be counted.
     * @param moodle_database $db database object to use
     * @return bool true if a matching record exists, else false.
     */
    public static function exists($filter=null, moodle_database $db=null) {
        require_once(elis::lib('data/data_filter.class.php'));
        global $DB;

        $tablename = static::TABLE;
        if ($db === null) {
            $db = $DB;
        }

        if ($filter === null) {
            $sql_clauses = array();
        } else if (is_object($filter)) {
            $sql_clauses = $filter->get_sql(true, "{{$tablename}}", SQL_PARAMS_QM, $db);
        } else {
            $sql_clauses = AND_filter::get_combined_sql($filter, true, "{{$tablename}}", SQL_PARAMS_QM, $db);
        }
        if (isset($sql_clauses['join'])) {
            $sql = "SELECT 'x'
                      FROM {{$tablename}}
                           {$sql_clauses['join']}";
            $parameters = $sql_clauses['join_parameters'];
            if (isset($sql_clauses['where'])) {
                $sql = "$sql WHERE {$sql_clauses['where']}";
                $parameters = array_merge($parameters, $sql_clauses['where_parameters']);
            }
            return $db->record_exists($sql, $parameters);
        } else {
            if ($filter === null) {
            } else if (is_object($filter)) {
                $sql_clauses = $filter->get_sql(false, "{{$tablename}}", SQL_PARAMS_QM, $db);
            } else {
                $sql_clauses = AND_filter::get_combined_sql($filter, false, null, SQL_PARAMS_QM, $db);
            }
            if (!isset($sql_clauses['where'])) {
                $sql_clauses['where'] = '';
                $sql_clauses['where_parameters'] = null;
            }
            return $db->record_exists_select($tablename,
                                             $sql_clauses['where'],
                                             $sql_clauses['where_parameters']);
        }
    }

    /**
     * Delete the records corresponding to some criteria.
     *
     * @param mixed $filter a filter or an array of filter objects.  (Note:
     * unlike in the find and count methods, this parameter is not optional)
     * @param moodle_database $db database object to use
     */
    public static function delete_records($filter, moodle_database $db=null) {
        require_once(elis::lib('data/data_filter.class.php'));
        global $DB;

        if (!empty(static::$delete_is_complex)) {
            // deleting involves more than just removing the DB records
            $items = static::find($filter, array(), 0, 0, $db);
            foreach ($items as $item) {
                $item->delete();
            }
            return;
        }

        $tablename = static::TABLE;
        if ($db === null) {
            $db = $DB;
        }

        if (is_object($filter)) {
            $sql_clauses = $filter->get_sql(false, "{{$tablename}}", SQL_PARAMS_QM, $db);
        } else {
            $sql_clauses = AND_filter::get_combined_sql($filter, false, null, SQL_PARAMS_QM, $db);
        }
        if (!isset($sql_clauses['where'])) {
            $sql_clauses['where'] = '';
            $sql_clauses['where_parameters'] = null;
        }
        return $db->delete_records_select($tablename,
                                          $sql_clauses['where'],
                                          $sql_clauses['where_parameters']);
    }

    public function get_db() {
        return $this->_db;
    }

    /**
     * Converts the data_object a dumb object representation (without
     * associations).  This is required when using the Moodle *_record
     * functions, or get_string.
     */
    public function to_object() {
        $obj = new object;
        // Add extradata fields first
        foreach ($this->_extradata as $key => $val) {
            $obj->$key = $val;
        }
        $reflect = new ReflectionClass(get_class($this));
        $prefix_len = strlen(self::FIELD_PREFIX);
        foreach ($reflect->getProperties() as $prop) {
            if (strncmp($prop->getName(), self::FIELD_PREFIX, $prefix_len) === 0) {
                $field_name = $prop->getName();
                $name = substr($field_name, $prefix_len);
                if ($this->$field_name !== self::$_unset) {
                    $obj->$name = $this->$field_name;
                }
            }
        }
        return $obj;
    }

    /**
     * Converts the data_object an array representation (without associations).
     */
    public function to_array() {
        return (array)($this->to_object());
    }

    /***************************************************************************
     * Magic Methods
     **************************************************************************/

    /**
     * Magic get method -- allows access to fields and associations via
     * $this->fieldname and $this->associationname.
     */
    public function __get($name) {
        require_once(elis::lib('data/data_filter.class.php'));

        $field_name = self::FIELD_PREFIX.$name;
        if (property_exists(get_class($this), $field_name)) {
            if ($this->$field_name === self::$_unset) {
                if ($name === 'id') {
                    return null;
                }
                //load data while keeping existing fields
                $this->load(false);
            }
            if ($this->$field_name === self::$_unset) {
                return null;
            } else {
                return $this->$field_name;
            }
        } else if ($this->_has_association($name)) {
            $associations = static::$associations;
            $association = $associations[$name];
            $classname = $association['class'];
            if (isset($association['idfield'])) {
                if (!isset($this->_associated_objects[$name])) {
                    // we don't have a cached copy, so load it and cache
                    $id_field_name = $association['idfield'];
                    $this->_associated_objects[$name] = new $classname($this->$id_field_name, null, array(), false, array(), $this->_db);
                }
                return $this->_associated_objects[$name];
            } else if (isset($association['foreignidfield'])) {
                return $classname::find(new field_filter($association['foreignidfield'], $this->_dbfield_id), array(), 0, 0, $this->_db);
            } else if (isset($association['filtermethod'])) {
                return $classname::find(call_user_func(array($classname, $association['filtermethod']), $this), array(), 0, 0, $this->_db);
            } else {
                return call_user_func(array($classname, $association['listmethod']), $this);
            }
        } else if (array_key_exists($name, $this->_extradata)) {
            return $this->_extradata[$name];
        } else {
            $trace = debug_backtrace();
            $classname = get_class($this);
            trigger_error(
                "Undefined property via __get(): $classname::\${$name} in {$trace[1]['file']} on line {$trace[1]['line']}",
                E_USER_NOTICE);
            return null;
        }
    }

    /**
     * Magic set method -- allows setting field values via $this->fieldname.
     */
    public function __set($name, $value) {
        $field_name = self::FIELD_PREFIX.$name;
        if (property_exists(get_class($this), $field_name)) {
            $this->$field_name = $value;
            $this->_is_saved = false;
        } else if (array_key_exists($name, $this->_extradata)) {
            $this->_extradata[$name] = $value;
        } else {
            /*
            $trace = debug_backtrace();
            $classname = get_class($this);
            trigger_error(
                "Attempt to set to undefined member via __set(): $classname::\${$name} in {$trace[1]['file']} on line {$trace[1]['line']}",
            E_USER_ERROR);
            */
            $a = new stdClass;
            $a->classname = get_class($this);
            $a->name = $name;
            throw new data_object_exception('set_nonexistent_member', 'elis_core', '', $a);
        }
    }

    /**
     * Magic isset method -- allows checking if a field value is set.
     */
    public function __isset($name) {
        $field_name = self::FIELD_PREFIX.$name;
        // we have to do it this way because isset will just call this method again
        $field_isset = property_exists(get_class($this), $field_name) && $this->$field_name !== self::$_unset;
        return $field_isset || $this->_has_association($name) || isset($this->_extradata[$name]);
    }

    /**
     * Magic unset method -- allows unsetting a field value.
     */
    public function __unset($name) {
        $field_name = self::FIELD_PREFIX.$name;
        if (property_exists(get_class($this), $field_name)) {
            $this->$field_name = self::$_unset;
        } else if (array_key_exists($name, $this->_extradata)) {
            unset($this->_extradata[$name]);
        }
        // FIXME: handle associations?
    }

    /**
     * Magic method call method -- allows getting and counting associations via
     * $this->get_associationname($filters) and
     * $this->count_associationname($filters), where $filters is an (optional)
     * filter or array of filter objects.
     */
    public function __call($name, $args) {
        require_once(elis::lib('data/data_filter.class.php'));

        if (strncmp($name, 'get_', 4) === 0) {
            $name = substr($name, 4);
            if ($this->_has_association($name)) {
                $associations = static::$associations;
                $association = $associations[$name];
                $classname = $association['class'];
                if (isset($association['foreignidfield']) || isset($association['filtermethod'])) {
                    if (isset($association['foreignidfield'])) {
                        $foreign_filter = new field_filter($association['foreignidfield'], $this->_dbfield_id);
                    } else {
                        $foreign_filter = call_user_func(array($classname, $association['filtermethod']), $this);
                    }
                    if (isset($args[0])) {
                        // $filters specified
                        if (is_array($args[0])) {
                            $args[0][] = $foreign_filter;
                        } else {
                            $args[0] = array($args[0], $foreign_filter);
                        }
                    } else {
                        $args[0] = $foreign_filter;
                    }
                    // fill in the default arguments
                    if (!isset($args[1])) {
                        $args[1] = array();
                    }
                    if (!isset($args[2])) {
                        $args[2] = 0;
                    }
                    if (!isset($args[3])) {
                        $args[3] = 0;
                    }
                    if (!isset($args[4])) {
                        $args[4] = $this->_db;
                    }
                    return call_user_func_array(array($classname, 'find'), $args);
                } else if (isset($association['listmethod'])) {
                    array_unshift($args, $this);
                    return call_user_func_array(array($classname, $association['listmethod']), $args);
                }
            }
        } else if (strncmp($name, 'count_', 6) === 0) {
            $name = substr($name, 6);
            if ($this->_has_association($name)) {
                $associations = static::$associations;
                $association = $associations[$name];
                $classname = $association['class'];
                if (isset($association['foreignidfield']) || isset($association['filtermethod'])) {
                    if (isset($association['foreignidfield'])) {
                        $foreign_filter = new field_filter($association['foreignidfield'], $this->_dbfield_id);
                    } else {
                        $foreign_filter = call_user_func(array($classname, $association['filtermethod']), $this);
                    }
                    if (isset($args[0])) {
                        if (is_array($args[0])) {
                            $args[0][] = $foreign_filter;
                        } else {
                            $args[0] = array($args[0], $foreign_filter);
                        }
                    } else {
                        $args[0] = $foreign_filter;
                    }
                    if (!isset($args[1])) {
                        $args[1] = $this->_db;
                    }
                    return call_user_func_array(array($classname, 'count'), $args);
                } else if (isset($association['countmethod'])) {
                    array_unshift($args, $this);
                    return call_user_func_array(array($classname, $association['countmethod']), $args);
                }
            }
        }
        $trace = debug_backtrace();
        $classname = get_class($this);
        trigger_error(
            "Call to undefined method via __call(): $classname::$name in {$trace[1]['file']} on line {$trace[1]['line']}",
            E_USER_ERROR);
    }

    /***************************************************************************
     * Low-level methods
     **************************************************************************/

    /**
     * setup extradata fields for a record object
     * @param object $rec the source record object
     * @param array $extradatafields extra data from the $src object/array
     * associated with the record that should be kept in the data object (such
     * as counts of related records)
     */
     protected function _setup_extradata($rec, array $extradatafields = array()) {
        foreach ($extradatafields as $field_name => $rec_name) {
            if (is_int($field_name)) {
                // array is an array instead of a map
                $field_name = $rec_name;
            }
            if (isset($rec->$rec_name)) {
                $this->_extradata[$field_name] = $rec->$rec_name;
            } else {
                $this->_extradata[$field_name] = null;
            }
        }
    }

    /**
     * Load data from a record object
     * @param object $rec the source record object
     * @param boolean $overwrite whether to overwrite existing values
     * @param mixed $field_map mapping for field names from $rec.  If it is a
     * string, then it will be treated as a prefix for field names.  If it is
     * an array, then it is a mapping of destination field names to source
     * field names.
     * @param boolean $from_db whether or not the record source object/array
     * comes from the database
     * @param array $extradatafields extra data from the $src object/array
     * associated with the record that should be kept in the data object (such
     * as counts of related records)
     */
    protected function _load_data_from_record($rec, $overwrite=false, $field_map=null, $from_db=false, array $extradatafields=array()) {
        // find all the fields from the current object
        $reflect = new ReflectionClass(get_class($this));
        $prefix_len = strlen(self::FIELD_PREFIX);
        foreach ($reflect->getProperties() as $prop) {
            if (strncmp($prop->getName(), self::FIELD_PREFIX, $prefix_len) === 0) {
                $field_name = $prop->getName();

                // figure out the name of the field to copy from
                $rec_name = substr($field_name, $prefix_len);
                if (is_string($field_map)) {
                    // just a simple prefix
                    $rec_name = $field_map.$rec_name;
                } else if (is_array($field_map)) {
                    if (!isset($field_map[$rec_name])) {
                        // field isn't mapped -- skip it
                        continue;
                    }
                    $rec_name = $field_map[$rec_name];
                }

                // set the field from the record if:
                // - we don't have a value already set, or if we want to
                //   overwrite; and
                // - the value is set in the source record
                if (($this->$field_name === self::$_unset || $overwrite)
                    && isset($rec->$rec_name)) {
                    $this->$field_name = $rec->$rec_name;
                }
            }
        }
        $this->_setup_extradata($rec, $extradatafields);
        $this->_is_loaded = true;
        if ($from_db) {
            $this->_is_saved = true;
        } else {
            $this->_is_saved = false;
        }
    }

    /**
     * Convenience function to check if an association exists.
     *
     * @param string $name the name of the association to check
     */
    protected function _has_association($name) {
        return isset(static::$associations[$name]);
    }

    /**
     * Method to test data_objects' _dbfield_ properties are in-sync with DB
     * no extras and no missing fields.
     *
     * @return boolean true if tests pass, false otherwise
     */
    public function _test_dbfields() {
        $dbfields = array();
        // find all the fields from the current object
        $objclass = get_class($this);
        $reflect = new ReflectionClass($objclass);
        $prefix_len = strlen(self::FIELD_PREFIX);
        foreach ($reflect->getProperties() as $prop) {
            if (strncmp($prop->getName(), self::FIELD_PREFIX, $prefix_len) === 0) {
                $dbfields[] = substr($prop->getName(), $prefix_len);
            }
        }

        $allfields = implode(', ', $dbfields);
        error_log("/elis/core/lib/data/data_object.class.php::_test_dbfields(): '{$allfields}' for class: {$objclass}");
        // Test that all data_object's fields are in the database table.
        $ret = true;
        foreach ($dbfields as $dbfield) {
            if (!$this->_db->get_manager()->field_exists($this::TABLE, $dbfield)) {
                $ret = false;
                error_log("/elis/core/lib/data/data_object.class.php::_test_dbfields(): Error class: {$objclass}  has invalid '\$_dbfield_{$dbfield}' property or TABLE spec.");
            }
        }

        // Get all database fields to make sure all are in data_object
        $recs = $this->_db->get_recordset($this::TABLE, null, '', '*', 0, 1);
        if ($recs->valid()) {
            foreach ($recs as $rec) {
                foreach ($rec as $key => $value) {
                    if (!in_array($key, $dbfields)) {
                        error_log("/elis/core/lib/data/data_object.class.php::_test_dbfields(): Error class: {$objclass}  missing dbfield: {$key} (\$_dbfield_{$key})");
                        $ret = false;
                    }
                }
            }
        } else {
            if ($this->_db->get_dbfamily() == 'mysql') {
                $sql = 'SHOW COLUMNS FROM {'. $this::TABLE .'}';
                $recs = $this->_db->get_recordset_sql($sql);
                foreach ($recs as $rec) {
                    if (!in_array($rec->field, $dbfields)) {
                        error_log("/elis/core/lib/data/data_object.class.php::_test_dbfields(): Error class: {$objclass}  missing dbfield: {$rec->field} (\$_dbfield_{$rec->field})");
                        $ret = false;
                    }
                }
            } else {
                error_log("/elis/core/lib/data/data_object.class.php::_test_dbfields(): WARNING '". $this::TABLE ."' table empty, could not test dbfields complete.");
            }
        }
        unset($recs);
        return $ret;
    }

    /**
     * Method to test data_objects' associations are in-sync with related
     * classes foreignidfield and data_objects idfield.
     *
     * @return boolean true if tests pass, false otherwise
     */
    public function _test_associations() {
        $ret = true;
        $objclass = get_class($this);
        foreach ($this::$associations as $key => $val) {
            if (!is_array($val)) {
                $ret = false;
                error_log("/elis/core/lib/data/data_object.class.php::_test_associations(): Error for class: {$objclass}  association '{$key}' - array expected, scalar found!.");
                continue;
            }
            if (!array_key_exists('class', $val)) {
                $ret = false;
                error_log("/elis/core/lib/data/data_object.class.php::_test_associations(): Error for class: {$objclass}  association '{$key}' - missing 'class' index!.");
            }
            if (array_key_exists('idfield', $val)) {
                if (!property_exists(get_class($this), self::FIELD_PREFIX . $val['idfield'])) {
                    $ret = false;
                    error_log("/elis/core/lib/data/data_object.class.php::_test_associations(): Error for class: {$objclass}  association '{$key}' - 'idfield' => '{$val['idfield']}' not property of class: ". get_class($this));
                }
            }
            if (array_key_exists('foreignidfield', $val)) {
                if (array_key_exists('idfield', $val)) {
                    $ret = false;
                    error_log("/elis/core/lib/data/data_object.class.php::_test_associations(): Error for class: {$objclass}  association '{$key}' - cannot have both 'idfield' and 'foreignidfield' defined!.");
                }
                if (!property_exists($val['class'], self::FIELD_PREFIX . $val['foreignidfield'])) {
                    $ret = false;
                    error_log("/elis/core/lib/data/data_object.class.php::_test_associations(): Error for class: {$objclass}  association '{$key}' - 'foreignidfield' => '{$val['foreignidfield']}' not property of class: {$val['class']}");
                }
            }
        }
        return $ret;
    }
}

/**
 * A collection of data objects (based on a Moodle recordset, or any other
 * iterator that contains a data record)
 */
class data_collection implements Iterator {
    /**
     * @param object $rs the iterator to base the collection on
     * @param string $dataclass the class to create the data objects from
     * @param mixed $field_map see elis_data_object constructor
     * @param array $associations see elis_data_object constructor
     * @param boolean $from_db whether or not the record source object/array
     * comes from the database
     * @param array $extradatafields extra data from the $src object/array
     * associated with the record that should be kept in the data object (such
     * as counts of related records)
     * @param moodle_database $database see elis_data_object constructor
     */
    public function __construct($rs, $dataclass, $field_map=null,
                                array $associations=array(), $from_db=false,
                                array $extradatafields=array(), moodle_database $database=null) {
        $this->rs = $rs;
        $this->dataclass = $dataclass;
        $this->field_map = $field_map;
        $this->associations = $associations;
        $this->from_db = $from_db;
        $this->extradatafields = $extradatafields;
        $this->database = $database;
    }

    public function current() {
        return new $this->dataclass($this->rs->current(), $this->field_map,
                                    $this->associations, $this->from_db,
                                    $this->extradatafields, $this->database);
    }

    public function key() {
        return $this->rs->key();
    }

    public function next() {
        return $this->rs->next();
    }

    public function rewind() {
        return $this->rs->rewind();
    }

    public function valid() {
        return $this->rs->valid();
    }

    public function close() {
        return $this->rs->close();
    }

    /**
     * Convert the iterator to an array
     * @param string $key the data field to use as the array key
     */
    public function to_array($key='id') {
        $array = array();
        foreach ($this as $rec) {
            $array[$rec->$key] = $rec;
        }
        $this->close();
        return $array;
    }
}

/**
 * Helper function for validating that a record has unique values in some
 * fields.
 */
function validate_is_unique(elis_data_object $record, array $fields) {
    require_once(elis::lib('data/data_filter.class.php'));

    $classname = get_class($record);
    $tablename = $classname::TABLE;
    $db = $record->get_db();
    $filters = array();
    foreach ($fields as $field) {
        $filters[] = new field_filter($field, $record->$field);
    }
    if (!empty($record->id)) {
        $filters[] = new field_filter('id', $record->id, field_filter::NEQ);
    }
    if ($classname::exists($filters, $record->get_db())) {
        $a = new stdClass;
        $a->tablename = $tablename;
        $a->fields = implode(',', $fields);
        throw new data_object_validation_exception('data_object_validation_unique', 'elis_core', '', $a);
    }
}

/**
 * Helper function for validating that a field is not empty.
 */
function validate_not_empty(elis_data_object $record, $field) {
    // if it's an existing record, and the field is set but empty, or if it's a
    // new record and the field is empty, then we have an error
    if ((isset($record->id) && isset($record->$field) && empty($record->$field))
        || (!isset($record->id) && empty($record->$field))) {
        $a = new stdClass;
        $classname = get_class($record);
        $a->tablename = $classname::TABLE;
        $a->field = $field;
        throw new data_object_validation_exception('data_object_validation_not_empty', 'elis_core', '', $a);
    }
}

/**
 * Helper function for validating that an associated databasse record exists
 *
 * @param object $record The data object whose associations we are checking
 * @param string $association The specific entry's key from the assocations array
 */
function validate_associated_record_exists(elis_data_object $record, $association) {
    //this will throw an appropriate exception if the association can't be fetched
    //using magic
    $object = $record->$association;
    $object->load();
}

/**
 * Helper function for generating a unique identifier
 *
 * @param string $table The table being checked for unique records
 * @param string $iterator The iterating field name
 * @param string $basevalue The starting value to check for uniqueness
 * @param array $params An array of parameters for the uniqueness check
 * @param string $classname An optional object name
 * @param object $class An optional object
 * @param array $classparams An optional array of parameters for the new object
 */
function generate_unique_identifier($table, $iterator, $basevalue, $params, $classname = NULL, &$class = NULL, $classparams = NULL) {
    global $DB;
    //create a unique idnumber by appending a suffix
    $count = 0;
    $oldbase = $basevalue;
    $basevalue = preg_replace('/\.[0-9]+$/', '', $basevalue);
    if ($oldbase != $basevalue) {
        $count = intval(substr($oldbase, strrpos($oldbase, '.') + 1));
    }
    do {
        $suffix = $count ? '.'. $count : '';
        ++$count;
        $params[$iterator] = $basevalue . $suffix;
        if (isset($classname)) {
            if (isset($classparams)) {
                $classparams[$iterator] = $basevalue . $suffix;
                $class = new $classname($classparams);
            } else {
                $class = new $classname($params);
            }
        }
    } while ($DB->record_exists($table, $params));
    return $basevalue . $suffix;
}

/**
 * Helper class for validation rules.
 *
 * Calling validation_helper::not_empty_{field1}($record) is equivalent to
 * calling validate_not_empty($record, '{field1}'), and calling
 * validation_helper::is_unique_{field1}_{field2}($record) is equivalent to
 * calling validate_is_unique($record, array('{field1}', '{field2}')).  (Note
 * that in the second case, the field names cannot contain underscores.
 *
 * This allows you to use array('validation_helper', 'not_empty_{field1}') and
 * array('validation_helper', 'is_unique_{field1}_{field2}') as in the
 * $validation_rules array, instead of having to create custom functions.
 */
class validation_helper {
    const NOTEMPTY = "not_empty_";
    const UNIQUE = "is_unique_";

    public static function __callStatic($name, $args) {
        $prefix_len = strlen(self::NOTEMPTY);
        if (strncmp($name, self::NOTEMPTY, $prefix_len) === 0) {
            $args[] = substr($name, $prefix_len);
            return call_user_func_array('validate_not_empty', $args);
        }
        $prefix_len = strlen(self::UNIQUE);
        if (strncmp($name, self::UNIQUE, $prefix_len) === 0) {
            $fields = explode('_', substr($name, $prefix_len));
            $args[] = $fields;
            return call_user_func_array('validate_is_unique', $args);
        }
    }
}

class data_object_exception extends moodle_exception {
}

class data_object_validation_exception extends data_object_exception {
}
