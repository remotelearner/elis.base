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
 * @package    elis-core
 * @subpackage filtering
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();


require_once($CFG->dirroot .'/elis/core/lib/filtering/selectall.php');

/**
 * This filter is a child to the selectall filter
 * and was created to deal with the any/all situation
 * where LEFT JOINS in report queries lead to the
 * need to check the filter field for IS NOT NULL
 * Specifically for the cluster filter at this time
 */
class generalized_filter_selectany extends generalized_filter_selectall {

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function generalized_filter_selectany($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        $new_options = $options;
        $new_options['choices'] = array();
        if (empty($options['noany'])) {
            $new_options['choices'][0] = empty($options['anyvalue'])
                                         ? get_string('report_filter_all', 'elis_core')
                                         : $options['anyvalue'];
            $new_options['noany'] = true;
        }
        if (!empty($options['choices'])) {
            $new_options['choices'] += $options['choices'];
        }

        parent::__construct($uniqueid, $alias, $name, $label, $advanced, $field, $new_options);
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        $mform->addElement('select', $this->_uniqueid, $this->_label, $this->_options);
        $mform->addHelpButton($this->_uniqueid, 'simpleselect', 'elis_core' /* , $this->_label */ ); // TBV
        if ($this->_advanced) {
            $mform->setAdvanced($this->_uniqueid);
        }
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) {
            return null;
        }

        $value = $data['value'];
        if (is_numeric($value) && $value == 0) { // TBD: is_numeric ?
            return array("{$full_fieldname} IS NOT NULL", array());
        } else if ($value == 'null') {
            return array("{$full_fieldname} IS NULL", array());
        }

        return parent::get_sql_filter($data);
    }
}

