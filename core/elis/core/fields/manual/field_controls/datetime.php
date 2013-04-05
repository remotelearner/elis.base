<?php

defined('MOODLE_INTERNAL') || die();

require_once elis::plugin_file('elisfields_manual', 'custom_fields.php');

/**
 * Adds an appropriate editing control to the provided form
 *
 * @param  moodleform or HTML_QuickForm  $form       The form to add the appropriate element to
 * @param  field                         $field      The definition of the field defining the controls
 * @param  boolean                       $as_filter  Whether to display a "choose" message
 */
function datetime_control_display($form, $mform, $customdata, $field, $as_filter=false) {
    if (!($form instanceof moodleform)) {
        $mform = $form;
        $form->_customdata = null;
    }

    $manual = new field_owner($field->owners['manual']);
    $mform->addElement($manual->param_inctime ? 'date_time_selector'
                                              : 'date_selector',
                "field_{$field->shortname}", $field->name,
                array('startyear' => $manual->param_startyear,
                      'stopyear' => $manual->param_stopyear,
                      'optional' => false)); // TBD!?!
    manual_field_add_help_button($mform, "field_{$field->shortname}", $field);
}

function datetime_control_get_value($data, $field) {
    $name = "field_{$field->shortname}";
    return $data->$name;
}

