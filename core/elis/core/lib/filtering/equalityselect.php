<?php //$Id$

require_once($CFG->dirroot.'/elis/core/lib/filtering/lib.php');

/**
 * Generic filter based on a list of values.
 */
class generalized_filter_equalityselect extends generalized_filter_type {

    static $OPERATOR_IS_ANY_VALUE = 0;
    static $OPERATOR_IS_EQUAL_TO = 1;
    static $OPERATOR_NOT_EQUAL_TO = 2;

    /**
     * options for the list values
     */
    var $_options;

    var $_field;

    var $_default;

    var $_numeric;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     * @param mixed $default option
     */
    function generalized_filter_equalityselect($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        global $CFG;

        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced,
                    !empty($options['help'])
                    ? $options['help'] : array('select', $label, 'filters'));
        $this->_field   = $field;

        $choices = $options['choices'];

        $this->_options = $choices;
        $this->_default = $options['default'];
        $this->_numeric = $options['numeric'];
    }

    /**
     * Returns an array of comparison operators
     * @return array of comparison operators
     */
    function get_operators() {
        return array(generalized_filter_equalityselect::$OPERATOR_IS_ANY_VALUE => get_string('isanyvalue','filters'),
                     generalized_filter_equalityselect::$OPERATOR_IS_EQUAL_TO            => get_string('isequalto','filters'),
                     generalized_filter_equalityselect::$OPERATOR_NOT_EQUAL_TO => get_string('isnotequalto','filters'));
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        $objs = array();
        $objs[] =& $mform->createElement('select', $this->_uniqueid.'_op', null, $this->get_operators());
        $objs[] =& $mform->createElement('select', $this->_uniqueid, null, $this->_options);
        $grp =& $mform->addElement('group', $this->_uniqueid.'_grp', $this->_label, $objs, '', false);
        $grp->setHelpButton($this->_filterhelp);
        $mform->disabledIf($this->_uniqueid, $this->_uniqueid.'_op', 'eq', 0);
        if (!is_null($this->_default)) {
            $mform->setDefault($this->_uniqueid, $this->_default);
        }
        if ($this->_advanced) {
            $mform->setAdvanced($this->_uniqueid.'_grp');
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field    = $this->_uniqueid;
        $operator = $field.'_op';

        if (array_key_exists($field, $formdata) and !empty($formdata->$operator)) {
            return array('operator' => (int)$formdata->$operator,
                         'value'    => (string)$formdata->$field);
        }

        return false;
    }

    function get_report_parameters($data) {

        $return_value = array('operator' => $data['operator'],
                              'value' => $data['value'],
                              'numeric' => $this->_numeric);

        return $return_value;
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $operators = $this->get_operators();
        $operator  = $data['operator'];
        $value     = $data['value'];

        if (empty($operator)) {
            return '';
        }

        $a = new object();
        $a->label    = $this->_label;
        $a->value    = '"'.s($this->_options[$value]).'"';
        $a->operator = $operators[$operator];

        return get_string('selectlabel', 'filters', $a);
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        $full_fieldname = $this->get_full_fieldname();

        $value = addslashes($data['value']);
        if(empty($data['numeric'])) {
            $value = "'{$value}'";
        }

        if($data['operator'] == generalized_filter_equalityselect::$OPERATOR_IS_EQUAL_TO) {
            return "{$full_fieldname} = {$value}";
        } else if($data['operator'] == generalized_filter_equalityselect::$OPERATOR_NOT_EQUAL_TO) {
            return "{$full_fieldname} <> {$value}";
        } else {
            //error call
            print_error('invalidoperator', 'block_php_report');
        }

    }

}

