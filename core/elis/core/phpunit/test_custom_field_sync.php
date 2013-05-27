<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
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
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/core/lib/setup.php');
require_once($CFG->dirroot.'/elis/core/accesslib.php');
require_once(elis::lib('testlib.php'));
require_once(elis::lib('data/customfield.class.php'));
require_once(elis::plugin_file('elisfields_moodle_profile','custom_fields.php'));

//NOTE: needed because this is used in customfield.class.php :-(
if (!defined('CONTEXT_ELIS_USER')) {
    define('CONTEXT_ELIS_USER', 1005);
}

/**
 * Class for testing the syncing of custom field settings
 */
class custom_field_sync_test extends elis_database_test {

    /**
     * Return the list of tables that should be overlayed.
     * @return array overlay tables
     */
    static protected function get_overlay_tables() {
        return array(
            'user_info_field'                  => 'moodle',
            'user_info_data'                   => 'moodle',
            field::TABLE                       => 'elis_core',
            field_contextlevel::TABLE          => 'elis_core',
            field_owner::TABLE                 => 'elis_core',
            field_data_char::TABLE             => 'elis_core',
            field_data_int::TABLE              => 'elis_core',
            field_data_num::TABLE              => 'elis_core',
            field_data_text::TABLE             => 'elis_core',
            field_category::TABLE              => 'elis_core',
            field_category_contextlevel::TABLE => 'elis_core'
        );
    }

    /**
     * Method to create ELIS field & owner objects given test data array
     *
     * @param array $inputarray  the test data array with params to build elis field object & owner
     *        input array format:
     *        array('field' => array(fieldparam => fieldparam_value [,...]),
     *              'context' => contextlevel,
     *              'manual' => array(fieldowners_manual_param => fomp_value [,...]),
     *              'moodle_profile' => array(fieldowners_moodleprofile_param => fompp_value [,...]),
     *        )
     * @return object  the ELIS field object created
     */
    public function build_elis_field_data($inputarray) {
        $field = new field((object)$inputarray['field']);
        $field->save();
        $fieldcontext = new field_contextlevel();
        $fieldcontext->fieldid = $field->id;
        $fieldcontext->contextlevel = $inputarray['context'];
        $fieldcontext->save();
        if (isset($inputarray['manual'])) {
            $manual = new field_owner();
            $manual->fieldid = $field->id;
            $manual->plugin = 'manual';
            foreach ($inputarray['manual'] as $key => $val) {
                $manual->{'param_'.$key} = $val;
            }
            $manual->save();
        }
        if (isset($inputarray['moodle_profile'])) {
            $moodleprofile = new field_owner();
            $moodleprofile->fieldid = $field->id;
            $moodleprofile->plugin = 'moodle_profile';
            foreach ($inputarray['moodle_profile'] as $key => $val) {
                $moodleprofile->{$key} = $val;
            }
            $moodleprofile->save();
        }
        $field->load(); // TBD
        return $field;
    }

    /**
     * Method to assert ELIS field & owner objects data matches values passed
     *
     * @param object $field             the field object to validate against
     * @param array  $elisfieldexpected array of elis field params & expected values
     *        input array format:
     *        array('field' => array(fieldparam => fieldparam_value [,...]),
     *              'context' => contextlevel,
     *              'manual' => array(fieldowners_manual_param => fomp_value [,...]),
     *              'moodle_profile' => array(fieldowners_moodleprofile_param => fompp_value [,...]),
     *        )
     */
    public function assert_elis_field_data($field, $elisfieldexpected) {
        if (!empty($elisfieldexpected)) {
            $field = new field($field->id); // reload field data
            if (!empty($elisfieldexpected['field'])) {
                foreach ($elisfieldexpected['field'] as $key => $val) {
                    $this->assertEquals($val, $field->{$key});
                }
            }
            if (!empty($elisfieldexpected['manual'])) {
                $manual = new field_owner($field->owners['manual']);
                foreach ($elisfieldexpected['manual'] as $key => $val) {
                    $this->assertEquals($val, $manual->{'param_'.$key});
                }
            }
            if (!empty($elisfieldexpected['moodle_profile'])) {
                $moodleprofile = new field_owner($field->owners['moodle_profile']);
                foreach ($elisfieldexpected['moodle_profile'] as $key => $val) {
                    $this->assertEquals($val, $moodleprofile->{$key});
                }
            }
        }
    }

    /**
     * Data provider for test method
     * @return array the data for test methods
     */
    public function customfieldsync_dataprovider() {
        /**
         * Format of data passed to method:
         * (Moodlefield data-array, ELISfield data-array, cansync bool, Moodlefield expect-array, ELISfield expect-array)
         */
        static $themes = null;
        if ($themes == null) {
            $themes = array(get_string('unset_theme_option', 'elisfields_manual'));
            foreach (get_list_of_themes() as $theme) {
                $themes[] = $theme->name;
            }
        }

        $testdata = array();
        $testdata[] = array(
            // checkbox => checkbox with options source - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf0',
                'name'      => 'mdlcf0', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf0',
                    'name'        => 'elis_mdlcf0',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => checkbox with options - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf1',
                'name'      => 'mdlcf1', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf1',
                    'name'        => 'elis_mdlcf1',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => 'Option1\nOption2\nOption3'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => checkbox with mismatched shortnames - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf2',
                'name'      => 'mdlcf2', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => '_mdlcf2_',
                    'name'        => 'elis_mdlcf2',
                    'datatype'    => 'bool',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => datetime - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf3',
                'name'      => 'mdlcf3', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf3',
                    'name'        => 'elis_mdlcf3',
                    'datatype'    => 'int',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'datetime',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => '',
                    'startyear'       => '1971',
                    'stopyear'        => '2014',
                    'inctime'         => '1'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => menu themes - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf4',
                'name'      => 'mdlcf4', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf4',
                    'name'        => 'elis_mdlcf4',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => menu options - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf5',
                'name'      => 'mdlcf5', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf5',
                    'name'        => 'elis_mdlcf5',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => 'Option1\nOption2\nOption3'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => text - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf6',
                'name'      => 'mdlcf6', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf6',
                    'name'        => 'elis_mdlcf6',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'text',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => textarea - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf7',
                'name'      => 'mdlcf7', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf7',
                    'name'        => 'elis_mdlcf7',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'textarea',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => password - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf8',
                'name'      => 'mdlcf8', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf8',
                    'name'        => 'elis_mdlcf8',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'password',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // datetime => checkbox - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf9',
                'name'      => 'mdlcf9', 
                'datatype'  => 'datetime',
                'param1'    => '1971',
                'param2'    => '2014',
                'param3'    => '1'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf9',
                    'name'        => 'elis_mdlcf9',
                    'datatype'    => 'bool',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // datetime => menu themes - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf10',
                'name'      => 'mdlcf10', 
                'datatype'  => 'datetime',
                'param1'    => '1971',
                'param2'    => '2014',
                'param3'    => '1'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf10',
                    'name'        => 'elis_mdlcf10',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // datetime => menu options - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf11',
                'name'      => 'mdlcf11', 
                'datatype'  => 'datetime',
                'param1'    => '1971',
                'param2'    => '2014',
                'param3'    => '1'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf11',
                    'name'        => 'elis_mdlcf11',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => 'Option1\nOption2\nOption3'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // datetime => text - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf12',
                'name'      => 'mdlcf12', 
                'datatype'  => 'datetime',
                'param1'    => '1971',
                'param2'    => '2014',
                'param3'    => '1'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf12',
                    'name'        => 'elis_mdlcf12',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'text',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // datetime => text area - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf13',
                'name'      => 'mdlcf13', 
                'datatype'  => 'datetime',
                'param1'    => '1971',
                'param2'    => '2014',
                'param3'    => '1'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf13',
                    'name'        => 'elis_mdlcf13',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'textarea',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // datetime => password - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf14',
                'name'      => 'mdlcf14', 
                'datatype'  => 'datetime',
                'param1'    => '1971',
                'param2'    => '2014',
                'param3'    => '1'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf14',
                    'name'        => 'elis_mdlcf14',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'password',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // menu => checkbox (no options) - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf15',
                'name'      => 'mdlcf15', 
                'datatype'  => 'menu',
                'param1'    => 'MoodleOption1\nMoodleOption2\nMoodleOption3',
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf15',
                    'name'        => 'elis_mdlcf15',
                    'datatype'    => 'bool',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // menu => datetime - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf16',
                'name'      => 'mdlcf16', 
                'datatype'  => 'menu',
                'param1'    => 'MoodleOption1\nMoodleOption2\nMoodleOption3',
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf16',
                    'name'        => 'elis_mdlcf16',
                    'datatype'    => 'int',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'datetime',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => '',
                    'startyear'       => '1971',
                    'stopyear'        => '2014',
                    'inctime'         => '1'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // menu => text - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf17',
                'name'      => 'mdlcf17', 
                'datatype'  => 'menu',
                'param1'    => 'MoodleOption1\nMoodleOption2\nMoodleOption3',
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf17',
                    'name'        => 'elis_mdlcf17',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'text',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // menu => textarea - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf18',
                'name'      => 'mdlcf18', 
                'datatype'  => 'menu',
                'param1'    => 'MoodleOption1\nMoodleOption2\nMoodleOption3',
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf18',
                    'name'        => 'elis_mdlcf18',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'textarea',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // menu => password - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf19',
                'name'      => 'mdlcf19', 
                'datatype'  => 'menu',
                'param1'    => 'MoodleOption1\nMoodleOption2\nMoodleOption3',
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf19',
                    'name'        => 'elis_mdlcf19',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'password',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // text => datetime - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf20',
                'name'      => 'mdlcf20', 
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf20',
                    'name'        => 'elis_mdlcf20',
                    'datatype'    => 'int',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'datetime',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => '',
                    'startyear'       => '1971',
                    'stopyear'        => '2014',
                    'inctime'         => '1'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // textarea => datetime - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf21',
                'name'      => 'mdlcf21', 
                'datatype'  => 'textarea'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf21',
                    'name'        => 'elis_mdlcf21',
                    'datatype'    => 'int',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'datetime',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => '',
                    'startyear'       => '1971',
                    'stopyear'        => '2014',
                    'inctime'         => '1'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => checkbox - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf22',
                'name'      => 'mdlcf22', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf22',
                    'name'        => 'elis_mdlcf22',
                    'datatype'    => 'bool',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // menu => checkbox w/ options - can sync - update moodle options
            array( // Moodle field data
                'shortname' => 'mdlcf23',
                'name'      => 'mdlcf23', 
                'datatype'  => 'menu',
                'param1'    => "MoodleOption1\nMoodleOption2\nMoodleOption3"
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf23',
                    'name'        => 'elis_mdlcf23',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => "Option1\nOption2\nOption3"
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            array( // Moodle field expected
                'param1' => "Option1\nOption2\nOption3"
            ),
            null
        );

        $testdata[] = array(
            // menu => checkbox w/ options themse - can sync - update moodle options
            array( // Moodle field data
                'shortname' => 'mdlcf24',
                'name'      => 'mdlcf24', 
                'datatype'  => 'menu',
                'param1'    => "MoodleOption1\nMoodleOption2\nMoodleOption3"
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf24',
                    'name'        => 'elis_mdlcf24',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            array( // Moodle field expected
                'param1' => implode("\n", $themes)
            ),
            null
        );

        $testdata[] = array(
            // menu => menu - can sync - update moodle options
            array( // Moodle field data
                'shortname' => 'mdlcf25',
                'name'      => 'mdlcf25', 
                'datatype'  => 'menu',
                'param1'    => "MoodleOption1\nMoodleOption2\nMoodleOption3"
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf25',
                    'name'        => 'elis_mdlcf25',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => "Option1\nOption2\nOption3"
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            array( // Moodle field expected
                'param1' => "Option1\nOption2\nOption3"
            ),
            null
        );

        $testdata[] = array(
            // menu => menu options themes - can sync - update moodle options
            array( // Moodle field data
                'shortname' => 'mdlcf26',
                'name'      => 'mdlcf26', 
                'datatype'  => 'menu',
                'param1'    => "Option1\nOption2\nOption3"
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf26',
                    'name'        => 'elis_mdlcf26',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            array( // Moodle field expected
                'param1' => implode("\n", $themes)
            ),
            null
        );

        $testdata[] = array(
            // datetime => datetime - can sync - update Moodle field params
            array( // Moodle field data
                'shortname' => 'mdlcf27',
                'name'      => 'mdlcf27', 
                'datatype'  => 'datetime',
                'param1'    => '1970',
                'param2'    => '2013',
                'param3'    => '0'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf27',
                    'name'        => 'elis_mdlcf27',
                    'datatype'    => 'int',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'datetime',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => '',
                    'startyear'       => '1971',
                    'stopyear'        => '2014',
                    'inctime'         => '1'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            array( // Moodle field expected
                'param1' => '1971',
                'param3' => '2014',
                'param3' => '1'
            ),
            null
        );

        $testdata[] = array(
            // text => checkbox - can sync only if options
            array( // Moodle field data
                'shortname' => 'mdlcf28',
                'name'      => 'mdlcf28', 
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf28',
                    'name'        => 'elis_mdlcf28',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // text => menu - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf29',
                'name'      => 'mdlcf29', 
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf29',
                    'name'        => 'elis_mdlcf29',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // text => text - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf30',
                'name'      => 'mdlcf30', 
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf30',
                    'name'        => 'elis_mdlcf30',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'text',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // text => textarea - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf31',
                'name'      => 'mdlcf31', 
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf31',
                    'name'        => 'elis_mdlcf31',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'textarea',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // text => password - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf32',
                'name'      => 'mdlcf32', 
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf32',
                    'name'        => 'elis_mdlcf32',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'password',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // textarea => checkbox - can sync if options
            array( // Moodle field data
                'shortname' => 'mdlcf33',
                'name'      => 'mdlcf33', 
                'datatype'  => 'textarea'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf33',
                    'name'        => 'elis_mdlcf33',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // textarea => menu - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf34',
                'name'      => 'mdlcf34', 
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf34',
                    'name'        => 'elis_mdlcf34',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // textarea => text - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf35',
                'name'      => 'mdlcf35', 
                'datatype'  => 'textarea'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf35',
                    'name'        => 'elis_mdlcf35',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'text',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // textarea => textarea - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf36',
                'name'      => 'mdlcf36', 
                'datatype'  => 'textarea'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf36',
                    'name'        => 'elis_mdlcf36',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'textarea',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // textarea => password - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf37',
                'name'      => 'mdlcf37', 
                'datatype'  => 'textarea'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf37',
                    'name'        => 'elis_mdlcf37',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'password',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => checkbox with options source - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf38',
                'name'      => 'mdlcf38', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf38',
                    'name'        => 'elis_mdlcf38',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => checkbox with options - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf39',
                'name'      => 'mdlcf39', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf39',
                    'name'        => 'elis_mdlcf39',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => 'Option1\nOption2\nOption3'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => checkbox with mismatched shortnames - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf40',
                'name'      => 'mdlcf40', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => '_mdlcf40_',
                    'name'        => 'elis_mdlcf40',
                    'datatype'    => 'bool',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => datetime - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf41',
                'name'      => 'mdlcf41', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf41',
                    'name'        => 'elis_mdlcf41',
                    'datatype'    => 'int',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'datetime',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => '',
                    'startyear'       => '1971',
                    'stopyear'        => '2014',
                    'inctime'         => '1'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => menu themes - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf42',
                'name'      => 'mdlcf42', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf42',
                    'name'        => 'elis_mdlcf42',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => menu options - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf43',
                'name'      => 'mdlcf43', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf43',
                    'name'        => 'elis_mdlcf43',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => 'Option1\nOption2\nOption3'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => text - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf44',
                'name'      => 'mdlcf44', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf44',
                    'name'        => 'elis_mdlcf44',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'text',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => textarea - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf45',
                'name'      => 'mdlcf45', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf45',
                    'name'        => 'elis_mdlcf45',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'textarea',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => password - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf46',
                'name'      => 'mdlcf46', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf46',
                    'name'        => 'elis_mdlcf46',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'password',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // datetime => checkbox - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf47',
                'name'      => 'mdlcf47', 
                'datatype'  => 'datetime',
                'param1'    => '1971',
                'param2'    => '2014',
                'param3'    => '1'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf47',
                    'name'        => 'elis_mdlcf47',
                    'datatype'    => 'bool',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // datetime => menu themes - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf48',
                'name'      => 'mdlcf48', 
                'datatype'  => 'datetime',
                'param1'    => '1971',
                'param2'    => '2014',
                'param3'    => '1'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf48',
                    'name'        => 'elis_mdlcf48',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // datetime => menu options - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf49',
                'name'      => 'mdlcf49', 
                'datatype'  => 'datetime',
                'param1'    => '1971',
                'param2'    => '2014',
                'param3'    => '1'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf49',
                    'name'        => 'elis_mdlcf49',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => 'Option1\nOption2\nOption3'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // datetime => text - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf50',
                'name'      => 'mdlcf50', 
                'datatype'  => 'datetime',
                'param1'    => '1971',
                'param2'    => '2014',
                'param3'    => '1'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf50',
                    'name'        => 'elis_mdlcf50',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'text',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // datetime => text area - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf51',
                'name'      => 'mdlcf51', 
                'datatype'  => 'datetime',
                'param1'    => '1971',
                'param2'    => '2014',
                'param3'    => '1'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf51',
                    'name'        => 'elis_mdlcf51',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'textarea',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // datetime => password - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf52',
                'name'      => 'mdlcf52', 
                'datatype'  => 'datetime',
                'param1'    => '1971',
                'param2'    => '2014',
                'param3'    => '1'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf52',
                    'name'        => 'elis_mdlcf52',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'password',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // menu => checkbox (no options) - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf53',
                'name'      => 'mdlcf53', 
                'datatype'  => 'menu',
                'param1'    => 'MoodleOption1\nMoodleOption2\nMoodleOption3',
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf53',
                    'name'        => 'elis_mdlcf53',
                    'datatype'    => 'bool',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // menu => datetime - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf54',
                'name'      => 'mdlcf54', 
                'datatype'  => 'menu',
                'param1'    => 'MoodleOption1\nMoodleOption2\nMoodleOption3',
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf54',
                    'name'        => 'elis_mdlcf54',
                    'datatype'    => 'int',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'datetime',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => '',
                    'startyear'       => '1971',
                    'stopyear'        => '2014',
                    'inctime'         => '1'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // menu => text - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf55',
                'name'      => 'mdlcf55', 
                'datatype'  => 'menu',
                'param1'    => 'MoodleOption1\nMoodleOption2\nMoodleOption3',
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf55',
                    'name'        => 'elis_mdlcf55',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'text',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // menu => textarea - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf56',
                'name'      => 'mdlcf56', 
                'datatype'  => 'menu',
                'param1'    => 'MoodleOption1\nMoodleOption2\nMoodleOption3',
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf56',
                    'name'        => 'elis_mdlcf56',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'textarea',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // menu => password - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf57',
                'name'      => 'mdlcf57', 
                'datatype'  => 'menu',
                'param1'    => 'MoodleOption1\nMoodleOption2\nMoodleOption3',
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf57',
                    'name'        => 'elis_mdlcf57',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'password',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // text => datetime - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf58',
                'name'      => 'mdlcf58', 
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf58',
                    'name'        => 'elis_mdlcf58',
                    'datatype'    => 'int',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'datetime',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => '',
                    'startyear'       => '1971',
                    'stopyear'        => '2014',
                    'inctime'         => '1'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // textarea => datetime - can't sync
            array( // Moodle field data
                'shortname' => 'mdlcf59',
                'name'      => 'mdlcf59', 
                'datatype'  => 'textarea'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf59',
                    'name'        => 'elis_mdlcf59',
                    'datatype'    => 'int',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'datetime',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => '',
                    'startyear'       => '1971',
                    'stopyear'        => '2014',
                    'inctime'         => '1'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // checkbox => checkbox - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf60',
                'name'      => 'mdlcf60', 
                'datatype'  => 'checkbox'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf60',
                    'name'        => 'elis_mdlcf60',
                    'datatype'    => 'bool',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // menu => checkbox w/ options - can sync - update elis options
            array( // Moodle field data
                'shortname' => 'mdlcf61',
                'name'      => 'mdlcf61', 
                'datatype'  => 'menu',
                'param1'    => "MoodleOption1\nMoodleOption2\nMoodleOption3"
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf61',
                    'name'        => 'elis_mdlcf61',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => "Option1\nOption2\nOption3"
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            array( // ELIS field expected
                'manual' => array(
                    'options'        => "MoodleOption1\nMoodleOption2\nMoodleOption3",
                    'options_source' => '',
                )
            )
        );

        $testdata[] = array(
            // menu => checkbox w/ options themse - can sync - update elis options
            array( // Moodle field data
                'shortname' => 'mdlcf62',
                'name'      => 'mdlcf62', 
                'datatype'  => 'menu',
                'param1'    => "MoodleOption1\nMoodleOption2\nMoodleOption3"
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf62',
                    'name'        => 'elis_mdlcf62',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            array( // ELIS field expected
                'manual' => array(
                    'options'        => "MoodleOption1\nMoodleOption2\nMoodleOption3",
                    'options_source' => ''
                )
            )
        );

        $testdata[] = array(
            // menu => menu - can sync - update elis options
            array( // Moodle field data
                'shortname' => 'mdlcf63',
                'name'      => 'mdlcf63', 
                'datatype'  => 'menu',
                'param1'    => "MoodleOption1\nMoodleOption2\nMoodleOption3"
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf63',
                    'name'        => 'elis_mdlcf63',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => "Option1\nOption2\nOption3"
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            array( // ELIS field expected
                'manual' => array(
                    'options'        => "MoodleOption1\nMoodleOption2\nMoodleOption3",
                    'options_source' => ''
                )
            )
        );

        $testdata[] = array(
            // menu => menu options themes - can sync - update moodle options
            array( // Moodle field data
                'shortname' => 'mdlcf64',
                'name'      => 'mdlcf64', 
                'datatype'  => 'menu',
                'param1'    => "Option1\nOption2\nOption3"
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf64',
                    'name'        => 'elis_mdlcf64',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            array( // ELIS field expected
                'manual' => array(
                    'options'        => "Option1\nOption2\nOption3",
                    'options_source' => ''
                )
            )
        );

        $testdata[] = array(
            // datetime => datetime - can sync - update ELIS field params
            array( // Moodle field data
                'shortname' => 'mdlcf65',
                'name'      => 'mdlcf65', 
                'datatype'  => 'datetime',
                'param1'    => '1970',
                'param2'    => '2013',
                'param3'    => '0'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf65',
                    'name'        => 'elis_mdlcf65',
                    'datatype'    => 'int',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'datetime',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => '',
                    'startyear'       => '1971',
                    'stopyear'        => '2014',
                    'inctime'         => '1'
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            array( // ELIS field expected
                'manual' => array(
                    'startyear' => '1970',
                    'stopyear'  => '2013',
                    'inctime'   => '0'
                )
            )
        );

        $testdata[] = array(
            // text => checkbox - can sync if options
            array( // Moodle field data
                'shortname' => 'mdlcf66',
                'name'      => 'mdlcf66', 
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf66',
                    'name'        => 'elis_mdlcf66',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // text => menu - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf67',
                'name'      => 'mdlcf67', 
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf67',
                    'name'        => 'elis_mdlcf67',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // text => text - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf68',
                'name'      => 'mdlcf68', 
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf68',
                    'name'        => 'elis_mdlcf68',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'text',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // text => textarea - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf69',
                'name'      => 'mdlcf69', 
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf69',
                    'name'        => 'elis_mdlcf69',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'textarea',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // text => password - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf70',
                'name'      => 'mdlcf70', 
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf70',
                    'name'        => 'elis_mdlcf70',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'password',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // textarea => checkbox - can sync if options
            array( // Moodle field data
                'shortname' => 'mdlcf71',
                'name'      => 'mdlcf71', 
                'datatype'  => 'textarea'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf71',
                    'name'        => 'elis_mdlcf71',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // textarea => menu - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf72',
                'name'      => 'mdlcf72', 
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf72',
                    'name'        => 'elis_mdlcf72',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => 'themes',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // textarea => text - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf73',
                'name'      => 'mdlcf73', 
                'datatype'  => 'textarea'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf73',
                    'name'        => 'elis_mdlcf73',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'text',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // textarea => textarea - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf74',
                'name'      => 'mdlcf74', 
                'datatype'  => 'textarea'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf74',
                    'name'        => 'elis_mdlcf74',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'textarea',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // textarea => password - can sync
            array( // Moodle field data
                'shortname' => 'mdlcf75',
                'name'      => 'mdlcf75', 
                'datatype'  => 'textarea'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf75',
                    'name'        => 'elis_mdlcf75',
                    'datatype'    => 'text',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'password',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            null
        );

        $testdata[] = array(
            // text => checkbox - can't sync no options
            array( // Moodle field data
                'shortname' => 'mdlcf76',
                'name'      => 'mdlcf76',
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf76',
                    'name'        => 'elis_mdlcf76',
                    'datatype'    => 'int',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // text => checkbox - can't sync no options
            array( // Moodle field data
                'shortname' => 'mdlcf77',
                'name'      => 'mdlcf77',
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf77',
                    'name'        => 'elis_mdlcf77',
                    'datatype'    => 'int',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // textarea => checkbox - can't sync no options
            array( // Moodle field data
                'shortname' => 'mdlcf78',
                'name'      => 'mdlcf78',
                'datatype'  => 'textarea'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf78',
                    'name'        => 'elis_mdlcf78',
                    'datatype'    => 'int',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // textarea => checkbox - can't sync no options
            array( // Moodle field data
                'shortname' => 'mdlcf79',
                'name'      => 'mdlcf79',
                'datatype'  => 'text'
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf79',
                    'name'        => 'elis_mdlcf79',
                    'datatype'    => 'int',
                    'categoryid'  => 0, // TBD
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'checkbox',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => ''
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            false, // can sync
            null,
            null
        );

        $testdata[] = array(
            // menu => multi-valued menu - can't sync _to_ moodle
            array( // Moodle field data
                'shortname' => 'mdlcf80',
                'name'      => 'mdlcf80',
                'datatype'  => 'menu',
                'param1'    => "MoodleOption1\nMoodleOption2\nMoodleOption3"
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf80',
                    'name'        => 'elis_mdlcf80',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                    'multivalued' => 1
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => "Option1\nOption2\nOption3"
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_to_moodle
                )
            ),
            false, // can't sync
            null,
            null
        );

        $testdata[] = array(
            // menu => multi-valued menu - can sync _from_ moodle - update options
            array( // Moodle field data
                'shortname' => 'mdlcf81',
                'name'      => 'mdlcf81',
                'datatype'  => 'menu',
                'param1'    => "MoodleOption1\nMoodleOption2\nMoodleOption3"
            ),
            array( // ELIS field data
                'field'          => array(
                    'shortname'   => 'mdlcf81',
                    'name'        => 'elis_mdlcf81',
                    'datatype'    => 'char',
                    'categoryid'  => 0, // TBD
                    'multivalued' => 1
                ),
                'context'        => CONTEXT_ELIS_USER,
                'manual'         => array(
                    'control'         => 'menu',
                    'edit_capability' => '',
                    'view_capability' => '',
                    'options_source'  => '',
                    'options'         => "Option1\nOption2\nOption3"
                ),
                'moodle_profile' => array(
                    'exclude'     => pm_moodle_profile::sync_from_moodle
                )
            ),
            true, // can sync
            null,
            array( // ELIS field expected
                'manual' => array(
                    'options'        => "MoodleOption1\nMoodleOption2\nMoodleOption3",
                    'options_source' => '',
                )
            )
        );

        return $testdata;
    }

    /**
     * Validate that Moodle & ELIS custom user field settings are synced
     * @param array $moodlefielddata   array of moodle field params
     * @param array $elisfielddata     array of elis field, context & owner params
     * @param bool  $cansync           true if Moodle & ELIS field should be sync-able
     * @param array $moodlefieldexpect array of expected Moodle field values
     * @param array $elisfieldexpect   array of expected ELIS field/owner values
     * @uses $DB
     * @dataProvider customfieldsync_dataprovider
     */
    public function test_custom_user_field_settings_synced($moodlefielddata, $elisfielddata, $cansync, $moodlefieldexpect, $elisfieldexpect) {
        global $DB;

        // create Moodle profile field with data
        if (!empty($moodlefielddata)) {
            $DB->insert_record('user_info_field', (object)$moodlefielddata);
        } 

        // create ELIS field, context & owner data
        $field = $this->build_elis_field_data($elisfielddata);

        $this->assertTrue(moodle_profile_can_sync($field->shortname) == $cansync);

        sync_profile_field_settings_to_moodle($field);
        sync_profile_field_settings_from_moodle($field);

        if (!empty($moodlefieldexpect)) {
            $mdlfield = $DB->get_record('user_info_field', array('shortname' => $field->shortname));
            foreach ($moodlefieldexpect as $key => $val) {
                $this->assertEquals($val, $mdlfield->{$key});
            }
        }

        $this->assert_elis_field_data($field, $elisfieldexpect);
    }

}
