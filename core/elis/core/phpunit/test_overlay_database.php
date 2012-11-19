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
require_once($CFG->dirroot . '/elis/core/lib/setup.php');
require_once(elis::lib('data/data_object.class.php'));
require_once(elis::lib('testlib.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');

class overlay_databaseTest extends PHPUnit_Framework_TestCase {
    protected $backupGlobalsBlacklist = array('DB');

    protected function tearDown() {
        if (isset($this->overlaydb)) {
            $this->overlaydb->cleanup();
        }
    }

    /**
     * Test that the overlay database creates and accesses a new empty overlay table.
     */
    public function testCreatesEmptyOverlayTable() {
        global $DB;

        $overlaydb = new overlay_database($DB, array('config' => 'moodle'));
        $this->overlaydb = $overlaydb;
        $this->assertEquals(0, $overlaydb->count_records('config'));
        $this->assertThat($DB->count_records('config'), $this->logicalNot($this->equalTo(0)));
    }

    /**
     * Test that records cannot be inserted to a non-overlay table.
     *
     * @expectedException overlay_database_exception
     */
    public function testPreventsInsertToNonOverlayTable() {
        global $DB;

        $overlaydb = new overlay_database($DB, array('config' => 'moodle'));
        $this->overlaydb = $overlaydb;
        $this->overlaydb->insert_record('user', new stdClass);
    }

    /**
     * Test that records cannot be deleted from a non-overlay table.
     *
     * @expectedException overlay_database_exception
     */
    public function testPreventsDeleteFromNonOverlayTable() {
        global $DB;

        $overlaydb = new overlay_database($DB, array('config' => 'moodle'));
        $this->overlaydb = $overlaydb;
        $this->overlaydb->delete_records('user', array('idnumber' => '____phpunit_overlay_db_delete_test____'));
    }
}
