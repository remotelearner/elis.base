<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

// Form functions

/**
 * Performs the necessary form setup for editing a "manual" custom field by modifying
 * the supplied form in-place
 *
 * @param  object  $mform  A moodle quickform to add the necessary fields to
 */
function manual_field_edit_form_definition($form) {
    global $CFG;
    require_once($CFG->dirroot . '/elis/core/lib/setup.php');

    $form->addElement('header', '', get_string('field_manual_header', 'elisfields_manual'));

    $form->addElement('checkbox', 'manual_field_enabled', get_string('field_manual_allow_editing', 'elisfields_manual'));
    $form->setDefault('manual_field_enabled', 'checked');

    $form->addElement('checkbox', 'manual_field_required', get_string('profilerequired', 'admin'));
    $form->disabledIf('manual_field_required', 'manual_field_enabled', 'notchecked');

    $choices = array(
        '' => get_string('field_manual_anyone_edit', 'elisfields_manual'),
        'moodle/user:update' => get_string('field_manual_admin_edit', 'elisfields_manual'),
        'disabled' => get_string('field_manual_nobody', 'elisfields_manual'),
        );
    $form->addElement('select', 'manual_field_edit_capability', get_string('manual_field_edit_capability', 'elisfields_manual'), $choices);
    $form->disabledIf('manual_field_edit_capability', 'manual_field_enabled', 'notchecked');
    $form->setAdvanced('manual_field_edit_capability');

    $choices = array(
        '' => get_string('field_manual_anyone_view', 'elisfields_manual'),
        'moodle/user:viewhiddendetails' => get_string('field_manual_admin_view', 'elisfields_manual'),
        );
    $form->addElement('select', 'manual_field_view_capability', get_string('manual_field_view_capability', 'elisfields_manual'), $choices);
    $form->disabledIf('manual_field_view_capability', 'manual_field_enabled', 'notchecked');
    $form->setAdvanced('manual_field_view_capability');

    $choices = array(
        'checkbox' => get_string('pluginname', 'profilefield_checkbox'),
        'menu' => get_string('pluginname', 'profilefield_menu'),
        'text' => get_string('pluginname', 'profilefield_text'),
        'textarea' => get_string('pluginname', 'profilefield_textarea'),
        'password' => get_string('password_control', 'elisfields_manual'),
        );
    $form->addElement('select', 'manual_field_control', get_string('manual_field_control', 'elisfields_manual'), $choices);
    $form->setType('manual_field_control', PARAM_ACTION);
    $form->disabledIf('manual_field_control', 'manual_field_enabled', 'notchecked');

    $choices = array();
    require_once elis::plugin_file('elisfields_manual','sources.php');
    $basedir = elis::plugin_file('elisfields_manual','sources');
    $dirhandle = opendir($basedir);
    while (false !== ($file = readdir($dirhandle))) {
        if (filetype($basedir .'/'. $file) === 'dir') {
            continue;
        }
        if (substr($file,-4) !== '.php') {
            continue;
        }
        require_once($basedir.'/'.$file);
        $file = substr($file, 0, -4);
        $classname = "manual_options_$file";
        $plugin = new $classname();
        if ($plugin->is_applicable(required_param('level', PARAM_ACTION))) {
            $choices[$file] = get_string("options_source_$file", 'elisfields_manual');;
        }
    }
    asort($choices);
    $choices = array('' => get_string('options_source_text', 'elisfields_manual')) + $choices;
    $form->addElement('select', 'manual_field_options_source', get_string('options_source', 'elisfields_manual'), $choices);
    $form->disabledIf('manual_field_options_source', 'manual_field_enabled', 'notchecked');
    $form->disabledIf('manual_field_options_source', 'manual_field_control', 'eq', 'text');
    $form->disabledIf('manual_field_options_source', 'manual_field_control', 'eq', 'textarea');
    $form->disabledIf('manual_field_options_source', 'manual_field_control', 'eq', 'password');
    $form->disabledIf('manual_field_options_source', 'datatype', 'eq', 'bool');
    $form->setAdvanced('manual_field_options_source');

    $form->addElement('textarea', 'manual_field_options', get_string('profilemenuoptions', 'admin'), array('rows' => 6, 'cols' => 40));
    $form->setType('manual_field_options', PARAM_MULTILANG);
    $form->disabledIf('manual_field_options', 'manual_field_enabled', 'notchecked');
    $form->disabledIf('manual_field_options', 'manual_field_control', 'eq', 'text');
    $form->disabledIf('manual_field_options', 'manual_field_control', 'eq', 'textarea');
    $form->disabledIf('manual_field_options', 'manual_field_control', 'eq', 'password');
    $form->disabledIf('manual_field_options', 'datatype', 'eq', 'bool');
    $form->disabledIf('manual_field_options', 'manual_field_options_source', 'neq', '');

    $form->addElement('text', 'manual_field_columns', get_string('profilefieldcolumns', 'admin'), 'size="6"');
    $form->setDefault('manual_field_columns', 30);
    $form->setType('manual_field_columns', PARAM_INT);
    $form->disabledIf('manual_field_columns', 'manual_field_enabled', 'notchecked');
    $form->disabledIf('manual_field_columns', 'manual_field_control', 'eq', 'checkbox');
    $form->disabledIf('manual_field_columns', 'manual_field_control', 'eq', 'menu');
    $form->disabledIf('manual_field_columns', 'datatype', 'eq', 'bool');

    $form->addElement('text', 'manual_field_rows', get_string('profilefieldrows', 'admin'), 'size="6"');
    $form->setDefault('manual_field_rows', 10);
    $form->setType('manual_field_rows', PARAM_INT);
    $form->disabledIf('manual_field_rows', 'manual_field_enabled', 'notchecked');
    $form->disabledIf('manual_field_rows', 'manual_field_control', 'eq', 'checkbox');
    $form->disabledIf('manual_field_rows', 'manual_field_control', 'eq', 'menu');
    $form->disabledIf('manual_field_rows', 'manual_field_control', 'eq', 'text');
    $form->disabledIf('manual_field_rows', 'manual_field_control', 'eq', 'password');
    $form->disabledIf('manual_field_rows', 'datatype', 'eq', 'bool');

    $form->addElement('text', 'manual_field_maxlength', get_string('profilefieldmaxlength', 'admin'), 'size="6"');
    $form->setDefault('manual_field_maxlength', 2048);
    $form->setType('manual_field_maxlength', PARAM_INT);
    $form->disabledIf('manual_field_maxlength', 'manual_field_enabled', 'notchecked');
    $form->disabledIf('manual_field_maxlength', 'manual_field_control', 'eq', 'checkbox');
    $form->disabledIf('manual_field_maxlength', 'manual_field_control', 'eq', 'menu');
    $form->disabledIf('manual_field_maxlength', 'manual_field_control', 'eq', 'textarea');
    $form->disabledIf('manual_field_maxlength', 'datatype', 'eq', 'bool');
}

/**
 * Specialization method to determine additional values to set on the form
 * as defined in the form definition method based on custom field information
 *
 * @param   object  $form   The custom field form as defined in the form definition method
 * @param   object  $field  The field object we are currently editing
 * @return  array           The values to set on the form
 */
function manual_field_get_form_data($form, $field) {
    if (!isset($field->owners['manual'])) {
        return array('manual_field_enabled' => false);
    }
    $manual = new field_owner($field->owners['manual']);
    $result = array('manual_field_enabled' => true);
    $parameters = array('required', 'edit_capability', 'view_capability',
                        'control', 'options_source', 'options', 'columns',
                        'rows', 'maxlength', 'help_file');
    foreach ($parameters as $param) {
        $paramname = "param_$param";
        if (isset($manual->$paramname)) {
            $result["manual_field_$param"] = $manual->$paramname;
        }
    }

    //if a help file is already set for this element, make sure a hidden field
    //is set up so we can preserve that value
    if (isset($manual->param_help_file)) {
        //need to use an accessor because _form is now protected
        $quickform = $form->get_quickform();

        //add the hidden field and set the appropriate type
        $quickform->addElement('hidden', 'manual_field_help_file', $manual->param_help_file);
        $quickform->setType('manual_field_help_file', PARAM_PATH);
    }

    return $result;
}

function manual_field_save_form_data($form, $field, $data) {
    if (isset($data->manual_field_enabled) && $data->manual_field_enabled) {
        if (isset($field->owners['manual'])) {
            $manual = new field_owner($field->owners['manual']);
        } else {
            $manual = new field_owner();
            $manual->fieldid = $field->id;
            $manual->plugin = 'manual';
        }
        if (isset($data->manual_field_required)) {
            $manual->param_required = $data->manual_field_required;
        } else {
            $manual->param_required = false;
        }
        $parameters = array('edit_capability', 'view_capability',
                            'control', 'options_source', 'options', 'columns',
                            'rows', 'maxlength', 'help_file');
        foreach ($parameters as $param) {
            $dataname = "manual_field_$param";
            if (isset($data->$dataname)) {
                $manual->{"param_$param"} = $data->$dataname;
            }
        }
        $manual->save();
    } else {
        global $DB;
        $DB->delete_records(field_owner::TABLE, array('fieldid'=>$field->id, 'plugin'=>'manual'));
    }
}

/**
 * Add an element to a form for a field.
 */
function manual_field_add_form_element($form, $mform, $context, $customdata, $field, $check_required = true) {
    //$mform = $form->_form;
    $manual = new field_owner($field->owners['manual']);
    if (!empty($manual->param_edit_capability)) {
        $capability = $manual->param_edit_capability;
        if ($capability == 'disabled' || !has_capability($capability, $context)) {
            if (!empty($manual->param_view_capability)) {
                $capability = $manual->param_view_capability;
                if (!has_capability($capability, $context)) {
                    return;
                }
            }
            $mform->addElement('static', "field_{$field->shortname}", $field->name);
            return;
        }
    }
    $control = $manual->param_control;
    require_once elis::plugin_file('elisfields_manual',"field_controls/{$control}.php");
    call_user_func("{$control}_control_display", $form, $mform, $customdata, $field);
    if ($check_required) {
        $manual_params = unserialize($manual->params);
        if (!empty($manual_params['required'])) {
            $mform->addRule("field_{$field->shortname}", null, 'required', null, 'client'); // TBD
        }
    }
}

/**
 * Adds a help button to the provided form for the provided field
 *
 * @param  MoodleQuickForm  $mform        The form to add the help button to
 * @param  string           $elementname  The shortname of the element to add the help button to
 * @param  field            $field        The field corresponding to the input control
 */
function manual_field_add_help_button($mform, $elementname, $field) {
    global $CFG, $OUTPUT, $PAGE;
    //error_log("manual_field_add_help_button(mform, elementname $elementname, field {$field->name})");
    $manual = isset($field->owners['manual'])
              ? new field_owner($field->owners['manual']) : new stdClass;
    $filename = '';
    if (!empty($manual->param_help_file)) {
        // First check for help_file spec. (now _help identifier in Moodle 2.0)
        list($plugin, $filename) = explode('/', $manual->param_help_file, 2);
        $filename = ltrim($filename, '_');
        //^Note: language string identifiers CANNOT start with underscore (_)
        //error_log("manual_field_add_help_button()::adding help button for plugin: plugin,identifier(filename) = {$plugin},{$filename}");
    }
    if (!empty($filename) && get_string_manager()->string_exists($filename, $plugin)) {
        $mform->addHelpButton($elementname, $filename, $plugin);
    } else if (!empty($field->description)) {
        // No help specified in language files, so we'll use field->description
        $ajax = ''; // TBD
        $id = html_writer::random_id('helpicon'); // 'helpicon'. dechex(mt_rand(286331153, 4294967295));
        $heading = get_string('helpprefix2', '', $field->name);
        $url = $CFG->wwwroot .'/elis/program/help.php?heading='.
               urlencode($heading) .'&helptext='.
               urlencode($field->description) . $ajax;
        // help using custom_field->description
        $divclass = 'fitem';
        if (array_key_exists($elementname, $mform->_advancedElements)) {
            $divclass .= ' advanced hide';
            //$mform->setAdvanced($elementname .'_help');
        }
        //$mform->addElement('static', $elementname .'_help',
        $mform->addElement('html',
            '<div class="'. $divclass .'"><div class="fitemtitle"><label for="id_'.
            $elementname .'"><span class="helplink"><a href="'. $url
            .'" title="'. $heading .'" id="'. $id .'"><img src="'.
            $OUTPUT->pix_url('help') .'" alt="'. $heading .'" title="'.
            $heading .'" class="iconhelp"></a></span>&nbsp;</label></div></div>'
        );
        $PAGE->requires->js_init_call('M.util.help_icon.add',
                             array(array('id' => $id, 'url' => $url)));
    }
}
