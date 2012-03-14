<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once($CFG->dirroot.'/user/filters/lib.php');

/**
 * Generalized Filter Display Text
 *
 * @author Tyler Bannister <tyler.bannister@remote-learner.net>
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 */
class generalized_filter_display_text extends generalized_filter_type {
    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function generalized_filter_display_text($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced,
                    !empty($options['help'])
                    ? $options['help']
                    : array('displaytext', $label, 'elis_core'));
    }
    /**
     * Returns the condition to be used with SQL where
     *
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        return null;
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $value = $data['value'];

        $a = new object();
        $a->label    = $this->_label;
        $a->value    = $value;
        $a->operator = get_string('isequalto','filters');

        return get_string('selectlabel', 'filters', $a);
    }

    /**
     * Adds controls specific to this filter in the form.
     *
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        $mform->addElement('textarea', $this->_uniqueid, $this->_label, array('cols' => '40', 'rows' => '5'));
        $mform->addHelpButton($this->_uniqueid, $this->_filterhelp[0], $this->_filterhelp[2]);
    }

    /**
     * Placeholder function
     *
     * @param array $data Report parameters?
     */
    function get_report_parameters($data) {
        //obsolete
    }

    /**
     * Retrieves data from the form data
     *
     * @param object $formdata Data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $key = $this->_uniqueid;

        if (isset($formdata->$key)) {
            return array('value' => $formdata->$key);
        }

        return false;
    }
}
