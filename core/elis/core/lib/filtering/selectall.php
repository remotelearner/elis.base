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

require_once($CFG->dirroot .'/elis/core/lib/filtering/simpleselect.php');

/**
 * Generic filter based on a list of values, which also has a "select all" option.
 */
class generalized_filter_selectall extends generalized_filter_simpleselect {
    
    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function generalized_filter_selectall($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {        
        $new_options = array();
        if (!empty($options)) {
            foreach ($options as $id => $value) {
                $new_options[$id] = $value;
            }
        }

        $new_options['choices'] = array();
        if (empty($options['noany']) && !isset($options['choices'][''])) {
            $new_options['choices'][''] = empty($options['anyvalue'])
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
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return array the filtering condition with optional parameters 
     *               or null if the filter is disabled
     */
    function get_sql_filter($data) {
        if ($data['value'] === 0) {
            return array('TRUE', array());
        }
        
        return parent::get_sql_filter($data);
    }
}

