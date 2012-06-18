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

require_once(elis::lib('filtering/lib.php'));

/**
 * Generic filter based on a list of values.
 */
class generalized_filter_autocomplete extends generalized_filter_type {
    /**
     * options for the list values
     */
    public $_options;
    public $_field;
    public $_fields;
    public $_table;
    public $_parent_report;
    public $_label_template;
    public $_selection_enabled = true;
    public $_default_id='';
    public $_default_label='';

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    public function __construct($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced,
                    !empty($options['help'])
                    ? $options['help']
                    : array('simpleselect', $label, 'filters'));
        $this->_field    = $field;
        $this->_table  = $options['table'];
        $this->_fields  = $options['fields'];
        $this->_parent_report  = $options['report'];
        $this->_selection_enabled = (!empty($options['selection_enabled']) && $options['selection_enabled'] === true) ? true : false;
        $this->_restriction_sql = (!empty($options['restriction_sql'])) ? $options['restriction_sql'] : '';
        $this->_default_id = (!empty($options['defaults']['id'])) ? $options['defaults']['id'] : '';
        $this->_default_label = (!empty($options['defaults']['label'])) ? $options['defaults']['label'] : '';
        if (isset($options['label_template'])) {
            $this->_label_template = $options['label_template'];
        } else {
            $this->_label_template = (!empty($this->_fields[0]))
                                        ? $options['label_template']
                                        : '';
        }
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    public function setupForm(&$mform) {
        global $CFG;

        $report = $this->_parent_report;
        $filter = $this->_uniqueid;
        $popup_url = $CFG->wwwroot.'/elis/core/lib/form/autocomplete.php';
        $popup_url .= '?report='.$report.'&filter='.$filter;
        $popup_link = '<span id="id_'.$this->_uniqueid.'_label"></span> ';
        if ($this->_selection_enabled === true) {
            $popup_link .= '<a onclick="show_panel(\''.$popup_url.'\');" href="#">Select...</a>';
        }

        $mform->addElement('static', 'selector', $this->_label,$popup_link);
        $mform->addElement('html','<div style="display:none;">');
        $mform->addElement('text', $this->_uniqueid.'_labelsave', '');
        $mform->addElement('text', $this->_uniqueid, '');
        if (!empty($this->_default_label)) {
            $mform->setDefault($this->_uniqueid.'_labelsave',$this->_default_label);
        }
        if (!empty($this->_default_id)) {
            $mform->setDefault($this->_uniqueid,$this->_default_id);
        }
        $mform->addElement('html','</div>');
        if (!empty($this->_filterhelp)) {
            $mform->addHelpButton('selector', $this->_filterhelp[0], $this->_filterhelp[2]);
        }

        $mform->addElement('html','
                <script>
                function show_panel( url ) {
                    var x = window.open(url, \'newWindow\', \'height=700,width=650,resizable=yes,scrollbars=yes,screenX=50,screenY=50\');
                }
                labelsave = document.getElementById(\'id_'.$this->_uniqueid.'_labelsave\');
                labeldisp = document.getElementById(\'id_'.$this->_uniqueid.'_label\');
                if (labelsave != null && labeldisp != null) {
                    labeldisp.innerHTML = labelsave.value;
                }
                </script>');
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    public function check_data($formdata) {
        $field = $this->_uniqueid;

        if (array_key_exists($field, $formdata) and $formdata->$field !== '') {
            return array('value'=>(string)$formdata->$field);
        }

        return false;
    }

    public function get_report_parameters($data) {
        return array('value' => $data['value'],
                     'numeric' => $this->_numeric);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label($data) {
        $value = $data['value'];

        $a = new object();
        $a->label    = $this->_label;
        $a->value    = '"'.s($this->_options[$value]).'"';
        $a->operator = get_string('isequalto','filters');

        return get_string('selectlabel', 'filters', $a);
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    public function get_sql_filter($data) {
        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) { // for manual filter use
            return null;
        }

        $value = addslashes($data['value']);

        return array("{$full_fieldname} = :p_autocompleteuserid", array('p_autocompleteuserid' => $value));
    }

}
