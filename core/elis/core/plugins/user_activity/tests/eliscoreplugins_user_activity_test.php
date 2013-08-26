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
 * @package    eliscoreplugins_user_activity
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../../test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/core/lib/setup.php');
require_once($CFG->libdir.'/upgradelib.php');
require_once(elis::plugin_file('eliscoreplugins_user_activity', 'db/upgrade.php'));

/**
 * Testing the user_activity plugin.
 *
 * @group elis_core
 * @group eliscoreplugins_user_activity
 */
class eliscoreplugins_user_activity_testcase extends elis_database_test {
    /**
     * Test the user_activity upgrade.
     */
    public function test_etl_upgrade() {
        global $DB;

        $datatables = array(
            'etl_user_activity' => elis::plugin_file('eliscoreplugins_user_activity', 'tests/fixtures/etl_user_activity.csv'),
            'etl_user_module_activity' => elis::plugin_file('eliscoreplugins_user_activity', 'tests/fixtures/etl_user_module_activity.csv')
        );

        $dataset = $this->createCsvDataSet($datatables);
        $this->loadDataSet($dataset);

        // Ensure we can run our upgrade steps.
        $DB->set_field('config_plugins', 'value', 2011101700, array('plugin' => 'eliscoreplugins_user_activity'));

        xmldb_eliscoreplugins_user_activity_upgrade(2011101800);

        $this->assertFalse($DB->record_exists_select('etl_user_activity', 'duration > 3600'));
        $this->assertFalse($DB->record_exists_select('etl_user_module_activity', 'duration > 3600'));
    }
}
