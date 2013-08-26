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

defined('MOODLE_INTERNAL') || die();

function xmldb_elis_core_upgrade($oldversion=0) {
    global $CFG, $THEME, $DB, $OUTPUT;

    $dbman = $DB->get_manager();
    $result = true;

    if ($result && $oldversion < 2011063000) {

        // Define table elis_scheduled_tasks to be created
        $table = new xmldb_table('elis_scheduled_tasks');

        // Adding fields to table elis_scheduled_tasks
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('plugin', XMLDB_TYPE_CHAR, '166', null, XMLDB_NOTNULL, null, null);
        $table->add_field('taskname', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('callfile', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('callfunction', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lastruntime', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('nextruntime', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('blocking', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('minute', XMLDB_TYPE_CHAR, '25', null, XMLDB_NOTNULL, null, null);
        $table->add_field('hour', XMLDB_TYPE_CHAR, '25', null, XMLDB_NOTNULL, null, null);
        $table->add_field('day', XMLDB_TYPE_CHAR, '25', null, XMLDB_NOTNULL, null, null);
        $table->add_field('month', XMLDB_TYPE_CHAR, '25', null, XMLDB_NOTNULL, null, null);
        $table->add_field('dayofweek', XMLDB_TYPE_CHAR, '25', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timezone', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '99');
        $table->add_field('runsremaining', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('startdate', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('enddate', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('customized', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');

        // Adding keys to table elis_scheduled_tasks
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_scheduled_tasks
        $table->add_index('plugin_idx', XMLDB_INDEX_NOTUNIQUE, array('plugin', 'taskname'));
        $table->add_index('nextruntime_idx', XMLDB_INDEX_NOTUNIQUE, array('nextruntime'));

        // Conditionally launch create table for elis_scheduled_tasks
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_workflow_instances to be created
        $table = new xmldb_table('elis_workflow_instances');

        // Adding fields to table elis_workflow_instances
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '127', null, XMLDB_NOTNULL, null, null);
        $table->add_field('subtype', XMLDB_TYPE_CHAR, '127', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('data', XMLDB_TYPE_TEXT, 'medium', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);

        // Adding keys to table elis_workflow_instances
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_workflow_instances
        $table->add_index('usertype_idx', XMLDB_INDEX_NOTUNIQUE, array('userid', 'type', 'subtype'));

        // Conditionally launch create table for elis_workflow_instances
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // core savepoint reached
        upgrade_plugin_savepoint(true, 2011063000, 'elis', 'core');
    }

    if ($result && $oldversion < 2011080100) {
        // create tables that were created by block curr_admin (if upgrading a
        // non-CM site)

        // Define table context_levels to be created
        $table = new xmldb_table('context_levels');

        // Adding fields to table context_levels
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table context_levels
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table context_levels
        $table->add_index('name', XMLDB_INDEX_NOTUNIQUE, array('name'));
        $table->add_index('component', XMLDB_INDEX_NOTUNIQUE, array('component'));

        // Conditionally launch create table for context_levels
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field to be created
        $table = new xmldb_table('elis_field');

        // Adding fields to table elis_field
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('shortname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL, null, null);
        $table->add_field('datatype', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('multivalued', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, '0');
        $table->add_field('forceunique', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('params', XMLDB_TYPE_TEXT, 'big', null, null, null, null);

        // Adding keys to table elis_field
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_field
        $table->add_index('shortname_idx', XMLDB_INDEX_NOTUNIQUE, array('shortname'));

        // Conditionally launch create table for elis_field
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_categories to be created
        $table = new xmldb_table('elis_field_categories');

        // Adding fields to table elis_field_categories
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');

        // Adding keys to table elis_field_categories
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for elis_field_categories
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_contextlevels to be created
        $table = new xmldb_table('elis_field_contextlevels');

        // Adding fields to table elis_field_contextlevels
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('contextlevel', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);

        // Adding keys to table elis_field_contextlevels
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for elis_field_contextlevels
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_category_contexts to be created
        $table = new xmldb_table('elis_field_category_contexts');

        // Adding fields to table elis_field_category_contexts
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('contextlevel', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);

        // Adding keys to table elis_field_category_contexts
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_field_category_contexts
        $table->add_index('contextlevel_idx', XMLDB_INDEX_NOTUNIQUE, array('contextlevel'));
        $table->add_index('category_idx', XMLDB_INDEX_NOTUNIQUE, array('categoryid'));

        // Conditionally launch create table for elis_field_category_contexts
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_data_text to be created
        $table = new xmldb_table('elis_field_data_text');

        // Adding fields to table elis_field_data_text
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('data', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table elis_field_data_text
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_field_data_text
        $table->add_index('context_idx', XMLDB_INDEX_NOTUNIQUE, array('contextid'));
        $table->add_index('field_idx', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

        // Conditionally launch create table for elis_field_data_text
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_data_int to be created
        $table = new xmldb_table('elis_field_data_int');

        // Adding fields to table elis_field_data_int
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('data', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table elis_field_data_int
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_field_data_int
        $table->add_index('context_idx', XMLDB_INDEX_NOTUNIQUE, array('contextid'));
        $table->add_index('field_idx', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

        // Conditionally launch create table for elis_field_data_int
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_data_num to be created
        $table = new xmldb_table('elis_field_data_num');

        // Adding fields to table elis_field_data_num
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('data', XMLDB_TYPE_NUMBER, '15, 5', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table elis_field_data_num
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_field_data_num
        $table->add_index('context_idx', XMLDB_INDEX_NOTUNIQUE, array('contextid'));
        $table->add_index('field_idx', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

        // Conditionally launch create table for elis_field_data_num
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_data_char to be created
        $table = new xmldb_table('elis_field_data_char');

        // Adding fields to table elis_field_data_char
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('data', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table elis_field_data_char
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_field_data_char
        $table->add_index('context_idx', XMLDB_INDEX_NOTUNIQUE, array('contextid'));
        $table->add_index('field_idx', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

        // Conditionally launch create table for elis_field_data_char
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_owner to be created
        $table = new xmldb_table('elis_field_owner');

        // Adding fields to table elis_field_owner
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('plugin', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('exclude', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, '0');
        $table->add_field('params', XMLDB_TYPE_TEXT, 'big', null, null, null, null);

        // Adding keys to table elis_field_owner
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_field_owner
        $table->add_index('field_idx', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

        // Conditionally launch create table for elis_field_owner
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // core savepoint reached
        upgrade_plugin_savepoint(true, 2011080100, 'elis', 'core');
    }

    if ($result && $oldversion < 2011080200) {

        // Define index sortorder_ix (not unique) to be dropped form elis_field
        // (so that we can change the default value)
        $table = new xmldb_table('elis_field');
        $index = new xmldb_index('sortorder_ix', XMLDB_INDEX_NOTUNIQUE, array('sortorder'));

        // Conditionally launch drop index shortname_idx
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Changing the default of field sortorder on table elis_field to 0
        $table = new xmldb_table('elis_field');
        $field = new xmldb_field('sortorder', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'categoryid');

        // Launch change of default for field sortorder
        $dbman->change_field_default($table, $field);

        // Changing the default of field sortorder on table elis_field_categories to 0
        $table = new xmldb_table('elis_field_categories');
        $field = new xmldb_field('sortorder', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'name');

        // Launch change of default for field sortorder
        $dbman->change_field_default($table, $field);

        // Changing the default of field forceunique on table elis_field to 0
        $table = new xmldb_table('elis_field');
        $field = new xmldb_field('forceunique', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'multivalued');

        // Launch change of default for field forceunique
        $dbman->change_field_default($table, $field);

        // core savepoint reached
        upgrade_plugin_savepoint(true, 2011080200, 'elis', 'core');
    }

    if ($result && $oldversion < 2011083000) {
        // Remove elis_info blocks
        $eiblock = $DB->get_record('block', array('name' => 'elis_info'));
        if ($eiblock) {
            // elis_info block exists
            $eiinstance = $DB->get_record('block_instances', array('blockname' => 'elis_info'));

            if ($eiinstance) {
                // elis_info instances exist, delete them ...
                $DB->delete_records('block_positions', array('blockinstanceid' => $eiinstance->id));

                // remove instance
                $DB->delete_records('block_instances', array('blockname' => 'elis_info'));
            }

            // remove any old instances
            if ($dbman->table_exists(new xmldb_table('block_instance_old'))) {
                $DB->delete_records('block_instance_old', array('blockid' => $eiblock->id));
            }
            // remove any old pinned blocks
            if ($dbman->table_exists(new xmldb_table('block_pinned_old'))) {
                $DB->delete_records('block_pinned_old', array('blockid' => $eiblock->id));
            }
            // remove block record
            $DB->delete_records('block', array('id' => $eiblock->id));

            if (file_exists($CFG->dirroot .'/blocks/elis_info')) {
                echo $OUTPUT->notification("Warning: {$CFG->dirroot}/blocks/elis_info directory still exists - please remove it!");
            }
        }

        // core savepoint reached
        upgrade_plugin_savepoint(true, 2011083000, 'elis', 'core');
    }

    if ($result && $oldversion < 2011091401) {
        $table = new xmldb_table('elis_scheduled_tasks');

        // change precisions of cron fields from varchar(25) to varchar(50)
        $field = new xmldb_field('minute', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('hour', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('day', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('month', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('dayofweek', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $dbman->change_field_precision($table, $field);

        // core savepoint reached
        upgrade_plugin_savepoint(true, 2011091401, 'elis', 'core');
    }

    if ($result && $oldversion < 2012032100) {
        // ELIS-4089 -- Attempt to detect if somehow this site had the 'context_levels' table populated out-of-order

        // Is the ELIS PM code present? -- ELIS-6717
        if (file_exists($CFG->dirroot.'/elis/program/lib/setup.php')) {
            require_once($CFG->dirroot.'/elis/program/lib/setup.php');

            $ctxmap = array(); // An array of 'correct_context_id' => 'invalid_context_id' to be used for cleanup purposes

            $ctxlvls = $DB->get_recordset('context_levels');
            if ($ctxlvls->valid()) {
                foreach ($ctxlvls as $ctxlvl) {
                    $level = context_elis_helper::get_level_from_name($ctxlvl->name);

                    // Check if this is an invalid level
                    if ($level != $ctxlvl->id + 1000) {
                        $ctxmap[$level] = $ctxlvl->id + 1000;
                    }
                }
            }
            unset($ctxlvls);

            // Do we have bad contexts that we need to remap
            if (!empty($ctxmap)) {
                // Initial pass, change all context levels to avoid collitions when resetting to "correct" values
                foreach ($ctxmap as $level_good => $level_bad) {
                    $tmp_level = $level_bad + 1000;
                    if ($DB->record_exists('context', array('contextlevel' => $tmp_level))) {
                        throw new coding_exception('Context level '.$tmp_level.' exists but really should not');
                    }

                    $sql = "UPDATE {context}
                            SET contextlevel = ?
                            WHERE contextlevel = ?";

                    $DB->execute($sql, array($tmp_level, $level_bad));
                }

                reset($ctxmap);

                // Second pass, reset the temp context levels to the good values now
                foreach ($ctxmap as $level_good => $level_bad) {
                    $tmp_level = $level_bad + 1000;
                    $sql = "UPDATE {context}
                            SET contextlevel = ?
                            WHERE contextlevel = ?";

                    $DB->execute($sql, array($level_good, $tmp_level));
                }
            }
        }

        // Get rid of the 'context_levels' table as it's no longer needed
        $table = new xmldb_table('context_levels');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        upgrade_plugin_savepoint(true, 2012032100, 'elis', 'core');
    }

    if ($result && $oldversion < 2012091900) {
        // clear out duplicate default values from elis_field_data tables
        $tables = array('elis_field_data_char',
                        'elis_field_data_int',
                        'elis_field_data_num',
                        'elis_field_data_text');
        foreach ($tables as $data_table) {
            $table = new xmldb_table($data_table);
            $dbman->rename_table($table, 'old_'. $data_table);
            $dbman->install_one_table_from_xmldb_file("{$CFG->dirroot}/elis/core/db/install.xml", $data_table);
            $DB->execute("INSERT INTO {{$data_table}} (contextid, fieldid, data) SELECT DISTINCT contextid, fieldid, data FROM {old_{$data_table}}");
            $table = new xmldb_table('old_'. $data_table);
            $dbman->drop_table($table);
        }
        upgrade_plugin_savepoint(true, 2012091900, 'elis', 'core');
    }

    if ($result && $oldversion < 2012121900) {
        // ELIS-8124: convert Moodle profile field menu-of-choices for ELIS
        require_once($CFG->dirroot.'/user/profile/lib.php');
        require_once($CFG->dirroot.'/user/profile/field/menu/field.class.php');
        // Note: not using a recordset for fields because we require this as an array & the max number << 1000 (probably << 100)
        $toconvertfields = $DB->get_records_select('user_info_field', "datatype  = 'menu' AND param1 <> '' AND param1 IS NOT NULL", null, '', 'id, param1');

        if (!empty($toconvertfields)) {
            array_walk($toconvertfields, function(&$item, $key) {
                    $item->param1 = explode("\n", $item->param1);
                }
            );
            $toconvertrecords = $DB->get_recordset_select('user_info_data', "fieldid IN ('". implode("','", array_keys($toconvertfields)) ."')");
            if (!empty($toconvertrecords) && $toconvertrecords->valid()) {
                foreach ($toconvertrecords as $rec) {
                    $val = profile_field_menu::decode_data($rec->data);
                    $valint = intval($val);
                    if (is_numeric($val) && floatval($val) == (float)$valint && !in_array($val, $toconvertfields[$rec->fieldid]->param1)
                        && $valint < count($toconvertfields[$rec->fieldid]->param1)) {
                        $rec->data = profile_field_menu::encode_data($toconvertfields[$rec->fieldid]->param1[$valint]);
                        $DB->update_record('user_info_data', $rec);
                    }
                }
                $toconvertrecords->close();
            }
        }

        upgrade_plugin_savepoint(true, 2012121900, 'elis', 'core');
    }

    if ($result && $oldversion < 2013022700) {
        // ELIS-8295: install missing message processors
        if ($dbman->table_exists('message_processors')) {
            foreach (get_list_of_plugins('message/output') as $mp) {
                // error_log("elis_core::upgrade.php: checking for message processor: '{$mp}'");
                if (!$DB->record_exists('message_processors', array('name' => $mp))) {
                    require_once("{$CFG->dirroot}/message/output/{$mp}/db/install.php");
                    $installfcn = "xmldb_message_{$mp}_install";
                    if (function_exists($installfcn)) {
                        $installfcn();
                    }
                }
            }
        }
        upgrade_plugin_savepoint(true, 2013022700, 'elis', 'core');
    }

    if ($result && $oldversion < 2013051400) {
        // Add new table column: (int)blocked
        $table = new xmldb_table('elis_scheduled_tasks');
        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('blocked', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'customized');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2013051400, 'elis', 'core');
    }

    return $result;
}

