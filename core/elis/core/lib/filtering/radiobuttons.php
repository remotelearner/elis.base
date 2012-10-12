<?php //$Id$
/**
 * radiobuttons.php - filter
 *
 * Group of radio buttons (TBD: add layout options, columns/row designation ...)
 * for selecting choices for DB field using SQL =
 * options include:
 *   ['choices']   = array(key = 'DB field value' => value = 'label string' ...)
 *   ['prelabels'] = array(key = 'DB field value' => value = 'pre-label string')
 *   ['checked']   = (string) 'DB field value' - initially checked on form (optional)
 *   ['advanced']  = boolean - true if radio buttons are advanced form elements,
 *                             false (default) is not advanced.
 *   ['heading']   = string - the radio group heading (optional, raw html)
 *   ['footer']    = string - the radio group footer (optional, raw html)
 *   ['numeric']   = boolean - true if DB field is numeric,
 *                             false (the default) -> string
 *
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
 * radiobuttons.php - filter
 *
 * Group of radio buttons (TBD: add layout options, columns/row designation ...)
 * for selecting choices for DB field using SQL =
 * options include:
 *   ['choices']   = array(key = 'DB field value' => value = 'label string' ...)
 *   ['prelabels'] = array(key = 'DB field value' => value = 'pre-label string')
 *   ['checked']   = (string) 'DB field value' - initially checked on form (optional)
 *   ['advanced']  = boolean - true if radio buttons are advanced form elements,
 *                             false (default) is not advanced.
 *   ['heading']   = string - the radio group heading (optional, raw html)
 *   ['footer']    = string - the radio group footer (optional, raw html)
 *   ['numeric']   = boolean - true if DB field is numeric,
 *                             false (the default) -> string
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/elis/core/lib/filtering/lib.php');
require_once($CFG->dirroot .'/user/filters/lib.php');

/**
 * Generic radio button filter
 */
class generalized_filter_radiobuttons extends generalized_filter_type {
    /**
     * Array - options for the radio buttons:
     * 'choices'  => array
     *     keys are radio button 'values' numbers or strings (eg DB field vals)
     *     values are radio button label strings
     * 'checked'  => single key (from 'choices') to be intially checked
     * 'default'  => single key (from 'choices') to be used as default.
     * 'advanced' => boolean - true if radio buttons are advanced form elements
     * 'prelabels' => array
     *     keys are same as 'choices' keys
     *     values are radio form elements 'pre-label string' argument
     * 'heading'  => string [optional] - radio buttons group heading
     * 'footer'   => string [optional] - radio buttons group footer
     * 'numeric'  => boolean - true if 'keys' should be numerically compared
     *                        optional, defaults to false (not numeric)
     */
    var $_options;

    var $_field;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table field name
     * @param array $options mixed array of radio button options - see above
     */
    function generalized_filter_radiobuttons($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced,
                    !empty($options['help']) ? $options['help'] : null);
        $this->_field   = $field;
        $this->_options = $options;
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        $objs = array();

        if (!empty($this->_options['choices'])) {
            foreach ($this->_options['choices'] as $key => $value) {
                $objs[] = &$mform->createElement('radio', $this->_uniqueid,
                                   !empty($this->_options['prelabels'][$key])
                                    ? $this->_options['prelabels'][$key] : '',
                                   $value, $key);
            }
        }

        if (!empty($this->_options['footer'])) {
            $objs[] = &$mform->createElement('html', $this->_options['footer']);
        }

        $mform->addElement('group', $this->_uniqueid.'_grp',
                    !empty($this->_options['heading'])
                    ? $this->_options['heading']: '' , $objs, '<br/>', false);

        if (!empty($this->_filterhelp)) {
            $mform->addHelpButton($this->_uniqueid.'_grp', $this->_filterhelp[0], $this->_filterhelp[2] /* , $this->_filterhelp[1] */); // TBV
        }

        if (!empty($mform->_defaultValues[$this->_uniqueid]) ||
            !empty($this->_options['checked'])) {
            $mform->setDefault($this->_uniqueid,
                        !empty($mform->_defaultValues[$this->_uniqueid])
                        ? $mform->_defaultValues[$this->_uniqueid]
                        : $this->_options['checked']);
        }
 
        if (!empty($this->_options['advanced'])) {
            // TBD: heading & footer will still show!!!
            $mform->setAdvanced($this->_uniqueid); // . '_grp' ???
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $retval = null;
        // check which radio button is checked ...
        if (!empty($formdata->{$this->_uniqueid})) {
            $val = $formdata->{$this->_uniqueid};
            $retval = empty($this->_options['numeric'])
                      ? "'". $val ."'" : $val;
            //error_log("radiobuttons.php::check_data(): formdata->{$this->_uniqueid} ({$val}) is CHECKED");
        }

        return empty($retval) ? false : array('value' => (string)$retval);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        if (isset($data['value'])) {
            $val = trim($data['value'], "'");
            foreach ($this->_options['choices'] as $key => $value) {
                if ($val == $key) {
                    return "{$this->_label}: {$value}";
                }
            }
        }
        return "{$this->_label}: ". get_string('none');
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        static $counter = 0;
        $param_name = 'ex_radiobuttons'. $counter++;
        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) {
            return null; // TBD ''
        }
        return array("{$full_fieldname} = :{$param_name}",
                     array($param_name => $data['value']));
    }

    function get_report_parameters($data) {
        return array('value' => $data['value']);
    }

    function get_default_values($filter_data) {
        if (isset($this->_options['default'])) {
            foreach($filter_data as $key => $val) {
                // Should be only the one key ...
                return array($key => $this->_options['default']);
            }
        }
        return parent::get_default_values($filter_data);
    }

}

