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

defined('MOODLE_INTERNAL') or die();

global $CFG;
require_once($CFG->libdir.'/phpunit/lib.php');
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');

/**
 * Base class for ELIS PHPUnit tests that require a database connection.
 */
abstract class elis_database_test extends advanced_testcase {

    /**
     * Perform setup before every test. This tells Moodle's phpunit to reset the database after every test.
     */
    protected function setUp() {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Asserts that two given tables are equal.
     * @param PHPUnit_Extensions_Database_DataSet_ITable $expected
     * @param PHPUnit_Extensions_Database_DataSet_ITable $actual
     * @param string $message
     */
    public static function assertTablesEqual(PHPUnit_Extensions_Database_DataSet_ITable $expected,
                                             PHPUnit_Extensions_Database_DataSet_ITable $actual, $message = '') {
        $constraint = new PHPUnit_Extensions_Database_Constraint_TableIsEqual($expected);

        self::assertThat($actual, $constraint, $message);
    }

    /**
     * Asserts that two given datasets are equal.
     * @param PHPUnit_Extensions_Database_DataSet_ITable $expected
     * @param PHPUnit_Extensions_Database_DataSet_ITable $actual
     * @param string $message
     */
    public static function assertDataSetsEqual(PHPUnit_Extensions_Database_DataSet_IDataSet $expected,
                                               PHPUnit_Extensions_Database_DataSet_IDataSet $actual, $message = '') {
        $constraint = new PHPUnit_Extensions_Database_Constraint_DataSetIsEqual($expected);

        self::assertThat($actual, $constraint, $message);
    }
}

/**
 * PHPUnit DataTable for a Moodle recordset (or record array, or ELIS data collection)
 */
class moodle_recordset_phpunit_datatable extends PHPUnit_Extensions_Database_DataSet_DefaultTable {
    public function __construct($tablename, $rs) {
        // Try to get the column names from an entry.
        if (is_array($rs)) {
            if (!empty($rs)) {
                $refobj = current($rs);
            }
        } else if ($rs->valid()) {
            $refobj = $rs->current();
        }
        if (isset($refobj)) {
            if (method_exists($refobj, 'to_array')) {
                $refobj = $refobj->to_array();
            } else if (method_exists($refobj, 'to_object')) {
                $refobj = $refobj->to_object();
            }
            if (is_array($refobj)) {
                $columns = array_keys($refobj);
            } else {
                $columns = array_keys(get_object_vars($refobj));
            }
        } else {
            $columns = array();
        }

        $metadata = new PHPUnit_Extensions_Database_DataSet_DefaultTableMetaData($tablename, $columns);

        parent::__construct($metadata);

        foreach ($rs as $record) {
            if (is_array($record)) {
                $this->addRow($record);
            } else if (method_exists($record, 'to_array')) {
                $this->addRow($record->to_array());
            } else {
                $this->addRow((array)$record);
            }
        }
    }
}

/**
 * This function gets test user from DB user table or creates the test user if it doesn't already exist
 * @param string $username Optional username for test user (defaults to 'testuser')
 * @param string $email Optional email address for test user (defaults to 'testuser@example.com')
 * @return object The testuser object or false on error.
 */
function get_test_user($username = '', $email = '') {
    global $DB, $CFG;
    if ($username === '') {
        $username = 'testuser';
    }

    $testuser = $DB->get_record('user', array('username' => $username));
    if (empty($testuser)) {
        $testuser = new stdClass;
        $testuser->username = $username;
        $testuser->password = md5('Test1234!');
        $testuser->mnethostid = $CFG->mnet_localhost_id;
        $testuser = create_user_record($testuser->username, $testuser->password);
        if (empty($testuser)) {
            return false;
        }

        // Setup fields to get thru: require_login().
        $testuser->firstname = 'Test';
        $testuser->lastname = 'User';
        if (empty($email)) {
            $email = "{$username}@example.com";
        }
        $testuser->email = $email;
        $testuser->confirmed = true;
        $testuser->deleted = false;
        $testuser->country = 'CA';
        $testuser->city = 'Waterloo';
    }

    $testuser->deleted = false;
    $DB->update_record('user', $testuser);
    return $testuser;
}

/**
 * This function deletes the test user.
 * @param string $username Optional username of test user to delete (defaults to 'testuser')
 * @param bool $remove Optional flag to actually remove test user from DB 'user' table.
 * @return bool True on success, false on error.
 */
function delete_test_user($username = '', $remove = false) {
    global $DB, $CFG;
    if ($username == '') {
        $username = 'testuser';
    }

    $testuser = $DB->get_record('user', array('username' => $username, 'mnethostid' => $CFG->mnet_localhost_id));
    if (!empty($testuser)) {
        if ($remove) {
            return $DB->delete_records('user', array('id' => $testuser->id));
        } else {
            $testuser->deleted = true;
            return $DB->update_record('user', $testuser);
        }
    }
    return false;
}