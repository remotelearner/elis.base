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
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

/**
 * Function to convert Moodle custom profile fields to correct option
 * to be called after profile_load_data()
 * @param object $mu Moodle user object with custom menu fields present
 */
function fix_moodle_profile_fields(&$mu) {
    global $CFG, $DB, $USER;
    // Find custom profile menu-type fields & ensure values are a valid option
    foreach ($mu as $key => $value) {
        if (preg_match('/^profile_field_/', $key)) {
            $shortname = str_replace('profile_field_', '', $key);
            if ($fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => $shortname, 'datatype' => 'menu'))) {
                require_once($CFG->dirroot.'/user/profile/field/menu/field.class.php');
                $menufield = new profile_field_menu($fieldid, isset($mu->id) ? $mu->id : 0);
                if (($mu->$key = $menufield->convert_external_data($value)) === null) {
                    unset($mu->$key); // illegal value so unset it
                }
            }
        }
    }
}
