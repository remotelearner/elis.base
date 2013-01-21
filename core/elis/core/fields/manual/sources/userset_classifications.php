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

class manual_options_userset_classifications extends manual_options_base_class {
    function get_options($dataobject) {
        global $DB;

        require_once elispm::file('plugins/userset_classification/usersetclassification.class.php');
        $result = array();
        $recs = $DB->get_recordset(usersetclassification::TABLE, null, 'name ASC', 'shortname, name');
        foreach ($recs as $rec) {
            $result[$rec->shortname] = $rec->name;
        }
        unset($recs);

        return $result;
    }

    function is_applicable($contextlevel) {
        return $contextlevel === 'cluster' && is_readable(elis::plugin_file('pmplugins_userset_classification','usersetclassification.class.php'));
    }
}
