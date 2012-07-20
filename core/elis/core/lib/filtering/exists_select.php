<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * exists_select.php - PHP Report filter for extra/custom entity fields
 *
 * Filter for matching on tables not included in the main report
 *
 * @package    elis-core
 * @subpackage filtering
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/elis/core/lib/filtering/lib.php');
require_once($CFG->dirroot .'/elis/core/lib/filtering/simpleselect.php');

/**
 * Generic filter based on a list of values.
 */
class generalized_filter_exists_select extends generalized_filter_simpleselect {

    /**
     * Wrapper SQL to get back to desired table
     */
    protected $_wrapper = '';

    /**
     * Wrapper SQL to get back to desired table
     */
    protected $_table = 'unknown';

    /**
     * Inner field name for complex queries that use the wrapper setting
     */
    protected $_innerfield = 'id';

    /**
     * Inner field name for complex queries that use the wrapper setting
     */
    protected $_outerfield = 'id';

    protected $_fields = array(
        '_wrapper'    => 'wrapper',
        '_innerfield' => 'innerfield',
        '_outerfield' => 'outerfield',
        '_table'      => 'table',
    );

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function generalized_filter_exists_select($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {

        parent::generalized_filter_simpleselect($uniqueid, $alias, $name, $label, $advanced, $field, $options);

        foreach ($this->_fields as $property => $field) {
            if (array_key_exists($field, $options)) {
                $this->$property = $options[$field];
            }
        }
    }

    /**
     * Returns the condition to be used with SQL where
     * @uses $CFG
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        global $CFG;

        list($sql, $params) = parent::get_sql_filter($data);

        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) {
            return null;
        }

        $sql = "{$this->_outerfield} IN 
                    (SELECT {$this->_innerfield}
                       FROM {$CFG->prefix}{$this->_table} {$this->_alias}
                     {$this->_wrapper}
                      WHERE {$sql})";

        return array($sql, $params);
    }

}

