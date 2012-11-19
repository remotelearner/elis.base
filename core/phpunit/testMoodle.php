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
 * Test Moodle
 *
 * Basic functionality tests.
 *
 * @author Tyler Bannister <tyler.bannister@remote-learner.net>
 * @copyright 2011 Remote Learner http://www.remote-learner.net/
 */

if (!defined('PHPUnit_MAIN_METHOD') && !defined('MOODLE_INTERNAL')) {
    //  Must be run via phpunit command-line or included from a Moodle page
    die('Direct access to this script is forbidden.');
}

defined('PHPUNIT_SCRIPT') || define('PHPUNIT_SCRIPT', true);

require_once(dirname(__FILE__) .'/../config.php');

if (! isset($CFG)) {
    global $CFG; // Required when running test from Moodle admin reports.
}

/**
 * Moodle Test Class
 *
 * Implement the basic functionality tests.
 *
 * @author Tyler Bannister <tyler.bannister@remote-learner.net>
 */
class MoodleTest extends PHPUnit_Framework_TestCase {
    protected $backupGlobals = FALSE;

    public function testSanity() {
        $this->assertTrue(true);
    }
}
