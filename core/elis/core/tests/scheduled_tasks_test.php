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
require_once($CFG->dirroot.'/elis/core/lib/setup.php');
require_once(elis::lib('data/data_object.class.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once(elis::lib('tasklib.php'));

/**
 * Class to test scheduled tasks.
 * @group elis_core
 */
class scheduled_tasks_testcase extends elis_database_test {
    /**
     * Validate scheduled tasks.
     */
    public function test_elis_tasks_get_cached() {
        $dataset = $this->createCsvDataSet(array(
            'elis_scheduled_tasks' => elis::component_file('core', 'tests/fixtures/elis_scheduled_tasks.csv')
        ));
        $this->loadDataSet($dataset);

        $cachedtasks = elis_tasks_get_cached('elis_program');

        $this->assertNotEmpty($cachedtasks);
        $this->assertInternalType('array', $cachedtasks);
        $this->assertArrayHasKey('s:7:"pm_cron";', $cachedtasks);
        $this->assertNotEmpty($cachedtasks['s:7:"pm_cron";']);
        $this->assertInternalType('array', $cachedtasks['s:7:"pm_cron";']);
    }

    /**
     * test_elis_tasks_cron_next_run_time() data providea
     * @return array the test data
     */
    public function elis_tasks_cron_next_run_time_data() {
        return array(
                array(array('minute' => '00', 'hour' => '00', 'day' => '*', 'dayofweek' => '*', 'month' => '*', 'timezone' => '99',
                    'lastruntime' => array(2013, 1, 1, 1, 15)), array(2013, 1, 2, 0, 0)),
                array(array('minute' => '00', 'hour' => '00', 'day' => '*', 'dayofweek' => '*', 'month' => '*', 'timezone' => '99',
                    'lastruntime' => array(2012, 2, 19, 13, 13)), array(2012, 2, 20, 0, 0)),
                array(array('minute' => '00', 'hour' => '00', 'day' => '*', 'dayofweek' => '*', 'month' => '*', 'timezone' => '99',
                    'lastruntime' => array(2013, 10, 8, 20, 30)), array(2013, 10, 9, 0, 0)),
        );
    }

    /**
     * Validate cron_next_run_time() function for tasks with '00' hour and/or minute
     * @param array $job array of cron task parameters
     * @param array $expnextrun the expected next run time
     * @dataProvider elis_tasks_cron_next_run_time_data
     */
    public function test_elis_tasks_cron_next_run_time($job, $expnextrun) {
        $lastrun = $job['lastruntime'];
        $this->assertEquals(make_timestamp($expnextrun[0], $expnextrun[1], $expnextrun[2], $expnextrun[3], $expnextrun[4]),
                cron_next_run_time(make_timestamp($lastrun[0], $lastrun[1], $lastrun[2], $lastrun[3], $lastrun[4]), $job));
    }
}
