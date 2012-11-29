<?php
/**
 * A script to run certain steps that are required before an upgrade to Moodle 2.x / ELIS 2.
 *
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


define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/ddllib.php');


$dbman = $DB->get_manager();

/*
 * Handle duplicate records in the mdl_grade_letters table.
 */

$status = true;

mtrace(' >>> '.get_string('preup_gl_check', 'elis_core'));

// Detect if we have any duplicate records that need removal
$sql = "SELECT contextid, lowerboundary, letter, COUNT('x') count
        FROM {grade_letters}
        GROUP BY contextid, lowerboundary, letter
        HAVING COUNT('x') > 1";

if ($rec = $DB->record_exists_sql($sql, array())) {
    mtrace(' --- '.get_string('preup_dupfound', 'elis_core'));

    $table = new xmldb_table('grade_letters_temp');
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('lowerboundary', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
    $table->add_field('letter', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

    try {
        $dbman->create_table($table);
    } catch (Excpetion $e) {
        mtrace(' xxx '.get_string('preup_error_tablecreate', 'elis_core'));

        $status = false;
    }

    if ($status) {
        $sql = 'INSERT INTO {grade_letters_temp} (contextid, lowerboundary, letter)
                SELECT contextid, lowerboundary, letter
                FROM {grade_letters}
                GROUP BY contextid, lowerboundary, letter
                ORDER BY id ASC';

        try {
            $DB->execute($sql);
        } catch (Exception $e) {
            mtrace(' xxx '.get_string('preup_error_uniquecopy', 'elis_core'));

            $status = false;
            break;
        }
    }

    if ($status) {
        try {
            $dbman->drop_table(new xmldb_table('grade_letters'));
            $dbman->rename_table($table, 'grade_letters');

            mtrace(' --- '.get_string('preup_gl_success', 'elis_core'));
        } catch (Exception $e) {
            mtrace(' xxx '.get_string('preup_error_uniquecopy', 'elis_core'));

            $status = false;
        }
    }
}

mtrace(' ... '.get_string('done', 'elis_core')."!\n");


/*
* Handle duplicate records in the mdl_user_preferences table.
*/

mtrace(' >>> '.get_string('preup_up_check', 'elis_core'));

// Detect if we have any duplicate records before we try to remove duplicates
$sql = "SELECT userid, name, value, COUNT('x') count
        FROM {user_preferences}
        GROUP BY userid, name, value
        HAVING COUNT('x') > 1
        ORDER BY count DESC";

if ($rec = $DB->record_exists_sql($sql, array())) {
    $table = new xmldb_table('user_preferences');
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    $table->add_field('value', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

    try {
        $dbman->create_table($table);
    } catch (Excpetion $e) {
        mtrace(' xxx '.get_string('preup_error_tablecreate', 'elis_core'));

        $status = false;
    }

    if ($status) {
        $sql = 'INSERT INTO {user_preferences_temp} (userid, name, value)
                SELECT userid, name, value
                FROM {user_preferences}
                GROUP BY userid, name, value
                ORDER BY id ASC';

        try {
            $DB->execute($sql);
        } catch (Exception $e) {
            mtrace(' xxx '.get_string('preup_error_uniquecopy', 'elis_core'));

            $status = false;
        }
    }

    if ($status) {
        try {
            $dbman->drop_table(new xmldb_table('user_preferences'));
            $dbman->rename_table($table, 'user_preferences');

            mtrace(' --- '.get_string('preup_up_success', 'elis_core'));
        } catch (Exception $e) {
            mtrace(' xxx '.get_string('preup_error_uniquecopy', 'elis_core'));

            $status = false;
        }
    }
}

mtrace(' ... '.get_string('done', 'elis_core')."!\n");


/*
 * Migrate the old Alfresco capability / role assignments to new ELIS Files capabilities.
 */

mtrace(' >>> '.get_string('preup_ec_check', 'elis_core'));

if ($status) {
    try {
        // Find all of the capabilities that are set to enabled
        $select = 'capability LIKE :cap AND permission LIKE :perm';
        $params = array('cap' => 'block/repository:%', 'perm' => CAP_ALLOW);

        if ($rcaps = $DB->get_recordset_select('role_capabilities', $select, $params, 'timemodified ASC', 'id, capability')) {
            mtrace(' --- '.get_string('preup_ec_found', 'elis_core'));

            foreach ($rcaps as $rcap) {
                $rcap->capability = str_replace('block/repository:', 'repository/elis_files:', $rcap->capability);
                $DB->update_record_raw('role_capabilities', $rcap, true);
            }

            $rcaps->close();
            mtrace(' --- '.get_string('preup_ec_success', 'elis_core'));
        }
    } catch (Exception $e) {
        mtrace(' xxx '.get_string('preup_ec_error', 'elis_core'));

        $status = false;
    }
}

mtrace(' ... '.get_string('done', 'elis_core')."!\n");


/*
 * Migrate the old Alfresco repository plugin configuration settings to the new ELIS Files repository plugin.
 */

mtrace(' >>> '.get_string('preup_ac_check', 'elis_core'));

if ($status) {
    try {
    // Find all of the old Alfresco repository plugin capabilities that are set to enabled
        $select = 'name LIKE :name';
        $params = array('name' => 'repository_alfresco%');

        if ($cfgs = $DB->get_recordset_select('config', $select, $params, 'name ASC')) {
            mtrace(' --- '.get_string('preup_ac_found', 'elis_core'));

            foreach ($cfgs as $cfg) {
                // We need to create a new entry in the mdl_plugin_config table and remove the mdl_config values
                $pcfg = new stdClass;
                $pcfg->plugin = 'elis_files';

                // Some variables should not be migrated and need to just be deleted
                if ($cfg->name == 'repository_alfresco_version' || $cfg->name == 'repository_alfresco_cachetime') {
                    continue;
                }

                $pcfg->name  = str_replace('repository_alfresco_', '', $cfg->name);
                $pcfg->value = $cfg->value;

                // ELIS-3677 changing "empty" values as a workaround for limitations in the repository
                // system
                $update_setting = ($pcfg->name == 'user_quota' || $pcfg->name == 'deleteuserdir') &&
                                  $pcfg->value === '0';
                if ($update_setting) {
                    $pcfg->value = '';
                }

                $DB->insert_record_raw('config_plugins', $pcfg, false, true);
            }

            $cfgs->close();

            // Delete the old plugin configuration values
            $DB->delete_records_select('config', $select, $params);
            mtrace(' --- '.get_string('preup_ac_success', 'elis_core'));
        }
    } catch (Exception $e) {
        mtrace(' xxx '.get_string('preup_ac_error', 'elis_core'));

        $status = false;
    }
}

mtrace(' ... '.get_string('done', 'elis_core')."!\n");


/*
 * Remove two ELIS auth plugins that are no longer present if they are enabled (Alfresco SSO and ELIS dummy plugin).
 */

mtrace(' >>> '.get_string('preup_as_check', 'elis_core'));

if ($status) {
    try {
        $auth = $DB->get_field('config', 'value', array('name' => 'auth'));

        $auths = explode(',', $auth);
        $found = false;

        foreach ($auths as $i => $val) {
            switch ($val) {
                case 'alfrescosso':
                case 'elis':
                    // Remove these values if found
                    unset($auths[$i]);
                    $found = true;
                    break;

                default:
                    // do nothing
            }
        }

        if ($found) {
            mtrace(' --- '.get_string('preup_as_found', 'elis_core'));

            $DB->set_field('config', 'value', implode(',', $auths), array('name' => 'auth'));
            mtrace(' --- '.get_string('preup_as_success', 'elis_core'));
        }
    } catch (Excpetion $e) {
        mtrace(' xxx '.get_string('preup_as_error', 'elis_core'));

        $status = false;
    }
}

mtrace(' ... '.get_string('done', 'elis_core')."!\n");
