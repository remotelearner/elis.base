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

require_once(elis::lib('filtering/autocomplete_base.php'));

class generalized_filter_autocomplete extends generalized_filter_autocomplete_base {

    public function load_options($options) {

        //required options
        if (empty($options['table'])) {
            print_error('autocomplete_notable', 'elis_core');
        }
        $this->_table = $options['table'];

        if (empty($options['search_fields']) || !is_array($options['search_fields'])) {
            print_error('autocomplete_nofields', 'elis_core');
        }
        $this->_fields = $options['search_fields'];


        //optional options
        if (!empty($options['results_fields'])) {
            $this->results_fields = $options['results_fields'];
        } else {
            $this->results_fields = array_combine($options['search_fields'],$options['search_fields']);
        }
    }


    /**
     * Gets the labels for each column of the results table.
     * @return  array  An array of strings with values in the same order as $this->get_results_fields();
     */
    public function get_results_headers() {
        return array_values($this->results_fields);
    }

    /**
     * Gets the fields for each column of the results table.
     * @return  array  An array of strings corresponding to members of a SQL result row with values
     *                  in the same order as $this->get_results_headers();
     */
    public function get_results_fields() {
        return array_keys($this->results_fields);
    }

    /**
     * Gets the autocomplete search SQL for the autocomplete UI
     * Note that this is the SQL used to select a value, not the SQL used in the report SQL
     * @global  $CFG
     * @param   string  $q  The query string
     * @return  string      The SQL query
     */
    public function get_search_results($q) {
        global $CFG, $USER, $DB;

        $q = explode(' ',$q);
        $search = array();
        foreach ($q as $q_word) {
            $this_word = array();
            foreach ($this->_fields as $i => $field) {
                $this_word[] = $field.' LIKE "%'.$q_word.'%"';
            }
            $search[] = $this_word;
        }

        foreach ($search as $i => $sqls) {
            $search[$i] = implode(' OR ',$sqls);
        }

        if (!empty($this->_restriction_sql)) {
            $search[] = $this->_restriction_sql;
        }

        $wherestr = '('.implode(') AND (',$search).')';

        $sql = 'SELECT id,'.implode(',',$this->_fields)
                .' FROM '.$CFG->prefix.$this->_table
                .' WHERE '.$wherestr
                .' LIMIT 0,100';
        return $DB->get_records_sql($sql);
    }
}

