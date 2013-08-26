<?php
/**
 * Main library for performing a course rollover
 *
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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/backup/util/includes/restore_includes.php');
require_once(elis::lib('rollover/backup/rollover_backup_controller.class.php'));
require_once(elis::lib('rollover/restore/rollover_restore_controller.class.php'));

/**
 * Roll over an existing Moodle course into a new one using a customized backup
 * and restore routine
 *
 * @param int $courseid Id of the course we are copying
 * @param int $categoryid
 * @return int|boolean Id of the newly created course
 */
function course_rollover($courseid, $categoryid = null) {
    global $DB;

    if ($course = $DB->get_record('course', array('id' => $courseid))) {
        //figure out the category
        if ($categoryid === null) {
            $categoryid = $course->category;
        }

        //make sure the category is valid
        if (!$DB->record_exists('course_categories', array('id' => $categoryid))) {
            //invalid category
            return false;
        }

        //perform backup without including user info
        $controller = new rollover_backup_controller($courseid);
        $controller->get_plan()->get_setting('users')->set_value(false);
        $controller->execute_plan();

        //get directory name for use in restore
        $backupid = $controller->get_backupid();

        //start a database transaction to make sure restore is atomic, etc
        $transaction = $DB->start_delegated_transaction();

        //create the new course
        $result = restore_dbops::create_new_course($course->fullname, $course->shortname, $categoryid);

        //restore the content into it within the current transaction without including user information
        $controller = new rollover_restore_controller($backupid, $result);
        $controller->get_plan()->get_setting('users')->set_value(false);
        $controller->execute_precheck();
        $controller->execute_plan();

        //make sure the sort order is defined as expected
        fix_course_sortorder();

        try {
            //try to finalize
            $transaction->allow_commit();
        } catch (dml_transaction_exception $e) {
            //failure
            $result = false;
        }

        return $result;
    } else {
        //invalid course specified
        return false;
    }
}