<?php

defined('MOODLE_INTERNAL') || die();

require_once elis::plugin_file('elisfields_manual', 'custom_fields.php');

/**
 * Adds an appropriate editing control to the provided form
 *
 * @param  moodleform or HTML_QuickForm  $form   The form to add the appropriate element to
 * @param  field                         $field  The definition of the field defining the controls
 */
function textarea_control_display($form, $mform, $customdata, $field) {
    if (!($form instanceof moodleform)) {
        $mform = $form;
        $form->_customdata = null;
    }

    $fieldname = "field_{$field->shortname}";
    $manual = new field_owner($field->owners['manual']);
    $mform->addElement('htmleditor', $fieldname, $field->name, "cols=\"{$manual->param_columns}\" rows=\"{$manual->param_rows}\"");
    $mform->setType($fieldname, PARAM_CLEAN);
    manual_field_add_help_button($mform, $fieldname, $field);
}

function textarea_control_get_value($data, $field) {
    $name = "field_{$field->shortname}";
    return $data->$name;
}

?>
