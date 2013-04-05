<?php

defined('MOODLE_INTERNAL') || die();

require_once elis::plugin_file('elisfields_manual', 'custom_fields.php');

/**
 * Adds an appropriate editing control to the provided form
 *
 * @param  moodleform or HTML_QuickForm  $form   The form to add the appropriate element to
 * @param  field                         $field  The definition of the field defining the controls
 */
function text_control_display($form, $mform, $customdata, $field) {
    if (!($form instanceof moodleform)) {
        $mform = $form;
        $form->_customdata = null;
    }

    $param = '';
    if (isset($field->owners['manual'])) {
        $manual = new field_owner($field->owners['manual']);
        if (isset($manual->param_maxlength) && isset($manual->param_columns)) {
            $param = "maxlength=\"{$manual->param_maxlength}\" size=\"{$manual->param_columns}\"";
        }
    }
    $fieldname = "field_{$field->shortname}";
    $mform->addElement('text', $fieldname, $field->name, $param);
    $mform->setType($fieldname, PARAM_MULTILANG);
    manual_field_add_help_button($mform, $fieldname, $field);
}

function text_control_get_value($data, $field) {
    $name = "field_{$field->shortname}";
    return $data->$name;
}

?>
