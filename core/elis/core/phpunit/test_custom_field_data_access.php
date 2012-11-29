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

require_once(dirname(__FILE__).'/../test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/core/lib/setup.php');
require_once($CFG->dirroot.'/elis/core/accesslib.php');
require_once(elis::lib('testlib.php'));
require_once(elis::lib('data/customfield.class.php'));

//NOTE: needed because this is used in customfield.class.php :-(
//(not actually setting anything on the PM user context)
if (!defined('CONTEXT_ELIS_USER')) {
    define('CONTEXT_ELIS_USER',    1005);
}

/**
 * Class for testing the storage and retrieval of custom field data
 */
class customFieldDataAccessTest extends elis_database_test {
    //our sample context level
    const contextlevel = 9999;
    //id of our bogus context record
    const contextid = 99999;

    /**
     * Return the list of tables that should be overlayed.
     */
    static protected function get_overlay_tables() {
        return array(
            field::TABLE                       => 'elis_core',
            field_category::TABLE              => 'elis_core',
            field_category_contextlevel::TABLE => 'elis_core',
            field_contextlevel::TABLE          => 'elis_core',
            field_data_char::TABLE             => 'elis_core',
            field_owner::TABLE                 => 'elis_core'
        );
    }

    /**
     * Initialize our custom field data, and persist in the database
     *
     * @param  mixed  $cl optional contextlevel to use,
                          default null for self::contextlevel
     * @return object The custom field created
     */
    protected function init_custom_field($cl = null) {
        //set up our custom field
        $field = new field(array(
            'name'        => 'testcustomfieldname',
            'datatype'    => 'char',
            'multivalued' => 1
        ));
        $field_category = new field_category(array('name' => 'testcategoryname'));
        $field = field::ensure_field_exists_for_context_level($field,
                          $cl ? $cl : self::contextlevel, $field_category);

       /*
        //set up the default data
        $default_params = array(
            'fieldid'   => $field->id,
            'contextid' => NULL,
            'data'      => 'value1'
        );
        $default_data = new field_data_char($default_params);
        $default_data->save();
       */
        field_data::set_for_context_and_field(NULL, $field, array('value1'));

        return $field;
    }

    /**
     * Set custom field data for the test context instance
     *
     * @param object $field The custom field we are setting data for
     * @param array $data The data to set for the field
     */
    protected function set_custom_field_data($field, $data) {
        //run set_for_context_and_field, to set data for the particular context
        $context = new stdClass;
        $context->id = self::contextid;
        field_data::set_for_context_and_field($context, $field, $data);
    }

    /**
     * Validate that the field_data "set_for_context_and_field" method explicitly
     * sets all customfield data even if contains the field's default value
     */
    public function testSetForContextAndFieldAddsSelectedDefaultForMultivalueField() {
        global $DB;

        //set up our custom field
        $field = $this->init_custom_field();

        //run set_for_context_and_field, to set data for the particular context
        $data = array(
            'value1',
            'value2',
            'value3'
        );
        $this->set_custom_field_data($field, $data);

        //validate number of data records (default plus three for specific context)
        $count = $DB->count_records(field_data_char::TABLE);
        $this->assertEquals(4, $count);

        //validate that the default data is still correct
        $default_params = array(
            'fieldid'   => $field->id,
            'contextid' => NULL,
            'data'      => 'value1'
        );
        $exists = $DB->record_exists(field_data_char::TABLE, $default_params);
        $this->assertTrue($exists);

        //validate that all three values are correctly set for the appropriate context
        foreach ($data as $datum) {
            $params = array(
                'fieldid'   => $field->id,
                'contextid' => self::contextid,
                'data'      => $datum
            );
            $exists = $DB->record_exists(field_data_char::TABLE, $params);
            $this->assertTrue($exists);
        }
    }

    /**
     * Validate that "set_for_context_and_field" does not delete the default
     * value when no other values are set
     */
    public function testSetForContextAndFieldDoesNotDeleteDefaultValue() {
        global $DB;

        //set up our custom field
        $field = $this->init_custom_field();

        //run set_for_context_and_field with an empty dataset
        $this->set_custom_field_data($field, array());

        //validate number of data records (just one for the default)
        $count = $DB->count_records(field_data_char::TABLE);
        $this->assertEquals(1, $count);

        //validate that the default data is still correct
        $default_params = array(
            'fieldid'   => $field->id,
            'contextid' => NULL,
            'data'      => 'value1'
        );
        $exists = $DB->record_exists(field_data_char::TABLE, $default_params);
        $this->assertTrue($exists);
    }

    /**
     * Validate that, by default, the field_data "get_for_context_and_field"
     * method includes a custom field's default data when no data exists
     * for the appropriate context
     */
    public function testGetForContextAndFieldIncludesDefaultValueWhenNoDataExists() {
        global $DB;

        //set up our custom field data
        $field = $this->init_custom_field();

        //run get_for_context_and_field to obtain our data set
        $context = new stdClass;
        $context->id = self::contextid;
        $data = field_data::get_for_context_and_field($context, $field);

        //validate number of data records (one for specific context)
        $count = 0;
        $record = NULL;
        foreach ($data as $datum) {
            $count++;
            $record = $datum;
        }
        $this->assertEquals(1, $count);

        $this->assertEquals($field->id, $record->fieldid);
        $this->assertEquals('value1', $record->data);
    }

    /**
     * Validate that, when needed, the field_data "get_for_context_and_field"
     * method excludes a custom field's default data when no data exists
     * for the appropriate context
     */
    public function testGetForContextAndFieldExcludesDefaultValueWhenNoDataExists() {
        //set up our custom field data
        $field = $this->init_custom_field();

        //run get_for_context_and_field to obtain our data set
        $context = new stdClass;
        $context->id = self::contextid;
        $data = field_data::get_for_context_and_field($context, $field, false);

        //validate that no data is returned
        $this->assertFalse($data->valid());
    }

    /**
     * Data provider that provides parameter values for the $include_default
     * parameter of 'get_for_context_and_field'
     *
     * @return array The appropriate parameter data
     */
    public function includeDefaultProvider() {
        return array(
            array(false),
            array(true)
        );
    }

    /**
     * Validate that the field_data "get_for_context_and_field" method always
     * excludes a custom field's default data when some data exists for the
     * appropriate context, regardless of the related parameter value
     *
     * @param boolean $include_default
     * @dataProvider includeDefaultProvider
     */
    public function testGetForContextAndFieldExcludesDefaultValueWhenDataExists($include_default) {
        //set up our custom field data
        $field = $this->init_custom_field();

        //set up our data point
        $field_data_char = new field_data_char(array(
            'fieldid'   => $field->id,
            'contextid' => self::contextid,
            'data'      => 'value3'
        ));
        $field_data_char->save();

        //run get_for_context_and_field to obtain our data set
        $context = new stdClass;
        $context->id = self::contextid;
        $data = field_data::get_for_context_and_field($context, $field, $include_default);

        //validate number of data records (one for specific context)
        $count = 0;
        $record = NULL;
        foreach ($data as $datum) {
            $count++;
            $record = $datum;
        }
        $this->assertEquals(1, $count);

        $this->assertEquals($field->id, $record->fieldid);
        $this->assertEquals('value3', $record->data);
    }

    /**
     * Validate fix for ELIS-7545
     * ensure_field_exists_for_context_level() correctly supports context names
     *
     */
    public function testELIS7545() {
        $field = $this->init_custom_field('user');
        $this->assertTrue(!empty($field));
        $field->delete();
    }
}
