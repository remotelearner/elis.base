<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    elis_core
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/core/lib/data/data_filter.class.php');

/**
 * Helpers for testing filters
 */
abstract class filter_testcase extends PHPUnit_Framework_TestCase {
    /**
     * Equality check for SQL output from filters.
     * @param array $expected List of expected clauses
     * @param array $actual List of actual clauses
     * @param string $message Message
     */
    public static function assertfiltersqlequals($expected, $actual, $message='') {
        if (isset($expected['where'])) {
            $expected['where'] = trim(preg_replace('/\s+/', ' ', $expected['where']));
        }
        if (isset($expected['join'])) {
            $expected['join'] = trim(preg_replace('/\s+/', ' ', $expected['join']));
        }
        if (isset($actual['where'])) {
            $actual['where'] = trim(preg_replace('/\s+/', ' ', $actual['where']));
        }
        if (isset($actual['join'])) {
            $actual['join'] = trim(preg_replace('/\s+/', ' ', $actual['join']));
        }
        self::assertEquals($expected, $actual, $message);
    }
}

/**
 * Class to test the field filter.
 * @group elis_core
 */
class data_filter_testcase extends filter_testcase {
    /**
     * Data provider for field_filter test cases.
     * @return array SQL clauses
     */
    public function field_filter_provider() {
        $tests = array();

        // Basic functionality.
        $tests[] = array(
                array('foo', 'bar'),
                array(
                    'where' => 'foo = ?',
                    'where_parameters' => array('bar')
                )
        );

        // Other comparison operator.
        $tests[] = array(
                array('foo', 'bar', field_filter::NEQ),
                array(
                    'where' => 'foo != ?',
                    'where_parameters' => array('bar')
                )
        );

        // Checking for null.
        $tests[] = array(
                array('foo', null),
                array(
                    'where' => 'foo IS NULL',
                    'where_parameters' => array()
                )
        );

        // Checking for null.
        $tests[] = array(
                array('foo', null, field_filter::NEQ),
                array(
                    'where' => 'foo IS NOT NULL',
                    'where_parameters' => array()
                )
        );

        return $tests;
    }

    /**
     * Validate field filters.
     * @dataProvider field_filter_provider
     * @param array $init Parameters
     * @param array $expected Expected values
     */
    public function test_field_filter($init, $expected) {
        $construct = function ($name, $value, $comparison=field_filter::EQ) {
            return new field_filter($name, $value, $comparison);
        };
        $filter = call_user_func_array($construct, $init);

        $this->assertfiltersqlequals($expected, $filter->get_sql());
        $this->assertfiltersqlequals($expected, $filter->get_sql(true));
        $expected['where'] = 'x.'.$expected['where'];
        $this->assertfiltersqlequals($expected, $filter->get_sql(false, 'x'));
        $this->assertfiltersqlequals($expected, $filter->get_sql(true, 'x'));
    }

    /**
     * Data provider for join_filter test cases.
     * @return array SQL clauses
     */
    public function join_filter_provider() {
        $tests = array();

        // Simple join.
        $tests[] = array(
                array('id', 'foreign', 'foreignid'),
                array(
                    'where' => 'id IN (SELECT table_1.foreignid FROM {foreign} table_1 )',
                    'where_parameters' => array()
                ),
                array(
                    'join' => 'JOIN {foreign} table_1 ON table_1.foreignid = id',
                    'join_parameters' => array()
                ),
                array(
                    'where' => "EXISTS (SELECT 'x' FROM {foreign} table_1 WHERE table_1.foreignid = x.id)",
                    'where_parameters' => array()
                ),
                array(
                    'join' => 'JOIN {foreign} table_1 ON table_1.foreignid = x.id',
                    'join_parameters' => array()
                )
        );

        // Use nonunique table (so can't use join).
        $tests[] = array(
                array('id', 'foreign', 'foreignid', null, false, false),
                array(
                    'where' => 'id IN (SELECT table_1.foreignid FROM {foreign} table_1 )',
                    'where_parameters' => array()
                ),
                array(
                    'where' => 'id IN (SELECT table_1.foreignid FROM {foreign} table_1 )',
                    'where_parameters' => array()
                ),
                array(
                    'where' => "EXISTS (SELECT 'x' FROM {foreign} table_1 WHERE table_1.foreignid = x.id)",
                    'where_parameters' => array()
                ),
                array(
                    'where' => "EXISTS (SELECT 'x' FROM {foreign} table_1 WHERE table_1.foreignid = x.id)",
                    'where_parameters' => array()
                )
        );

        // Left join.
        $tests[] = array(
                array('id', 'foreign', 'foreignid', null, true),
                array(
                    'where' => 'id NOT IN (SELECT table_1.foreignid FROM {foreign} table_1 )',
                    'where_parameters' => array()
                ),
                array(
                    'join' => 'LEFT JOIN {foreign} table_1 ON table_1.foreignid = id ',
                    'join_parameters' => array(),
                    'where' => 'table_1.id IS NULL',
                    'where_parameters' => array()
                ),
                array(
                    'where' => "NOT EXISTS (SELECT 'x' FROM {foreign} table_1 WHERE table_1.foreignid = x.id)",
                    'where_parameters' => array()
                ),
                array(
                    'join' => 'LEFT JOIN {foreign} table_1 ON table_1.foreignid = x.id',
                    'join_parameters' => array(),
                    'where' => 'table_1.id IS NULL',
                    'where_parameters' => array()
                )
        );

        // Sub joins.
        $tests[] = array(
                array('id', 'foreign', 'foreignid', new join_filter('id', 'ff', 'ffid')),
                array(
                    'where' => 'id IN (SELECT table_1.foreignid
                                         FROM {foreign} table_1
                                         JOIN {ff} table_3
                                           ON table_3.ffid = table_1.id)',
                    'where_parameters' => array()
                ),
                array(
                    'join' => 'JOIN {foreign} table_1
                                 ON table_1.foreignid = id
                               JOIN {ff} table_2
                                 ON table_2.ffid = table_1.id',
                    'join_parameters' => array()
                ),
                array(
                    'where' => "EXISTS (SELECT 'x'
                                  FROM {foreign} table_1
                                  JOIN {ff} table_2
                                    ON table_2.ffid = table_1.id
                                 WHERE table_1.foreignid = x.id)",
                    'where_parameters' => array()
                ),
                array(
                    'join' => 'JOIN {foreign} table_1
                                 ON table_1.foreignid = x.id
                               JOIN {ff} table_2
                                 ON table_2.ffid = table_1.id',
                    'join_parameters' => array()
                )
        );

        // Left join with subjoin.
        $tests[] = array(
                array('id', 'foreign', 'foreignid', new join_filter('id', 'ff', 'ffid'), true),
                array(
                    'where' => 'id NOT IN (SELECT table_1.foreignid
                                     FROM {foreign} table_1
                                     JOIN {ff} table_3
                                       ON table_3.ffid = table_1.id)',
                    'where_parameters' => array()
                ),
                array(
                    // Gross SQL.
                    'join' => "LEFT JOIN {foreign} table_1
                                      ON table_1.foreignid = id
                                     AND (EXISTS (SELECT 'x'
                                                    FROM {ff} table_2
                                                   WHERE table_2.ffid = table_1.id))",
                    'join_parameters' => array(),
                    'where' => 'table_1.id IS NULL',
                    'where_parameters' => array()
                ),
                array(
                    'where' => "NOT EXISTS (SELECT 'x'
                                      FROM {foreign} table_1
                                      JOIN {ff} table_2
                                        ON table_2.ffid = table_1.id
                                     WHERE table_1.foreignid = x.id)",
                    'where_parameters' => array()
                ),
                array(
                    'join' => "LEFT JOIN {foreign} table_1
                                      ON table_1.foreignid = x.id
                                     AND (EXISTS (SELECT 'x'
                                                    FROM {ff} table_2
                                                   WHERE table_2.ffid = table_1.id))",
                    'join_parameters' => array(),
                    'where' => 'table_1.id IS NULL',
                    'where_parameters' => array()
                )
        );

        return $tests;
    }

    /**
     * Validate join filters.
     * @dataProvider join_filter_provider
     * @param array $init
     * @param array $expected00
     * @param array $expected01
     * @param array $expected10
     * @param array $expected11
     */
    public function test_join_filter($init, $expected00, $expected01, $expected10, $expected11) {
        $construct = function ($localfield, $foreigntable, $foreignfield, data_filter $filter = null, $notexist = false, $unique = true) {
            return new join_filter($localfield, $foreigntable, $foreignfield, $filter, $notexist, $unique);
        };
        $filter = call_user_func_array($construct, $init);

        data_filter::$_prefix_num = 0;
        $this->assertfiltersqlequals($expected00, $filter->get_sql());
        data_filter::$_prefix_num = 0;
        $this->assertfiltersqlequals($expected01, $filter->get_sql(true));
        data_filter::$_prefix_num = 0;
        $this->assertfiltersqlequals($expected10, $filter->get_sql(false, 'x'));
        data_filter::$_prefix_num = 0;
        $this->assertfiltersqlequals($expected11, $filter->get_sql(true, 'x'));
    }
}
