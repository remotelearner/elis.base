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
 * @package    elis_core
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 */

defined('MOODLE_INTERNAL') || die();

$string['cli_error_blocked'] = 'Another ETL cron job is already running';
$string['cli_error_hours'] = 'An invlid hour number was entered';
$string['cli_error_max_time_exceeded'] = 'You have specified a duration that exceeds the maximum allowed of {$a} seconds';
$string['cli_error_minutes'] = 'An invalid minute number was entered';
$string['cli_error_no_pcntl'] = 'Missing Process Control extension for PHP - pcntl_signal function not available!';
$string['cli_error_zero_duration'] = 'An ETL duration of zero means nothing to do.  Exiting script.';
$string['cli_help'] = "Run ELIS ETL cron with a defined period of time.\n\nFor example: '\$sudo -u www-data /usr/bin/php elis/core/plugins/user_activity/cli/run_etl_cron.php -m=55 -H=44' will run the ETL process for 55 minutes and 44 hours.  If you include -m and -H with no numbers then the process will run for 1 hour and 1 minute.\n\nThere are no security checks here because anybody who is able to execute this file may execute any PHP too.\n\nOptions:\n-h, --help          Print out this help.\n-m=minutes          The number of minutes to run the process for.\n-H=hours            The number of hours to run the process for.\n";
$string['cli_run_etl_cron_heading'] = 'Execute ETL cron for {$a->minutes} minutes and {$a->hours} hours';
$string['eliscoreplugins_user_activity'] = 'User Activity ETL';
$string['pluginname'] = 'User Activity ETL';
