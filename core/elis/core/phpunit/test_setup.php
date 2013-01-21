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

require_once(dirname(__FILE__) . '/../test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/core/lib/setup.php');
require_once(elis::lib('data/data_object.class.php'));
require_once(elis::lib('testlib.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');

class setupTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

    protected static function get_overlay_tables() {
        return array(
            'config_plugins' => 'moodle',
        );
    }

    public function test_elis_config() {

        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable('config_plugins', elis::component_file('core', 'phpunit/config_plugins.csv'));
        load_phpunit_data_set($dataset, true, self::$overlaydb);

        $elis_config = new elis_config;

        $plugin_config = $elis_config->testplugin;

        $this->assertNotEmpty($plugin_config);
        $this->assertInternalType('object',$plugin_config);
        $this->assertObjectHasAttribute('testconfigkey',$plugin_config);
        $this->assertEquals('testconfigvalue',$plugin_config->testconfigkey);
    }
}