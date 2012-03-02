<?php //$Id$

require_once($CFG->dirroot.'/elis/core/lib/filtering/lib.php');

/**
 * Generic filter based on a date.
 */
class generalized_filter_date extends generalized_filter_type {
    /**
     * the fields available for comparisson
     */

    var $_field;


    /**
     * Boolean set from options['never_included'],
     */
    var $_never_included = false; // TBD: default

    /**
     * Constructor
     * @param string $alias aliacs for the table being filtered on
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     */
    function generalized_filter_date($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced,
                     !empty($options['help'])
                     ? $options['help'] : array('date', $label, 'filters'));

        $this->_field = $field;
        if (isset($options['never_included'])) {
            $this->_never_included = $options['never_included'];
        }
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        $objs = array();

        $objs[] =& $mform->createElement('advcheckbox', $this->_uniqueid.'_sck', null, get_string('isafter', 'filters'), null, array('0', '1'));
        $objs[] =& $mform->createElement('date_selector', $this->_uniqueid.'_sdt', null, array('optional' => false));
        $objs[] =& $mform->createElement('static', $this->_uniqueid.'_break', null, '<br/>');
        $objs[] =& $mform->createElement('advcheckbox', $this->_uniqueid.'_eck', null, get_string('isbefore', 'filters'), null, array('0', '1'));
        $objs[] =& $mform->createElement('date_selector', $this->_uniqueid.'_edt', null, array('optional' => false));

        if ($this->_never_included) {
            $objs[] = & $mform->createElement('advcheckbox', $this->_uniqueid.'_never', null, get_string('includenever', 'filters'));
        }

        $grp =& $mform->addElement('group', $this->_uniqueid.'_grp', $this->_label, $objs, '', false);
        $grp->setHelpButton($this->_filterhelp);

        if ($this->_advanced) {
            $mform->setAdvanced($this->_uniqueid.'_grp');
        }

        $mform->disabledIf($this->_uniqueid.'_sdt[day]', $this->_uniqueid.'_sck', '0');
        $mform->disabledIf($this->_uniqueid.'_sdt[month]', $this->_uniqueid.'_sck', '0');
        $mform->disabledIf($this->_uniqueid.'_sdt[year]', $this->_uniqueid.'_sck', '0');
        $mform->disabledIf($this->_uniqueid.'_edt[day]', $this->_uniqueid.'_eck', '0');
        $mform->disabledIf($this->_uniqueid.'_edt[month]', $this->_uniqueid.'_eck', '0');
        $mform->disabledIf($this->_uniqueid.'_edt[year]', $this->_uniqueid.'_eck', '0');

        if ($this->_never_included) {
            $mform->disabledIf($this->_uniqueid.'_never', $this->_uniqueid.'_eck', '0');
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $sck = $this->_uniqueid.'_sck';
        $sdt = $this->_uniqueid.'_sdt';
        $eck = $this->_uniqueid.'_eck';
        $edt = $this->_uniqueid.'_edt';
        $never = $this->_uniqueid.'_never';

        if (!array_key_exists($sck, $formdata) and !array_key_exists($eck, $formdata)) {
            return false;
        }

        $data = array();
        if (array_key_exists($sck, $formdata) && !empty($formdata->$sck)) {
            $data['after'] = $formdata->$sdt;
        } else {
            $data['after'] = 0;
        }
        if (array_key_exists($eck, $formdata) && !empty($formdata->$eck)) {
            $data['before'] = $formdata->$edt;
        } else {
            $data['before'] = 0;
        }
        if (array_key_exists($never, $formdata) && !empty($formdata->$never)) {
            $data['never'] = $formdata->$never;
        } else {
            $data['never'] = 0;
        }

        return $data;
    }

    function get_report_parameters($data) {

        return array('after' => $data['after'],
                     'before' => $data['before'],
                     'never' => $data['never']);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $after  = $data['after'];
        $before = $data['before'];
        $never = $data['never'];
        $field  = $this->_field;

        $a = new object();
        $a->label  = $this->_label;
        $a->after  = userdate($after);
        $a->before = userdate($before);

        if ($never) {
            $strnever = ' ('.get_string('includenever', 'filters').')';
        } else {
            $strnever = '';
        }

        if ($after and $before) {
            return get_string('datelabelisbetween', 'filters', $a).$strnever;

        } else if ($after) {
            return get_string('datelabelisafter', 'filters', $a).$strnever;;

        } else if ($before) {
            return get_string('datelabelisbefore', 'filters', $a).$strnever;;
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

        if (empty($full_fieldname)) {
            return null;
        }

        if ($this->_never_included) { // TBD
            if(!empty($data['never'])) {
                $sql = "{$full_fieldname} >= 0";
            } else {
                $sql = "{$full_fieldname} > 0";
            }
        } else {
            $sql = 'TRUE';
        }

        if(!empty($data['after'])) {
            $sql .= " AND {$full_fieldname} >= {$data['after']}";
        }

        if(!empty($data['before'])) {
            $sql .= " AND {$full_fieldname} <= {$data['before']}";
        }

        return $sql;
    }

}
?>
