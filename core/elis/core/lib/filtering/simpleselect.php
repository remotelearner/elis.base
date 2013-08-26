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
 * Generic filter based on a list of values.
 */
class generalized_filter_simpleselect extends generalized_filter_type {
    /**
     * options for the list values
     */
    var $_field;

    var $_numeric  = false;    // TBD: obsolete - set true if field is numeric
    var $_anyvalue = null;     // The "any value" entry
    var $_noany    = false;    // Whether to hide the "any value" entry
    var $_onchange = '';       // On change javascript
    var $_multiple = false;    // Whether this select is a multi-select
    var $_class    = '';       // CSS class attribute
    var $_nofilter = false;    // boolean - true makes get_sql_filter() always return null
    var $_nooptions = false;   // If true, hide all select options

    var $_optionfields = array(
        '_options'   => 'choices',   // The options to be displayed
        '_numeric'   => 'numeric',   // No longer used (used to be for numeric values)
        '_anyvalue'  => 'anyvalue',  // The "any value" string
        '_noany'     => 'noany',     // Used to hide the "any value" entry
        '_onchange'  => 'onchange',  // Often used to alter dependent fields
        '_multiple'  => 'multiple',  // Used to make this select into a multi-select
        '_class'     => 'class',     // Used to add a CSS class attribute for display
        '_nofilter'  => 'nofilter',  // Used for filters that change report behaviour
        '_nooptions' => 'nochoices'  // Used for filters that start blank
    );

    /**
     * Constructor
     *
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function generalized_filter_simpleselect($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced,
                    !empty($options['help'])
                    ? $options['help']
                    : array('simpleselect', $label, 'elis_core'));

        if (!is_array($options)) {
            $options = array($options);
        }

        foreach ($this->_optionfields as $var => $option) {
            if (array_key_exists($option, $options)) {
                $this->$var = $options[$option];
            }
        }
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        $choices = array();

        if (!$this->_nooptions) {

            if (!$this->_noany) {
                $choices[''] = empty($this->_anyvalue)
                               ? get_string('anyvalue', 'filters')
                               : $this->_anyvalue;
            }

            // Have to do this manually because php function renumber numeric indexes.
            foreach ($this->_options as $key => $value) {
                // ELIS-7048 -- Menu select options are including a carriage return at the end of the $key property, causing
                //              the filter to not return any data on form submission. =(
                $key = trim($key, "\n\r");
                $choices[$key] = $value;
            }
        }

        $options = array();
        if (!empty($this->_onchange)) {
            $options['onchange'] = $this->_onchange;
        }
        if (!empty($this->_multiple)) {
            $options['multiple'] = $this->_multiple;
        }
        if (!empty($this->_class)) {
            $options['class'] = $this->_class;
        }
        $mform->addElement('select', $this->_uniqueid, $this->_label, $choices, $options);

        $mform->addHelpButton($this->_uniqueid, $this->_filterhelp[0], $this->_filterhelp[2] /* , $this->_filterhelp[1] */ ); // TBV
        if ($this->_advanced) {
            $mform->setAdvanced($this->_uniqueid);
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field = $this->_uniqueid;

        if (array_key_exists($field, $formdata)) {
            $value = $formdata->$field;

            if ($this->_multiple && is_array($value)) {
                foreach ($value as $val) {
                    if ($val === '') {
                        return false;
                    }
                }
                return array('value' => $value);
            } else if ($value !== '') {
                return array('value' => (string)$value);
            }
        }
        return false;
    }

    function get_report_parameters($data) {
        return array('value' => $data['value'],
                     'numeric' => $this->_numeric);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $value = $data['value'];

        $a = new object();
        $a->label = $this->_label;

        if (is_array($value)) {
            foreach ($value as $key => $subvalue) {
                $value[$key] = '"'. s($this->_options[$subvalue]) .'"';
            }
            $a->value = implode(get_string('or', 'elis_core'), $value);
        } else {
            $a->value = '"'. s($this->_options[$value]) .'"';
        }
        $a->operator = get_string('isequalto', 'filters');

        return get_string('selectlabel', 'filters', $a);
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        static $counter = 0;
        $param_name = 'ex_simpleselect'. $counter++;
        $params = array();

        // For dummy filters
        if (empty($this->_name)) {
            return null;
        }

        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname) || $this->_nofilter) {
            // for manual filter use
            return null;
        }

        $value = $data['value'];
        if (is_array($value) && sizeof($value) == 1) {
            $value = reset($value);
        }

        if ($this->_multiple && is_array($value)) {
            $values = array();
            foreach ($value as $key => $val) {
                $name = $param_name .'_'. $key;
                $values[$name] = $val; // TBD: addslashes($val);
            }

            return array("{$full_fieldname} IN ( :". implode(', :', array_keys($values)) .')', $values);
        }

        return array("{$full_fieldname} = :{$param_name}",
                     array($param_name => $value));
    }
}

