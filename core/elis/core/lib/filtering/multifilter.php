<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    elis
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

// Include the simple filter types that will be returned by an object of this type.
require_once($CFG->dirroot.'/curriculum/lib/filtering/simpleselect.php');
require_once($CFG->dirroot.'/curriculum/lib/filtering/text.php');
require_once($CFG->dirroot.'/curriculum/lib/filtering/date.php');

/**
 * Base class for multi-filter filters.
 *
 * A multi-filter is any filter which returns a group of related filter objects.  The primary
 * example of this filter who be a user profile filter which displays all of the profile fields
 * as individual filter elements.  In fact, this base class is derived from just such a filter.
 */

// BJB110330: php_report block upgrade increased size from 50 to 255
define('MAX_FILTER_SUFFIX_LEN', 30); // was 6 before DB upgrade

/**
 * Generic multifilter class - standalone, no parent!
 */
class generalized_filter_multifilter {
    /**
     * Class contants: required sub-filter types
     */
    const filtertypetext     = 'text';
    const filtertypeselect   = 'simpleselect'; // any + choices
    const filtertypedate     = 'date';

    const filtertypecountry  = 'simpleselect'; // any, equal, not equal
    const filtertypetristate = 'simpleselect'; // any, No, Yes

    const filtertype_custom_field_select = 'custom_field_select';
    const filtertype_custom_field_text   = 'custom_field_text';

    // This array should map each field to a filter type
    protected $fieldtofiltermap = array();

    // The default map from fields to label names
    protected $labels = array();

    // The default tables
    protected $tables = array();

    // Data type map for custom fields.
    protected $datatypemap = array(
        'checkbox'  => self::filtertype_custom_field_select,
        'menu'      => self::filtertype_custom_field_select,
        'password'  => self::filtertype_custom_field_text,  // Shouldn't be used!
        'text'      => self::filtertype_custom_field_text,
        'textarea'  => self::filtertype_custom_field_text,
    );

    protected $selects = array(
        self::filtertype_custom_field_select => 1,
        self::filtertypeselect               => 1,
    );

    // The field-based filters, the field id keyes the array, and the value is a filter object
    protected $_filters;

    // to store/check truncated field names used for filterids
    protected $_shortfields = array();

    // to store custom choices
    protected $_choices = array();

    // to store custom choices
    protected $_fields = array();

    // This is the field to be returned by the custom profile fields.
    protected $_innerfield = array('default' => 'id');

    // This is the field to compare the custom profile field against.
    protected $_outerfield = array('default' => 'id');

    // This wrapper is an SQL fragment to relate the custom profile to a specific table.
    protected $_wrapper    = array('default' => '');

    protected $_optionals = array(
        '_heading'     => 'heading',
        '_footer'      => 'footer',
        'languagefile' => 'langfile',
        '_innerfield'  => 'innerfield',
        '_outerfield'  => 'outerfield',
        '_wrapper'     => 'wrapper',
    );

    // Stores section related data
    protected $sections = array();

    /**
     * Constructor
     *
     * @param string $uniqueid Unique prefix for filters
     * @param string $label    Filter label
     * @param array  $options  Filter options (see above)
     */
    function multifilter($uniqueid, $label, $options) {
        $this->_uniqueid = $uniqueid;
        $this->_label = $label;

        foreach ($this->_optionals as $field => $option) {

            if (!empty($options[$option])) {
                $this->$field = $options[$option];
            }
        }
    }


    /**
     * Method to return all sub-filters as array
     *
     * @return array of sub-filters
     */
    function get_filters() {
        $results = array();
        foreach ($this->_filters as $group => $filters) {
            foreach ($filters as $filter) {
                if (!empty($filter)) {
                    $results[] = $filter;
                }
            }
        }
        return $results;
    }

    /**
     * Is associative array
     *
     * Test whether an array is associative
     *
     * @param array $a The array to test
     * @return bool True/false whether the array is associative
     */
    function is_assoc_array($a) {
        return (is_array($a) &&
                (count($a) == 0 ||
                 0 !== count(array_diff_key($a, array_keys(array_keys($a)))))
        );
    }

    /**
     * Err dump
     *
     * @param object $obj  The object to be dumped
     * @param string $name The name of the object to be dumped
     */
    function err_dump($obj, $name = '') {
        ob_start();
        var_dump($obj);
        $tmp = ob_get_contents();
        ob_end_clean();
        error_log('err_dump:: '.$name." = {$tmp}");
    }

    /**
     * Check field name to see if it's a duplicate
     *
     * @param $name
     */
    function record_short_field_name($name) {
        $shortfieldname = substr($name, 0, MAX_FILTER_SUFFIX_LEN);

        if (in_array($shortfieldname, $this->_shortfields)) {
            error_log("generalized_filter_curriculumclass::non-unique field name: '{$shortfieldname}' - modify code!");
        } else {
            $this->_shortfields[] = $shortfieldname;
        }
    }

    /**
     * Get custom fields
     *
     * This function returns an array of custom field names to labels, and has a few side effects
     * that set up data to use the custom fields later.  The side effects reduce the number of
     * database lookups required to generate the form.
     *
     * @param array $fields An array of db records representing custom fields
     * @return array Custom field names mapped to labels.
     */
    function get_custom_fields($group, $fields) {
        $yesno = array(1 => get_string('yes'), 0 => get_string('no'));
        // Array $xoptions to append to existing options['choices']
        $options = array();

        if (! is_array($fields)) {
            $fields = array();
        }

        foreach ($fields as $field) {
            $field = new field($field);

            $field_identifier = 'customfield-'.$field->id;

            $this->_fields[$field_identifier] = $field;

            $this->record_short_field_name($field_identifier);
            $this->labels[$group][$field_identifier] = $field->name;
            $options[$field_identifier] = $field->name;

            if (!isset($field->owners['manual'])) {
                continue;
            }
            $owner = new field_owner($field->owners['manual']);
            $params = unserialize($owner->params);

            $this->fieldtofiltermap[$group][$field_identifier] = $this->datatypemap[$params['control']];

             switch ($params['control']) {
                case 'checkbox':
                    $this->_choices[$field_identifier] = $yesno;
                    break;

                case 'menu':
                    if (empty($params['options_source'])) {
                        if (! empty($params['options'])) {
                            $choices = explode("\n", $params['options']);
                            foreach ($choices as $key => $choice) {
                                $choices[$key] = trim($choice);
                            }
                            $this->_choices[$field_identifier] = array_combine($choices, $choices);
                        } else {
                            $this->_choices[$field_identifier] = $yesno;
                        }
                    } else {
                        unset($options[$field_identifier]);
                    }
                    break;

                case 'text':
                    // fall-thru case!
                case 'textarea':
                    // no options required for text fields
                    break;


                default:
                    error_log("multifilter.php:: datatype = {$field->datatype} not supported");
                    break;
            }
        }
        $this->sections[$group]['custom'] = $options;
    }

    /**
     * Make Filter Options
     *
     * This functions makes the array that specified the options for sub filters based on the
     * sub filter name.
     *
     * @param string $group  The index of the group to which the sub filter belongs to.
     * @param string $name   The name of the sub filter to process.
     * @param array  $help   An array representing the help icon for the filter
     * @return array An array representing the options for the sub-filter.
     */
    function make_filter_options($group, $name, $help) {
        $options   = array('numeric' => 0);
        $section   = $this->sections[$group];
        $is_xfield = array_key_exists($name, $section['custom']);

        if ($is_xfield) {
            if (array_key_exists($group, $this->_outerfield)) {
                $options['dbfield'] = $this->_outerfield[$group];
            } else {
                $options['dbfield'] = $this->_outerfield['default'];
            }
            $options['talias'] = '';
            $options['tables'] = $this->tables[$group];

            if (array_key_exists($name, $this->_fields)) {
                $options['fieldid']  = $this->_fields[$name]->id;
                $options['datatype'] = $this->_fields[$name]->datatype;
            }
        } else {
            $options['dbfield'] = $name;
            $options['talias']  = current($this->tables[$group]);  // Default to first value

            // default help for standard user profile fields
            if (empty($options['help'])) {
                $options['help'] =
                    array($name,
                          array_key_exists($name, $this->labels[$group])
                          ? get_string($this->labels[$group][$name], $this->languagefile)
                          : $name, $this->languagefile);
            }
        }

        if (array_key_exists($name, $this->_choices)) {
            $options['choices'] = $this->_choices[$name];
        }

        $options = $this->make_filter_options_custom($options, $group, $name);

        $field = $is_xfield ? $name : $options['dbfield'];
        $filtertype = $this->fieldtofiltermap[$group][$field];

        if (array_key_exists($filtertype, $this->selects) && empty($options['choices'])) {
            $this->err_dump($options, '$options');
            error_log("multifilter.php: empty options['choices'] - requested for field: $name");
            return false;
        }

        if (!empty($options['help']) && array_key_exists($group, $help) && array_key_exists($name, $help[$group])) {
            $options['help'] = $help[$group][$name];
        }

        return $options;
    }

    /**
     * Make Custom Filter Options
     *
     * This function handles filters that require custom values (languages, countries, etc).
     * This function should be overriden in child classes to provide the proper options for selects.
     *
     * @param string $group  The index of the group to which the sub filter belongs to.
     * @param string $name   The name of the sub filter to process.
     * @param array  $help   An array representing the help icon for the filter
     * @return array The customized options for the selected sub-filter
     */
    function make_filter_options_custom($options, $group, $name) {
        return $options;
    }
}

 ?>
