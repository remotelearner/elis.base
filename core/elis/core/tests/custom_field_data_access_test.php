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
require_once($CFG->dirroot.'/elis/core/accesslib.php');
require_once(elis::lib('data/customfield.class.php'));

// NOTE: needed because this is used in customfield.class.php :-(
// Not actually setting anything on the PM user context.
if (!defined('CONTEXT_ELIS_USER')) {
    define('CONTEXT_ELIS_USER', 1005);
}

/**
 * Class for testing the storage and retrieval of custom field data.
 * @group elis_core
 */
class custom_field_data_access_testcase extends elis_database_test {
    // Our sample context level.
    const CONTEXTLEVEL = 9999;
    // Id of our bogus context record.
    const CONTEXTID = 99999;

    /**
     * Initialize our custom field data, and persist in the database.
     *
     * @param  mixed  $cl optional contextlevel to use, default null for self::contextlevel
     * @return object The custom field created
     */
    protected function init_custom_field($cl = null) {
        // Set up our custom field.
        $field = new field(array(
            'name' => 'testcustomfieldname',
            'datatype' => 'char',
            'multivalued' => 1
        ));
        $fieldcategory = new field_category(array('name' => 'testcategoryname'));
        $field = field::ensure_field_exists_for_context_level($field, $cl ? $cl : self::CONTEXTLEVEL, $fieldcategory);

        field_data::set_for_context_and_field(null, $field, array('value1'));

        return $field;
    }

    /**
     * Set custom field data for the test context instance.
     *
     * @param object $field The custom field we are setting data for
     * @param array $data The data to set for the field
     */
    protected function set_custom_field_data($field, $data) {
        // Run set_for_context_and_field, to set data for the particular context.
        $context = new stdClass;
        $context->id = self::CONTEXTID;
        field_data::set_for_context_and_field($context, $field, $data);
    }

    /**
     * Validate that the field_data "set_for_context_and_field" method explicitly sets all customfield
     * data even if contains the field's default value.
     * @uses $DB
     */
    public function test_set_for_context_and_field_adds_selected_default_for_multi_value_field() {
        global $DB;

        // Set up our custom field.
        $field = $this->init_custom_field();

        // Run set_for_context_and_field, to set data for the particular context.
        $data = array(
                'value1',
                'value2',
                'value3'
        );
        $this->set_custom_field_data($field, $data);

        // Validate number of data records (default plus three for specific context).
        $count = $DB->count_records(field_data_char::TABLE);
        $this->assertEquals(4, $count);

        // Validate that the default data is still correct.
        $defaultparams = array(
            'fieldid' => $field->id,
            'contextid' => null,
            'data' => 'value1'
        );
        $exists = $DB->record_exists(field_data_char::TABLE, $defaultparams);
        $this->assertTrue($exists);

        // Validate that all three values are correctly set for the appropriate context.
        foreach ($data as $datum) {
            $params = array(
                'fieldid' => $field->id,
                'contextid' => self::CONTEXTID,
                'data' => $datum
            );
            $exists = $DB->record_exists(field_data_char::TABLE, $params);
            $this->assertTrue($exists);
        }
    }

    /**
     * Validate that "set_for_context_and_field" does not delete the default value when no other values are set.
     * @uses $DB
     */
    public function test_set_for_context_and_field_does_not_delete_default_value() {
        global $DB;

        // Set up our custom field.
        $field = $this->init_custom_field();

        // Run set_for_context_and_field with an empty dataset.
        $this->set_custom_field_data($field, array());

        // Validate number of data records (just one for the default).
        $count = $DB->count_records(field_data_char::TABLE);
        $this->assertEquals(1, $count);

        // Validate that the default data is still correct.
        $defaultparams = array(
            'fieldid' => $field->id,
            'contextid' => null,
            'data' => 'value1'
        );
        $exists = $DB->record_exists(field_data_char::TABLE, $defaultparams);
        $this->assertTrue($exists);
    }

    /**
     * Validate that, by default, the field_data "get_for_context_and_field" method includes a 
     * custom field's default data when no data exists for the appropriate context.
     * @uses $DB
     */
    public function test_get_for_context_and_field_includes_default_value_when_no_data_exists() {
        global $DB;

        // Set up our custom field data.
        $field = $this->init_custom_field();

        // Run get_for_context_and_field to obtain our data set.
        $context = new stdClass;
        $context->id = self::CONTEXTID;
        $data = field_data::get_for_context_and_field($context, $field);

        // Validate number of data records (one for specific context).
        $count = 0;
        $record = null;
        foreach ($data as $datum) {
            $count++;
            $record = $datum;
        }
        $this->assertEquals(1, $count);

        $this->assertEquals($field->id, $record->fieldid);
        $this->assertEquals('value1', $record->data);
    }

    /**
     * Validate that, when needed, the field_data "get_for_context_and_field" method excludes a custom field's
     * default data when no data exists for the appropriate context
     */
    public function test_get_for_context_and_field_excludes_default_value_when_no_data_exists() {
        // Set up our custom field data.
        $field = $this->init_custom_field();

        // Run get_for_context_and_field to obtain our data set.
        $context = new stdClass;
        $context->id = self::CONTEXTID;
        $data = field_data::get_for_context_and_field($context, $field, false);

        // Validate that no data is returned.
        $this->assertFalse($data->valid());
    }

    /**
     * Data provider that provides parameter values for the $include_default parameter of 'get_for_context_and_field'.
     *
     * @return array The appropriate parameter data
     */
    public function include_default_provider() {
        return array(
                array(false),
                array(true)
        );
    }

    /**
     * Validate that the field_data "get_for_context_and_field" method always excludes a custom field's default 
     * data when some data exists for the  appropriate context, regardless of the related parameter value.
     *
     * @param boolean $includedefault
     * @dataProvider include_default_provider
     */
    public function test_get_for_context_and_field_excludes_default_value_when_data_exists($includedefault) {
        // Set up our custom field data.
        $field = $this->init_custom_field();

        // Set up our data point.
        $fielddatachar = new field_data_char(array(
            'fieldid'   => $field->id,
            'contextid' => self::CONTEXTID,
            'data'      => 'value3'
        ));
        $fielddatachar->save();

        // Run get_for_context_and_field to obtain our data set.
        $context = new stdClass;
        $context->id = self::CONTEXTID;
        $data = field_data::get_for_context_and_field($context, $field, $includedefault);

        // Validate number of data records (one for specific context).
        $count = 0;
        $record = null;
        foreach ($data as $datum) {
            $count++;
            $record = $datum;
        }

        $this->assertEquals(1, $count);
        $this->assertEquals($field->id, $record->fieldid);
        $this->assertEquals('value3', $record->data);
    }

    /**
     * Validate fix for ELIS-7545 ensure_field_exists_for_context_level() correctly supports context names.
     */
    public function test_elis7545() {
        $field = $this->init_custom_field('user');
        $this->assertTrue(!empty($field));
        $field->delete();
    }

    /**
     * Validate new library method fix_moodle_profile_fields()
     */
    public function test_fix_moodle_profile_fields() {
        global $CFG;
        require_once($CFG->dirroot.'/user/profile/lib.php');
        require_once($CFG->dirroot.'/user/profile/definelib.php');
        require_once($CFG->dirroot.'/user/profile/field/menu/define.class.php');
        require_once($CFG->dirroot.'/user/profile/field/menu/field.class.php');
        require_once($CFG->dirroot.'/user/profile/field/checkbox/define.class.php');
        require_once($CFG->dirroot.'/user/profile/field/checkbox/field.class.php');
        require_once elis::lib('lib.php');
        require_once elis::lib('testlib.php');

        // Create a couple Moodle profile fields, one menu-of-choices
        $profiledefinemenu = new profile_define_menu();
        $data = new stdClass;
        $data->datatype = 'menu';
        $data->categoryid = 99999;
        $data->shortname = 'testfieldmenu';
        $data->name = 'testfieldmenu';
        $data->param1 = 'Option1
Option2';
        $data->defaultdata = 'Option2';
        $profiledefinemenu->define_save($data);

        $profiledefinecheckbox = new profile_define_checkbox();
        $data = new stdClass;
        $data->datatype = 'checkbox';
        $data->categoryid = 99999;
        $data->shortname = 'testfieldcheckbox';
        $data->name = 'testfieldcheckbox';
        $profiledefinecheckbox->define_save($data);

        $testuser = get_test_user();
        $testuser->profile_field_testfieldmenu = 'Option3'; // illegal value
        $testuser->profile_field_testfieldcheckbox = 0;

        fix_moodle_profile_fields($testuser);
        $this->assertTrue(!$testuser->profile_field_testfieldcheckbox);
        $this->assertTrue(!isset($testuser->profile_field_testfieldmenu));

        $testuser->profile_field_testfieldmenu = 'Option1'; // legal value
        $testuser->profile_field_testfieldcheckbox = 1;

        fix_moodle_profile_fields($testuser);
        $this->assertTrue($testuser->profile_field_testfieldcheckbox == 1);
        $this->assertTrue($testuser->profile_field_testfieldmenu == 'Option1');
    }
}
