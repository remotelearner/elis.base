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

require_once($CFG->libdir.'/formslib.php');

/**
 * User filtering wrapper class.
 */
class generalized_filtering {
    const FILTER_CLASS_PREFIX    = 'generalized_filter_';
    //const REPORT_INSTANCE_PREFIX = 'report_instance_prefix';

    var $_fields;
    var $_addform;
    var $_activeform;
    var $_id;

    /**
     * Contructor
     * @param array array of visible user fields
     * @param string base url used for submission/return, null if the same of current page
     * @param array extra page parameters
     * @uses $SESSION
     */
    function generalized_filtering($fields = null, $baseurl = null, $extraparams = null, $id = 0) {
        global $SESSION;

        $this->_id = $id;

        //$form_prefix = ''; // TBD: not used?
        //if (!empty($id)) {
        //    $form_prefix = self::REPORT_INSTANCE_PREFIX . $id;
        //}

        if (!isset($filtering_array)) {
            $filtering_array = array();
        }

        if (empty($fieldnames)) {
            $fieldnames = array();
        }

        $this->_fields  = array();

        foreach ($fields as $field) {
            if ($field_object = $this->get_field($field->uniqueid, $field->tablealias, $field->fieldname, $field->advanced, $field->displayname, $field->type, $field->options)) {
                //$this->_fields[$field->fieldname] = $field_object;
                $this->_fields[$field->uniqueid] = $field_object;
            }

        }
    }

    /**
     * Creates known user filter if present
     * @param string $fieldname
     * @param boolean $advanced
     * @uses $CFG
     * @uses $SITE
     * @uses $USER
     * @return object filter
     */
    function get_field($uniqueid, $tablealias, $fieldname, $advanced, $displayname, $type, $options) {
        global $USER, $CFG, $SITE;

        if (file_exists($CFG->dirroot .'/elis/core/lib/filtering/'. $type .'.php')) {
            require_once($CFG->dirroot .'/elis/core/lib/filtering/'. $type .'.php');
        } else if (file_exists($CFG->dirroot .'/elis/program/lib/filtering/'. $type .'.php')) {
            require_once($CFG->dirroot .'/elis/program/lib/filtering/'. $type .'.php');
        }

        $classname = self::FILTER_CLASS_PREFIX . $type;
        return new $classname ($uniqueid, $tablealias, $fieldname, $displayname, $advanced, $fieldname, $options);
    }

    function get_report_parameters() {
        global $SESSION;

        if (!empty($this->_id)) {
            $filtering_array =& $SESSION->user_index_filtering[$this->_id];
        } else {
            $filtering_array =& $SESSION->user_filtering;
        }

        $parameters = array();

        if (!empty($filtering_array)) {
            foreach ($filtering_array as $fname => $datas) {
                if (!array_key_exists($fname, $this->_fields)) {
                    continue; // filter not used
                }
                $field = $this->_fields[$fname];
                foreach ($datas as $i => $data) {
                    $parameter_object = new stdClass;
                    $parameter_object->alias = $field->_alias;
                    $parameter_object->field = $field->_field;
                    $parameter_object->type = substr(get_class($field), strlen('generalized_filter_'));
                    $parameter_object->parameters = $field->get_report_parameters($data);
                    $parameters[] = $parameter_object;
                }
            }
        }

        return $parameters;
    }

    /**
     * Returns sql where statement based on active user filters
     * @param array or string  $extra - extra sql with optional params (default none)
     * @param array  exceptions are filters to ignore (default none)
     * @uses $SESSION
     * @return TBD: empty('') or array ($sql, array('param1' => $param1 ...))
     */
    function get_sql_filter($extra = null, $exceptions = array()) {
        global $SESSION;

        if (!empty($this->_id)) {
            $filtering_array =& $SESSION->user_index_filtering[$this->_id];
        } else {
            $filtering_array =& $SESSION->user_filtering;
        }

        $sqls = array();
        $params = array();
        if (!empty($extra)) {
            if (is_array($extra)) {
                $sqls[] = $extra[0];
                $params = $extra[1];
            } else if (is_string($extra)) { // TBD: support old method?
                $sqls[] = $extra;
            }
        }

        if (!empty($filtering_array)) {
            foreach ($filtering_array as $fname => $datas) {
                if (!array_key_exists($fname, $this->_fields) || in_array($fname, $exceptions)) {
                    continue; // filter not used
                }
                $field = $this->_fields[$fname];
                foreach ($datas as $i => $data) {
                    $sqlfilter = $field->get_sql_filter($data);
                    if (!empty($sqlfilter)) { // TBD: was != null
                        $sqls[] = $sqlfilter[0];
                        if (!empty($sqlfilter[1])) {
                            $params = array_merge($params, $sqlfilter[1]);
                        }
                    }
                }
            }
        }

        if (empty($sqls)) {
            return ''; // TBD: null ?
        } else {
            return array(implode(' AND ', $sqls), $params);
        }
    }

    /**
     * Print the add filter form.
     */
    function display_add() {
        $this->_addform->display();
    }

    /**
     * Print the active filter form.
     */
    function display_active() {
        $this->_activeform->display();
    }

}

/**
 * The base user filter class. All abstract classes must be implemented.
 */
abstract class generalized_filter_type {

    var $_uniqueid;

    var $_alias;

    /**
     * The name of this filter instance.
     */
    var $_name;

    /**
     * The label of this filter instance.
     */
    var $_label;

    /**
     * Advanced form element flag
     */
    var $_advanced;

    /**
     * Help array to pass to setHelpButton() in filters' setupForm()
     */
    var $_filterhelp;

    /**
     * Mode in which the parent object is being executed
     */
    var $execution_mode;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     */
    function generalized_filter_type($uniqueid, $alias, $name, $label, $advanced, $help = null) {
        $this->_uniqueid   = $uniqueid;
        $this->_alias      = $alias;
        $this->_name       = $name;
        $this->_label      = $label;
        $this->_advanced   = $advanced;
        $this->_filterhelp = $help;
    }

    function get_full_fieldname() {
        if (empty($this->_alias)) {
            return $this->_name;
        } else {
            return $this->_alias .'.'. $this->_name;
        }
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array  $data filter settings
     * @return array the filtering condition with optional parameter array
     *               or null if the filter is disabled
     *               Eg.   return array($sql, array('param1' => $param1 ...));
     */
    abstract function get_sql_filter($data);

    abstract function get_report_parameters($data);

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    abstract function check_data($formdata);

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    abstract function setupForm(&$mform);

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        error('Abstract method get_label() called - must be implemented');
    }

    /**
     * Sets a constant representing the mode in which the parent object is being run
     *
     * @param  int  $execution_mode  The appropriate execution mode constant
     */
    function set_execution_mode($execution_mode) {
        $this->execution_mode = $execution_mode;
    }

    /**
     * Retrieves the constant representing the mode in which the parent object is being run
     *
     * @param  int  The apppropriate execution mode constant
     */
    function get_execution_mode() {
        return $this->execution_mode;
    }

    /**
     * Takes a set of submitted values and retuns this filter's default values
     * for them in the same structure (used to reset the filtering form)
     */
    function get_default_values($filter_data) {
        //our data map of field shortnames to values
        $default_values = array();

        //set all fields to an empty value
        foreach ($filter_data as $key => $value) {
            $default_values[$key] = '';
        }

        //return our data mapping
        return $default_values;
    }

    /**
     * Hook for providing any required javascript to reset the filter
     * @return string  any required js code to reset filter.
     */
    function reset_js() {
        return ''; // default none
    }
}

class generalized_filter_entry {
    public $uniqueid;
    public $tablealias;
    public $fieldname;
    public $displayname;
    public $advanced;
    public $type;
    public $options;

    function generalized_filter_entry($uniqueid, $tablealias, $fieldname, $displayname, $advanced, $type, $options = array()) {
        $this->uniqueid    = $uniqueid;
        $this->tablealias  = $tablealias;
        $this->fieldname   = $fieldname;
        $this->displayname = $displayname;
        $this->advanced    = $advanced;
        $this->type        = $type;
        $this->options     = $options;
    }

}

