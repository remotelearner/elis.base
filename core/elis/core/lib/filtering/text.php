<?php //$Id$

require_once($CFG->dirroot.'/elis/core/lib/filtering/lib.php');

/**
 * Generic filter for text fields.
 */
class generalized_filter_text extends generalized_filter_type {

    static $OPERATOR_CONTAINS = 0;
    static $OPERATOR_DOES_NOT_CONTAIN = 1;
    static $OPERATOR_IS_EQUAL_TO = 2;
    static $OPERATOR_STARTS_WITH = 3;
    static $OPERATOR_ENDS_WITH = 4;
    static $OPERATOR_IS_EMPTY = 5;

    var $_field;

    /**
     * Constructor
     * @param string $alias aliacs for the table being filtered on
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     */
    function generalized_filter_text($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced,
                    !empty($options['help'])
                    ? $options['help'] : array('text', $label, 'filters'));
        $this->_field = $field;
    }

    /**
     * Returns an array of comparison operators
     * @return array of comparison operators
     */
    function getOperators() {
        return array(0 => get_string('contains', 'filters'),
                     1 => get_string('doesnotcontain','filters'),
                     2 => get_string('isequalto','filters'),
                     3 => get_string('startswith','filters'),
                     4 => get_string('endswith','filters'),
                     5 => get_string('isempty','filters'));
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        $objs = array();
        $objs[] =& $mform->createElement('select', $this->_uniqueid.'_op', null, $this->getOperators());
        $objs[] =& $mform->createElement('text', $this->_uniqueid, null);
        $grp =& $mform->addElement('group', $this->_uniqueid.'_grp', $this->_label, $objs, '', false);
        $grp->setHelpButton($this->_filterhelp);
        $mform->disabledIf($this->_uniqueid, $this->_uniqueid.'_op', 'eq', 5);
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

        if (array_key_exists($operator, $formdata)) {
            if ($formdata->$operator != 5 and empty($formdata->$field)) {
                // no data - no change except for empty filter
                return false;
            }
            $value = empty($formdata->$field) ? '' : $formdata->$field;
            return array('operator'=>(int)$formdata->$operator, 'value'=>$value);
        }

        return false;
    }

    function get_report_parameters($data) {

        $return_value = array('operator' => $data['operator']);

        if(!empty($data['value'])) {
            $return_value['value'] = $data['value'];
        }

        return $return_value;
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $operator  = $data['operator'];
        $value     = $data['value'];
        $operators = $this->getOperators();

        $a = new object();
        $a->label    = $this->_label;
        $a->value    = '"'.s($value).'"';
        $a->operator = $operators[$operator];


        switch ($operator) {
            case generalized_filter_text::$OPERATOR_CONTAINS: // contains
            case generalized_filter_text::$OPERATOR_DOES_NOT_CONTAIN: // doesn't contain
            case generalized_filter_text::$OPERATOR_IS_EQUAL_TO: // equal to
            case generalized_filter_text::$OPERATOR_STARTS_WITH: // starts with
            case generalized_filter_text::$OPERATOR_ENDS_WITH: // ends with
                return get_string('textlabel', 'filters', $a);
            case generalized_filter_text::$OPERATOR_IS_EMPTY: // empty
                return get_string('textlabelnovalue', 'filters', $a);
        }

        return '';
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        $full_fieldname = $this->get_full_fieldname();

        $value = addslashes($data['value']);

        switch($data['operator']) {
            case generalized_filter_text::$OPERATOR_CONTAINS:
                //contains
                return "{$full_fieldname} LIKE '%{$value}%'";
                break;
            case generalized_filter_text::$OPERATOR_DOES_NOT_CONTAIN:
                //does not contain
                return "{$full_fieldname} NOT LIKE '%{$value}%'";
                break;
            case generalized_filter_text::$OPERATOR_IS_EQUAL_TO:
                //equals
                return "{$full_fieldname} = '{$value}'";
                break;
            case generalized_filter_text::$OPERATOR_STARTS_WITH:
                //starts with
                return "{$full_fieldname} LIKE '{$value}%'";
                break;
            case generalized_filter_text::$OPERATOR_ENDS_WITH:
                //ends with
                return "{$full_fieldname} LIKE '%{$value}'";
                break;
            case generalized_filter_text::$OPERATOR_IS_EMPTY:
                return "{$full_fieldname} = ''";
                break;
            default:
                //error call
                print_error('invalidoperator', 'block_php_report');
        }
    }

}
