<?php //$Id$

require_once($CFG->dirroot.'/elis/core/lib/filtering/lib.php');

/**
 * Generic filter based on a list of values.
 */
class generalized_filter_simpleselect extends generalized_filter_type {
    /**
     * options for the list values
     */
    var $_options = array();

    var $_field;
    var $_numeric  = false;
    var $_anyvalue = null;
    var $_noany    = false;
    var $_onchange = '';
    var $_multiple = '';
    var $_class    = '';

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function generalized_filter_simpleselect($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced,
                    !empty($options['help'])
                    ? $options['help']
                    : array('simpleselect', $label, 'filters'));
        $this->_field    = $field;

        $extrafields = array(
            '_options' => 'choices',
            '_numeric' => 'numeric',
            '_anyvalue' => 'anyvalue',
            '_noany' => 'noany',
            '_onchange' => 'onchange',
            '_multiple' => 'multiple',
            '_class'   => 'class'
            );

        if (! is_array($options)) {
            $options = array($options);
        }

        foreach ($extrafields as $var => $extra) {
            if (array_key_exists($extra, $options)) {
                $this->$var = $options[$extra];
            }
        }
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        if (!$this->_noany) {
            if (!empty($this->_anyvalue)) {
                $choices = array(''=>$this->_anyvalue) + $this->_options;
            } else {
                $choices = array(''=>get_string('anyvalue', 'filters')) + $this->_options;
            }
        } else {
            $choices = $this->_options;
        }

        $options = array();
        if (! empty($this->_onchange)) {
            $options['onchange'] = $this->_onchange;
        }
        if (! empty($this->_multiple)) {
            $options['multiple'] = $this->_multiple;
        }

        $mform->addElement('select', $this->_uniqueid, $this->_label, $choices, $options);
        $mform->setHelpButton($this->_uniqueid, $this->_filterhelp);
        if ($this->_advanced) {
            $mform->setAdvanced($this->_uniqueid);
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field = $this->_uniqueid;

        if (array_key_exists($field, $formdata)) {
            $value = $formdata->$field;
            if ($this->_multiple && is_array($value)) {
                foreach ($value as $val) {
                    if ($val === '') {
                        return false;
                    }
                }
                return array('value'=>$value);
            } else if ($value !== '') {
                return array('value'=>(string)$value);
            }
        }

        return false;
    }

    function get_report_parameters($data) {
        return array('value' => $data['value'],
                     'numeric' => $this->_numeric);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $value = is_array($data['value']) ? $data['value']
                                          : array($data['value']);

        $a = new object();
        $a->label = $this->_label;
        $a->value = '';
        foreach ($value as $val) {
            if (!empty($a->value)) {
                $a->value .= ' '. get_string('or', 'elis_core') .' ';
            }
            $a->value .= '"'. s($this->_options[$val]) .'"';
        }
        $a->operator = get_string('isequalto','filters');

        return get_string('selectlabel', 'filters', $a);
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) { // for manual filter use
            return null;
        }

        $value = $data['value'];

        if (is_array($value) && (sizeof($value) == 1)) {
            $value = reset($value);
        }

        if ($this->_multiple && is_array($value)) {
            foreach ($value as $key => $val) {
                $val = addslashes($val);
                if (!empty($this->_numeric)) {
                    $val = "'$val'";
                }
                $value[$key] = $val;
            }

            return "{$full_fieldname} IN (". implode(',', $value) .')';
        } else {
            $value = addslashes($value);

            if(!empty($this->_numeric)) {
                return "{$full_fieldname} = {$value}";
            } else {
                return "{$full_fieldname} = '{$value}'";
            }
        }
    }

}

