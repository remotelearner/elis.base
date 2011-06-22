<?php //$Id$

require_once($CFG->libdir.'/formslib.php');

/**
 * User filtering wrapper class.
 */
class generalized_filtering {
    var $_fields;
    var $_addform;
    var $_activeform;
    var $_id;

    /**
     * Contructor
     * @param array array of visible user fields
     * @param string base url used for submission/return, null if the same of current page
     * @param array extra page parameters
     */
    function generalized_filtering($fields=null, $baseurl=null, $extraparams=null, $id=0) {
        global $SESSION;

        $this->_id = $id;

        $form_prefix = '';
        if(!empty($id)) {
            $form_prefix = 'report_instance_prefix' . $id;
        }

        if(!empty($this->_id)) {
            $filtering_array =& $SESSION->user_index_filtering[$this->_id];
        } else {
            $filtering_array =& $SESSION->user_filtering;
        }

        if (!isset($filtering_array)) {
            $filtering_array = array();
        }

        if (empty($fieldnames)) {
            $fieldnames = array();
        }

        $this->_fields  = array();

        foreach ($fields as $field) {
            if ($field_object = $this->get_field($field->uniqueid, $field->tablealias, $field->fieldname, $field->advanced, $field->displayname, $field->type, $field->options)) {
                //$this->_fields[$field->fieldname] = $field_object;
                $this->_fields[$field->uniqueid] = $field_object;
            }

        }

        // first the new filter form
        $this->_addform = new user_add_filter_form($baseurl, array('fields'=>$this->_fields, 'extraparams'=>$extraparams), 'post', '', array('formprefix' => $form_prefix));
        if ($adddata = $this->_addform->get_data(false)) {
            foreach($this->_fields as $fname=>$field) {
                $data = $field->check_data($adddata);
                if ($data === false) {
                    continue; // nothing new
                }
                if (!array_key_exists($fname, $filtering_array)) {
                    $filtering_array[$fname] = array();
                }
                $filtering_array[$fname][] = $data;
            }
            // clear the form
            $_POST = array();
            $this->_addform = new user_add_filter_form($baseurl, array('fields'=>$this->_fields, 'extraparams'=>$extraparams), 'post', '', array('formprefix' => $form_prefix));
        }

        // now the active filters

        $this->_activeform = new user_multi_active_filter_form($baseurl, array('fields'=>$this->_fields, 'extraparams'=>$extraparams, 'id' => $this->_id), 'post', '', array('formprefix' => $form_prefix));
        if ($adddata = $this->_activeform->get_data(false)) {
            if (!empty($adddata->removeall)) {
                $filtering_array = array();

            } else if (!empty($adddata->removeselected) and !empty($adddata->filter)) {
                foreach($adddata->filter as $fname=>$instances) {
                    foreach ($instances as $i=>$val) {
                        if (empty($val)) {
                            continue;
                        }
                        unset($filtering_array[$fname][$i]);
                    }
                    if (empty($filtering_array[$fname])) {
                        unset($filtering_array[$fname]);
                    }
                }
            }
            // clear+reload the form
            $_POST = array();
            $this->_activeform = new user_multi_active_filter_form($baseurl, array('fields'=>$this->_fields, 'extraparams'=>$extraparams, 'id' => $this->_id), 'post', '', array('formprefix' => $form_prefix));
        }
        // now the active filters
    }

    /**
     * Creates known user filter if present
     * @param string $fieldname
     * @param boolean $advanced
     * @return object filter
     */
    function get_field($uniqueid, $tablealias, $fieldname, $advanced, $displayname, $type, $options) {
        global $USER, $CFG, $SITE;

        if (file_exists($CFG->dirroot . '/curriculum/lib/filtering/' . $type . '.php')) {
            require_once($CFG->dirroot . '/curriculum/lib/filtering/' . $type . '.php');
        }

        $classname = 'generalized_filter_' . $type;

        return new $classname ($uniqueid, $tablealias, $fieldname, $displayname, $advanced, $fieldname, $options);
    }

    function get_report_parameters() {
        global $SESSION;

        if(!empty($this->_id)) {
            $filtering_array =& $SESSION->user_index_filtering[$this->_id];
        } else {
            $filtering_array =& $SESSION->user_filtering;
        }

        $parameters = array();

        if (!empty($filtering_array)) {
            foreach ($filtering_array as $fname=>$datas) {
                if (!array_key_exists($fname, $this->_fields)) {
                    continue; // filter not used
                }
                $field = $this->_fields[$fname];
                foreach($datas as $i=>$data) {
                    $parameter_object = new stdClass;
                    $parameter_object->alias = $field->_alias;
                    $parameter_object->field = $field->_field;
                    $parameter_object->type = substr(get_class($field), strlen('generalized_filter_'));
                    $parameter_object->parameters = $field->get_report_parameters($data);
                    $parameters[] = $parameter_object;
                }
            }
        }

        return $parameters;
    }

    /**
     * Returns sql where statement based on active user filters
     * @param string $extra sql
     * @return string
     */
    function get_sql_filter($extra='', $exceptions = array()) {
        global $SESSION;

        if(!empty($this->_id)) {
            $filtering_array =& $SESSION->user_index_filtering[$this->_id];
        } else {
            $filtering_array =& $SESSION->user_filtering;
        }

        $sqls = array();
        if ($extra != '') {
            $sqls[] = $extra;
        }

        if (!empty($filtering_array)) {
            foreach ($filtering_array as $fname=>$datas) {
                if (!array_key_exists($fname, $this->_fields) || in_array($fname, $exceptions)) {
                    continue; // filter not used
                }
                $field = $this->_fields[$fname];
                foreach($datas as $i=>$data) {
                    $sqlfilter = $field->get_sql_filter($data);
                    if ($sqlfilter != null) {
                        $sqls[] = $sqlfilter;
                    }
                }
            }
        }

        if (empty($sqls)) {
            return '';
        } else {
            return implode(' AND ', $sqls);
        }
    }

    /**
     * Print the add filter form.
     */
    function display_add() {
        $this->_addform->display();
    }

    /**
     * Print the active filter form.
     */
    function display_active() {
        $this->_activeform->display();
    }

}

/**
 * The base user filter class. All abstract classes must be implemented.
 */
abstract class generalized_filter_type {

    var $_uniqueid;

    var $_alias;

    /**
     * The name of this filter instance.
     */
    var $_name;

    /**
     * The label of this filter instance.
     */
    var $_label;

    /**
     * Advanced form element flag
     */
    var $_advanced;

    /**
     * Help array to pass to setHelpButton() in filters' setupForm()
     */
    var $_filterhelp;

    /**
     * Mode in which the parent object is being executed
     */
    var $execution_mode;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     */
    function generalized_filter_type($uniqueid, $alias, $name, $label, $advanced, $help = null) {
        $this->_uniqueid = $uniqueid;
        $this->_alias    = $alias;
        $this->_name     = $name;
        $this->_label    = $label;
        $this->_advanced = $advanced;
        $this->_filterhelp = $help;
    }

    function get_full_fieldname() {
        if(empty($this->_alias)) {
            return $this->_name;
        } else {
            return $this->_alias . '.' . $this->_name;
        }
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    abstract function get_sql_filter($data);

    abstract function get_report_parameters($data);

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    abstract function check_data($formdata);

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    abstract function setupForm(&$mform);

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        error('Abstract method get_label() called - must be implemented');
    }

    /**
     * Sets a constant representing the mode in which the parent object is being run
     *
     * @param  int  $execution_mode  The appropriate execution mode constant
     */
    function set_execution_mode($execution_mode) {
        $this->execution_mode = $execution_mode;
    }

    /**
     * Retrieves the constant representing the mode in which the parent object is being run
     *
     * @param  int  The apppropriate execution mode constant
     */
    function get_execution_mode() {
        return $this->execution_mode;
    }
}

class generalized_filter_entry {
    public $uniqueid;
    public $tablealias;
    public $fieldname;
    public $displayname;
    public $advanced;
    public $type;
    public $options;

    function generalized_filter_entry($uniqueid, $tablealias, $fieldname, $displayname, $advanced, $type, $options=array()) {
        $this->uniqueid = $uniqueid;
        $this->tablealias = $tablealias;
        $this->fieldname = $fieldname;
        $this->displayname = $displayname;
        $this->advanced = $advanced;
        $this->type = $type;
        $this->options = $options;
    }

}

/**
 * This class adds support for having multiple filters active
 * at the same time
 */
class user_multi_active_filter_form extends moodleform {

    function definition() {
        global $SESSION; // this is very hacky :-(

        if(!empty($this->_customdata['id'])) {
            $filtering_array =& $SESSION->user_index_filtering[$this->_customdata['id']];
        } else {
            $filtering_array =& $SESSION->user_filtering;
        }

        $mform       =& $this->_form;
        $fields      = $this->_customdata['fields'];
        $extraparams = $this->_customdata['extraparams'];

        if (!empty($filtering_array)) {
            // add controls for each active filter in the active filters group
            $mform->addElement('header', 'actfilterhdr', get_string('actfilterhdr','filters'));

            foreach ($filtering_array as $fname=>$datas) {
                if (!array_key_exists($fname, $fields)) {
                    continue; // filter not used
                }
                $field = $fields[$fname];
                foreach($datas as $i=>$data) {
                    $description = $field->get_label($data);
                    $mform->addElement('checkbox', 'filter['.$fname.']['.$i.']', null, $description);
                }
            }

            if ($extraparams) {
                foreach ($extraparams as $key=>$value) {
                    $mform->addElement('hidden', $key, $value);
                    $mform->setType($key, PARAM_RAW);
                }
            }

            $objs = array();
            $objs[] = &$mform->createElement('submit', 'removeselected', get_string('removeselected','filters'));
            $objs[] = &$mform->createElement('submit', 'removeall', get_string('removeall','filters'));
            $mform->addElement('group', 'actfiltergrp', '', $objs, ' ', false);
        }
    }
}
