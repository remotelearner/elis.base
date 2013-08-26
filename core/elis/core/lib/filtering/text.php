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

require_once($CFG->dirroot.'/elis/core/lib/filtering/lib.php');

/**
 * Generic filter for text fields.
 */
class generalized_filter_text extends generalized_filter_type {

    static $OPERATOR_CONTAINS = 0;
    static $OPERATOR_DOES_NOT_CONTAIN = 1;
    static $OPERATOR_IS_EQUAL_TO = 2;
    static $OPERATOR_STARTS_WITH = 3;
    static $OPERATOR_ENDS_WITH = 4;
    static $OPERATOR_IS_EMPTY = 5;

    var $_field;
    //by default, make text filtering case-insensitive (at least until ELIS
    //works outside of MySQL)
    var $_casesensitive = false; // override with $options['casesensitive']

    /**
     * Constructor
     * @param string $alias aliacs for the table being filtered on
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     */
    function generalized_filter_text($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced,
                    !empty($options['help'])
                    ? $options['help'] : array('text', $label, 'elis_core'));
        $this->_field = $field;
        if (isset($options['casesensitive'])) {
            $this->_casesensitive = $options['casesensitive'];
        }
    }

    /**
     * Returns an array of comparison operators
     * @return array of comparison operators
     */
    function getOperators() {
        return array(0 => get_string('contains', 'filters'),
                     1 => get_string('doesnotcontain','filters'),
                     2 => get_string('isequalto','filters'),
                     3 => get_string('startswith','filters'),
                     4 => get_string('endswith','filters'),
                     5 => get_string('isempty','filters'));
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        $objs = array();
        $objs[] =& $mform->createElement('select', $this->_uniqueid.'_op', null, $this->getOperators());
        $objs[] =& $mform->createElement('text', $this->_uniqueid, null);
        $grp =& $mform->addElement('group', $this->_uniqueid.'_grp', $this->_label, $objs, '', false);
        $mform->addHelpButton($this->_uniqueid.'_grp', $this->_filterhelp[0], $this->_filterhelp[2] /* , $this->_filterhelp[1] */ ); // TBV
        $mform->disabledIf($this->_uniqueid, $this->_uniqueid.'_op', 'eq', 5);
        if ($this->_advanced) {
            $mform->setAdvanced($this->_uniqueid.'_grp');
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field    = $this->_uniqueid;
        $operator = $field.'_op';

        if (property_exists($formdata, $operator)) {
            $value = (property_exists($formdata, $field) && !empty($formdata->$field))
                     ? $formdata->$field : '';
            //ELIS-3478: Fixed problems with 'is_empty' operator

            if ($formdata->$operator != 5 and $value == '') {
                //do not apply a filter here because no data was entered into the text
                //box - this is only valid for the "is_empty" operator
                return false;
            }
            //$value = empty($formdata->$field) ? '' : $formdata->$field;

            return array('operator' => (int)$formdata->$operator,
                            'value' => $value);
        }

        return false;
    }

    function get_report_parameters($data) {

        $return_value = array('operator' => $data['operator']);

        if (!empty($data['value'])) {
            $return_value['value'] = $data['value'];
        }

        return $return_value;
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $operator  = $data['operator'];
        $value     = $data['value'];
        $operators = $this->getOperators();

        $a = new object();
        $a->label    = $this->_label;
        $a->value    = '"'.s($value).'"';
        $a->operator = $operators[$operator];


        switch ($operator) {
            case generalized_filter_text::$OPERATOR_CONTAINS: // contains
            case generalized_filter_text::$OPERATOR_DOES_NOT_CONTAIN: // doesn't contain
            case generalized_filter_text::$OPERATOR_IS_EQUAL_TO: // equal to
            case generalized_filter_text::$OPERATOR_STARTS_WITH: // starts with
            case generalized_filter_text::$OPERATOR_ENDS_WITH: // ends with
                return get_string('textlabel', 'filters', $a);
            case generalized_filter_text::$OPERATOR_IS_EMPTY: // empty
                return get_string('textlabelnovalue', 'filters', $a);
        }

        return '';
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        global $DB;
        static $counter = 0;
        $param_name = 'ex_text'. $counter++;
        $params = array();

        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) {
            return null;
        }

        $value = $data['value'];

        switch($data['operator']) {
            case generalized_filter_text::$OPERATOR_CONTAINS:
                //contains
                $sql = $DB->sql_like($full_fieldname, ":{$param_name}", $this->_casesensitive);
                $params[$param_name] = "%{$value}%";
                break;
            case generalized_filter_text::$OPERATOR_DOES_NOT_CONTAIN:
                //does not contain
                $sql = $DB->sql_like($full_fieldname, ":{$param_name}", $this->_casesensitive, true, true);
                $params[$param_name] = "%{$value}%";
                break;
            case generalized_filter_text::$OPERATOR_IS_EQUAL_TO:
                //equals
                $sql = $DB->sql_like($full_fieldname, ":{$param_name}", $this->_casesensitive);
                $params[$param_name] = $value;
                break;
            case generalized_filter_text::$OPERATOR_STARTS_WITH:
                //starts with
                $sql = $DB->sql_like($full_fieldname, ":{$param_name}", $this->_casesensitive);
                $params[$param_name] = "{$value}%";
                break;
            case generalized_filter_text::$OPERATOR_ENDS_WITH:
                //ends with
                $sql = $DB->sql_like($full_fieldname, ":{$param_name}", $this->_casesensitive);
                $params[$param_name] = "%{$value}";
                break;
            case generalized_filter_text::$OPERATOR_IS_EMPTY:
                $sql = "{$full_fieldname} = ''";
                break;
            default:
                //error call
                print_error('invalidoperator', 'elis_core'); // TBD
        }
        return array($sql, $params);
    }

}

