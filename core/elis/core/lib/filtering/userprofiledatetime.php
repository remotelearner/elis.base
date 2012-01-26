<?php //$Id$
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
 * @package    elis-core
 * @subpackage filtering
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/elis/core/lib/filtering/date.php');

/**
 * Generic filter based on a date.
 */
class generalized_filter_userprofiledatetime extends generalized_filter_date {

    /**
     * Array of tables: table as key => table alias as value
     */
    var $_tables;

    /**
     * User profile field id (int)
     */
    var $_fieldid;

    /**
     * Constructor
     * @param string $alias aliacs for the table being filtered on
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     */
    function generalized_filter_userprofiledatetime($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_date($uniqueid, $alias, $name, $label, $advanced, $field, $options);
        $this->_tables = $options['tables'];
        $this->_fieldid = $options['fieldid'];
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return array the filtering condition with optional parameter array 
     *               or null if the filter is disabled
     */
    function get_sql_filter($data) {
        static $counter = 0;
        $params = array();

        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) {
            return null;
        }

        $param_id = 'userprofiledateid'. $counter;
        $params[$param_id] = $this->_fieldid;
        $sql = "{$this->_tables['user']}.id IN
                (SELECT userid FROM {user_info_data}
                 WHERE fieldid = :{$param_id}
                ";

        if ($this->_never_included) { // TBD
            $sql .= 'AND ';
            if (!empty($data['never'])) {
                $sql = "{$full_fieldname} >= 0
                       ";
            } else {
                $sql = "{$full_fieldname} > 0
                       ";
            }
        }

        if (!empty($data['after'])) {
            $param_after = 'userprofiledateafter'. $counter;
            $sql .= "AND {$full_fieldname} >= :{$param_after}
                    ";
            $params[$param_after] = $data['after'];
        }

        if (!empty($data['before'])) {
            $param_before = 'userprofiledatebefore'. $counter;
            $sql .= "AND {$full_fieldname} <= :{$param_before}
                    ";
            $params[$param_before] = $data['before'];
        }
        $sql .= ')';

        if (!empty($params)) {
            $counter++;
        }

        return array($sql, $params);
    }

}

