<?php
/**
 * Task management functions.  Based heavily on /lib/eventslib.php
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
 * Loads the task definitions for the component (from file). If no
 * tasks are defined for the component, we simply return an empty array.
 * @param $component - examples: 'moodle', 'mod_forum', 'block_quiz_results'
 * @return array of tasks or empty array if not exists
 *
 * INTERNAL - to be used from cronlib only
 */
function elis_tasks_load_def($component) {
    global $CFG;

    if ($component == 'moodle') {
        $defpath = $CFG->libdir.'/db/tasks.php';

    //} else if ($component == 'unittest') {
    //    $defpath = $CFG->libdir.'/simpletest/fixtures/tasks.php';

    } else {
        $defpath = get_component_directory($component) .'/db/tasks.php';
        //error_log("/elis/core/lib/tasklib.php::elis_tasks_load_def('{$component}') looking for: {$defpath}");
    }

    $tasks = array();

    if (file_exists($defpath)) {
        require($defpath);
    }

    return $tasks;
}

/**
 * Gets the tasks that have been cached in the database for this
 * component.
 * @param $component - examples: 'moodle', 'mod/forum', 'block/quiz_results'
 * @return array of tasks
 *
 * INTERNAL - to be used from cronlib only
 */
function elis_tasks_get_cached($component) {
    global $DB;
    $cachedtasks = array();

    $dbman = $DB->get_manager();

    //check needed during install
    if ($dbman->table_exists('elis_scheduled_tasks')) {
        $storedtasks = $DB->get_recordset_select(
                'elis_scheduled_tasks',
                "plugin = ? AND taskname IS NULL",
                array($component)
        );
        foreach ($storedtasks as $task) {
            $cachedtasks[$task->callfunction] = (array)$task;
        }
        unset($storedtasks);
    }

    return $cachedtasks;
}

/**
 * We can not removed all event handlers in table, then add them again
 * because event handlers could be referenced by queued items
 *
 * Note that the absence of the db/events.php event definition file
 * will cause any queued events for the component to be removed from
 * the database.
 *
 * @param $component - examples: 'moodle', 'mod_forum', 'block_quiz_results'
 * @return boolean
 */
function elis_tasks_update_definition($component='moodle') {
    global $DB;

    // load event definition from events.php
    $filetasks = elis_tasks_load_def($component);

    // load event definitions from db tables
    // if we detect an event being already stored, we discard from this array later
    // the remaining needs to be removed
    $cachedtasks = elis_tasks_get_cached($component);

    foreach ($filetasks as $filetask) {
        // preprocess file task
        $filetask['blocking']  = empty($filetask['blocking'])  ? 0 : 1;
        $filetask['minute']    = empty($filetask['minute'])    ? '*' : $filetask['minute'];
        $filetask['hour']      = empty($filetask['hour'])      ? '*' : $filetask['hour'];
        $filetask['day']       = empty($filetask['day'])       ? '*' : $filetask['day'];
        $filetask['month']     = empty($filetask['month'])     ? '*' : $filetask['month'];
        $filetask['dayofweek'] = empty($filetask['dayofweek']) ? '*' : $filetask['dayofweek'];
        $callfunction = serialize($filetask['callfunction']);

        if (!empty($cachedtasks[$callfunction])) {
            $cachedtask = &$cachedtasks[$callfunction];
            if ($cachedtask['customized']) {
                // task is customized by the administrator, so don't change it
                unset($cachedtask[$callfunction]);
                continue;
            }
            if ($cachedtask['callfile'] == $filetask['callfile'] &&
                $cachedtask['blocking'] == $filetask['blocking'] &&
                $cachedtask['minute'] == $filetask['minute'] &&
                $cachedtask['hour'] == $filetask['hour'] &&
                $cachedtask['day'] == $filetask['day'] &&
                $cachedtask['month'] == $filetask['month'] &&
                $cachedtask['dayofweek'] == $filetask['dayofweek']) {
                // exact same task already present in db, ignore this entry

                unset($cachedtasks[$callfunction]);
                continue;

            } else {
                // same task matches, this task has been updated, update the datebase
                $task = new object();
                $task->id           = $cachedtask['id'];
                $task->callfile     = $filetask['callfile'];
                $task->callfunction = $callfunction;
                $task->blocking     = $filetask['blocking'];
                $task->minute       = $filetask['minute'];
                $task->hour         = $filetask['hour'];
                $task->day          = $filetask['day'];
                $task->month        = $filetask['month'];
                $task->dayofweek    = $filetask['dayofweek'];

                $DB->update_record('elis_scheduled_tasks', $task);

                unset($cachedtasks[$callfunction]);
                continue;
            }

        } else {
            // if we are here, this event handler is not present in db (new)
            // add it
            $task = new object();
            $task->plugin       = $component;
            $task->callfile     = $filetask['callfile'];
            $task->callfunction = $callfunction;
            $task->blocking     = $filetask['blocking'];
            $task->minute       = $filetask['minute'];
            $task->hour         = $filetask['hour'];
            $task->day          = $filetask['day'];
            $task->month        = $filetask['month'];
            $task->dayofweek    = $filetask['dayofweek'];
            $task->timezone     = 99;
            $task->nextruntime  = cron_next_run_time(time(), (array)$task);

            $DB->insert_record('elis_scheduled_tasks', $task);
        }
    }

    // clean up the left overs, the entries in cachedtasks array at this points are deprecated event handlers
    // and should be removed, delete from db
    elis_tasks_cleanup($component, $cachedtasks);

    return true;
}

/**
 * Remove all tasks
 * @param $component - examples: 'moodle', 'mod/forum', 'block/quiz_results'
 */
function elis_tasks_uninstall($component) {
    $cachedtasks = elis_tasks_get_cached($component);
    elis_tasks_cleanup($component, $cachedtasks);
}

/**
 * Deletes cached tasks that are no longer needed by the component.
 * @param $component - examples: 'moodle', 'mod/forum', 'block/quiz_results'
 * @param $chachedtasks - array of the cached tasks definitions that will be
 * @return int - number of deprecated tasks that have been removed
 *
 * INTERNAL - to be used from tasklib only
 */
function elis_tasks_cleanup($component, $cachedtasks) {
    global $DB;
    $deletecount = 0;
    foreach ($cachedtasks as $cachedtask) {
        if ($DB->delete_records('elis_scheduled_tasks',
                                array('id' => $cachedtask['id']))) {
            $deletecount++;
        }
    }

    return $deletecount;
}

/******************************************************************************
 * The rest of this file was copied from mahara:htdocs/lib/cron.php, with the
 * following modifications:
 * - take into account the task's timezone field
 *
 * Mahara: Electronic portfolio, weblog, resume builder and social networking
 * Copyright (C) 2006-2009 Catalyst IT Ltd and others; see:
 *                         http://wiki.mahara.org/Contributors
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
 ******************************************************************************/

function cron_next_run_time($lastrun, $job) {
    //error_log("cron_next_run_time($lastrun, (object)job) job['timezone'] = {$job['timezone']}");
    $run_date = usergetdate($lastrun, $job['timezone']);

    // we don't care about seconds for cron
    $run_date['seconds'] = 0;

    // get the specified timezone
    $run_date['timezone'] = $job['timezone'];

    // assert valid month
    if (!cron_valid_month($job, $run_date)) {
        cron_next_month($job, $run_date);

        cron_first_day($job, $run_date);
        cron_first_hour($job, $run_date);
        cron_first_minute($job, $run_date);

        return datearray_to_timestamp($run_date);
    }

    // assert valid day
    if (!cron_valid_day($job, $run_date)) {
        cron_next_day($job, $run_date);

        cron_first_hour($job, $run_date);
        cron_first_minute($job, $run_date);

        return datearray_to_timestamp($run_date);
    }

    // assert valid hour
    if (!cron_valid_hour($job, $run_date)) {
        cron_next_hour($job, $run_date);

        cron_first_minute($job, $run_date);

        return datearray_to_timestamp($run_date);
    }

    cron_next_minute($job, $run_date);

    return datearray_to_timestamp($run_date);

}

function datearray_to_timestamp($date_array) {
    $ts = make_timestamp(
                  $date_array['year'],
                  $date_array['mon'],
                  $date_array['mday'],
                  $date_array['hours'],
                  $date_array['minutes'],
                  $date_array['seconds'],
                  $date_array['timezone']
          );
    //error_log("tasklib.php::datearray_to_timestamp(): timezone = {$date_array['timezone']} => {$ts}");
    return $ts;
}

/**
  * Determine next value for a single cron field
  *
  * This function is designed to parse a cron field specification and then
  * given a current value of the field, determine the next value of that field.
  *
  * @param $fieldspec Cron field specification (e.g. "3,7,20-30,40-50/2")
  * @param $currentvalue Current value of this field
  * @param $ceiling Maximum value this field can take (e.g. for minutes this would be set to 60)
  * @param &$propagate Determines (a) if this value can remain at current value
  * or not, (b) returns true if this field wrapped to zero to find the next
  * value.
  * @param &$steps Returns the number of steps that were taken to get from currentvalue to the next value.
  * @param $allowzero Is this field allowed to be 0?
  * @param $ceil_zero_same If the fieldspec has a number equivalent of ceiling in it, is that the same as 0?
  *
  * @return The next value for this field
  */
function cron_next_field_value($fieldspec, $currentvalue, $ceiling, &$propagate, &$steps, $allowzero = true, $ceil_zero_same = false) {
    $currentvalue = (int) $currentvalue; // make sure it isn't a string
    $timeslices = array_pad(Array(), $ceiling, false);

    foreach ( explode(',',$fieldspec) as $spec ) {
		if (preg_match("~^(\\*|([0-9]{1,2})(-([0-9]{1,2}))?)(/([0-9]{1,2}))?$~",$spec,$matches)) {
            if ($matches[1] == '*') {
                $from = 0;
                $to   = $ceiling - 1;
            }
            else {
                $from = $matches[2];
                if (isset($matches[4])) {
                    $to   = $matches[4];
                }
                else {
                    $to   = $from;
                }
            }
            if (isset($matches[6])) {
                $step = $matches[6];
            }
            else {
                $step = 1;
            }

            for ($i = $from; $i <= $to; $i += $step) {
                if ($ceil_zero_same && $i == $ceiling) {
                    $timeslices[0] = true;
                }
                else {
                    $timeslices[$i] = true;
                }
            }

        }
    }

    // the previous field wrapped, this one HAS to change
    if ($propagate) {
        $currentvalue++;
        $steps = 1;
    }
    else {
        $steps = 0;
    }

    for ($currentvalue; $currentvalue < $ceiling; $currentvalue++, $steps++) {
        if ($timeslices[$currentvalue]) {
            break;
        }
    }

    // if we found a value
    if ($currentvalue != $ceiling) {
        $propagate = 0;
        return $currentvalue;
    }

    for ($currentvalue= ($allowzero ? 0 : 1); $currentvalue < $ceiling; $currentvalue++, $steps++) {
        if ($timeslices[$currentvalue]) {
            break;
        }
    }

    $propagate = 1;
    return $currentvalue;
}

function cron_day_of_week($date_array) {
    return date('w', mktime(0, 0, 0, $date_array['mon'], $date_array['mday'], $date_array['year']));
}

// --------------------------------------------------------

function cron_valid_month($job, $run_date) {
    $propagate = 0;
    cron_next_field_value($job['month'], $run_date['mon'], 13, $propagate, $steps, false);

    if ($steps) {
        return false;
    }
    else {
        return true;
    }
}

function cron_valid_day($job, $run_date) {
    $propagate = 0;
    cron_next_field_value($job['day'], $run_date['mday'], 32, $propagate, $dayofmonth_steps, false);

    $propagate = 0;
    cron_next_field_value($job['dayofweek'], cron_day_of_week($run_date), 7, $propagate, $dayofweek_steps, true);

    if ($job['dayofweek'] == '*') {
        return ($dayofmonth_steps ? false : true);
    }
    else if ($job['day'] == '*') {
        return ($dayofweek_steps ? false : true);
    }
    else {
        if ($dayofmonth_steps && $dayofweek_steps) {
            return false;
        }
        else {
            return true;
        }
    }
}

function cron_valid_hour($job, $run_date) {
    $propagate = 0;
    cron_next_field_value($job['hour'], $run_date['hours'], 24, $propagate, $steps);

    if ($steps) {
        return false;
    }
    else {
        return true;
    }
}

function cron_valid_minute($job, $run_date) {
    $propagate = 0;
    cron_next_field_value($job['minute'], $run_date['minutes'], 60, $propagate, $steps);

    if ($steps) {
        return false;
    }
    else {
        return true;
    }
}

function cron_next_month($job, &$run_date) {
    $propagate = 1;
    $run_date['mon'] = cron_next_field_value($job['month'], $run_date['mon'], 13, $propagate, $steps, false);

    if ($propagate) {
        $run_date['year']++;
    }
}

function cron_next_day($job, &$run_date) {
    // work out which has less steps
    $propagate = 1;
    cron_next_field_value($job['day'], $run_date['mday'], 32, $propagate, $month_steps, false);
    $propagate = 1;
    cron_next_field_value($job['dayofweek'], cron_day_of_week($run_date), 7, $propagate, $week_steps, true, true);

    if ($job['dayofweek'] == '*') {
        $run_date['mday'] += $month_steps;
    }
    else if ($job['day'] == '*') {
        $run_date['mday'] += $week_steps;
    }
    else if ($month_steps < $week_steps) {
        $run_date['mday'] += $month_steps;
    }
    else {
        $run_date['mday'] += $week_steps;
    }

    // if the day is outside the range of this month, try again from 0
    if ($run_date['mday'] > date('t', mktime(0, 0, 0, $run_date['mon'], 1, $run_date['year']))) {
        cron_next_month($job, $run_date);

        cron_first_day($job, $run_date);
    }
}

function cron_next_hour($job, &$run_date) {
    $propagate = 1;
    $run_date['hours'] = cron_next_field_value($job['hour'], $run_date['hours'], 24, $propagate, $steps);

    if ($propagate) {
        cron_next_day($job, $run_date);
    }
}

function cron_next_minute($job, &$run_date) {
    $propagate = 1;
    $run_date['minutes'] = cron_next_field_value($job['minute'], $run_date['minutes'], 60, $propagate, $steps);

    if ($propagate) {
        cron_next_hour($job, $run_date);
    }
}

function cron_first_day($job, &$run_date) {
    $propagate = 0;
    cron_next_field_value($job['day'], 1, 32, $propagate, $month_steps, false);

    $propagate = 0;
    $run_date['mday'] = 1;
    cron_next_field_value($job['dayofweek'], cron_day_of_week($run_date), 7, $propagate, $week_steps, true, true);

    if ($job['dayofweek'] == '*') {
        $run_date['mday'] += $month_steps;
    }
    else if ($job['day'] == '*') {
        $run_date['mday'] += $week_steps;
    }
    else if ($month_steps < $week_steps) {
        $run_date['mday'] += $month_steps;
    }
    else {
        //log_debug('using week_steps: ' . $week_steps);
        $run_date['mday'] += $week_steps;
    }

    //log_debug('    setting mday to ' . $run_date['mday']);
}

function cron_first_hour($job, &$run_date) {
    $propagate = 0;
    $run_date['hours'] = cron_next_field_value($job['hour'], 0, 24, $propagate, $steps);
}

function cron_first_minute($job, &$run_date) {
    $propagate = 0;
    $run_date['minutes'] = cron_next_field_value($job['minute'], 0, 60, $propagate, $steps);
}
