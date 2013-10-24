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

defined('MOODLE_INTERNAL') || die();

// Defines for return codes from manual_field_is_view_or_editable()
define('MANUAL_FIELD_NO_VIEW_OR_EDIT', -1);
define('MANUAL_FIELD_VIEWABLE', 0);
define('MANUAL_FIELD_EDITABLE', 1);

// Form functions

/**
 * Performs the necessary form setup for editing a "manual" custom field by modifying
 * the supplied form in-place
 *
 * @param  object  $mform  A moodle quickform to add the necessary fields to
 * @param  array   $attrs  Optional extra attributes to pass to form fields
 *                 format: $attr[<fieldid>] = array('option1' => val1, ...);
 */
function manual_field_edit_form_definition($form, $attrs = array()) {
    global $CFG;
    require_once($CFG->dirroot . '/elis/core/lib/setup.php');

    $attrfields = array('manual_field_edit_capability',
                        'manual_field_view_capability',
                        'manual_field_control',
                        'manual_field_options',
                        'manual_field_options_source',
                        'manual_field_columns',
                        'manual_field_rows',
                        'manual_field_maxlength',
                        'manual_field_startyear',
                        'manual_field_stopyear',
                        'manual_field_inctime');
    foreach ($attrfields as $attrfield) {
        if (!isset($attrs[$attrfield])) {
            $attrs[$attrfield] = array();
        }
    }

    $attrs['manual_field_inctime']['group'] = 1;

    $form->addElement('header', 'manualeditingsettings', get_string('field_manual_header', 'elisfields_manual'));

    $form->addElement('checkbox', 'manual_field_enabled', get_string('field_manual_allow_editing', 'elisfields_manual'));
    $form->setDefault('manual_field_enabled', 'checked');

    $form->addElement('checkbox', 'manual_field_required', get_string('profilerequired', 'admin'));
    $form->disabledIf('manual_field_required', 'manual_field_enabled', 'notchecked');

    $choices = array(
        '' => get_string('field_manual_anyone_edit', 'elisfields_manual'),
        'moodle/user:update' => get_string('field_manual_admin_edit', 'elisfields_manual'),
        'disabled' => get_string('field_manual_nobody', 'elisfields_manual'),
        );
    $form->addElement('select', 'manual_field_edit_capability',
                      get_string('manual_field_edit_capability', 'elisfields_manual'),
                      $choices, $attrs['manual_field_edit_capability']);
    $form->disabledIf('manual_field_edit_capability', 'manual_field_enabled', 'notchecked');
    $form->setAdvanced('manual_field_edit_capability');

    $choices = array(
        '' => get_string('field_manual_anyone_view', 'elisfields_manual'),
        'moodle/user:viewhiddendetails' => get_string('field_manual_admin_view', 'elisfields_manual'),
        );
    $form->addElement('select', 'manual_field_view_capability', get_string('manual_field_view_capability', 'elisfields_manual'), $choices, $attrs['manual_field_view_capability']);
    $form->disabledIf('manual_field_view_capability', 'manual_field_enabled', 'notchecked');
    $form->setAdvanced('manual_field_view_capability');

    $choices = array(
        'checkbox' => get_string('pluginname', 'profilefield_checkbox'),
        'menu' => get_string('pluginname', 'profilefield_menu'),
        'text' => get_string('pluginname', 'profilefield_text'),
        'textarea' => get_string('pluginname', 'profilefield_textarea'),
        'datetime' => get_string('pluginname', 'profilefield_datetime'),
        'password' => get_string('password_control', 'elisfields_manual'),
        );
    $form->addElement('select', 'manual_field_control', get_string('manual_field_control', 'elisfields_manual'), $choices, $attrs['manual_field_control']);
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

    $form->addElement('html', '<fieldset class="accesshide" id="field_options_menu">');
    $form->addElement('select', 'manual_field_options_source', get_string('options_source', 'elisfields_manual'), $choices, $attrs['manual_field_options_source']);
    $form->disabledIf('manual_field_options_source', 'manual_field_enabled', 'notchecked');
    $form->disabledIf('manual_field_options_source', 'manual_field_control', 'eq', 'text');
    $form->disabledIf('manual_field_options_source', 'manual_field_control', 'eq', 'textarea');
    $form->disabledIf('manual_field_options_source', 'manual_field_control', 'eq', 'password');
    $form->disabledIf('manual_field_options_source', 'manual_field_control', 'eq', 'datetime');
    $form->disabledIf('manual_field_options_source', 'datatype', 'eq', 'bool');
    $form->setAdvanced('manual_field_options_source');

    $attrs['manual_field_options'] = array_merge(array('rows' => 6, 'cols' => 40),
                                                 $attrs['manual_field_options']);
    $form->addElement('textarea', 'manual_field_options', get_string('profilemenuoptions', 'admin'), $attrs['manual_field_options']);
    $form->setType('manual_field_options', PARAM_MULTILANG);
    $form->disabledIf('manual_field_options', 'manual_field_enabled', 'notchecked');
    $form->disabledIf('manual_field_options', 'manual_field_control', 'eq', 'text');
    $form->disabledIf('manual_field_options', 'manual_field_control', 'eq', 'textarea');
    $form->disabledIf('manual_field_options', 'manual_field_control', 'eq', 'password');
    $form->disabledIf('manual_field_options', 'manual_field_control', 'eq', 'datetime');
    $form->disabledIf('manual_field_options', 'datatype', 'eq', 'bool');
    $form->disabledIf('manual_field_options', 'manual_field_options_source', 'neq', '');
    $form->addElement('html', '</fieldset>');

    $form->addElement('html', '<fieldset class="accesshide" id="field_options_text">');
    $form->addElement('text', 'manual_field_columns', get_string('profilefieldcolumns', 'admin'), 'size="6"');
    $form->setDefault('manual_field_columns', 30);
    $form->setType('manual_field_columns', PARAM_INT);
    $form->disabledIf('manual_field_columns', 'manual_field_enabled', 'notchecked');
    $form->disabledIf('manual_field_columns', 'manual_field_control', 'eq', 'checkbox');
    $form->disabledIf('manual_field_columns', 'manual_field_control', 'eq', 'menu');
    $form->disabledIf('manual_field_columns', 'manual_field_control', 'eq', 'datetime');
    $form->disabledIf('manual_field_columns', 'datatype', 'eq', 'bool');

    $form->addElement('text', 'manual_field_rows', get_string('profilefieldrows', 'admin'), 'size="6"');
    $form->setDefault('manual_field_rows', 10);
    $form->setType('manual_field_rows', PARAM_INT);
    $form->disabledIf('manual_field_rows', 'manual_field_enabled', 'notchecked');
    $form->disabledIf('manual_field_rows', 'manual_field_control', 'eq', 'checkbox');
    $form->disabledIf('manual_field_rows', 'manual_field_control', 'eq', 'menu');
    $form->disabledIf('manual_field_rows', 'manual_field_control', 'eq', 'text');
    $form->disabledIf('manual_field_rows', 'manual_field_control', 'eq', 'password');
    $form->disabledIf('manual_field_rows', 'manual_field_control', 'eq', 'datetime');
    $form->disabledIf('manual_field_rows', 'datatype', 'eq', 'bool');

    $form->addElement('text', 'manual_field_maxlength', get_string('profilefieldmaxlength', 'admin'), 'size="6"');
    $form->setDefault('manual_field_maxlength', 2048);
    $form->setType('manual_field_maxlength', PARAM_INT);
    $form->disabledIf('manual_field_maxlength', 'manual_field_enabled', 'notchecked');
    $form->disabledIf('manual_field_maxlength', 'manual_field_control', 'eq', 'checkbox');
    $form->disabledIf('manual_field_maxlength', 'manual_field_control', 'eq', 'menu');
    $form->disabledIf('manual_field_maxlength', 'manual_field_control', 'eq', 'textarea');
    $form->disabledIf('manual_field_maxlength', 'manual_field_control', 'eq', 'datetime');
    $form->disabledIf('manual_field_maxlength', 'datatype', 'eq', 'bool');
    $form->addElement('html', '</fieldset>');

    $form->addElement('html', '<fieldset class="accesshide" id="field_options_datetime">');
    $year_opts = array();
    for ($yr = 1902; $yr <= 2038; ++$yr) {
        $year_opts[$yr] = $yr;
    }
    $form->addElement('select', 'manual_field_startyear', get_string('options_startyear', 'elisfields_manual'), $year_opts, $attrs['manual_field_startyear']);
    $form->setDefault('manual_field_startyear', 1970);
    $form->addElement('select', 'manual_field_stopyear', get_string('options_stopyear', 'elisfields_manual'), $year_opts, $attrs['manual_field_stopyear']);
    $form->setDefault('manual_field_stopyear', 2038);
    $form->addElement('advcheckbox', 'manual_field_inctime', get_string('options_inctime', 'elisfields_manual'), '', $attrs['manual_field_inctime']); // TBD
    $form->setDefault('manual_field_inctime', false);
    $form->addElement('html', '</fieldset>');
}

/**
 * Specialization method to determine additional values to set on the form
 * as defined in the form definition method based on custom field information
 *
 * @param  object $form        The custom field form as defined in the form definition method
 * @param  object $fieldordata The field object or field_data we are currently editing
 * @return array               The values to set on the form
 */
function manual_field_get_form_data($form, $fieldordata) {
    // ELIS-6699 -- we might be passed a field data object here so we need to load the field itself in that case
    if (isset($fieldordata->fieldid)) {
        $field = new field($fieldordata->fieldid);
    } else {
        $field = $fieldordata;
    }

    if (!isset($field->owners['manual'])) {
        return array('manual_field_enabled' => false);
    }
    $manual = new field_owner($field->owners['manual']);
    $result = array('manual_field_enabled' => true);
    $parameters = array(
        'required',
        'edit_capability',
        'view_capability',
        'control',
        'options_source',
        'options',
        'columns',
        'rows',
        'maxlength',
        'help_file',
        'startyear',
        'stopyear',
        'inctime'
    );

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
                            'rows', 'maxlength', 'help_file', 'startyear',
                            'stopyear', 'inctime');
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
 * Check whether a field has view or edit capability on either Moodle or ELIS context(s).
 *
 * @param object $field the custom field we are viewing / editing
 * @param object $context Moodle context
 * @param string $contexteditcap The edit capability to check if the field owner
 *                                 is set up to use the "edit this context" option for editing
 * @param string $contextviewcap The view capability to check if the field owner
 *                                 is set up to use the "view this context" option for viewing
 * @param string $entity optional entity/context name
 * @param int $entityid The id of the entity.  Required only if an entity is passed.
 * @return int   MANUAL_FIELD_NO_VIEW_OR_EDIT (-1) if not viewable or editable
 *               MANUAL_FIELD_VIEWABLE (0) if viewable
 *               MANUAL_FIELD_EDITABLE (1) if editable (which implies viewable)
 */
function manual_field_is_view_or_editable($field, $context, $contexteditcap = NULL, $contextviewcap = NULL, $entity = 'system', $entityid = 0) {
    global $CFG, $USER;

    $canview = 0;
    $canedit = 0;

    if (!isset($field->owners['manual'])) {
        return MANUAL_FIELD_NO_VIEW_OR_EDIT;
    }
    $manual = new field_owner($field->owners['manual']);

    // Determine which exact capabilities we are checking
    $editcap = $manual->param_edit_capability;
    if ($editcap == '') {
        // Context-specific capability
        $editcap = $contexteditcap;
    }

    $viewcap = $manual->param_view_capability;
    if ($viewcap == '') {
        // Context-specific capability
        $viewcap = $contextviewcap;
    }

    if ($editcap == NULL || $viewcap == NULL) {
        // Capabilities for editing or viewing the context were not correctly specified
        return MANUAL_FIELD_NO_VIEW_OR_EDIT;
    }

    // Check if ELIS PM exists and if the given entity exists within PM
    if (0 != $entityid && file_exists($CFG->dirroot.'/elis/program/lib/setup.php') && context_elis_helper::get_level_from_name($entity)) {
        // Check ELIS contexts for the user's capability in an entity.
        $contextset = pm_context_set::for_user_with_capability($entity, $editcap, $USER->id);
        $canedit = $contextset->context_allowed($entityid, $entity);

        $contextset = pm_context_set::for_user_with_capability($entity, $viewcap, $USER->id);
        $canview = $contextset->context_allowed($entityid, $entity);
    }

    if ($editcap == 'disabled' || (!$canedit && !has_capability($editcap, $context))) {
        if (!$canview && !has_capability($viewcap, $context)) {
            // Do not have view or edit permissions
            return MANUAL_FIELD_NO_VIEW_OR_EDIT;
        }
        return MANUAL_FIELD_VIEWABLE;
    }

    return MANUAL_FIELD_EDITABLE;
}

/**
 * Add an element to a form for a field.
 *
 * @param object $form the moodle form object we are adding the element to
 * @param object $mform the moodle quick form object belonging to the moodle form
 * @param mixed $context Moodle context
 * @param array $customdata any additional information to pass along to the element
 * @param object $field the custom field we are viewing / editing
 * @param boolean $checkrequired if true, add a required rule for this field
 * @param string $contexteditcap the edit capability to check if the field owner
 *                                 is set up to use the "edit this context" option for editing
 * @param string $contextviewcap the view capability to check if the field owner
 *                                 is set up to use the "view this context" option for viewing
 * @param int $entityid The id of the entity.  Required only if an entity is passed.
 * @param string $entity  optional entity/context name
 */
function manual_field_add_form_element($form, $mform, $context, $customdata, $field, $checkrequired = true,
                                       $contexteditcap = NULL, $contextviewcap = NULL, $entity = 'system', $entityid = 0) {
    //$mform = $form->_form;

    $isvieworeditable = manual_field_is_view_or_editable($field, $context, $contexteditcap, $contextviewcap, $entity, $entityid);

    if ($isvieworeditable == MANUAL_FIELD_NO_VIEW_OR_EDIT) {
        return;
    }

    $elem = "field_{$field->shortname}";
    if ($isvieworeditable == MANUAL_FIELD_VIEWABLE) {
        //have view but not edit, show as static
        $mform->addElement('static', $elem, $field->name);
        // TBD: help link?
        return;
    }

    $manual = new field_owner($field->owners['manual']);
    $control = $manual->param_control;
    require_once elis::plugin_file('elisfields_manual',"field_controls/{$control}.php");
    call_user_func("{$control}_control_display", $form, $mform, $customdata, $field, false, $entity);

    $manual_params = unserialize($manual->params);

    // set default data if no over-riding value set!
    if (!isset($customdata['obj']->$elem)) {
        $defaultdata = field_data::get_for_context_and_field(NULL, $field);
        if (!empty($defaultdata)) {
            if ($field->multivalued) {
                $values = array();
                foreach ($defaultdata as $defdata) {
                    $values[] = $defdata->data;
                }
                $defaultdata = $values; // implode(',', $values)
            } else {
                foreach ($defaultdata as $defdata) {
                    $defaultdata = $defdata->data;
                    break;
                }
            }
        }

        // Format decimal numbers
        if ($field->datatype == 'num' && $manual_params['control'] != 'menu') {
            $defaultdata = $field->format_number($defaultdata);
        }

        if (!is_null($defaultdata) && !is_object($defaultdata) && $defaultdata !== false) {
            if (is_string($defaultdata)) {
                $defaultdata = trim($defaultdata, "\r\n"); // radio buttons!
            }
            $mform->setDefault($elem, $defaultdata);
        }
    }

    if ($checkrequired) {
        if (!empty($manual_params['required'])) {
            $mform->addRule($elem, null, 'required', null, 'client'); // TBD
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
        $mform->addElement('html', '<div class="'.$divclass.'"><div class="fitemtitle"><label for="id_'.$elementname.
                '"><span class="helptooltip"><a href="'.$url.'" title="'.$heading.'" id="'.$id.'" target="_blank" aria-haspopup="true"><img src="'.
                $OUTPUT->pix_url('help').'" alt="'.$heading.'" title="'.$heading.'" class="iconhelp"></a></span>&nbsp;</label></div></div>'
        );
    }
}

/**
 * Validates a custom field form element
 *
 * @param  mixed         $data      The form elements data to validate
 * @param  field object  $field     The custom field object to validate on
 * @param  int           $contextid The custom field's context id
 * @uses   $DB
 * @return mixed         Either: the error string for the custom field data
 *                       null for no validation errors, false for coding error.
 */
function manual_field_validation($data, $field, $contextid) {
    global $DB;

    if (!($field instanceof field)) {
        error_log("manual_field_validation(): coding error - non-field object passed (contextid = {$contextid})");
        return false;
    }

    if (!isset($field->owners['manual'])) {
        // BJB120605: Fix bug when viewing/editing disabled and no owner
        error_log("manual_field_validation(): no manual owner for field: {$field->shortname}");
        return false;
    }

    $errstr = null;
    if ($field->multivalued) {
        $manual = new field_owner($field->owners['manual']);
        if (!isset($data)) {
            $data = array();
        }
        if (!is_array($data)) {
            $data = array($data);
        }
        $fielddata = $data;
        sort($fielddata);
        if ($manual->param_required) {
            if (empty($fielddata)) {
                $errstr = get_string('required');
            } else if (!empty($manual->param_options)) {
                $options = explode("\n", $manual->param_options);
                array_walk($options, 'trim_cr'); // TBD: defined below
                foreach ($fielddata as $entry) {
                    if (!in_array($entry, $options)) {
                        $errstr = get_string('required');
                        break;
                    }
                }
            }
        }
        if (is_null($errstr) && $field->forceunique) {
            $curcontext = -1;
            $vals = null;

            $where = "contextid != {$contextid} AND fieldid = {$field->id}";
            $recs = $DB->get_recordset_select($field->data_table(), $where, null, '', 'id, contextid, data');
            foreach ($recs AS $rec) {
                if ($curcontext != $rec->contextid) {
                    if (!empty($vals)) {
                        sort($vals);
                        if ($vals == $fielddata) {
                            $errstr = get_string('valuealreadyused');
                            // TBD^^^ "[These/This combination of] values already uesd!"
                            $vals = null;
                            break;
                        }
                    }
                    $curcontext = $rec->contextid;
                    $vals = array();
                }
                $vals[] = $rec->data;
            }
            unset($recs);

            if (!empty($vals)) {
                sort($vals);
                if ($vals == $fielddata) {
                    $errstr = get_string('valuealreadyused');
                    // TBD^^^ "[These/This combination of] values already uesd!"
                }
            }
        }
    } else if ($field->forceunique) {
        // NON-MULTIVALUED case
        $datafield = 'data';
        if ($field->data_type() == 'text') {
            //error_log("manual_field_validation(): field({$field->id}), datafield = {$datafield}, type = ". $field->data_type() );
            $datafield = $DB->sql_compare_text('data', 255); // TBV
        }
        $where = "fieldid = ? AND {$datafield} = ?";
        $fielddata = $DB->get_recordset_select($field->data_table(), $where, array($field->id, $data));
        $fcount = $DB->count_records_select($field->data_table(), $where, array($field->id, $data));
        if (!empty($fielddata) && $fielddata->valid()) {
            $fdata = $fielddata->current();
            if ($fcount > 1 || $fdata->contextid != $contextid) {
                $errstr = get_string('valuealreadyused');
            }
            $fielddata->close();
        }
    }

    return $errstr;
}

// Helper function
function trim_cr(&$item, $key) {
    $item = trim($item, "\r\n");
}

