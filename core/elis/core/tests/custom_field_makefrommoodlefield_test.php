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

// Needed as we will create a user field.
if (!defined('CONTEXT_ELIS_USER')) {
    define('CONTEXT_ELIS_USER', 1005);
}

/**
 * Test the make_from_moodle_field function in field
 * @group elis_core
 */
class custom_field_makefromoodlefield_testcase extends elis_database_test {

    /**
     * Dataprovier for creating/verifying custom fields.
     * @return array Test data.
     */
    public function dataprovider_make_from_moodle_field() {
        $testdata = array();

        $fieldtypes = array(
                // Checkbox.
                array(
                    'datatype' => 'checkbox',
                    'param1' => null,
                    'param2' => null,
                    'param3' => null,
                    'param4' => null,
                    'param5' => null,
                    'edatatype' => 'bool',
                    'elis_manual_owner_params' => array(
                        'control' => 'checkbox'
                    )
                ),
                // Datetime w/out inctime.
                array(
                    'datatype' => 'datetime',
                    'param1' => '1987',
                    'param2' => '2013',
                    'param3' => '0',
                    'param4' => null,
                    'param5' => null,
                    'edatatype' => 'datetime',
                    'elis_manual_owner_params' => array(
                        'control' => 'datetime',
                        'startyear' => '1987',
                        'stopyear' => '2013',
                        'inctime' => '0',
                    )
                ),
                // Datetime with inctime.
                array(
                    'datatype' => 'datetime',
                    'param1' => '1987',
                    'param2' => '2013',
                    'param3' => '1',
                    'param4' => null,
                    'param5' => null,
                    'edatatype' => 'datetime',
                    'elis_manual_owner_params' => array(
                        'control' => 'datetime',
                        'startyear' => '1987',
                        'stopyear' => '2013',
                        'inctime' => '1',
                    )
                ),
                // Menu of choices with defaultdata.
                array(
                    'datatype' => 'menu',
                    'defaultdata' => "Value C",
                    'param1' => "Value A\nValue B\nValue C\nValue D",
                    'param2' => null,
                    'param3' => null,
                    'param4' => null,
                    'param5' => null,
                    'edatatype' => 'char',
                    'elis_manual_owner_params' => array(
                        'control' => 'menu',
                        'options' => "Value A\nValue B\nValue C\nValue D",
                    )
                ),
                // Menu of choices without default data.
                array(
                    'datatype' => 'menu',
                    'param1' => "Value A\nValue B\nValue C\nValue D",
                    'param2' => null,
                    'param3' => null,
                    'param4' => null,
                    'param5' => null,
                    'edatatype' => 'char',
                    'elis_manual_owner_params' => array(
                        'control' => 'menu',
                        'options' => "Value A\nValue B\nValue C\nValue D",
                    )
                ),
                // Textarea with default columns/rows.
                array(
                    'datatype' => 'textarea',
                    'param1' => null,
                    'param2' => null,
                    'param3' => null,
                    'param4' => null,
                    'param5' => null,
                    'edatatype' => 'text',
                    'elis_manual_owner_params' => array(
                        'control' => 'textarea',
                        'columns' => 30,
                        'rows' => 10,
                    )
                ),
                // Textarea with custom columns/rows.
                array(
                    'datatype' => 'textarea',
                    'param1' => 50,
                    'param2' => 20,
                    'param3' => null,
                    'param4' => null,
                    'param5' => null,
                    'edatatype' => 'text',
                    'elis_manual_owner_params' => array(
                        'control' => 'textarea',
                        'columns' => 50,
                        'rows' => 20,
                    )
                ),
                // Text.
                array(
                    'datatype' => 'text',
                    'param1' => 30,
                    'param2' => 2048,
                    'param3' => 0,
                    'param4' => null,
                    'param5' => null,
                    'edatatype' => 'text',
                    'elis_manual_owner_params' => array(
                        'control' => 'text',
                        'columns' => '30',
                        'maxlength' => '2048'
                    )
                ),
                // Password.
                array(
                    'datatype' => 'text',
                    'param1' => 30,
                    'param2' => 2048,
                    'param3' => 1,
                    'param4' => null,
                    'param5' => null,
                    'edatatype' => 'text',
                    'elis_manual_owner_params' => array(
                        'control' => 'password',
                        'columns' => '30',
                        'maxlength' => '2048'
                    )
                ),
                // Text/password with default data.
                array(
                    'datatype' => 'text',
                    'defaultdata' => 'test default data',
                    'param1' => 30,
                    'param2' => 2048,
                    'param3' => 1,
                    'param4' => null,
                    'param5' => null,
                    'edatatype' => 'text',
                    'elis_manual_owner_params' => array(
                        'control' => 'password',
                        'columns' => '30',
                        'maxlength' => '2048'
                    )
                ),
        );

        foreach ($fieldtypes as $type) {
            foreach (array(true, false) as $unique) {
                foreach (array(true, false) as $required) {
                    $type['elis_manual_owner_params']['required'] = $required;
                    $testdata[] = array(
                        array(
                            'shortname' => 'test'.$type['datatype'],
                            'name' => 'Test '.$type['datatype'],
                            'datatype' => $type['datatype'],
                            'description' => '<p>Test '.$type['datatype'].' Description</p>',
                            'descriptionformat' => '1',
                            'categoryid' => '1',
                            'sortorder' => '3',
                            'required' => (string)(int)$required,
                            'locked' => '0',
                            'visible' => '2',
                            'forceunique' => $unique,
                            'signup' => '0',
                            'defaultdata' => (isset($type['defaultdata'])) ? $type['defaultdata'] : '1',
                            'defaultdataformat' => '0',
                            'param1' => $type['param1'],
                            'param2' => $type['param2'],
                            'param3' => $type['param3'],
                            'param4' => $type['param4'],
                            'param5' => $type['param5'],
                        ),
                        array(
                            'shortname' => 'test'.$type['datatype'],
                            'name' => 'Test '.$type['datatype'],
                            'datatype' => $type['edatatype'],
                            'description' => '<p>Test '.$type['datatype'].' Description</p>',
                            'categoryid' => '7',
                            'multivalued' => '0',
                            'forceunique' => (string)(int)$unique,
                            'params' => serialize(array()),
                        ),
                        $type['elis_manual_owner_params']
                    );
                }
            }
        }

        return $testdata;
    }

    /**
     * Test make_from_moodle_field function.
     *
     * @dataProvider dataprovider_make_from_moodle_field
     * @param object $mfielddata Data to create the initial moodle profile with.
     * @param array $expectedfielddata Expected data created for the field.
     * @param array $expectedmanualownerdata Expected field_owner data created.
     */
    public function test_make_from_moodle_field($mfielddata, $expectedfielddata, $expectedmanualownerdata) {
        require_once(elis::file('core/lib/data/customfield.class.php'));
        require_once(elis::file('core/fields/moodle_profile/custom_fields.php'));

        global $DB;
        $mfieldid = $DB->insert_record('user_info_field', $mfielddata);

        $fieldcat = new field_category;
        $fieldcat->name = 'Moodle Fields';
        $fieldcat->save();

        $efield = field::make_from_moodle_field($mfieldid, $fieldcat, pm_moodle_profile::sync_from_moodle);

        $efieldrec = $DB->get_record(field::TABLE, array('shortname' => $efield->shortname));
        unset($efieldrec->id);
        unset($efieldrec->sortorder);
        $expectedfielddata['categoryid'] = (string)$fieldcat->id;
        $this->assertEquals($expectedfielddata, (array)$efieldrec);

        $manualowner = $DB->get_record(field_owner::TABLE, array('fieldid' => $efield->id, 'plugin' => 'manual'));
        ksort($expectedmanualownerdata);
        $actualparams = unserialize($manualowner->params);
        ksort($actualparams);
        $this->assertEquals($expectedmanualownerdata, $actualparams);

        $this->assertTrue(moodle_profile_can_sync($efield->shortname));

        if ($mfielddata['defaultdata'] != '') {
            switch($efield->datatype) {
                case 'bool':
                    $fielddatatype = 'int';
                    break;
                case 'datetime':
                    $fielddatatype = 'int';
                    break;
                case 'char':
                    $fielddatatype = 'char';
                    break;
                default:
                    $fielddatatype = 'text';
            }
            $fielddataclass = 'field_data_'.$fielddatatype;

            $defaultdata = $DB->get_record($fielddataclass::TABLE, array('fieldid' => $efield->id));
            $this->assertNotEmpty($defaultdata);
            $this->assertEquals($mfielddata['defaultdata'], $defaultdata->data);
        }
    }
}