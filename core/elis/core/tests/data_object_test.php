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
 * Config object class for Moodle database records.  We only model the minimum
 * needed for the tests in this file.
 */
class config_object extends elis_data_object {
    const TABLE = 'config';

    protected $_dbfield_name;
    protected $_dbfield_value;

    public static $validation_rules = array(
            array('validation_helper', 'not_empty_name'),
            array('validation_helper', 'is_unique_name'),
    );
}

/**
 * User object class for Moodle database records.  We only model the minimum
 * needed for the tests in this file.
 */
class user_object extends elis_data_object {
    const TABLE = 'user';

    public static $associations = array(
        'role_assignments' => array(
            'class' => 'role_assignment_object',
            'foreignidfield' => 'userid',
        ),
    );
}

/**
 * Role assignment object class for Moodle database records.  We only model the minimum
 * needed for the tests in this file.
 */
class role_assignment_object extends elis_data_object {
    const TABLE = 'role_assignments';

    public static $associations = array(
        'user' => array(
            'class' => 'user_object',
            'idfield' => 'userid',
        ),
    );

    protected $_dbfield_userid;
    protected $_dbfield_roleid;
    protected $_dbfield_contextid;
}

/**
 * Class for testing data objects.
 * @group elis_core
 */
class data_object_testcase extends elis_database_test {
    /**
     * Data provider for base constructor.
     * @return array Data objects
     */
    public function base_constructor_provider() {
        $obj = new stdClass;
        $obj->id = 10001;

        return array(
                array($obj, 10001), // Initialize from an object.
                array(array('id' => 10002), 10002), // Initialize from an array.
        );
    }

    /**
     * Test the constructor by initializing it and checking that the id field is set correctly.
     *
     * @dataProvider base_constructor_provider
     */
    public function test_can_initialize_base_class_from_array_and_object($init, $expected) {
        $dataobj = new elis_data_object($init);
        $this->assertEquals($dataobj->id, $expected);
    }

    /**
     * Data provider for derived constructor.
     * @return array Data objects
     */
    public function derived_constructor_provider() {
        $obj = new stdClass;
        $obj->id = 10001;
        $obj->name = 'foo';

        return array(
                array($obj, 10001, 'foo'), // Initialize from an object.
                array(array('id' => 10002, 'name' => 'bar'), 10002, 'bar'), // Initialize from an array.
        );
    }

    /**
     * Test the derived class constructor.
     *
     * @dataProvider derived_constructor_provider
     */
    public function test_can_initialize_derived_class_from_array_and_object($init, $expectedid, $expectedname) {
        $dataobj = new config_object($init);
        $this->assertEquals($dataobj->id, $expectedid);
        $this->assertEquals($dataobj->name, $expectedname);
    }

    /**
     * Test the isset and unset magic methods.
     */
    public function test_can_test_and_unset_fields() {
        $dataobj = new elis_data_object(array('id' => 10002));
        $this->assertFalse(isset($dataobj->notafield));
        $this->assertTrue(isset($dataobj->id));
        unset($dataobj->id);
        $this->assertFalse(isset($dataobj->id));
    }

    /**
     * Test the get and set magic methods.
     */
    public function test_can_get_and_set_fields() {
        $dataobj = new elis_data_object();
        $this->assertEquals($dataobj->id, null);
        $dataobj->id = 10003;
        $this->assertEquals($dataobj->id, 10003);
    }

    /**
     * Test the find method.
     * @uses $DB
     */
    public function test_can_find_records() {
        global $DB;
        require_once(elis::lib('data/data_filter.class.php'));

        $baseconfigs = config_object::find(null, array('name' => 'ASC'), 0, 0, $DB);
        $basecount = count($baseconfigs->to_array());

        $dataset = $this->createCsvDataSet(array(
            'config' => elis::component_file('core', 'tests/fixtures/phpunit_data_object_test.csv')
        ));

        $this->loadDataSet($dataset);

        $configs = config_object::find(new field_filter('name', 'foo'), array(), 0, 0, $DB);
        // Should only find one record, with value foooo.
        $this->assertEquals($configs->current()->value, 'foooo');
        $configs->next();
        $this->assertFalse($configs->valid());

        $configs = config_object::find(null, array('id' => 'DESC'), 0, 0, $DB);
        // Should find all three records at the beginning of reverse sorted results.
        $configs = $configs->to_array();
        $this->assertEquals(count($configs), $basecount + 3);
        $config = current($configs);
        $this->assertEquals($config->name, 'baz');
        $config = next($configs);
        $this->assertEquals($config->name, 'bar');
        $config = next($configs);
        $this->assertEquals($config->name, 'foo');
    }

    /**
     * Test the count method.
     * @uses $DB
     */
    public function test_can_count_records() {
        global $DB;
        require_once(elis::lib('data/data_filter.class.php'));

        $baseconfigs = config_object::find(null, array('name' => 'ASC'), 0, 0, $DB);
        $basecount = count($baseconfigs->to_array());

        $dataset = $this->createCsvDataSet(array(
            'config' => elis::component_file('core', 'tests/fixtures/phpunit_data_object_test.csv')
        ));

        $this->loadDataSet($dataset);

        $configs = config_object::find(new field_filter('name', 'foo'), array(), 0, 0, $DB);

        // Should only find one record.
        $this->assertEquals(config_object::count(new field_filter('name', 'foo'), $DB), 1);
        // Should find all records plus the three new records.
        $this->assertEquals(config_object::count(null, $DB), $basecount + 3);
    }

    /**
     * Test the delete method.
     * @uses $DB
     */
    public function test_can_delete_records() {
        global $DB;
        require_once(elis::lib('data/data_filter.class.php'));
        $dataset = $this->createCsvDataSet(array(
            'config' => elis::component_file('core', 'tests/fixtures/phpunit_data_object_test.csv')
        ));

        $this->loadDataSet($dataset);

        config_object::delete_records(new field_filter('name', 'foo'), $DB);

        $result = new moodle_recordset_phpunit_datatable('config', config_object::find(new field_filter('id', 10000, field_filter::GE), array(), 0, 0, $DB));
        $dataset = $this->createCsvDataSet(array(
            'config' => elis::component_file('core', 'tests/fixtures/phpunit_data_object_delete_test_result.csv')
        ));
        $this->assertTablesEqual($dataset->getTable('config'), $result);
    }

    /**
     * Test the exists method.
     * @uses $DB
     */
    public function test_can_check_records_exist() {
        global $DB;
        require_once(elis::lib('data/data_filter.class.php'));
        $dataset = $this->createCsvDataSet(array(
            'config' => elis::component_file('core', 'tests/fixtures/phpunit_data_object_test.csv')
        ));

        $this->loadDataSet($dataset);

        $this->assertTrue(config_object::exists(new field_filter('name', 'foo'), $DB));
        $this->assertFalse(config_object::exists(new field_filter('name', 'fooo'), $DB));
    }

    /**
     * Test loading from the database by record ID.
     * @uses $DB
     */
    public function test_can_load_records_by_id() {
        global $DB;
        $dataset = $this->createCsvDataSet(array(
            'config' => elis::component_file('core', 'tests/fixtures/phpunit_data_object_test.csv')
        ));

        $this->loadDataSet($dataset);

        $config = new config_object(10001, null, array(), false, array(), $DB);
        $this->assertEquals($config->name, 'foo');
        $this->assertEquals($config->value, 'foooo');
    }

    /**
     * Test the save method.
     * @uses $DB
     */
    public function test_can_save_records() {
        global $DB;

        $config = new config_object(false, null, array(), false, array(), $DB);
        $config->name = 'foo';
        $config->value = 'foovalue';
        $config->save();

        $result = new moodle_recordset_phpunit_datatable('config', config_object::find(new field_filter('id', $config->id), array(), 0, 0, $DB));
        $expected = array(
                array(
                    'name' => 'foo',
                    'value' => 'foovalue',
                    'id' => $config->id
                )
        );
        $expected = new moodle_recordset_phpunit_datatable('config', $expected);
        $this->assertTablesEqual($expected, $result);

        // Modify an existing record.
        $config->value = 'newfoovalue';
        $config->save();

        $result = new moodle_recordset_phpunit_datatable('config', config_object::find(new field_filter('id', $config->id), array(), 0, 0, $DB));
        $expected = array(
                array(
                    'name' => 'foo',
                    'value' => 'newfoovalue',
                    'id' => $config->id
                )
        );
        $expected = new moodle_recordset_phpunit_datatable('config', $expected);
        $this->assertTablesEqual($expected, $result);
    }

    /**
     * Test the single record delete method.
     * @uses $DB
     */
    public function test_can_delete_a_single_record() {
        global $DB;
        require_once(elis::lib('data/data_filter.class.php'));
        $dataset = $this->createCsvDataSet(array(
            'config' => elis::component_file('core', 'tests/fixtures/phpunit_data_object_test.csv')
        ));

        $this->loadDataSet($dataset);

        $config = config_object::find(new field_filter('name', 'foo'), array(), 0, 0, $DB);
        $config = $config->current();
        $config->delete();

        $result = new moodle_recordset_phpunit_datatable('config', config_object::find(new field_filter('id', 10000, field_filter::GE), array(), 0, 0, $DB));
        $dataset = $this->createCsvDataSet(array(
            'config' => elis::component_file('core', 'tests/fixtures/phpunit_data_object_delete_test_result.csv')
        ));
        $this->assertTablesEqual($dataset->getTable('config'), $result);
    }

    /**
     * Test the magic methods for getting associated records.
     * @uses $DB
     * @uses $USER
     */
    public function test_get_associated_records() {
        global $DB, $USER;

        // Get some random user.
        $user = $DB->get_record('user', array(), '*', IGNORE_MULTIPLE);
        // Get some random role.
        $role = $DB->get_record('role', array(), '*', IGNORE_MULTIPLE);
        // Add a role assignment.
        $syscontext = get_context_instance(CONTEXT_SYSTEM);
        // Create a new role assignment.
        $ra = new stdClass;
        $ra->userid = $user->id;
        $ra->roleid = $role->id;
        $ra->contextid = $syscontext->id;
        $ra->timemodified = time();
        $ra->modifierid = $USER->id;
        $ra->id = $DB->insert_record('role_assignments', $ra);

        // Count the role assignments for the user.
        $user = new user_object($user, null, array(), true, array(), $DB);
        $this->assertEquals($user->count_role_assignments(), 1);

        // Verify that we can get the role assignment via the magic get method.
        $roleassignments = $user->role_assignments->to_array();
        $this->assertEquals(count($roleassignments), 1);
        $ra = current($roleassignments);
        $this->assertEquals($ra->userid, $user->id);
        $this->assertEquals($ra->roleid, $role->id);
        $this->assertEquals($ra->contextid, $syscontext->id);

        // Verify that we can get the role assignment via the magic call method.
        $roleassignments = $user->get_role_assignments()->to_array();
        $this->assertEquals(count($roleassignments), 1);
        $ra = current($roleassignments);
        $this->assertEquals($ra->userid, $user->id);
        $this->assertEquals($ra->roleid, $role->id);
        $this->assertEquals($ra->contextid, $syscontext->id);

        // Test the filtered get and count methods.
        $roleassignments = $user->get_role_assignments(new field_filter('userid', $user->id, field_filter::NEQ))->to_array();
        $this->assertEquals(count($roleassignments), 0);

        $this->assertEquals($roleassignments = $user->count_role_assignments(new field_filter('userid', $user->id, field_filter::NEQ)), 0);

        $this->assertEquals($ra->user->id, $user->id);
    }

    /**
     * Test validation of duplicates.
     *
     * @expectedException data_object_validation_exception
     * @uses $DB
     */
    public function test_validation_prevents_duplicates() {
        global $DB;

        $dataset = $this->createCsvDataSet(array(
            'config' => elis::component_file('core', 'tests/fixtures/phpunit_data_object_test.csv')
        ));

        $this->loadDataSet($dataset);

        $config = new config_object(false, null, array(), false, array(), $DB);
        $config->name = 'foo';
        $config->value = 'foovalue';
        $config->save();
    }

    /**
     * Test validation of required fields.
     *
     * @expectedException data_object_validation_exception
     * @uses $DB
     */
    public function test_validation_prevents_empty_values() {
        global $DB;

        $config = new config_object(false, null, array(), false, array(), $DB);
        $config->save();
    }
}
