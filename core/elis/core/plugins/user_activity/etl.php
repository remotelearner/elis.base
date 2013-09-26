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
 * @package    elis
 * @subpackage user_activity
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../../lib/setup.php');
require_once(dirname(__FILE__).'/lib.php');

define('ETL_TABLE',     'etl_user_activity');
define('ETL_MOD_TABLE', 'etl_user_module_activity');

// process 10,000 records at a time
define ('USERACT_RECORD_CHUNK', 10000);
// max out at 2 minutes (= 120 seconds)
define ('USERACT_TIME_LIMIT', 120);

// define ('ETLUA_EXTRA_DEBUG', 1);

define('ETL_BLOCKED_MAX_TIME', 7 * DAYSECS); // An arbitrary long time to use when setting cron task blocking.

/**
 * ETL user activity
 */
class etl_user_activity {
    public $state;
    public $duration;

    /**
     * ETL user activity constructor
     * @param int $duration The amount of time to the run the cron
     * @param bool $outputmtrace Flag to show mtrace output
     */
    public function __construct($duration = 0, $outputmtrace = true) {
        $this->duration = $duration;
        $this->user_activity_task_init($outputmtrace);
    }

    /**
     * Add a session to the user activity ETL table.
     *
     * @param int $userid the user to add the session for
     * @param int $courseid the course to add the session for
     * @param int $sessionstart the start time of the session
     * @param int $sessionend the end time of the session
     * @uses $CFG;
     * @uses $DB
     */
    public function user_activity_add_session($userid, $courseid, $sessionstart, $sessionend) {
        global $CFG, $DB;
        if ($userid && $sessionstart && $sessionend) {
            $length = $sessionend - $sessionstart;
            if (defined('ETLUA_EXTRA_DEBUG') && $CFG->debug >= DEBUG_DEVELOPER) {
                mtrace("** adding {$length} second session for user {$userid} in course {$courseid}");
            }
            // split the session into hours
            $starthour = floor($sessionstart / HOURSECS) * HOURSECS;
            $first = true;
            while ($sessionend > $starthour + HOURSECS) {
                $sessionhourduration = $starthour + HOURSECS - $sessionstart;
                $params = array(
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'hour' => $starthour
                );
                if ($rec = $DB->get_record(ETL_TABLE, $params)) {
                    $rec->duration += $sessionhourduration;
                    if ($rec->duration <= HOURSECS) {
                        $DB->update_record(ETL_TABLE, $rec);
                    } else if ($CFG->debug >= DEBUG_DEVELOPER) {
                        mtrace("\nuser_activity_add_session(userid = {$userid}, courseid = {$courseid}, session_start = {$sessionstart}, ".
                                "session_end = {$sessionend}): Warning: duration > 3600\n");
                    }
                } else {
                    $rec = new stdClass;
                    $rec->userid = $userid;
                    $rec->courseid = $courseid;
                    $rec->hour = $starthour;
                    $rec->duration = $sessionhourduration;
                    $DB->insert_record(ETL_TABLE, $rec);
                }
                $starthour += HOURSECS;
                $sessionstart = $starthour;
                $first = false;
            }
            $remainder = $sessionend - $sessionstart;
            $params = array(
                'userid' => $userid,
                'courseid' => $courseid,
                'hour' => $starthour
            );
            if ($rec = $DB->get_record(ETL_TABLE, $params)) {
                $rec->duration += $remainder;
                if ($rec->duration <= HOURSECS) {
                    $DB->update_record(ETL_TABLE, $rec);
                } else if ($CFG->debug >= DEBUG_DEVELOPER) {
                    mtrace("\nuser_activity_add_session(userid = {$userid}, courseid = {$courseid}, session_start = {$sessionstart}, ".
                            "session_end = {$sessionend}): Warning: remainder duration > 3600\n");
                }
            } else {
                $rec = new stdClass;
                $rec->userid = $userid;
                $rec->courseid = $courseid;
                $rec->hour = $starthour;
                $rec->duration = $remainder;
                $DB->insert_record(ETL_TABLE, $rec);
            }
        }
    }

    /**
     * Add a session to the user module activity ETL table.
     *
     * @param int $userid the user to add the session for
     * @param int $courseid the course to add the session for
     * @param int $cmid the course module to add the session for
     * @param int $session_start the start time of the session
     * @param int $session_end the end time of the session
     * @uses $CFG;
     * @uses $DB;
     */
    public function user_module_activity_add_session($userid, $courseid, $cmid, $sessionstart, $sessionend) {
        global $CFG, $DB;
        if ($userid && $sessionstart && $sessionend) {
            $length = $sessionend - $sessionstart;
            if (defined('ETLUA_EXTRA_DEBUG') && $CFG->debug >= DEBUG_DEVELOPER) {
                mtrace("** adding {$length} second session for user {$userid} in course {$courseid}, module {$cmid}");
            }
            // split the session into hours
            $starthour = floor($sessionstart / HOURSECS) * HOURSECS;
            $first = true;
            while ($sessionend > $starthour + HOURSECS) {
                $sessionhourduration = $starthour + HOURSECS - $sessionstart;
                $params = array(
                    'userid' => $userid,
                    'cmid' => $cmid,
                    'hour' => $starthour
                );
                if ($rec = $DB->get_record(ETL_MOD_TABLE, $params)) {
                    $rec->duration += $sessionhourduration;
                    if ($rec->duration <= HOURSECS) {
                        $DB->update_record(ETL_MOD_TABLE, $rec);
                    } else if ($CFG->debug >= DEBUG_DEVELOPER) {
                        mtrace("\nuser_module_activity_add_session(userid = {$userid}, courseid = {$courseid}, cmid = {$cmid}, session_start = {$sessionstart}, ".
                                "session_end = {$sessionend}): Warning: duration > 3600\n");
                    }
                } else {
                    $rec = new stdClass;
                    $rec->userid = $userid;
                    $rec->courseid = $courseid;
                    $rec->cmid = $cmid;
                    $rec->hour = $starthour;
                    $rec->duration = $sessionhourduration;
                    $DB->insert_record(ETL_MOD_TABLE, $rec);
                }
                $starthour += HOURSECS;
                $sessionstart = $starthour;
                $first = false;
            }
            $remainder = $sessionend - $sessionstart;
            $params = array(
                'userid' => $userid,
                'cmid' => $cmid,
                'hour' => $starthour
            );
            if ($rec = $DB->get_record(ETL_MOD_TABLE, $params)) {
                $rec->duration += $remainder;
                if ($rec->duration <= HOURSECS) {
                    $DB->update_record(ETL_MOD_TABLE, $rec);
                } else if ($CFG->debug >= DEBUG_DEVELOPER) {
                    mtrace("\nuser_module_activity_add_session(userid = {$userid}, courseid = {$courseid}, cmid = {$cmid}, session_start = {$sessionstart}, ".
                            "session_end = {$sessionend}): Warning: duration > 3600\n");
                }
            } else {
                $rec = new stdClass;
                $rec->userid = $userid;
                $rec->courseid = $courseid;
                $rec->cmid = $cmid;
                $rec->hour = $starthour;
                $rec->duration = $remainder;
                $DB->insert_record(ETL_MOD_TABLE, $rec);
            }
        }
    }

    /**
     * Splits the Moodle log into sessions for each user, tracking how long they have spent in each Moodle course.
     * Processes approx 40k records / minute
     * @uses $DB
     */
    public function cron() {
        global $DB;

        $timenow = time();
        $rununtil = $timenow + (($this->duration > 0) ? $this->duration : USERACT_TIME_LIMIT);

        // Block other ETL cron tasks.
        $this->set_etl_task_blocked($timenow + ETL_BLOCKED_MAX_TIME);

        if (!isset($this->state['last_processed_time']) || (time() - $this->state['last_processed_time']) >= DAYSECS) {
            $this->state['recs_last_processed'] = 0;
            $this->state['last_processed_time'] = time();
            $this->state['log_entries_per_day'] = (float)$DB->count_records_select('log', 'time >= ? AND time < ?',
                    array($this->state['last_processed_time'] - 10 * DAYSECS, $this->state['last_processed_time'])) / 10.0;
        }
        do {
            list($completed, $total) = $this->user_activity_task_process();
            $this->state['recs_last_processed'] += $completed;
        } while (time() < $rununtil && $completed < $total);

        if ($completed < $total) {
            $this->user_activity_task_save();
        } else {
            $this->user_activity_task_finish();
        }

        // Clear blocking.
        $this->set_etl_task_blocked(0);
    }

    /**
     * Initialize the task state for the ETL process
     *
     * @uses $DB;
     * @param bool $outputmtrace Flag to show mtrace output
     */
    protected function user_activity_task_init($outputmtrace = true) {
        global $DB;
        if ($outputmtrace) {
            mtrace('Calculating user activity from Moodle log');
        }

        $state = isset(elis::$config->eliscoreplugins_user_activity->state) ? elis::$config->eliscoreplugins_user_activity->state : '';
        if (!empty($state)) {
            // We already have some state saved.  Use that.
            $this->state = unserialize($state);
        } else {
            $state = array();
            // ETL parameters
            $state['sessiontimeout'] = elis::$config->eliscoreplugins_user_activity->session_timeout;
            $state['sessiontail'] = elis::$config->eliscoreplugins_user_activity->session_tail;

            // the last run time that we have processed until
            $lastrun = isset(elis::$config->eliscoreplugins_user_activity->last_run) ? elis::$config->eliscoreplugins_user_activity->last_run : 0;
            $state['starttime'] = !empty($lastrun) ? (int)$lastrun : 0;

            $startrec = $DB->get_field_select('log', 'MAX(id)', 'time <= ?', array($state['starttime']));
            $startrec = empty($startrec) ? 0 : $startrec;
            $state['startrec'] = $startrec;

            $this->state = $state;
        }
    }

    /**
     * Process a chunk of the task
     *
     * @return array Completed and total records
     * @uses $CFG;
     * @uses $DB;
     */
    public function user_activity_task_process() {
        global $CFG, $DB;

        $sessiontimeout = $this->state['sessiontimeout'];
        $sessiontail = $this->state['sessiontail'];

        $starttime = $this->state['starttime'];

        // find the record ID corresponding to our start time
        $startrec = $DB->get_field_select('log', 'MIN(id)', 'time >= ?', array($starttime));
        $startrec = empty($startrec) ? 0 : $startrec;

        // find the last record that's close to our chunk size, without
        // splitting a second between runs
        $endtime = $DB->get_field_select('log', 'MIN(time)', 'id >= ? AND time > ?', array($startrec + USERACT_RECORD_CHUNK, $starttime));
        if (!$endtime) {
            $endtime = time();
        }

        // Get the logs between the last time we ran, and the current time.  Sort
        // by userid (so all records for a given user are together), and then by
        // time (so that we process a user's logs sequentially).
        $recstarttime = max(0, $starttime - $this->state['sessiontimeout']);
        $rs = $DB->get_recordset_select('log', 'time >= ? AND time < ? AND userid != 0', array($recstarttime, $endtime), 'userid, time');
        if ($CFG->debug >= DEBUG_ALL) {
            mtrace("* processing records from time:{$starttime} to time:{$endtime}");
        }

        $curuser = -1;
        $sessionstart = 0;
        $lastcourse = -1;
        $modulesessionstart = 0;
        $lastmodule = -1;
        $lasttime = 0;
        if ($rs && $rs->valid()) {
            foreach ($rs as $rec) { // WAS while($rec = rs_fetch_next_record($rs))
                if ($rec->userid != $curuser) {
                    // end of user's record
                    if ($curuser > 0 && $sessionstart > 0) {
                        // flush current session data
                        if ($lasttime > $endtime - $sessiontimeout) {
                            /* Last record is within the session timeout of our end
                             * time for this run.  Just use our last logged time as
                             * the session end time, and the rest will be picked up
                             * by the next run of the sessionizer. */
                            $sessionend = $lasttime;
                        } else {
                            /* Last record is not within the session timeout of our
                             * end time for this run, so do our normal session
                             * ending. */
                            $sessionend = $lasttime + $sessiontail;
                        }
                        $this->user_activity_add_session($curuser, $lastcourse, $sessionstart, $sessionend);
                        if ($lastmodule > 0) {
                            $this->user_module_activity_add_session($curuser, $lastcourse, $lastmodule, $modulesessionstart, $sessionend);
                        }
                    }
                    $curuser = $rec->userid;
                    $sessionstart = 0;
                    $lastcourse = -1;
                    $modulesessionstart = 0;
                    $lastmodule = -1;
                    $lasttime = 0;
                }
                if ($rec->time < $starttime) {
                    // Find the last log for the user before our start time, that's
                    // within the session timeout, and start the session with that
                    // record.
                    $sessionstart = $rec->time;
                    $lasttime = $rec->time;
                    $lastcourse = $rec->course;
                    $modulesessionstart = $rec->time;
                    $lastmodule = $rec->cmid;
                } else if ($rec->time > $lasttime + $sessiontimeout) {
                    if ($lastcourse >= 0) {
                        // session timed out -- add record
                        if (defined('ETLUA_EXTRA_DEBUG') && $CFG->debug >= DEBUG_DEVELOPER) {
                            mtrace('** session timed out');
                        }
                        $sessionend = $lasttime + $sessiontail;
                        $this->user_activity_add_session($curuser, $lastcourse, $sessionstart, $sessionend);
                        if ($lastmodule > 0) {
                            $this->user_module_activity_add_session($curuser, $lastcourse, $lastmodule, $modulesessionstart, $sessionend);
                        }
                    }
                    // start a new session with the current record
                    $sessionstart = $rec->time;
                    $lastcourse = $rec->course;
                    $modulesessionstart = $rec->time;
                    $lastmodule = $rec->cmid;
                } else if ($rec->action === 'logout') {
                    // user logged out -- add record
                    if (defined('ETLUA_EXTRA_DEBUG') && $CFG->debug >= DEBUG_DEVELOPER) {
                        mtrace('** user logged out');
                    }
                    $sessionend = $rec->time;
                    $this->user_activity_add_session($curuser, $lastcourse, $sessionstart, $sessionend);
                    if ($lastmodule > 0) {
                        $this->user_module_activity_add_session($curuser, $lastcourse, $lastmodule, $modulesessionstart, $sessionend);
                    }
                    // clear session info
                    $sessionstart = 0;
                    $modulesessionstart = 0;
                } else if ($rec->course != $lastcourse) {
                    // user switched to a different course -- start new session record
                    if ($lastcourse >= 0) {
                        if (defined('ETLUA_EXTRA_DEBUG') && $CFG->debug >= DEBUG_DEVELOPER) {
                            mtrace('** user switched courses');
                        }
                        $sessionend = $rec->time;
                        $this->user_activity_add_session($curuser, $lastcourse, $sessionstart, $sessionend);
                        if ($lastmodule > 0) {
                            $this->user_module_activity_add_session($curuser, $lastcourse, $lastmodule, $modulesessionstart, $sessionend);
                        }
                    }
                    $sessionstart = $rec->time;
                    $lastcourse = $rec->course;
                    $modulesessionstart = $rec->time;
                    $lastmodule = $rec->cmid;
                } else if ($rec->cmid != $lastmodule) {
                    // user switched to a different module -- start new module session
                    if ($lastmodule > 0) {
                        if (defined('ETLUA_EXTRA_DEBUG') && $CFG->debug >= DEBUG_DEVELOPER) {
                            mtrace('** user switched modules');
                        }
                        $sessionend = $rec->time;
                        $this->user_module_activity_add_session($curuser, $lastcourse, $lastmodule, $modulesessionstart, $sessionend);
                    }
                    $modulesessionstart = $rec->time;
                    $lastmodule = $rec->cmid;
                }
                $lasttime = $rec->time;
            }
            $rs->close();
        }

        if ($curuser > 0 && $sessionstart > 0) {
            // flush session data
            if ($lasttime > $endtime - $sessiontimeout) {
                $sessionend = $lasttime;
            } else {
                $sessionend = $lasttime + $sessiontail;
            }
            $this->user_activity_add_session($curuser, $lastcourse, $sessionstart, $sessionend);
        }

        $this->state['starttime'] = $endtime;

        $endrec = $DB->get_field_select('log', 'MAX(id)', 'time < ?', array($endtime));
        // possibly skip the last time when calculating the total number of
        // records, since we are purposely skipping anything less than $endtime
        $lasttime = $DB->get_field_select('log', 'MAX(time)', 'TRUE');
        $totalrec = $DB->get_field_select('log', 'MAX(id)', 'time < ?', array($lasttime));
        $totalrec = max($totalrec, $endrec);
        return array($endrec ? ($endrec - $this->state['startrec']) : 0, $totalrec ? ($totalrec - $this->state['startrec']) : 0);
    }

    /**
     * Save the task state for later continuation
     */
    public function user_activity_task_save() {
        mtrace('* over time limit -- saving state and pausing');
        set_config('state', serialize($this->state), 'eliscoreplugins_user_activity');
    }

    /**
     * Finish a task
     */
    public function user_activity_task_finish() {
        mtrace('* completed');
        set_config('last_run', $this->state['starttime'], 'eliscoreplugins_user_activity');
        set_config('state', 0, 'eliscoreplugins_user_activity'); // WAS: null but not allowed in config_plugins
    }

    /**
     * Callback to save the state of the ETL when the script is terminated
     */
    public function save_current_etl_state() {
        // Save the current state.
        $this->user_activity_task_save();

        // Clear blocking.
        $this->set_etl_task_blocked(0);

        exit(0);
    }

    /**
     * Set blocked value for ETL cron.
     *
     * @param int $secs The value in seconds to set blocked time.
     * @uses $DB
     */
    public function set_etl_task_blocked($secs) {
        global $DB;

        $task = $DB->get_record('elis_scheduled_tasks', array('plugin' => 'eliscoreplugins_user_activity'));
        $task->blocked = $secs;
        $DB->update_record('elis_scheduled_tasks', $task);
    }
}

/**
 * Run the ETL user activity cron.
 *
 * @param string $taskname The task name
 * @param int    $duration The length of time in seconds the cron is to run for
 * @param object $etlobj The ETL user activity object
 */
function user_activity_etl_cron($taskname = '', $duration = 0, &$etlobj = null) {
    if ($etlobj === null) {
        $etlobj = new etl_user_activity($duration);
    }
    // error_log("user_activity_etl_cron('{$taskname}', {$duration}, etlobj)");
    $etlobj->cron();
}
