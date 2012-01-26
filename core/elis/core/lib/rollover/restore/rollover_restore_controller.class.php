<?php
/**
 * Top-level controller for the rollover's restore portion
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

require_once(elis::lib('rollover/restore/rollover_restore_plan.class.php'));

/**
 * Class responsible for the top-level functionality related to the restore section
 * of the course rollover, specifically starting the process of building the execution
 * plan
 */
class rollover_restore_controller extends restore_controller {

    /**
     * Constructor for the rollover backup controller class
     *
     * @param string $tempdir Directory under dataroot/temp/backup awaiting restore
     * @param int $courseid Course id where restore is going to happen
     */
    public function __construct($tempdir, $courseid) {
        global $USER;

        parent::__construct($tempdir, $courseid, backup::INTERACTIVE_NO,
                            backup::MODE_SAMESITE, $USER->id, backup::TARGET_NEW_COURSE);
    }

    /**
     * Appends a single entry to the log
     * (Not actually done - declared only to satisfy the requirements of the parent class)
     *
     * @param string $message The message to display
     * @param int $level The LOG_ constant representing the severity of the log
     * @param unknown_type $a Additional piece of information added to the log, if applicable
     * @param unknown_type $depth Nesting depth of the message
     * @param boolean $display True to also display the log, otherwise false
     */
    public function log($message, $level, $a = null, $depth = null, $display = false) {
        //avoid logging to file to save on performance and prevent side-effects
    }

    /**
     * Sets up a restore plan and stores it within the current restore controller
     */
    protected function load_plan() {
        // First of all, we need to introspect the moodle_backup.xml file
        // in order to detect all the required stuff. So, create the
        // monster $info structure where everything will be defined
        $this->log('loading backup info', backup::LOG_DEBUG);
        $this->info = backup_general_helper::get_backup_information($this->tempdir);

        // Set the controller type to the one found in the information
        $this->type = $this->info->type;

        // Set the controller samesite flag as needed
        $this->samesite = backup_general_helper::backup_is_samesite($this->info);

        // Now we load the plan that will be configured following the
        // information provided by the $info
        $this->log('loading controller plan', backup::LOG_DEBUG);
        $this->plan = new rollover_restore_plan($this);
        $this->plan->build(); // Build plan for this controller
        $this->set_status(backup::STATUS_PLANNED);
    }
}