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

require_once(dirname(__FILE__) .'/../../../test_config.php');
global $CFG;
require_once($CFG->dirroot .'/elis/core/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elis::plugin_file('eliscoreplugins_user_activity', 'db/upgrade.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
require_once($CFG->libdir .'/upgradelib.php');

class etl_test extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(
            'config' => 'moodle',
            'config_plugins' => 'moodle',
            'etl_user_activity' => 'eliscoreplugins_user_activity',
            'etl_user_module_activity' => 'eliscoreplugins_user_activity'
        );
    }

    /**
     * Test the user_activity upgrade
     */
    public function testETLupgrade() {
        global $DB;
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('etl_user_activity', elis::plugin_file('eliscoreplugins_user_activity', 'phpunit/etl_user_activity.csv'));
        $dataset->addTable('etl_user_module_activity', elis::plugin_file('eliscoreplugins_user_activity', 'phpunit/etl_user_module_activity.csv'));

        $overlaydb = self::$overlaydb;
        load_phpunit_data_set($dataset, true, $overlaydb);

        xmldb_eliscoreplugins_user_activity_upgrade(2011101800);

        $this->assertFalse($DB->record_exists_select('etl_user_activity', 'duration > 3600'));
        $this->assertFalse($DB->record_exists_select('etl_user_module_activity', 'duration > 3600'));
     }
}
