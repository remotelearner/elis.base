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
 * @author     Remote-Learner.net Inc
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 * @version    $Id$
 * @package    elis-core
 * @subpackage filtering
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 * userprofiletext.php - PHP Report filter for extra user profile text fields
 *
 * Filter for matching user profile text fields
 *
 * Required options include: all text filter requirements PLUS
 *  ['tables'] => array, table names as keys => table alias as values
 *  ['fieldid'] => int, the user_info_field id of the extra user profile field
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/user/filters/lib.php');
require_once($CFG->dirroot .'/elis/core/lib/filtering/text.php');

/**
 * Generic filter for user profile text fields.
 */
class generalized_filter_userprofiletext extends generalized_filter_text {

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
    function generalized_filter_userprofiletext($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_text($uniqueid, $alias, $name, $label, $advanced, $field, $options);
        $this->_tables = $options['tables'];
        $this->_fieldid = $options['fieldid'];
        //print_object($this);
    }

    /**
     * Returns the condition to be used with SQL where
     * @param  array $data filter settings
     * @uses   $DB
     * @return array the filtering condition with optional parameters
     *               or null if the filter is disabled
     */
    function get_sql_filter($data) {
        global $DB;
        static $counter = 0;
        $param_id = 'ex_userprofiletext_id'. $counter;
        $param_data = 'ex_userprofiletext_data'. $counter;
        $counter++;

        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) {
            return null;
        }

        $params = array($param_id => $this->_fieldid);
        $value = $data['value'];

        switch($data['operator']) {
            case generalized_filter_text::$OPERATOR_CONTAINS:
                //contains
                $data_condition = $DB->sql_like($full_fieldname, ":{$param_data}", $this->_casesensitive);
                $params[$param_data] = "%{$value}%";
                break;
            case generalized_filter_text::$OPERATOR_DOES_NOT_CONTAIN:
                //does not contain
                $data_condition = $DB->sql_like($full_fieldname, ":{$param_data}", $this->_casesensitive, true, true);
                $params[$param_data] = "%{$value}%";
                break;
            case generalized_filter_text::$OPERATOR_IS_EQUAL_TO:
                //equals
                $data_condition = $DB->sql_like($full_fieldname, ":{$param_data}", $this->_casesensitive);
                $params[$param_data] = $value;
                break;
            case generalized_filter_text::$OPERATOR_STARTS_WITH:
                //starts with
                $data_condition = $DB->sql_like($full_fieldname, ":{$param_data}", $this->_casesensitive);
                $params[$param_data] = "{$value}%";
                break;
            case generalized_filter_text::$OPERATOR_ENDS_WITH:
                //ends with
                $data_condition = $DB->sql_like($full_fieldname, ":{$param_data}", $this->_casesensitive);
                $params[$param_data] = "%{$value}";
                break;
            case generalized_filter_text::$OPERATOR_IS_EMPTY:
                $data_condition = "{$full_fieldname} = ''";
                break;
            default:
                //error call
                print_error('invalidoperator', 'elis_core');
        }

        $sql = "{$this->_tables['user']}.id IN
                (SELECT userid FROM {user_info_data}
                 WHERE fieldid = :{$param_id} AND ({$data_condition}))";
        //error_log("userprofiletext.php::get_filter_sql() => {$sql}");
        return array($sql, $params);
    }

}

