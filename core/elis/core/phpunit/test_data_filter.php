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
 * @package    elis
 * @subpackage core
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) . '/../test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/core/lib/data/data_filter.class.php');

/**
 * Helpers for testing filters
 */
abstract class filter_TestCase extends PHPUnit_Framework_TestCase {
    /**
     * Equality check for SQL output from filters
     */
    public static function assertFilterSQLEquals($expected, $actual, $message='') {
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
 * Test the field filter
 */
class filterTest extends filter_TestCase {
    protected $backupGlobalsBlacklist = array('DB');

    /**
     * test cases for field_filter
     */
    public function field_filterProvider() {
        $tests = array();

        // basic functionality
        $tests[] = array(array('foo', 'bar'),
                         array('where' => 'foo = ?',
                               'where_parameters' => array('bar')));

        // other comparison operator
        $tests[] = array(array('foo', 'bar', field_filter::NEQ),
                         array('where' => 'foo != ?',
                               'where_parameters' => array('bar')));

        // checking for null
        $tests[] = array(array('foo', null),
                         array('where' => 'foo IS NULL',
                               'where_parameters' => array()));

        // checking for null
        $tests[] = array(array('foo', null, field_filter::NEQ),
                         array('where' => 'foo IS NOT NULL',
                               'where_parameters' => array()));

        return $tests;
    }

    /**
     * @dataProvider field_filterProvider
     */
    public function testFieldFilter($init, $expected) {
        $construct = function ($name, $value, $comparison=field_filter::EQ) {
            return new field_filter($name, $value, $comparison);
        };
        $filter = call_user_func_array($construct, $init);

        $this->assertFilterSQLEquals($expected, $filter->get_sql());
        $this->assertFilterSQLEquals($expected, $filter->get_sql(true));
        $expected['where'] = 'x.' . $expected['where'];
        $this->assertFilterSQLEquals($expected, $filter->get_sql(false, 'x'));
        $this->assertFilterSQLEquals($expected, $filter->get_sql(true, 'x'));
    }

    /**
     * test cases for join_filter
     */
    public function join_filterProvider() {
        $tests = array();

        // simple join
        $tests[] = array(
            array('id', 'foreign', 'foreignid'
            ),
            array(
                'where' => 'id IN (SELECT table_1.foreignid
                                     FROM {foreign} table_1 )',
                'where_parameters' => array()
            ),
            array(
                'join' => 'JOIN {foreign} table_1
                             ON table_1.foreignid = id',
                'join_parameters' => array()
            ),
            array(
                'where' => "EXISTS (SELECT 'x'
                                      FROM {foreign} table_1
                                     WHERE table_1.foreignid = x.id)",
                'where_parameters' => array()
            ),
            array(
                'join' => 'JOIN {foreign} table_1
                             ON table_1.foreignid = x.id',
                'join_parameters' => array()
            )
        );

        // use nonunique table (so can't use join)
        $tests[] = array(
            array('id', 'foreign', 'foreignid', null, false, false
            ),
            array(
                'where' => 'id IN (SELECT table_1.foreignid
                                     FROM {foreign} table_1 )',
                'where_parameters' => array()
            ),
            array(
                'where' => 'id IN (SELECT table_1.foreignid
                                     FROM {foreign} table_1 )',
                'where_parameters' => array()
            ),
            array(
                'where' => "EXISTS (SELECT 'x'
                                      FROM {foreign} table_1
                                     WHERE table_1.foreignid = x.id)",
                'where_parameters' => array()
            ),
            array(
                'where' => "EXISTS (SELECT 'x'
                                      FROM {foreign} table_1
                                     WHERE table_1.foreignid = x.id)",
                'where_parameters' => array()
            )
        );

        // left join
        $tests[] = array(
            array('id', 'foreign', 'foreignid', null, true
            ),
            array(
                'where' => 'id NOT IN (SELECT table_1.foreignid
                                         FROM {foreign} table_1 )',
                'where_parameters' => array()
            ),
            array(
                'join' => 'LEFT JOIN {foreign} table_1
                                  ON table_1.foreignid = id ',
                'join_parameters' => array(),
                'where' => 'table_1.id IS NULL',
                'where_parameters' => array()
            ),
            array(
                'where' => "NOT EXISTS (SELECT 'x'
                                          FROM {foreign} table_1
                                         WHERE table_1.foreignid = x.id)",
                'where_parameters' => array()
            ),
            array(
                'join' => 'LEFT JOIN {foreign} table_1
                                  ON table_1.foreignid = x.id',
                'join_parameters' => array(),
                'where' => 'table_1.id IS NULL',
                'where_parameters' => array()
            )
        );

        // sub joins
        $tests[] = array(
            array('id', 'foreign', 'foreignid',
                  new join_filter('id', 'ff', 'ffid')
            ),
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

        // left join with subjoin
        $tests[] = array(
            array('id', 'foreign', 'foreignid',
                  new join_filter('id', 'ff', 'ffid'), true
            ),
            array(
                'where' => 'id NOT IN (SELECT table_1.foreignid
                                         FROM {foreign} table_1
                                         JOIN {ff} table_3
                                           ON table_3.ffid = table_1.id)',
                'where_parameters' => array()
            ),
            array(
                // gross SQL
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

        /*
        $tests[] = array(
            array('id', 'foreign', 'foreignid'
            ),
            array(
            ),
            array(
            ),
            array(
            ),
            array(
            )
        );
        */

        return $tests;
    }

    /**
     * @dataProvider join_filterProvider
     */
    public function testJoinFilter($init, $expected00, $expected01, $expected10, $expected11) {
        $construct = function ($local_field, $foreign_table, $foreign_field, data_filter $filter=null, $not_exist = false, $unique = true) {
            return new join_filter($local_field, $foreign_table, $foreign_field, $filter, $not_exist, $unique);
        };
        $filter = call_user_func_array($construct, $init);

        data_filter::$_prefix_num = 0;
        $this->assertFilterSQLEquals($expected00, $filter->get_sql());
        data_filter::$_prefix_num = 0;
        $this->assertFilterSQLEquals($expected01, $filter->get_sql(true));
        data_filter::$_prefix_num = 0;
        $this->assertFilterSQLEquals($expected10, $filter->get_sql(false, 'x'));
        data_filter::$_prefix_num = 0;
        $this->assertFilterSQLEquals($expected11, $filter->get_sql(true, 'x'));
    }
}
