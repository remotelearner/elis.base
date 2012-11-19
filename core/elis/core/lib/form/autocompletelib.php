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
 * @subpackage form
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once($CFG->libdir.'/formslib.php');

class autocomplete_eliswithcustomfields_config extends moodleform {
    function definition() {
        global $CFG;

        $mform = &$this->_form;

        $instance_fields = array();
        if (!empty($this->_customdata['instance_fields']) && is_array($this->_customdata['instance_fields'])) {
            foreach ($this->_customdata['instance_fields'] as $field => $display) {
                $instance_fields[] = array('field'=>$field,'table'=>'instance','display'=>$display);
            }
        }

        $custom_fields = array();
        if (!empty($this->_customdata['custom_fields']) && is_array($this->_customdata['custom_fields'])) {
            foreach ($this->_customdata['custom_fields'] as $field_info) {
                $custom_fields[] = array('field'=>$field_info['shortname'],'table'=>'custom_field','display'=>$field_info['label']);
            }
        }

        $mform->addElement('header', 'configform',get_string('filt_autoc_wcf_config_formtitle','elis_core'));
        $mform->addElement('html',get_string('filt_autoc_wcf_config_desc','elis_core'));

        $mform->addElement('html','<table style="width:100%">');
        $headers_html =
            '<tr>'
                .'<th>'.get_string('filt_autoc_wcf_config_search','elis_core').'</th>'
                .'<th>'.get_string('filt_autoc_wcf_config_display','elis_core').'</th>'
                .'<th>'.get_string('filt_autoc_wcf_config_restrict','elis_core').'</th>'
                .'<th>'.get_string('filt_autoc_wcf_config_field','elis_core').'</th>'
            .'</tr>';
        $mform->addElement('html',$headers_html);
        $all_fields = array_merge($instance_fields,$custom_fields);
        foreach ($all_fields as $field) {
            $mform->addElement('html','<tr><td>');
            $mform->addElement('advcheckbox',$field['table'].'['.$field['field'].'][search]','');
            $mform->addElement('html','</td><td>');
            $mform->addElement('advcheckbox',$field['table'].'['.$field['field'].'][disp]','');
            $mform->addElement('html','</td><td>');
            if ($field['table'] === 'custom_field') {
                $mform->addElement('advcheckbox',$field['table'].'['.$field['field'].'][restrict]','');
            }
            $mform->addElement('html','</td><td>');
            $mform->addElement('html',$field['display']);
            $mform->addElement('html','</td></tr>');

            //set defaults
            if (isset($this->_customdata['config'][$field['table']][$field['field']])) {
                if (!empty($this->_customdata['config'][$field['table']][$field['field']]['search'])) {
                    $mform->setDefault($field['table'].'['.$field['field'].'][search]',1);
                }
                if (!empty($this->_customdata['config'][$field['table']][$field['field']]['disp'])) {
                    $mform->setDefault($field['table'].'['.$field['field'].'][disp]',1);
                }
                if ($field['table'] === 'custom_field') {
                    if (!empty($this->_customdata['config'][$field['table']][$field['field']]['restrict'])) {
                        $mform->setDefault($field['table'].'['.$field['field'].'][restrict]',1);
                    }
                }
            }
        }
        $mform->addElement('html','</table>');
        $this->add_action_buttons();
    }
}

function filt_autoc_get_config($report,$uniqid) {
    static $config = array();

    if (empty($config)) {
        $config = get_config('filter-autocomplete',$report.'/'.$uniqid);
        if (!empty($config)) {
            $config = @unserialize($config);
            $config = (is_array($config)) ? $config : array();
        } else {
            $config = array();
        }
    }

    return $config;
}

function filt_autoc_set_config($report,$uniqid,$configdata) {
    $configdata = serialize($configdata);
    set_config($report.'/'.$uniqid, $configdata,'filter-autocomplete');
}

function highlight_substrings($needles, $haystack) {
    $highlight_areas = array();
    foreach ($needles as $substr) {
        $substr = stripslashes($substr);
        $offset = 0;
        $substr_loc = null;
        while($substr_loc !== false) {
            $substr_loc = stripos($haystack,$substr,$offset);
            if ($substr_loc !== false) {
                $substr_len = strlen($substr);
                $min = $substr_loc;
                $max = $substr_loc + $substr_len;
                $offset = $max;
                $existing_expanded = false;
                foreach ($highlight_areas as $i => $info) {

                    //minimum is contained within another highlight
                    if ($min >= $info['min'] && $min <= $info['max']) {
                        if ($max >= $info['max']) {
                            $highlight_areas[$i]['max'] = $max;
                        } else {
                            //this range is contained within a preexisting range
                        }
                        $existing_expanded = true;
                        break;
                    }

                    //maximum is contained withing another highlight
                    if ($max >= $info['min'] && $max <= $info['max']) {
                        if ($min <= $info['min']) {
                            $highlight_areas[$i]['min'] = $min;
                        }
                        $existing_expanded = true;
                        break;
                    }

                    //another highlight is contained within this range
                    if ($min < $info['min'] && $max > $info['max']) {
                        $highlight_areas[$i]['min'] = $min;
                        $highlight_areas[$i]['max'] = $max;
                        $existing_expanded = true;
                        break;
                    }
                }

                if ($existing_expanded === false) {
                    $highlight_areas[] = array('min'=>$min, 'max'=>$max);
                }
            }
        }
    }

    if (!empty($highlight_areas) && is_array($highlight_areas)) {
        $highlight_areas_indexed = array();
        foreach ($highlight_areas as $i => $range) {
            $highlight_areas_indexed[$range['min']]=$range;
        }
        $highlight_areas = $highlight_areas_indexed;
        unset($highlight_areas_indexed);
        sort($highlight_areas);

        $processed_substrs = array();
        $offset = 0;
        foreach ($highlight_areas as $i => $range) {

            $min = $range['min']-$offset;
            $max = $range['max']-$offset;
            $length = ($max-$min);

            $part = '';
            if ($min > 0) {
                $part = substr($haystack,0,$min);
            }
            $part .= '<span class="reshighlight">'.substr($haystack,$min,$length).'</span>';
            //$part .= '['.substr($haystack,$min,$length).']';

            $haystack = substr($haystack,$max);
            $offset+=$max;
            $processed_substrs[] = $part;
        }
        $processed_substrs[] = $haystack;
        $haystack = implode('',$processed_substrs);
    }

    return $haystack;
}
