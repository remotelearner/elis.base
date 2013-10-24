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
require_once(elis::lib('data/customfield.class.php'));
require_once(elis::file('core/fields/manual/custom_fields.php'));
require_once($CFG->dirroot.'/lib/formslib.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/user/lib.php');

/**
 * Base form that is used as a skeleton to add fields to for ELIS custom fields.
 */
class custom_field_permissions_form extends moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        // We don't need any pre-defined elements.
    }

    /**
     * Helper method for obtaining the inner quick form.
     *
     * @return object the inner quickform object
     */
    public function get_mform() {
        return $this->_form;
    }
}

/**
 * Class for test case for testing permissions related to viewing and editing ELIS custom fields.
 * @group elis_core
 */
class custom_field_permissions_testcase extends elis_database_test {
    /**
     * Initialize configuration settings needed to appease accesslib.
     */
    private function init_config() {
        set_config('siteadmins', '');
        set_config('siteguest', '');
        set_config('defaultuserroleid', '');
        set_config('defaultfrontpageroleid', '');
    }

    /**
     * Initialize a test category and course, including their context records.
     *
     * @return object the course's context object
     * @uses $DB
     */
    private function init_category_and_course() {
        global $DB;

        // Category.
        $category = new stdClass;
        $category->name = 'category';
        $category->id = $DB->insert_record('course_categories', $category);
        context_coursecat::instance($category->id);

        // Course.
        $coursedata = new stdClass;
        $coursedata->category = $category->id;
        $coursedata->fullname = 'fullname';
        $course = create_course($coursedata);

        return context_course::instance($course->id);
    }

    /**
     * Initialize a custom field and field owner record.
     *
     * @param string $editcapability The capability configured as needed for editing
     *                               the custom field (empty string for context-specific capability)
     * @param string $viewcapability The capability configured as needed for viewing
     *                               the custom field (empty string for context-specific capability)
     * @return object The custom field object
     */
    private function init_field_and_owner($editcapability, $viewcapability) {
        // Set up the custom field.
        $field = new field(
                array(
                    'shortname' => 'field',
                    'name' => 'field',
                    'datatype' => 'bool',
                    'categoryid' => 99999
                )
        );
        $field->save();

        // Set up the field owner.
        $params = array(
            'control' => 'checkbox',
            'edit_capability' => $editcapability,
            'view_capability' => $viewcapability
        );
        $fieldowner = new field_owner(
                array(
                    'fieldid' => $field->id,
                    'plugin' => 'manual',
                    'params' => serialize($params)
                )
        );
        $fieldowner->save();

        return $field;
    }

    /**
     * Set up a test user and user context instance.
     * @uses $USER
     * @uses $DB
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
     * Validate all four combinations of whether the current user has edit and view permissions.
     *
     * @param string $editcapability The capability configured as needed for editing
     *                               the custom field (empty string for context-specific capability)
     * @param string $viewcapability The capability configured as needed for viewing
     *                               the custom field (empty string for context-specific capability)
     * @uses $USER
     */
    private function validate_all_role_assignment_combinations($editcapability, $viewcapability) {
        global $USER;

        // Setup.
        $this->init_config();
        $coursecontext = $this->init_category_and_course();
        $field = $this->init_field_and_owner($editcapability, $viewcapability);
        $this->init_user();

        $editparam = null;
        if ($editcapability == '') {
            // Use this as a default for a custom edit capability.
            $editcapability = 'moodle/course:enrolconfig';
            // Pass the real capability to the method we are testing.
            $editparam = $editcapability;
        }

        $viewparam = null;
        if ($viewcapability == '') {
            // Use this as a default for a custom view capability.
            $viewcapability = 'moodle/course:enrolreview';
            // Pass the real capability to the method we are testing.
            $viewparam = $viewcapability;
        }

        // System context.
        $syscontext = context_system::instance();

        // Role to user for editing.
        $editroleid = create_role('editrole', 'editrole', 'editrole');
        assign_capability($editcapability, CAP_ALLOW, $editroleid, $syscontext->id);

        // Role to user for viewing.
        $viewroleid = create_role('viewrole', 'viewrole', 'viewrole');
        assign_capability($viewcapability, CAP_ALLOW, $viewroleid, $syscontext->id);

        // User with both edit and view.
        role_assign($editroleid, $USER->id, $coursecontext->id);
        role_assign($viewroleid, $USER->id, $coursecontext->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $coursecontext, array(), $field, false, $editparam, $viewparam);
        $element = $mform->getElement('field_field');
        $this->assertEquals('checkbox', $element->getType());

        // User with just edit.
        role_unassign($viewroleid, $USER->id, $coursecontext->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $coursecontext, array(), $field, false, $editparam, $viewparam);
        $element = $mform->getElement('field_field');
        $this->assertEquals('checkbox', $element->getType());

        // User with just view.
        role_unassign($editroleid, $USER->id, $coursecontext->id);
        role_assign($viewroleid, $USER->id, $coursecontext->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $coursecontext, array(), $field, false, $editparam, $viewparam);
        $element = $mform->getElement('field_field');
        $this->assertEquals('static', $element->getType());

        // User without edit or view.
        role_unassign($viewroleid, $USER->id, $coursecontext->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $coursecontext, array(), $field, false, $editparam, $viewparam);
        $this->assertFalse($mform->elementExists('field_field'));
    }

    /**
     * Validate permissions when using custom edit and view capabilities.
     */
    public function test_custom_edit_capability_and_custom_view_capability() {
        $this->validate_all_role_assignment_combinations('', '');
    }

    /**
     * Validate permissions with custom edit capability, standard view capability.
     */
    public function test_custom_edit_capability_and_moodle_view_capability() {
        $this->validate_all_role_assignment_combinations('', 'moodle/user:viewhiddendetails');
    }

    /**
     * Validate permissions with standard edit capability, custom view capability.
     */
    public function test_moodle_edit_capability_and_custom_view_capability() {
        $this->validate_all_role_assignment_combinations('moodle/user:update', '');
    }

    /**
     * Validate permissions with standard edit and view capabilities.
     */
    public function test_moodle_edit_capability_and_moodle_view_capability() {
        $this->validate_all_role_assignment_combinations('moodle/user:update', 'moodle/user:viewhiddendetails');
    }

    /**
     * Validate permissions with editing disabled and custom view capability.
     * @uses $USER
     */
    public function test_editing_disabled_and_custom_view_capablity() {
        global $USER;

        // Setup.
        $this->init_config();
        $coursecontext = $this->init_category_and_course();
        $field = $this->init_field_and_owner('disabled', '');
        $this->init_user();

        // Role.
        $roleid = create_role('testrole', 'testrole', 'testrole');
        $syscontext = context_system::instance();
        assign_capability('moodle/course:enrolreview', CAP_ALLOW, $roleid, $syscontext->id);

        // User with capability.
        role_assign($roleid, $USER->id, $coursecontext->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $coursecontext, array(), $field, false, null, 'moodle/course:enrolreview');
        $element = $mform->getElement('field_field');
        $this->assertEquals('static', $element->getType());

        // User without capability.
        role_unassign($roleid, $USER->id, $coursecontext->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $coursecontext, array(), $field, false, null, 'moodle/course:enrolreview');
        $this->assertFalse($mform->elementExists('field_field'));
    }

    /**
     * Validate permissions with editing disabled and standard view capability.
     * @uses $USER
     */
    public function test_editing_disabled_and_moodle_view_capability() {
        global $USER;

        // Setup.
        $this->init_config();
        $coursecontext = $this->init_category_and_course();
        $field = $this->init_field_and_owner('disabled', 'moodle/user:viewhiddendetails');
        $this->init_user();

        // Role.
        $roleid = create_role('testrole', 'testrole', 'testrole');
        $syscontext = context_system::instance();
        assign_capability('moodle/user:viewhiddendetails', CAP_ALLOW, $roleid, $syscontext->id);

        // User with capability.
        role_assign($roleid, $USER->id, $coursecontext->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $coursecontext, array(), $field, false);
        $element = $mform->getElement('field_field');
        $this->assertEquals('static', $element->getType());

        // User without capability.
        role_unassign($roleid, $USER->id, $coursecontext->id);

        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $coursecontext, array(), $field, false);
        $this->assertFalse($mform->elementExists('field_field'));
    }

    /**
     * Validate error handling for incorrectly-specified context edit capability.
     */
    public function test_field_not_added_when_edit_context_capability_not_specified() {
        // Setup.
        $this->init_config();
        $coursecontext = $this->init_category_and_course();
        $field = $this->init_field_and_owner('', 'moodle/user:viewhiddendetails');
        $this->init_user();

        // Attempt to add the field.
        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $coursecontext, array(), $field, false);

        // Validation.
        $this->assertFalse($mform->elementExists('field_field'));
    }

    /**
     * Validate error handling for incorrectly-specified context view capability.
     */
    public function test_field_not_added_when_view_context_capability_not_specified() {
        // Setup.
        $this->init_config();
        $coursecontext = $this->init_category_and_course();
        $field = $this->init_field_and_owner('moodle/user:update', '');
        $this->init_user();

        // Attempt to add the field.
        $form = new custom_field_permissions_form();
        $mform = $form->get_mform();
        manual_field_add_form_element($form, $mform, $coursecontext, array(), $field, false);

        // Validation.
        $this->assertFalse($mform->elementExists('field_field'));
    }

    /**
     * Test using an invalid entity id
     * This test only rund when elis/program/setuplib.php exists.
     */
    public function test_manual_field_is_view_or_editable_with_pm_using_invalid_entity_id() {
        global $DB, $CFG;

        // Skip test if elis/program doesn't exist.
        if (!file_exists($CFG->dirroot.'/elis/program/lib/setup.php')) {
            $this->markTestSkipped('Requires elis/program to be installed.');
        }

        $this->resetAfterTest(true);
        $this->load_libraries_for_additional_tests();
        // Load CSV data.
        $this->load_csv_data();

        // Setup place holders for capabilities.
        $editcap = 'elis/program:user_edit';
        $viewcap = 'elis/program:user_view';

        // Retrieve the PM user id to be assigned to a userset.
        $param = array('id' => 103);
        $pmuserinusersetid = $DB->get_field('crlm_user', 'id', $param);

        // Get the userset context.
        $usersetcontext = context_elis_userset::instance(1);
        // System context.
        $syscontext = context_system::instance();

        $field = new field(array('id' => 101));
        $field->load();

        $result = manual_field_is_view_or_editable($field, $syscontext, $editcap, $viewcap, 'user', '99999');

        $this->assertEquals(MANUAL_FIELD_NO_VIEW_OR_EDIT, $result);
    }

    /**
     * Test using an invalid entity type.
     * This test only rund when elis/program/setuplib.php exists.
     * @expectedException coding_exception
     */
    public function test_manual_field_is_view_or_editable_with_pm_using_invalid_entity() {
        global $DB, $CFG;

        // Skip test if elis/program doesn't exist.
        if (!file_exists($CFG->dirroot.'/elis/program/lib/setup.php')) {
            $this->markTestSkipped('Requires elis/program to be installed.');
        }

        $this->resetAfterTest(true);
        $this->load_libraries_for_additional_tests();
        // Load CSV data.
        $this->load_csv_data();

        // Setup place holders for capabilities.
        $editcap = 'elis/program:user_edit';
        $viewcap = 'elis/program:user_view';

        // Retrieve the PM user id to be assigned to a userset.
        $param = array('id' => 103);
        $pmuserinusersetid = $DB->get_field('crlm_user', 'id', $param);

        // Get the userset context.
        $usersetcontext = context_elis_userset::instance(1);
        // System context.
        $syscontext = context_system::instance();

        $field = new field(array('id' => 101));
        $field->load();

        $result = manual_field_is_view_or_editable($field, $syscontext, $editcap, $viewcap, 'system', $pmuserinusersetid);

        $this->assertEquals(MANUAL_FIELD_NO_VIEW_OR_EDIT, $result);
    }

    /**
     * Test user with only edit permission on userset.
     * This test only rund when elis/program/setuplib.php exists.
     */
    public function test_manual_field_is_view_or_editable_with_no_permissions_on_userset() {
        global $DB, $CFG;

        // Skip test if elis/program doesn't exist.
        if (!file_exists($CFG->dirroot.'/elis/program/lib/setup.php')) {
            $this->markTestSkipped('Requires elis/program to be installed.');
        }

        $this->resetAfterTest(true);
        $this->load_libraries_for_additional_tests();
        // Load CSV data.
        $this->load_csv_data();

        // Setup place holders for capabilities.
        $editcap = 'elis/program:user_edit';
        $viewcap = 'elis/program:user_view';

        // Retrieve the PM user id to be assigned to a userset.
        $param = array('id' => 103);
        $pmuserinusersetid = $DB->get_field('crlm_user', 'id', $param);

        // Retrieve the user who will be assigned a role in the user set.
        $param = array('id' => 101);
        $userroleinuserset = $DB->get_record('user', $param);
        // Set user with role as logged in user
        $this->setUser($userroleinuserset);

        // Get the userset context.
        $usersetcontext = context_elis_userset::instance(1);
        // System context.
        $syscontext = context_system::instance();

        // Create role and assign capabilites to it.
        $roleid = create_role('testrole', 'testrole', 'testrole');

        // Assin role to user in the userset context.
        role_assign($roleid, $userroleinuserset->id, $usersetcontext->id);

        // Add user to cluster/userset.
        $usersetassign = new clusterassignment(array('clusterid' => 1, 'userid' => $pmuserinusersetid, 'plugin' => 'manual'));
        $usersetassign->save();

        $field = new field(array('id' => 101));
        $field->load();

        $result = manual_field_is_view_or_editable($field, $syscontext, $editcap, $viewcap, 'user', $pmuserinusersetid);

        $this->assertEquals(MANUAL_FIELD_NO_VIEW_OR_EDIT, $result);
    }

    /**
     * Test user with only edit permission on userset.
     * This test only rund when elis/program/setuplib.php exists.
     */
    public function test_manual_field_is_view_or_editable_with_edit_permissions_on_userset() {
        global $DB, $CFG;

        // Skip test if elis/program doesn't exist.
        if (!file_exists($CFG->dirroot.'/elis/program/lib/setup.php')) {
            $this->markTestSkipped('Requires elis/program to be installed.');
        }

        $this->resetAfterTest(true);
        $this->load_libraries_for_additional_tests();
        // Load CSV data.
        $this->load_csv_data();

        // Setup place holders for capabilities.
        $editcap = 'elis/program:user_edit';
        $viewcap = 'elis/program:user_view';

        // Retrieve the PM user id to be assigned to a userset.
        $param = array('id' => 103);
        $pmuserinusersetid = $DB->get_field('crlm_user', 'id', $param);

        // Retrieve the user who will be assigned a role in the user set.
        $param = array('id' => 101);
        $userroleinuserset = $DB->get_record('user', $param);
        // Set user with role as logged in user
        $this->setUser($userroleinuserset);

        // Get the userset context.
        $usersetcontext = context_elis_userset::instance(1);
        // System context.
        $syscontext = context_system::instance();

        // Create role and assign capabilites to it.
        $roleid = create_role('testrole', 'testrole', 'testrole');
        assign_capability($editcap, CAP_ALLOW, $roleid, $syscontext->id);

        // Assin role to user in the userset context.
        role_assign($roleid, $userroleinuserset->id, $usersetcontext->id);

        // Add user to cluster/userset.
        $usersetassign = new clusterassignment(array('clusterid' => 1, 'userid' => $pmuserinusersetid, 'plugin' => 'manual'));
        $usersetassign->save();

        $field = new field(array('id' => 101));
        $field->load();

        $result = manual_field_is_view_or_editable($field, $syscontext, $editcap, $viewcap, 'user', $pmuserinusersetid);

        $this->assertEquals(MANUAL_FIELD_EDITABLE, $result);
    }

    /**
     * Test user with only view permission on userset.
     * This test only rund when elis/program/setuplib.php exists.
     */
    public function test_manual_field_is_view_or_editable_with_view_permissions_on_userset() {
        global $DB, $CFG;

        // Skip test if elis/program doesn't exist.
        if (!file_exists($CFG->dirroot.'/elis/program/lib/setup.php')) {
            $this->markTestSkipped('Requires elis/program to be installed.');
        }

        $this->resetAfterTest(true);
        $this->load_libraries_for_additional_tests();
        // Load CSV data.
        $this->load_csv_data();

        // Setup place holders for capabilities.
        $editcap = 'elis/program:user_edit';
        $viewcap = 'elis/program:user_view';

        // Retrieve the PM user id to be assigned to a userset.
        $param = array('id' => 103);
        $pmuserinusersetid = $DB->get_field('crlm_user', 'id', $param);

        // Retrieve the user who will be assigned a role in the user set.
        $param = array('id' => 101);
        $userroleinuserset = $DB->get_record('user', $param);
        // Set user with role as logged in user
        $this->setUser($userroleinuserset);

        // Get the userset context.
        $usersetcontext = context_elis_userset::instance(1);
        // System context.
        $syscontext = context_system::instance();

        // Create role and assign capabilites to it.
        $roleid = create_role('testrole', 'testrole', 'testrole');
        assign_capability($viewcap, CAP_ALLOW, $roleid, $syscontext->id);

        // Assin role to user in the userset context.
        role_assign($roleid, $userroleinuserset->id, $usersetcontext->id);

        // Add user to cluster/userset.
        $usersetassign = new clusterassignment(array('clusterid' => 1, 'userid' => $pmuserinusersetid, 'plugin' => 'manual'));
        $usersetassign->save();

        $field = new field(array('id' => 101));
        $field->load();

        $result = manual_field_is_view_or_editable($field, $syscontext, $editcap, $viewcap, 'user', $pmuserinusersetid);

        $this->assertEquals(MANUAL_FIELD_VIEWABLE, $result);
    }

    /**
     * Test user with view and edit permissions on a userset.
     * This test only rund when elis/program/setuplib.php exists.
     */
    public function test_manual_field_is_view_or_editable_with_view_edit_permissions_on_userset() {
        global $DB, $CFG;

        // Skip test if elis/program doesn't exist.
        if (!file_exists($CFG->dirroot.'/elis/program/lib/setup.php')) {
            $this->markTestSkipped('Requires elis/program to be installed.');
        }

        $this->resetAfterTest(true);
        $this->load_libraries_for_additional_tests();
        // Load CSV data.
        $this->load_csv_data();

        // Setup place holders for capabilities.
        $editcap = 'elis/program:user_edit';
        $viewcap = 'elis/program:user_view';

        // Retrieve the PM user id to be assigned to a userset
        $param = array('id' => 103);
        $pmuserinusersetid = $DB->get_field('crlm_user', 'id', $param);

        // Retrieve the user who will be assigned a role in the user set.
        $param = array('id' => 101);
        $userroleinuserset = $DB->get_record('user', $param);
        // Set user with role as logged in user
        $this->setUser($userroleinuserset);

        // Get the userset context.
        $usersetcontext = context_elis_userset::instance(1);
        // System context.
        $syscontext = context_system::instance();

        // Create role and assign capabilites to it.
        $roleid = create_role('testrole', 'testrole', 'testrole');
        assign_capability($editcap, CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability($viewcap, CAP_ALLOW, $roleid, $syscontext->id);

        // Assin role to user in the userset context.
        role_assign($roleid, $userroleinuserset->id, $usersetcontext->id);

        // Add user to cluster/userset.
        $usersetassign = new clusterassignment(array('clusterid' => 1, 'userid' => $pmuserinusersetid, 'plugin' => 'manual'));
        $usersetassign->save();

        $field = new field(array('id' => 101));
        $field->load();

        $result = manual_field_is_view_or_editable($field, $syscontext, $editcap, $viewcap, 'user', $pmuserinusersetid);

        $this->assertEquals(MANUAL_FIELD_EDITABLE, $result);
    }

    /**
     * Require libraries needed to run additional tests on roles added to usersets
     */
    protected function load_libraries_for_additional_tests() {
        global $CFG;

        require_once($CFG->dirroot.'/elis/program/lib/setup.php');
        require_once(elispm::lib('data/userset.class.php'));
        require_once(elispm::lib('data/user.class.php'));
        require_once(elispm::lib('data/usermoodle.class.php'));
        require_once(elispm::lib('data/userset.class.php'));
    }

    /**
     * Load custom profile field data from CSV.
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            'user'             => elis::component_file('program', 'tests/fixtures/mdluser.csv'),
            'user_info_field'  => elis::component_file('program', 'tests/fixtures/user_info_field.csv'),
            'user_info_data'   => elis::component_file('program', 'tests/fixtures/user_info_data.csv'),
            user::TABLE        => elis::component_file('program', 'tests/fixtures/pmuser.csv'),
            usermoodle::TABLE  => elis::component_file('program', 'tests/fixtures/usermoodle.csv'),
            field::TABLE       => elis::component_file('program', 'tests/fixtures/user_field.csv'),
            field_owner::TABLE => elis::component_file('program', 'tests/fixtures/user_field_owner.csv'),
            userset::TABLE     => elis::component_file('program', 'tests/fixtures/userset.csv')
        ));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addSubStrReplacement('\n', "\n");
        $this->loadDataSet($dataset);
    }
}