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
 * Strings for component 'enrol_authorize', language 'en_us', branch 'MOODLE_20_STABLE'
 *
 * @package   enrol_authorize
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['adminneworder'] = 'Dear Admin,

  You have received a new pending order:

   Order ID: {$a->orderid}
   Transaction ID: {$a->transid}
   User: {$a->user}
   Course: {$a->course}
   Amount: {$a->amount}

   SCHEDULED-CAPTURE ENABLED?: {$a->acstatus}

  If scheduled-capture enabled the credit card will be captured on {$a->captureon}
  and then student will be enrolled to course, otherwise it will be expired
  on {$a->expireon} and cannot be captured after this day.

  Also you can accept/deny the payment to enroll the student immediately following this link:
  {$a->url}';
$string['choosemethod'] = 'If you know the enrollment key of the cource, please enter it; otherwise you need to pay for this course.';
$string['description'] = 'The Authorize.net module allows you to set up paid courses via CC providers.  If the cost for any course is zero, then students are not asked to pay for entry.  Two ways to set the course cost (1) a site-wide cost as a default for the whole site or (2) a course setting that you can set for each course individually. The course cost overrides the site cost.<br /><br /><b>Note:</b> If you enter an enrollment key in the course settings, then students will also have the option to enroll using a key. This is useful if you have a mixture of paying and non-paying students.';
$string['unenrolstudent'] = 'Unenroll student?';
$string['usingccmethod'] = 'Enroll using <a href="{$a->url}"><strong>Credit Card</strong></a>';
$string['usingecheckmethod'] = 'Enroll using <a href="{$a->url}"><strong>eCheck</strong></a>';
