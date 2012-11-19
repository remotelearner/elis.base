<?php
/**
 * The main plan for the rollover backup
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

require_once($CFG->dirroot.'/backup/util/includes/backup_includes.php');
require_once(elis::lib('rollover/backup/rollover_backup_factory.class.php'));

/**
 * Class representing the plan used during the backup portion of the rollover
 */
class rollover_backup_plan extends backup_plan {

    /**
     * Entry point for building the backup plan
     */
    public function build() {
        rollover_backup_factory::build_plan($this->controller); // Dispatch to correct format
        $this->built = true;
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
}