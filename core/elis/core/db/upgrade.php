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
 * @subpackage core
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

function xmldb_elis_core_upgrade($oldversion=0) {
    global $CFG, $THEME, $db;

    $result = true;

    if ($result && $oldversion < 2011030402) {
        /***********************************************************************
         * Replace plugintype and pluginname with plugin field
         **********************************************************************/

    /// Define field plugin to be added to elis_scheduled_tasks
        $table = new XMLDBTable('elis_scheduled_tasks');
        $field = new XMLDBField('plugin');
        $field->setAttributes(XMLDB_TYPE_CHAR, '166', null, XMLDB_NOTNULL, null, null, null, null, 'id');

    /// Launch add field plugin
        $result = $result && add_field($table, $field);

    /// Define index plugin_idx (not unique) to be dropped form elis_scheduled_tasks
        $index = new XMLDBIndex('plugin_idx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('plugintype', 'pluginname', 'taskname'));

    /// Launch drop index plugin_idx
        $result = $result && drop_index($table, $index);

    /// Define index plugin_idx (not unique) to be added to elis_scheduled_tasks
        $index = new XMLDBIndex('plugin_idx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('plugin', 'taskname'));

    /// Launch add index plugin_idx
        $result = $result && add_index($table, $index);

    /// Define field plugin to be dropped from elis_scheduled_tasks
        $field = new XMLDBField('plugintype');

    /// Launch drop field plugin
        $result = $result && drop_field($table, $field);

    /// Define field plugin to be dropped from elis_scheduled_tasks
        $field = new XMLDBField('pluginname');

    /// Launch drop field plugin
        $result = $result && drop_field($table, $field);

        /***********************************************************************
         * Change callfunction from text to char
         **********************************************************************/

    /// Changing type of field callfunction on table elis_scheduled_tasks to char
        $field = new XMLDBField('callfunction');
        $field->setAttributes(XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null, 'callfile');

    /// Launch change of type for field callfunction
        $result = $result && change_field_type($table, $field);
    }

    if ($result && $oldversion < 2011030403) {

    /// Define field startdate to be added to elis_scheduled_tasks
        $table = new XMLDBTable('elis_scheduled_tasks');
        $field = new XMLDBField('startdate');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null, 'runsremaining');

    /// Launch add field startdate
        $result = $result && add_field($table, $field);
    }

    return $result;
}
