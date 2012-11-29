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

/**
 * Upgrade for eliscoreplugins_user_activity
 * @return boolean
 */
function xmldb_eliscoreplugins_user_activity_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    $result = true;

    if ($oldversion < 2011101800) {
        $DB->delete_records('elis_scheduled_tasks', array('plugin' => 'crlm/user_activity'));
        upgrade_plugin_savepoint(true, 2011101800, 'eliscoreplugins','user_activity');
    }

    if ($result && $oldversion < 2012021400) {
        $sql = "SELECT userid, courseid, hour, COUNT('x') count
                  FROM {etl_user_activity}
                 GROUP BY userid, courseid, hour
                HAVING COUNT('x') > 1";
        if ($DB->record_exists_sql($sql)) {
            $table = new xmldb_table('etl_user_activity_temp');
            // fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null, 'id');
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null, 'userid');
            $table->add_field('hour', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null, 'courseid');
            $table->add_field('duration', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null, 'hour');

            // Keys & indexes
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_index('user_idx', XMLDB_INDEX_UNIQUE,
                              array('userid', 'courseid', 'hour'));
            $table->add_index('course_idx', XMLDB_INDEX_NOTUNIQUE,
                              array('courseid'));
            $table->add_index('hour_idx', XMLDB_INDEX_NOTUNIQUE,
                              array('hour'));

            $dbman->create_table($table);
            $sql = "INSERT INTO {etl_user_activity_temp} (userid, courseid, hour, duration)
                        SELECT userid, courseid, hour, duration
                          FROM {etl_user_activity}
                      GROUP BY userid, courseid, hour
                      ORDER BY id ASC";
            $result = $result && $DB->execute($sql);
            if ($result) {
                $oldtable = new xmldb_table('etl_user_activity');
                $dbman->drop_table($oldtable);
                $dbman->rename_table($table, 'etl_user_activity');
            }
        }
        if ($result) {
            $sql = "UPDATE {etl_user_activity} SET duration = 3600 WHERE duration > 3600";
            $DB->execute($sql);
        }
        upgrade_plugin_savepoint($result, 2012021400, 'eliscoreplugins','user_activity');
    }

    if ($result && $oldversion < 2012110200) {
        $sql = "UPDATE {etl_user_module_activity} SET duration = 3600 WHERE duration > 3600";
        $DB->execute($sql);
        upgrade_plugin_savepoint($result, 2012110200, 'eliscoreplugins','user_activity');
    }

    return $result;
}

