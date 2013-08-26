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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

class manual_options_learning_objectives extends manual_options_base_class {
    function is_applicable($contextlevel) {
        return $contextlevel === 'course' || $contextlevel === 'class';
    }

    function get_options($data) {
        if (is_array($data) && isset($data['obj']) && !empty($data['obj']->id)) {
            $dataobject = $data['obj'];
            if (property_exists($dataobject, 'name')) {
                $course = new course($dataobject);
            } else if (property_exists($dataobject, 'courseid')) {
                $course = new course($dataobject->courseid);
            } else {
                return array();
            }
            $compelems = $course->get_completion_elements();
        } else {
            // just get ALL completion elements (LOs)
            global $DB;
            $compelems = $DB->get_recordset('crlm_course_completion', null, '', 'id, name, idnumber');
        }
        $result = array('' => get_string('anyvalue', 'filters'));
        foreach ($compelems as $compelem) {
            $result[$compelem->idnumber] = "{$compelem->name} ({$compelem->idnumber})";
        }
        unset($compelems);
        return $result;
    }
}
