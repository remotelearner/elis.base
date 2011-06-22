<?php //$Id$

require_once($CFG->dirroot.'/elis/core/lib/filtering/lib.php');
require_once($CFG->dirroot.'/elis/core/lib/filtering/simpleselect.php');

/**
 * Generic yes/no filter with radio buttons for integer fields.
 */
class generalized_filter_yesno extends generalized_filter_simpleselect {

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     */
    function generalized_filter_yesno($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        $options['choices'] = array(0 => get_string('no'),
                                    1 => get_string('yes'));

        parent::generalized_filter_simpleselect($uniqueid, $alias, $name, $label, $advanced, $field, $options);
    }
}
