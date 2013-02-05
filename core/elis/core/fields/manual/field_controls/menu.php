<?php

defined('MOODLE_INTERNAL') || die();

require_once elis::plugin_file('elisfields_manual', 'custom_fields.php');

/**
 * Adds an appropriate editing control to the provided form
 *
 * @param  moodleform or HTML_QuickForm  $form       The form to add the appropriate element to
 * @param  field                         $field      The definition of the field defining the controls
 * @param  boolean                       $as_filter  Whether to display a "choose" message
 * @param  string                        $contextname Optional context name/entity
 */
function menu_control_display($form, $mform, $customdata, $field, $as_filter = false, $contextname= 'system') {
    if (!($form instanceof moodleform)) {
        $mform = $form;
        $form->_customdata = null;
        $customdata = null;
    }

    $manual = new field_owner($field->owners['manual']);
    if ($field->datatype != 'bool') {
        if (!isset($manual->param_options_source) || $manual->param_options_source == '') {
            $tmpoptions = explode("\n", $manual->param_options);
            if ($as_filter) {
                $options = array('' => get_string("choose"));
            }
            foreach($tmpoptions as $key => $option) {
                $option = trim($option, "\n\r");
                $option = format_string($option);
                $options[$option] = $option;//multilang formatting
            }
        } else {
            $options = array();
            $source = $manual->param_options_source;
            $srcfile = elis::plugin_file('elisfields_manual', "sources/{$source}.php");
            if (file_exists($srcfile)) {
                require_once elis::plugin_file('elisfields_manual', 'sources.php');
                require_once($srcfile) ;
                $classname = "manual_options_{$source}";
                $plugin = new $classname();
                if ($plugin && $plugin->is_applicable($contextname)) {
                    $options = $plugin->get_options($customdata);
                }
            }
        }
    } else {
        if ($as_filter) {
            $options = array('' => get_string("choose"),
                             0 => get_string('no'),
                             1 => get_string('yes'));
        } else {
            $options = array(0 => get_string('no'), 1 => get_string('yes'));
        }
    }
    $menu = $mform->addElement('select', "field_{$field->shortname}", $field->name, $options);
    if ($field->multivalued && !$as_filter) {
        $menu->setMultiple(true);
    }
    manual_field_add_help_button($mform, "field_{$field->shortname}", $field);
}

function menu_control_get_value($data, $field) {
    $name = "field_{$field->shortname}";
    return $data->$name;
}

?>
