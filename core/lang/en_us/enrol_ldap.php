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
 * Strings for component 'enrol_ldap', language 'en_us', branch 'MOODLE_20_STABLE'
 *
 * @package   enrol_ldap
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['autocreate'] = 'Courses can be created automatically if there are
                                    enrollments to a course  that doesn\'t yet exist 
                                    in Snap.';
$string['course_settings'] = 'Course enrollment settings';
$string['pluginname_desc'] = '<p>You can use an LDAP server to control your enrollments.  
                          It is assumed your LDAP tree contains groups that map to 
                          the courses, and that each of thouse groups/courses will 
                          have membership entries to map to students.</p>
                          <p>It is assumed that courses are defined as groups in 
                          LDAP, with each group having multiple membership fields 
                          (<em>member</em> or <em>memberUid</em>) that contain a unique
                          identification of the user.</p>
                          <p>To use LDAP enrollment, your users <strong>must</strong> 
                          to have a valid  idnumber field. The LDAP groups must have 
                          that idnumber in the member fields for a user to be enrolled 
                          in the course.
                          This will usually work well if you are already using LDAP 
                          Authentication.</p>
                          <p>Enrollments will be updated when the user logs in. You
                           can also run a script to keep enrollments in synch. Look in 
                          <em>enrol/ldap/enrol_ldap_sync.php</em>.</p>
                          <p>This plugin can also be set to automatically create new 
                          courses when new groups appear in LDAP.</p>';
