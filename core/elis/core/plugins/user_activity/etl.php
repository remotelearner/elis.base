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
 * @subpackage user_activity
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) .'/../../lib/setup.php');

define('ETL_TABLE',     'etl_user_activity');
define('ETL_MOD_TABLE', 'etl_user_module_activity');

// process 10,000 records at a time
define ('USERACT_RECORD_CHUNK', 10000);
// max out at 2 minutes (= 120 seconds)
define ('USERACT_TIME_LIMIT', 120);

//define ('ETLUA_EXTRA_DEBUG', 1);

/**
 * Add a session to the user activity ETL table.
 *
 * @param int $userid the user to add the session for
 * @param int $courseid the course to add the session for
 * @param int $session_start the start time of the session
 * @param int $session_end the end time of the session
 * @uses $CFG;
 * @uses $DB
 */
function user_activity_add_session($userid, $courseid, $session_start, $session_end) {
    global $CFG, $DB;
    if ($userid && $session_start && $session_end) {
        $length = $session_end - $session_start;
        if (defined('ETLUA_EXTRA_DEBUG') && $CFG->debug >= DEBUG_DEVELOPER) {
            mtrace("** adding {$length} second session for user {$userid} in course {$courseid}");
        }
        // split the session into hours
        $start_hour = floor($session_start/HOURSECS)*HOURSECS;
        $first = true;
        while ($session_end > $start_hour + HOURSECS) {
            $session_hour_duration = $start_hour + HOURSECS - $session_start;
            $params = array('userid'   => $userid,
                            'courseid' => $courseid,
                            'hour'     => $start_hour
                      );
            if ($rec = $DB->get_record(ETL_TABLE, $params)) {
                $rec->duration += $session_hour_duration;
                if ($rec->duration <= HOURSECS) {
                    $DB->update_record(ETL_TABLE, $rec);
                } else if ($CFG->debug >= DEBUG_DEVELOPER) {
                    mtrace("\nuser_activity_add_session(userid = {$userid}, courseid = {$courseid}, session_start = {$session_start}, session_end = {$session_end}): Warning: duration > 3600\n");
                }
            } else {
                $rec = new stdClass;
                $rec->userid = $userid;
                $rec->courseid = $courseid;
                $rec->hour = $start_hour;
                $rec->duration = $session_hour_duration;
                $DB->insert_record(ETL_TABLE, $rec);
            }
            $start_hour += HOURSECS;
            $session_start = $start_hour;
            $first = false;
        }
        $remainder = $session_end - $session_start;
        $params = array('userid'   => $userid,
                        'courseid' => $courseid,
                        'hour'     => $start_hour
                  );
        if ($rec = $DB->get_record(ETL_TABLE, $params)) {
            $rec->duration += $remainder;
            if ($rec->duration <= HOURSECS) {
                $DB->update_record(ETL_TABLE, $rec);
            } else if ($CFG->debug >= DEBUG_DEVELOPER) {
                mtrace("\nuser_activity_add_session(userid = {$userid}, courseid = {$courseid}, session_start = {$session_start}, session_end = {$session_end}): Warning: remainder duration > 3600\n");
            }
        } else {
            $rec = new stdClass;
            $rec->userid = $userid;
            $rec->courseid = $courseid;
            $rec->hour = $start_hour;
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
function user_module_activity_add_session($userid, $courseid, $cmid, $session_start, $session_end) {
    global $CFG, $DB;
    if ($userid && $session_start && $session_end) {
        $length = $session_end - $session_start;
        if (defined('ETLUA_EXTRA_DEBUG') && $CFG->debug >= DEBUG_DEVELOPER) {
            mtrace("** adding {$length} second session for user {$userid} in course {$courseid}, module {$cmid}");
        }
        // split the session into hours
        $start_hour = floor($session_start/HOURSECS)*HOURSECS;
        $first = true;
        while ($session_end > $start_hour + HOURSECS) {
            $session_hour_duration = $start_hour + HOURSECS - $session_start;
            $params = array('userid' => $userid,
                            'cmid'   => $cmid,
                            'hour'   => $start_hour
                      );
            if ($rec = $DB->get_record(ETL_MOD_TABLE, $params)) {
                $rec->duration += $session_hour_duration;
                if ($rec->duration <= HOURSECS) {
                    $DB->update_record(ETL_MOD_TABLE, $rec);
                } else if ($CFG->debug >= DEBUG_DEVELOPER) {
                    mtrace("\nuser_module_activity_add_session(userid = {$userid}, courseid = {$courseid}, cmid = {$cmid}, session_start = {$session_start}, session_end = {$session_end}): Warning: duration > 3600\n");
                }
            } else {
                $rec = new stdClass;
                $rec->userid = $userid;
                $rec->courseid = $courseid;
                $rec->cmid = $cmid;
                $rec->hour = $start_hour;
                $rec->duration = $session_hour_duration;
                $DB->insert_record(ETL_MOD_TABLE, $rec);
            }
            $start_hour += HOURSECS;
            $session_start = $start_hour;
            $first = false;
        }
        $remainder = $session_end - $session_start;
        $params = array('userid' => $userid,
                        'cmid'   => $cmid,
                        'hour'   => $start_hour
                  );
        if ($rec = $DB->get_record(ETL_MOD_TABLE, $params)) {
            $rec->duration += $remainder;
            if ($rec->duration <= HOURSECS) {
                $DB->update_record(ETL_MOD_TABLE, $rec);
            } else if ($CFG->debug >= DEBUG_DEVELOPER) {
                mtrace("\nuser_module_activity_add_session(userid = {$userid}, courseid = {$courseid}, cmid = {$cmid}, session_start = {$session_start}, session_end = {$session_end}): Warning: duration > 3600\n");
            }
        } else {
            $rec = new stdClass;
            $rec->userid = $userid;
            $rec->courseid = $courseid;
            $rec->cmid = $cmid;
            $rec->hour = $start_hour;
            $rec->duration = $remainder;
            $DB->insert_record(ETL_MOD_TABLE, $rec);
        }
    }
}

/**
 * Splits the Moodle log into sessions for each user, tracking how long they
 * have spent in each Moodle course.
 *
 * Processes approx 40k records / minute
 */
function user_activity_etl_cron() {
    $rununtil = time() + USERACT_TIME_LIMIT;

    $state = user_activity_task_init();

    do {
        list($completed,$total) = user_activity_task_process($state);
    } while (time() < $rununtil && $completed < $total);

    if ($completed < $total) {
        user_activity_task_save($state);
    } else {
        user_activity_task_finish($state);
    }
}

/**
 * Initialize the task state for the ETL process
 *
 * @uses $DB;
 * @return array the initial task state
 */
function user_activity_task_init( $output_mtrace = true ) {
    global $DB;
    if ($output_mtrace) {
        mtrace('Calculating user activity from Moodle log');
    }

    $state = isset(elis::$config->eliscoreplugins_user_activity->state) ?
                 elis::$config->eliscoreplugins_user_activity->state : '';
    if (!empty($state)) {
        // We already have some state saved.  Use that.
        return unserialize($state);
    }

    $state = array();
    // ETL parameters
    $state['sessiontimeout'] = elis::$config->eliscoreplugins_user_activity->session_timeout;
    $state['sessiontail'] = elis::$config->eliscoreplugins_user_activity->session_tail;

    // the last run time that we have processed until
    $lastrun = isset(elis::$config->eliscoreplugins_user_activity->last_run) ?
                   elis::$config->eliscoreplugins_user_activity->last_run : 0;
    $state['starttime'] = !empty($lastrun) ? (int)$lastrun : 0;

    $startrec = $DB->get_field_select('log', 'MAX(id)', 'time <= ?',
                                      array($state['starttime']));
    $startrec = empty($startrec) ? 0 : $startrec;
    $state['startrec'] = $startrec;

    return $state;
}

/**
 * Process a chunk of the task
 *
 * @param array $state the task state
 * @uses $CFG;
 * @uses $DB;
 */
function user_activity_task_process(&$state) {
    global $CFG, $DB;

    $sessiontimeout = $state['sessiontimeout'];
    $sessiontail = $state['sessiontail'];

    $starttime = $state['starttime'];

    // find the record ID corresponding to our start time
    $startrec = $DB->get_field_select('log', 'MIN(id)', 'time >= ?', array($starttime));
    $startrec = empty($startrec) ? 0 : $startrec;

    // find the last record that's close to our chunk size, without
    // splitting a second between runs
    $endtime = $DB->get_field_select('log', 'MIN(time)', 'id >= ? AND time > ?',
                                     array($startrec + USERACT_RECORD_CHUNK,
                                           $starttime));
    if (!$endtime) {
        $endtime = time();
    }

    // Get the logs between the last time we ran, and the current time.  Sort
    // by userid (so all records for a given user are together), and then by
    // time (so that we process a user's logs sequentially).
    $recstarttime = max(0, $starttime - $state['sessiontimeout']);
    $rs = $DB->get_recordset_select('log',
                                    'time >= ? AND time < ? AND userid != 0',
                                    array($recstarttime, $endtime),
                                    'userid, time');
    if ($CFG->debug >= DEBUG_ALL) {
        mtrace("* processing records from time:{$starttime} to time:{$endtime}");
    }

    $curuser = -1;
    $session_start = 0;
    $last_course = -1;
    $module_session_start = 0;
    $last_module = -1;
    $last_time = 0;
    if ($rs && $rs->valid()) {
        foreach ($rs as $rec) { // WAS while($rec = rs_fetch_next_record($rs))
            if ($rec->userid != $curuser) {
                // end of user's record
                if ($curuser > 0 && $session_start > 0) {
                    // flush current session data
                    if ($last_time > $endtime - $sessiontimeout) {
                        /* Last record is within the session timeout of our end
                         * time for this run.  Just use our last logged time as
                         * the session end time, and the rest will be picked up
                         * by the next run of the sessionizer. */
                        $session_end = $last_time;
                    } else {
                        /* Last record is not within the session timeout of our
                         * end time for this run, so do our normal session
                         * ending. */
                        $session_end = $last_time + $sessiontail;
                    }
                    user_activity_add_session($curuser, $last_course, $session_start, $session_end);
                    if ($last_module > 0) {
                        user_module_activity_add_session($curuser, $last_course, $last_module, $module_session_start, $session_end);
                    }
                }
                $curuser = $rec->userid;
                $session_start = 0;
                $last_course = -1;
                $module_session_start = 0;
                $last_module = -1;
                $last_time = 0;
            }
            if ($rec->time < $starttime) {
                // Find the last log for the user before our start time, that's
                // within the session timeout, and start the session with that
                // record.
                $session_start = $rec->time;
                $last_time = $rec->time;
                $last_course = $rec->course;
                $module_session_start = $rec->time;
                $last_module = $rec->cmid;
            } elseif ($rec->time > $last_time + $sessiontimeout) {
                if ($last_course >= 0) {
                    // session timed out -- add record
                    if (defined('ETLUA_EXTRA_DEBUG') && $CFG->debug >= DEBUG_DEVELOPER) {
                        mtrace('** session timed out');
                    }
                    $session_end = $last_time + $sessiontail;
                    user_activity_add_session($curuser, $last_course, $session_start, $session_end);
                    if ($last_module > 0) {
                        user_module_activity_add_session($curuser, $last_course, $last_module, $module_session_start, $session_end);
                    }
                }
                // start a new session with the current record
                $session_start = $rec->time;
                $last_course = $rec->course;
                $module_session_start = $rec->time;
                $last_module = $rec->cmid;
            } elseif ($rec->action === 'logout') {
                // user logged out -- add record
                if (defined('ETLUA_EXTRA_DEBUG') && $CFG->debug >= DEBUG_DEVELOPER) {
                    mtrace('** user logged out');
                }
                $session_end = $rec->time;
                user_activity_add_session($curuser, $last_course, $session_start, $session_end);
                if ($last_module > 0) {
                    user_module_activity_add_session($curuser, $last_course, $last_module, $module_session_start, $session_end);
                }
                // clear session info
                $session_start = 0;
                $module_session_start = 0;
            } elseif ($rec->course != $last_course) {
                // user switched to a different course -- start new session record
                if ($last_course >= 0) {
                    if (defined('ETLUA_EXTRA_DEBUG') && $CFG->debug >= DEBUG_DEVELOPER) {
                        mtrace('** user switched courses');
                    }
                    $session_end = $rec->time;
                    user_activity_add_session($curuser, $last_course, $session_start, $session_end);
                    if ($last_module > 0) {
                        user_module_activity_add_session($curuser, $last_course, $last_module, $module_session_start, $session_end);
                    }
                }
                $session_start = $rec->time;
                $last_course = $rec->course;
                $module_session_start = $rec->time;
                $last_module = $rec->cmid;
            } elseif ($rec->cmid != $last_module) {
                // user switched to a different module -- start new module session
                if ($last_module > 0) {
                    if (defined('ETLUA_EXTRA_DEBUG') && $CFG->debug >= DEBUG_DEVELOPER) {
                        mtrace('** user switched modules');
                    }
                    $session_end = $rec->time;
                    user_module_activity_add_session($curuser, $last_course, $last_module, $module_session_start, $session_end);
                }
                $module_session_start = $rec->time;
                $last_module = $rec->cmid;
            }
            $last_time = $rec->time;
        }
        $rs->close();
    }

    if ($curuser > 0 && $session_start > 0) {
        // flush session data
        if ($last_time > $endtime - $sessiontimeout) {
            $session_end = $last_time;
        } else {
            $session_end = $last_time + $sessiontail;
        }
        user_activity_add_session($curuser, $last_course, $session_start, $session_end);
    }

    $state['starttime'] = $endtime;

    $endrec = $DB->get_field_select('log', 'MAX(id)', 'time < ?', array($endtime));
    // possibly skip the last time when calculating the total number of
    // records, since we are purposely skipping anything less than $endtime
    $lasttime = $DB->get_field_select('log', 'MAX(time)', 'TRUE');
    $totalrec = $DB->get_field_select('log', 'MAX(id)', 'time < ?', array($lasttime));
    $totalrec = max($totalrec, $endrec);
    return array($endrec ? ($endrec - $state['startrec']) : 0,
                 $totalrec ? ($totalrec - $state['startrec']) : 0);
}

/**
 * Save the task state for later continuation
 *
 * @param array $state the task state
 */
function user_activity_task_save($state) {
    mtrace('* over time limit -- saving state and pausing');
    set_config('state', serialize($state), 'eliscoreplugins_user_activity');
}

/**
 * Finish a task
 *
 * @param array $state the task state
 */
function user_activity_task_finish($state) {
    mtrace('* completed');
    set_config('last_run', $state['starttime'], 'eliscoreplugins_user_activity');
    set_config('state', 0, 'eliscoreplugins_user_activity'); // WAS: null but not allowed in config_plugins
}
