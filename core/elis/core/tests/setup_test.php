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

require_once(dirname(__FILE__).'/../test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/core/lib/setup.php');
require_once(elis::lib('data/data_object.class.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');

/**
 * Class to test config setup.
 * @group elis_core
 */
class setup_testcase extends elis_database_test {
    /**
     * Validate ELIS config settings.
     */
    public function test_elis_config() {
        $dataset = $this->createCsvDataSet(array(
            'config_plugins' => elis::component_file('core', 'tests/fixtures/config_plugins.csv')
        ));
        $this->loadDataSet($dataset);

        $elisconfig = new elis_config;
        $pluginconfig = $elisconfig->testplugin;

        $this->assertNotEmpty($pluginconfig);
        $this->assertInternalType('object', $pluginconfig);
        $this->assertObjectHasAttribute('testconfigkey', $pluginconfig);
        $this->assertEquals('testconfigvalue', $pluginconfig->testconfigkey);
    }
}
