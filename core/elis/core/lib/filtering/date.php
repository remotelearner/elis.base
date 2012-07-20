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
 * Generic filter based on a date.
 */
class generalized_filter_date extends generalized_filter_type {
    /**
     * the fields available for comparisson
     */

    var $_field;

    /**
     * the timezone to use for date labels with default
     */
    var $_timezone = 99;

    /**
     * the format for date labels with default
     */
    var $_dateformat = '';

    /**
     * Boolean set from options['never_included'],
     */
    var $_never_included = false; // TBD: default

    /**
     * Optional start & stop year
     */
    var $_startyear = 0;
    var $_stopyear = 0;

    /**
     * Option to include time hh:mm fields
     */
    var $_inctime = false;

    /**
     * Constructor
     * @param string $alias aliacs for the table being filtered on
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     */
    function generalized_filter_date($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced,
                     !empty($options['help'])
                     ? $options['help'] : array('date', $label, 'elis_core'));

        $this->_field = $field;
        if (isset($options['never_included'])) {
            $this->_never_included = $options['never_included'];
        }
        if (isset($options['timezone'])) {
            $this->_timezone = $options['timezone'];
        }
        if (isset($options['dateformat'])) {
            $this->_dateformat = $options['dateformat'];
        }
        if (isset($options['choices']['startyear'])) {
            $this->_startyear = $options['choices']['startyear'];
        }
        if (isset($options['choices']['stopyear'])) {
            $this->_stopyear = $options['choices']['stopyear'];
        }
        if (isset($options['inctime'])) {
            $this->_inctime = $options['inctime'];
        } else if (isset($options['choices']['inctime'])) {
            $this->_inctime = $options['choices']['inctime'];
        }
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        $objs = array();

        $options = array('optional' => false);
        if (!empty($this->_timezone)) {
            $options['timezone'] = $this->_timezone;
        }
        if (!empty($this->_startyear)) {
            $options['startyear'] = $this->_startyear;
        }
        if (!empty($this->_stopyear)) {
            $options['stopyear'] = $this->_stopyear;
        }
        $date_elem = $this->_inctime ? 'date_time_selector' : 'date_selector';
        $objs[] =& $mform->createElement('checkbox', $this->_uniqueid.'_sck', null, get_string('isafter', 'filters'));
        $objs[] =& $mform->createElement($date_elem, $this->_uniqueid.'_sdt', null, $options);
        $objs[] =& $mform->createElement('static', $this->_uniqueid.'_break', null, '<br/>');
        $objs[] =& $mform->createElement('checkbox', $this->_uniqueid.'_eck', null, get_string('isbefore', 'filters'));
        $objs[] =& $mform->createElement($date_elem, $this->_uniqueid.'_edt', null, $options);

        if ($this->_never_included) {
            $objs[] = & $mform->createElement('advcheckbox', $this->_uniqueid.'_never', null, get_string('includenever', 'filters'));
        }

        $grp =& $mform->addElement('group', $this->_uniqueid.'_grp', $this->_label, $objs, '', false);
        $mform->addHelpButton($this->_uniqueid.'_grp', $this->_filterhelp[0], $this->_filterhelp[2] /* , $this->_filterhelp[1] */ ); // TBV
        if ($this->_advanced) {
            $mform->setAdvanced($this->_uniqueid.'_grp');
        }

        $mform->disabledIf($this->_uniqueid.'_sdt[day]', $this->_uniqueid.'_sck', 'notchecked');
        $mform->disabledIf($this->_uniqueid.'_sdt[month]', $this->_uniqueid.'_sck', 'notchecked');
        $mform->disabledIf($this->_uniqueid.'_sdt[year]', $this->_uniqueid.'_sck', 'notchecked');
        $mform->disabledIf($this->_uniqueid.'_edt[day]', $this->_uniqueid.'_eck', 'notchecked');
        $mform->disabledIf($this->_uniqueid.'_edt[month]', $this->_uniqueid.'_eck', 'notchecked');
        $mform->disabledIf($this->_uniqueid.'_edt[year]', $this->_uniqueid.'_eck', 'notchecked');

        if ($this->_inctime) {
            $mform->disabledIf($this->_uniqueid.'_sdt[hour]', $this->_uniqueid.'_sck', 'notchecked');
            $mform->disabledIf($this->_uniqueid.'_sdt[minute]', $this->_uniqueid.'_sck', 'notchecked');
            $mform->disabledIf($this->_uniqueid.'_edt[hour]', $this->_uniqueid.'_eck', 'notchecked');
            $mform->disabledIf($this->_uniqueid.'_edt[minute]', $this->_uniqueid.'_eck', 'notchecked');
        }

        if ($this->_never_included) {
            $mform->disabledIf($this->_uniqueid.'_never', $this->_uniqueid.'_eck', '0');
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $sck = $this->_uniqueid.'_sck';
        $sdt = $this->_uniqueid.'_sdt';
        $eck = $this->_uniqueid.'_eck';
        $edt = $this->_uniqueid.'_edt';
        $never = $this->_uniqueid.'_never';

        if (!array_key_exists($sck, $formdata) and !array_key_exists($eck, $formdata)) {
            return false;
        }

        $data = array();
        if (array_key_exists($sck, $formdata) && !empty($formdata->$sck)) {
            $data['after'] = $formdata->$sdt;
        } else {
            $data['after'] = 0;
        }
        if (array_key_exists($eck, $formdata) && !empty($formdata->$eck)) {
            $data['before'] = $formdata->$edt;
        } else {
            $data['before'] = 0;
        }
        if (array_key_exists($never, $formdata) && !empty($formdata->$never)) {
            $data['never'] = $formdata->$never;
        } else {
            $data['never'] = 0;
        }

        return $data;
    }

    function get_report_parameters($data) {

        return array('after' => $data['after'],
                     'before' => $data['before'],
                     'never' => $data['never']);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $after  = $data['after'];
        $before = $data['before'];
        $never = $data['never'];
        $field  = $this->_field;

        $a = new object();
        $a->label  = $this->_label;
        $a->after  = userdate($after, $this->_dateformat, $this->_timezone);
        $a->before = userdate($before, $this->_dateformat, $this->_timezone);

        if ($never) {
            $strnever = ' ('. get_string('includenever', 'filters') .')';
        } else {
            $strnever = '';
        }

        if ($after and $before) {
            return get_string('datelabelisbetween', 'filters', $a) . $strnever;
        } else if ($after) {
            return get_string('datelabelisafter', 'filters', $a) . $strnever;
        } else if ($before) {
            return get_string('datelabelisbefore', 'filters', $a) . $strnever;
        }
        return '';
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

        if ($this->_never_included) { // TBD
            if (!empty($data['never'])) {
                $sql = "{$full_fieldname} >= 0";
            } else {
                $sql = "{$full_fieldname} > 0";
            }
        } else {
            $sql = 'TRUE';
        }

        if (!empty($data['after'])) {
            $param_after = 'ex_dateafter'. $counter;
            $sql .= " AND {$full_fieldname} >= :{$param_after}";
            $params[$param_after] = $data['after'];
        }

        if (!empty($data['before'])) {
            $param_before = 'ex_datebefore'. $counter;
            $sql .= " AND {$full_fieldname} <= :{$param_before}";
            $params[$param_before] = $data['before'];
        }

        if (!empty($params)) {
            $counter++;
        }

        return array($sql, $params);
    }

}

