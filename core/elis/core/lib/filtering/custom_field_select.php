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
 * custom_field_select.php - PHP Report filter for extra user profile menu fields
 *
 * Filter for matching user profile menu fields
 *
 * Required options include: all text filter requirements PLUS
 *  ['tables'] => array, table names as keys => table alias as values
 *  ['fieldid'] => int, the user_info_field id of the extra user profile field
 *
 * @package    elis-core
 * @subpackage filtering
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/user/filters/lib.php');
require_once($CFG->dirroot .'/elis/core/lib/filtering/simpleselect.php');
require_once($CFG->dirroot .'/elis/core/lib/data/customfield.class.php');

/**
 * Generic filter based on a list of values.
 */
class generalized_filter_custom_field_select extends generalized_filter_simpleselect {

    /**
     * Data type for field, used to search the correct db table
     */
    protected $_fieldtypes = array(
        'bool' => 'int',
        'char' => 'char',
        'int'  => 'int',
        'datetime'  => 'int',
        'num'  => 'num',
        'text' => 'text',
    );

    /**
     * Data type for field, used to search the correct db table
     */
    protected $_datatype;

    /**
     * User profile field id (int)
     */
    protected $_fieldid;

    /**
     * Wrapper SQL to get back desired context level
     */
    protected $_wrapper = '';

    /**
     * Inner field name for complex queries that use the wrapper setting
     */
    protected $_innerfield = 'c.instanceid';

    /**
     * A prefix to add to the subquery, usually involving IN or EXISTS
     */
    protected $_subqueryprefix = '';

    /**
     * Extra conditions to add to the filter query
     */
    public $_extraconditions = '';

    /**
     * This is the context level the current field lives at
     */
    protected $_contextlevel = 0;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function generalized_filter_custom_field_select($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {

        //ob_start();
        //var_dump($options);
        //$tmp = ob_get_contents();
        //ob_end_clean();
        //error_log("generalized_filter_custom_field_select($uniqueid, $alias, $name, $label, $advanced, $field, options = {$tmp}");

        parent::generalized_filter_simpleselect($uniqueid, $alias, $name, $label, $advanced, $field, $options);

        if (!array_key_exists('datatype', $options)) {
            print_error('missing_datatype', 'elis_core');
        }
        if (!array_key_exists($options['datatype'], $this->_fieldtypes)) {
            print_error('unknown_datatype', 'elis_core');
        }

        if (array_key_exists('wrapper', $options)) {
            $this->_wrapper = $options['wrapper'];
        }
        if (array_key_exists('innerfield', $options)) {
            $this->_innerfield = $options['innerfield'];
        }

        $this->_datatype = $options['datatype'];
        $this->_fieldid  = $options['fieldid'];

        //set up a "prefix" for the subquery, typically involving IN or EXISTS
        if (!empty($options['subqueryprefix'])) {
            //manually specified via constructor
            $this->_subqueryprefix = $options['subqueryprefix'];
        } else {
            $this->_subqueryprefix = "{$alias}.id IN ";
        }

        //allow for specification of extra conditions to impose on the IN/ EXISTS subquery
        $this->_extraconditions = '';
        if (!empty($options['extraconditions'])) {
            $this->_extraconditions = $options['extraconditions'];
        }

        if (!empty($options['contextlevel'])) {
            //store the context level if it's specified
            $this->_contextlevel = $options['contextlevel'];
        }
    }

    /**
     * Returns the condition to be used with SQL where
     * @uses $CFG
     * @uses $DB
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        global $CFG, $DB;
        static $counter = 0;

        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) {
            return null;
        }

        $value = $data['value'];
        $param_id = 'ex_cfs_id'. $counter;
        $param_value = 'ex_cfs_value'. $counter;
        $param_clevel = 'ex_cfs_clevel'. $counter;
        $counter++;

        //the data table where we can find the data we're filtering on
        $data_table = field_data::TABLE .'_'. $this->_fieldtypes[$this->_datatype];

        $cmpdata = 'data';
        if ($this->_datatype == 'text') {
            $cmpdata = $DB->sql_compare_text($cmpdata);
        }

        $check_null = '';
        $join_type  = '';
        $where = "fieldid = :fieldid AND contextid IS NULL
                  AND {$cmpdata} = :data";
        if ($DB->record_exists_select($data_table, $where,
                        array('fieldid'   => $this->_fieldid,
                              'data'      => $value))) {
            //filtering by the default value, so allow for null values
            $check_null = 'OR d.data IS NULL';
            $join_type = 'LEFT';
        }

        $cmpdata = 'd.data';
        if ($this->_datatype == 'text') {
            $cmpdata = $DB->sql_compare_text($cmpdata);
        }
        $sql = "{$this->_subqueryprefix}
                (SELECT {$this->_innerfield}
                   FROM {$CFG->prefix}context c
                  {$join_type} JOIN {$CFG->prefix}{$data_table} d
                     ON c.id = d.contextid
                    AND d.fieldid = :{$param_id}
                 {$this->_wrapper}
                  WHERE ({$cmpdata} = :{$param_value} {$check_null})
                    AND c.contextlevel = :{$param_clevel}
                 {$this->_extraconditions})";

        $params = array($param_id     => $this->_fieldid,
                        $param_value  => $value,
                        $param_clevel => $this->_contextlevel);
        //ob_start();
        //var_dump($params);
        //$tmp = ob_get_contents();
        //ob_end_clean();
        //error_log("custom_field_select::get_sql_filter(); sql = {$sql}, params = {$tmp}");
        return array($sql, $params);
    }

}

