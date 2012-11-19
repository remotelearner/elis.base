<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for ______ file
 *
 * @author    _______ <_____@remote-learner.net>
 * @package   blocks______
 * @copyright 2011 Remote Learner - http://www.remote-learner.net/
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
if (!defined('PHPUnit_MAIN_METHOD') && !defined('MOODLE_INTERNAL')) {
    //  must be run via phpunit command-line or included from a Moodle page
    die('Direct access to this script is forbidden.');
}
 
defined('PHPUNIT_SCRIPT') || define('PHPUNIT_SCRIPT', true);
 
/**
 * Set $blockundertest to block under test spec.
 **/
$blockundertest = 'email_list'; // example block email_list
$blockname = 'block_'. $blockundertest;

/**
 * NOTE: if testing output use
 * require_once 'PHPUnit/Extensions/OutputTestCase.php'
 * instead
 **/
require_once 'PHPUnit/Framework/TestCase.php';

/**
 * Change path to config.php if directory is below:
 *  blocks/{$blockundertest}/phpunit/
 **/
require_once(dirname(__FILE__) . '/../../../config.php');
 
if (!isset($CFG)) global $CFG; // required  when running tests from MOODLE UI: admin/reports/phpunittest

require_once($CFG->dirroot .'/lib/phpunittestlib/testlib.php');
 
// Block specific includes:
require_once($CFG->dirroot .'/lib/accesslib.php');
require_once($CFG->dirroot .'/lib/blocklib.php');
require_once($CFG->dirroot .'/lib/datalib.php');
require_once($CFG->dirroot .'/lib/dmllib.php');
require_once($CFG->dirroot .'/lib/moodlelib.php');
require_once($CFG->dirroot .'/lib/weblib.php');
require_once($CFG->dirroot .'/blocks/moodleblock.class.php');
 
require_once($CFG->dirroot .'/blocks/{$blockundertest}/{$blockname}.php');
 
global $testObject, $saveCFG, $saveCOURSE, $saveUSER;

/**
 * MUST change TEST_CLASS to unique classname and define
 * since cannot redefine in PHP (TBD)
 **/
define('TEST_CLASS', $blockname . "Test");

class TEST_CLASS extends PHPUnit_Framework_TestCase // <-- MUST change TEST_CLASS
// NOTE: if testing output extend PHPUnit_Extensions_OutputTestCase instead
{
    protected $backupGlobals = FALSE;  // Directs PHPUnit NOT to keep separate globals for each test case
                                       // maybe required since MOODLE has many globals to init in setUpBeforeClass();

    public static function setUpBeforeClass()
    {
        // called only once before all other methods
        global $testObject, $CFG, $saveCFG, $COURSE, $saveCOURSE, $USER, $saveUSER;
        // preserve commonly used globals (add more as required)
        $saveCFG    = $CFG;
        $saveCOURSE = $COURSE;
        $saveUSER   = $USER;
        $testObject = new $blockname;
    }
 
    protected function setUp()
    {
        // called before each test function
    }
 
    protected function assertPreConditions()
    {
        // called after setUp() before each test function
        global $testObject;
        $this->assertTrue(!empty($testObject));
    }

    public function test_block_selftest()
    {
        global $testObject;
        $this->assertTrue($testObject->_self_test());
    }
 
    public function test_mycode()
    {
        global $testObject;
        // test code goes here ...

        // Eg. For unsafe/destructive tests check: $CFG->not_live_site
        global $CFG;
        if (empty($CFG->not_live_site)) { // this code ONLY required for destructive/unsafe test
            $this->markTestSkipped('Test unsafe or destructive to database: $CFG->not_live_site must be set to run.');
        }

    }
 
    protected function assertPostConditions()
    {
        // called after each test function
    }
 
    protected function tearDown()
    {
        // called after assertPostConditions() for each test function
    }
 
    public static function tearDownAfterClass()
    {
        // called only once after all test functions, it's always the last function called.
        global $CFG, $saveCFG, $COURSE, $saveCOURSE, $USER, $saveUSER;
 
        // restore commonly used globals
        $CFG    = $saveCFG;
        $COURSE = $saveCOURSE;
        $USER   = $saveUSER;
    }
 
    protected function onNotSuccessfulTest(Exception $e)
    {
        // called when test function fails, i.e. $this->assertTrue(FALSE);
        throw $e;
    }
}
?>
