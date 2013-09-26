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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

$user_activity_health_checks = array(
        'user_activity_health_empty',
        'user_activity_health_log_prune'
);

class user_activity_health_empty extends crlm_health_check_base {
    function __construct() {
        global $CURMAN;
        $this->lastrun = isset(elis::$config->eliscoreplugins_user_activity->last_run) ? (int)elis::$config->eliscoreplugins_user_activity->last_run : 0;
        $this->inprogress = !empty(elis::$config->eliscoreplugins_user_activity->state);
    }

    function exists() {
        global $DB;
        // health warning if the cron hasn't been run, or is more than a week behind
        if (!$this->lastrun) {
            return true;
        }
        require_once(dirname(__FILE__) .'/etl.php');
        $etlobj = new etl_user_activity(0, false);
        $last_time = (int)$etlobj->state['starttime'];
        return($DB->count_records_select('log', "time >= $last_time") > 0 &&
               (time() - (7 * DAYSECS)) > $last_time);
    }

    function title() {
        if ($this->inprogress) {
            return 'ETL process is in progress';
        } else {
            return 'ETL process has not run yet';
        }
    }

    function severity() {
        if ($this->inprogress) {
            return healthpage::SEVERITY_NOTICE;
        } else {
            return healthpage::SEVERITY_ANNOYANCE;
        }
    }

    function description() {
        global $DB;

        if ($this->inprogress) {
            require_once(dirname(__FILE__) .'/etl.php');
            $etlobj = new etl_user_activity(0, false);
            $lasttime = (int)$etlobj->state['starttime'];
            $lastprocessed = !empty($etlobj->state['recs_last_processed']) ? (int)$etlobj->state['recs_last_processed'] : 0;
            $etlminhour = $DB->get_field('etl_user_activity', 'MIN(hour)', array());
            $etlmaxhour = $DB->get_field('etl_user_activity', 'MAX(hour)', array());
            $logendtime = $DB->get_field('log', 'MAX(time)', array());
            if (empty($logendtime) || $logendtime < $etlmaxhour) {
                $logendtime = time();
            }
            $percentcomplete = sprintf('%.2f', ($etlmaxhour - $etlminhour)/($logendtime - $etlminhour) * 100);
            $description = 'The ETL process has not completed running.  Certain reports (such as the site-wide time summary) may show incomplete data '.
                    "until the ETL process has completed.<br/>Currently, the ETL process is <b>{$percentcomplete}%</b> complete";
            if ($lastprocessed) {
                if (isset($etlobj->state['log_entries_per_day']) && ($logentriesperday = (float)$etlobj->state['log_entries_per_day']) > 0) {
                    $est1 = (float)$DB->count_records_select('log', 'time >= ?', array($lasttime)) / $lastprocessed;
                    $daystodo = ceil($est1 + ($logentriesperday * $est1 / $lastprocessed));
                } else {
                    $daystodo = ceil($DB->count_records_select('log', 'time >= ?', array($lasttime)) / $lastprocessed);
                }
                $description .= " and should take ~ {$daystodo} days to complete.";
            } else {
                $description .= '.';
            }
            return $description;
        } else {
            return "The ETL process has not been run. This prevents certain reports (such as the site-wide time summary) from working.";
        }
    }

    function solution() {
        if ($this->inprogress) {
            return "The ETL process is in progress, and should complete automatically.  It may take some time, depending on the size of the log table.  Please check back in a day or two.  If the problem persists, please contact support.  Support, please escalate to the development team.";
        } else {
            return "If the Moodle cron is run regularly, then the ETL cron should run automatically overnight.  Please check back tomorrow.  If the problem persists, please contact support.  Support, please escalate to the development team.";
        }
    }
}

/**
 * health check class for user activity and log table pruning interactions
 */
class user_activity_health_log_prune extends crlm_health_check_base {
    /**
     * @var int The last run time of the ETL process
     */
    protected $lastrun = 0;

    /**
     * @var bool true if the ETL process is in saved state, false otherwise
     */
    protected $inprogress = 0;

    /**
     * user_activity_health_log_prune class constructor
     */
    function __construct() {
        $this->lastrun = isset(elis::$config->eliscoreplugins_user_activity->last_run)
                ? (int)elis::$config->eliscoreplugins_user_activity->last_run : 0;
        $this->inprogress = !empty(elis::$config->eliscoreplugins_user_activity->state);
    }

    /**
     * user_activity_health_log_prune class exists() method
     * @return bool true if health problem exists, false otherwise
     */
    public function exists() {
        global $DB;
        // health warning if ETL processing, Moodle's loglifetime set and within 30 days of current ETL record's time
        if ($this->inprogress && ($loglifetime = get_config('moodle', 'loglifetime')) &&
                ($this->lastrun + ($loglifetime - 29) * DAYSECS) <= time()) {
            return true;
        }
        return false;
    }

    /**
     * user_activity_health_log_prune class title() method
     * @return string the health check title string
     */
    public function title() {
        return get_string('health_etl_prune_log_title', 'elis_core');
    }

    /**
     * user_activity_health_log_prune class serverity() method
     * @return mixed the health check severity constant
     */
    public function severity() {
        $loglifetime = get_config('moodle', 'loglifetime');
        if (($this->lastrun + ($loglifetime - 6) * DAYSECS) <= time()) {
            return healthpage::SEVERITY_SIGNIFICANT;
        }
        return healthpage::SEVERITY_NOTICE;
    }

    /**
     * user_activity_health_log_prune class description() method
     * @return string the health check description
     */
    public function description() {
        $loglifetime = get_config('moodle', 'loglifetime');
        return get_string('health_etl_prune_log_desc', 'elis_core', $loglifetime);
    }

    /**
     * user_activity_health_log_prune class solution() method
     * @return string the health check solution
     */
    public function solution() {
        return get_string('health_etl_prune_log_soln', 'elis_core');
    }
}
