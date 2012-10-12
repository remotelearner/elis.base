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
require_once(elis::lib('testlib.php'));
require_once(elis::lib('data/customfield.class.php'));
require_once(elis::file('core/fields/manual/custom_fields.php'));
require_once($CFG->dirroot.'/lib/formslib.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/user/lib.php');

/**
 * Base form that is used as a skeleton to add fields to for ELIS custom fields
 */
class custom_field_permissions_form extends moodleform {
    /**
     * Form definition
     */
    function definition() {
        //we don't need any pre-defined elements
    }

    /**
     * Helper method for obtaining the inner quick form
     *
     * @return object the inner quickform object
     */
    function get_mform() {
        return $this->_form;
    }
}

/**
 * Test case for testing permissions related to viewing and editing ELIS custom fields
 */
class customFieldPermissionsTest extends elis_database_test {
    /**
     * Return the list of tables that should be overlayed.
     */
    static protected function get_overlay_tables() {
        return array('config' => 'moodle',
                     'context' => 'moodle',
                     'course' => 'moodle',
                     'course_categories' => 'moodle',
                     'role' => 'moodle',
                     'role_assignments' => 'moodle',
                     'role_capabilities' => 'moodle',
                     'user' => 'moodle',
                     field::TABLE => 'elis_core',
                     field_owner::TABLE => 'elis_core');
    }

    /**
     * Return the list of tables that should be ignored for writes.
     */
    static protected function get_ignored_tables() {
        return array('block_instances' => 'moodle',
                     'cache_flags' => 'moodle',
                     'course_sections' => 'moodle',
                     'enrol' => 'moodle',
                     'log' => 'moodle');
    }

    /**
     * Initialize configuration settings needed to appease accesslib
     */
    private function init_config() {
        set_config('siteadmins', '');
        set_config('siteguest', '');
        set_config('defaultuserroleid', '');
        set_config('defaultfrontpageroleid', '');
    }

    /**
     * Initialize critical context records in the database
     */
    private function init_key_contexts() {
        global $DB;

        $prefix = self::$origdb->get_prefix();
        $DB->execute("INSERT INTO {context}
                      SELECT * FROM {$prefix}context
                      WHERE contextlevel = ?", array(CONTEXT_SYSTEM));

        $DB->execute("INSERT INTO {course}
                      SELECT * FROM {$prefix}course
                      WHERE id = ?", array(SITEID));

        $DB->execute("INSERT INTO {role}
                      SELECT * FROM {$prefix}role
                      WHERE archetype = ?", array('guest')); 
    }

    /**
     * Initialize a test category and course, including their context records
     *
     * @return object the course's context object
     */
    private function init_category_and_course() {
        global $DB;

        //category
        $category = new stdClass;
        $category->name = 'category';
        $category->id = $DB->insert_record('course_categories', $category);
        context_coursecat::instance($category->id);

        //course
        $coursedata = new stdClass;
        $coursedata->category = $category->id;
        $coursedata->fullname = 'fullname';
        $course = create_course($coursedata);
        return context_course::instance($course->id);
    }

    /**
     * Initialize a custom field and field owner record
     *
     * @param string $edit_capability the capability configured as needed for editing
     *                                the custom field (empty string for context-specific capability)
     * @param string $view_capability the capability configured as needed for viewing
     *                                the custom field (empty string for context-specific capability)
     * @return object the custom field object
     */
    private function init_field_and_owner($edit_capability, $view_capability) {
        //set up the custom field
        $field = new field(array('shortname' => 'field',
                                 'name' => 'field',
                                 'datatype' => 'bool',
                                 'categoryid' => 99999));
        $field->save();

        //set up the field owner
        $params = array('control' => 'checkbox',
                        'edit_capability' => $edit_capability,
                        'view_capability' => $view_capability);
        $field_owner = new field_owner(array('fieldid' => $field->id,
                                             'plugin' => 'manual',
                                             'params' => serialize($params)));
        $field_owner->save();

        return $field;
    }

    /**
     * Set up a test user and user context instance
     */
    private function init_user() {
        global $USER, $DB;

        $userdata = new stdClass;
        $userdata->username = 'user';
        $userid = user_create_user($userdata);
        $USER = $DB->get_record('user', array('id' => $userid));
        context_user::instance($USER->id);
    }

    /**
     * Validate all four combinations of whether the current user has edit and view permissions
     *
     * @param string $edit_capability the capability configured as needed for editing
     *                                the custom field (empty string for context-specific capability)
     * @param string $view_capability the capability configured as needed for viewing
     *                                the custom field (empty string for context-specific capability)
     */
    private function validate_all_role_assignment_combinations($edit_capability, $view_capability) {
        global $USER;

        //setup
        $this->init_config();
        $this->init_key_contexts();
        $course_context = $this->init_category_and_course();
        $field = $this->init_field_and_owner($edit_capability, $view_capability);
        $this->init_user();

        $edit_param = NULL;
        if ($edit_capability == '') {
            //use this as a default for a custom edit capability
            $edit_capability = 'moodle/course:enrolconfig';
            //pass the real capability to the method we are testing
            $edit_param = $edit_capability;
        }

        $view_param = NULL;
        if ($view_capability == '') {
            //use this as a default for a custom view capability
            $view_capability = 'moodle/course:enrolreview';
            //pass the real capability to the method we are testing
            $view_param = $view_capability;
        }

        //system context
        $syscontext = context_system::instance();

        //role to user for editing
        $editroleid = create_role('editrole', 'editrole', 'editrole');
        assign_capability($edit_capability, CAP_ALLOW, $editroleid, $syscontext->id);

        //role to user for viewing
        $viewroleid = create_role('viewrole', 'viewrole', 'viewrole');
        assign_capability($view_capability, CAP_ALLOW, $viewroleid, $syscontext->id);

        //user with both edit and view
        role_assign($editroleid, $USER->id, $course_context->id);
        role_assign($viewroleid, $USER->id, $course_context->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $course_context, array(), $field, false, $edit_param, $view_param);
        $element = $mform->getElement('field_field');
        $this->assertEquals('checkbox', $element->getType());

        //user with just edit
        role_unassign($viewroleid, $USER->id, $course_context->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $course_context, array(), $field, false, $edit_param, $view_param);
        $element = $mform->getElement('field_field');
        $this->assertEquals('checkbox', $element->getType());

        //user with just view
        role_unassign($editroleid, $USER->id, $course_context->id);
        role_assign($viewroleid, $USER->id, $course_context->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $course_context, array(), $field, false, $edit_param, $view_param);
        $element = $mform->getElement('field_field');
        $this->assertEquals('static', $element->getType());

        //user without edit or view
        role_unassign($viewroleid, $USER->id, $course_context->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $course_context, array(), $field, false, $edit_param, $view_param);
        $this->assertFalse($mform->elementExists('field_field'));
    }

    /**
     * Validate permissions when using custom edit and view capabilities
     */
    public function testCustomEditCapabilityAndCustomViewCapability() {
        $this->validate_all_role_assignment_combinations('', '');
    }

    /**
     * Validate permissions with custom edit capability, standard view capability
     */
    public function testCustomEditCapabilityAndMoodleViewCapability() {
        $this->validate_all_role_assignment_combinations('', 'moodle/user:viewhiddendetails');
    }

    /**
     * Validate permissions with standard edit capability, custom view capability
     */
    public function testMoodleEditCapabilityAndCustomViewCapability() {
        $this->validate_all_role_assignment_combinations('moodle/user:update', '');
    }

    /**
     * Validate permissions with standard edit and view capabilities
     */
    public function testMoodleEditCapabilityAndMoodleViewCapability() {
        $this->validate_all_role_assignment_combinations('moodle/user:update', 'moodle/user:viewhiddendetails');
    }

    /**
     * Validate permissions with editing disabled and custom view capability
     */
    public function testEditingDisabledAndCustomViewCapablity() {
        global $USER;

        //setup
        $this->init_config();
        $this->init_key_contexts();
        $course_context = $this->init_category_and_course();
        $field = $this->init_field_and_owner('disabled', '');
        $this->init_user();

        //role
        $roleid = create_role('testrole', 'testrole', 'testrole');
        $syscontext = context_system::instance();
        assign_capability('moodle/course:enrolreview', CAP_ALLOW, $roleid, $syscontext->id);

        //user with capability
        role_assign($roleid, $USER->id, $course_context->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $course_context, array(), $field, false, NULL, 'moodle/course:enrolreview');
        $element = $mform->getElement('field_field');
        $this->assertEquals('static', $element->getType());

        //user without capability
        role_unassign($roleid, $USER->id, $course_context->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $course_context, array(), $field, false, NULL, 'moodle/course:enrolreview');
        $this->assertFalse($mform->elementExists('field_field'));
    }

    /**
     * Validate permissions with editing disabled and standard view capability
     */
    public function testEditingDisabledAndMoodleViewCapability() {
        global $USER;

        //setup
        $this->init_config();
        $this->init_key_contexts();
        $course_context = $this->init_category_and_course();
        $field = $this->init_field_and_owner('disabled', 'moodle/user:viewhiddendetails');
        $this->init_user();

        //role
        $roleid = create_role('testrole', 'testrole', 'testrole');
        $syscontext = context_system::instance();
        assign_capability('moodle/user:viewhiddendetails', CAP_ALLOW, $roleid, $syscontext->id);

        //user with capability
        role_assign($roleid, $USER->id, $course_context->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $course_context, array(), $field, false);
        $element = $mform->getElement('field_field');
        $this->assertEquals('static', $element->getType());

        //user without capability
        role_unassign($roleid, $USER->id, $course_context->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $course_context, array(), $field, false);
        $this->assertFalse($mform->elementExists('field_field'));
    }

    /**
     * Validate error handling for incorrectly-specified context edit capability
     */
    public function testFieldNotAddedWhenEditContextCapabilityNotSpecified() {
        //setup
        $this->init_config();
        $this->init_key_contexts();
        $course_context = $this->init_category_and_course();
        $field = $this->init_field_and_owner('', 'moodle/user:viewhiddendetails');
        $this->init_user();

        //attempt to add the field
        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $course_context, array(), $field, false);

        //validation
        $this->assertFalse($mform->elementExists('field_field'));
    }

    /**
     * Validate error handling for incorrectly-specified context view capability
     */
    public function testFieldNotAddedWhenViewContextCapabilityNotSpeciofied() {
        //setup
        $this->init_config();
        $this->init_key_contexts();
        $course_context = $this->init_category_and_course();
        $field = $this->init_field_and_owner('moodle/user:update', '');
        $this->init_user();

        //attempt to add the field
        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $course_context, array(), $field, false);

        //validation
        $this->assertFalse($mform->elementExists('field_field'));
    }
}