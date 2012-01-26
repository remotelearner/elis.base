<?php
/**
 * Custom steps used in the rollover backup plan
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

/**
 * Class representing the step responsible for cleaning up temporary data
 * during the backup section of the rollover
 */
class rollover_backup_drop_and_clean_temp_stuff extends backup_execution_step {

    /**
     * Entry point for executing this step
     */
    protected function define_execution() {
        global $CFG;

        backup_controller_dbops::drop_backup_ids_temp_table($this->get_backupid()); // Drop ids temp table

        //avoid purging old backup dirs to save on performance
        //avoid deleting the current backup dir because it's needed during the restore

        //delete the log file that was created
        $backupid = $this->get_backupid();
        unlink($CFG->dataroot.'/temp/backup/'.$backupid.'.log');
    }
}