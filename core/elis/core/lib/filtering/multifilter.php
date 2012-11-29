<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 *
 * multifilter - Base class for handling groups of filters
 *
 * Configuration options include:
 *  ['choices'] => array, defines included DB user fields [as key],
 *                 *optional* value (string) as form field label
 *                 language string key for options['langfile'].
 *   --- filters displayed in the order of this array!
 *
 * ['advanced']    => array, DB user feilds which are advanced form elements.
 *   or
 * ['notadvanced'] => array, DB user fields which are NOT advanced form elements
 *   --- (use only one of keys 'advanced' or 'notadvanced')
 *
 * ['langfile'] => string optional language file, default: 'elis_core'
 *
 *   ['tables'] => optional array of tables as key, table alias as value
 *
 *    ['extra'] => boolean : true includes all extra profile fields,
 *                 false (default) does not auto include them.
 *
 *     ['help'] => optional array of arrays:
 *                  keys are user fields (above, or extra profile fields shortnames)
 *                 values are arrays to pass to setHelpButton($helpbuttonargs)
 *  --- not implemented in all sub-filters!
 *
 * @package    elis-core
 * @subpackage filtering
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/elis/core/lib/filtering/lib.php');

// Include the simple filter types that will be returned by an object of this type.
require_once(elis::lib('filtering/simpleselect.php'));
require_once(elis::lib('filtering/text.php'));
require_once(elis::lib('filtering/date.php'));
require_once(elis::lib('filtering/userprofiledatetime.php'));

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
    const filtertype_custom_field_datetime = 'custom_field_datetime'; // TBD

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
        'datetime'  => self::filtertype_custom_field_datetime, // TBD
    );

    protected $selects = array(
        self::filtertype_custom_field_select => 1,
        self::filtertypeselect               => 1,
    );

    // to store custom choices
    protected $_choices = array();

    // to store the field list
    protected $_fields = array();

    // The field-based filters, the field id keyes the array, and the value is a filter object
    protected $_filters = array();

    // to store/check truncated field names used for filterids
    protected $_shortfields = array();

    // This is the field to be returned by the custom profile fields.
    protected $_innerfield = array('default' => 'id');

    // This is the field to compare the custom profile field against.
    protected $_outerfield = array('default' => 'id');

    // This wrapper is an SQL fragment to relate the custom profile to a specific table.
    protected $_wrapper    = array('default' => '');

    // This field stores limits for generating limited select fields
    protected $_limits     = array();

    // The are optional fields which can be passed to multifilter objects
    protected $_optionals = array(
        '_heading'     => 'heading',
        '_footer'      => 'footer',
        'languagefile' => 'langfile',
        '_innerfield'  => 'innerfield',
        '_outerfield'  => 'outerfield',
        '_wrapper'     => 'wrapper',
        '_limits'      => 'limits',
    );

    // Language file (can't be constant because it's an optional parameter)
    protected $languagefile = 'elis_core';

    // Wether to display empty selects - useful for cascading/dependent selects
    protected $allowempty = false;

    // Stores section related data
    protected $sections = array();

    /**
     * Constructor
     *
     * @param string $uniqueid Unique prefix for filters
     * @param string $label    Filter label
     * @param array  $options  Filter options (see above)
     */
    function __construct($uniqueid, $label, $options) {
        $this->_uniqueid = $uniqueid;
        $this->_label = $label;

        // Import option fields
        foreach ($this->_optionals as $field => $option) {
            if (!empty($options[$option])) {
                $this->$field = $options[$option];
            }
        }

        // Make list of filters to display
        if (array_key_exists('choices', $options)) {
            $this->make_field_list($options['choices']);
        } else {
            $this->make_field_list($this->labels);
        }

        // Record aliases
        foreach ($this->labels as $group => $labels) {
            foreach ($labels as $key => $val) {
                $this->record_short_field_name($group .'-'. $key);
            }
        }

        // Check for and assign table aliases
        if (array_key_exists('tables', $options)) {
            foreach ($this->tables as $group => $tables) {

                if (!array_key_exists($group, $options['tables'])) {
                    continue;
                }

                foreach ($tables as $key => $val) {
                    if (! empty($options['tables'][$group][$key])) {
                        // If an alias has peen specified, us that instead of default
                        $this->tables[$group][$key] = $options['tables'][$group][$key];
                    }
                }
            }
        }

        // Get necesary data
        $this->load_data();
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
            error_log("generalized_filter_multifilter: non-unique field name: '{$shortfieldname}' - modify code!");
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

        if (!($fields instanceof Iterator)) {
            $fields = array();
        }

        foreach ($fields as $field) {
            $field = new field($field);
            if (!isset($field->owners['manual'])) {
                error_log("multifilter.php::get_custom_fields() - no field->owners['manual'] for {$field->name} ({$field_identifier})");
                continue;
            }
            $field_identifier = 'customfield-'. $field->id;
            $this->_fields[$group][$field_identifier] = $field;

            $this->record_short_field_name($field_identifier);
            $this->labels[$group][$field_identifier] = $field->name;
            $options[$field_identifier] = $field->name;

            $owner = new field_owner($field->owners['manual']);
            $params = unserialize($owner->params);

            //error_log("multifilter.php::get_custom_fields(): {$field_identifier} => {$params['control']} ({$this->datatypemap[$params['control']]})");
            $this->fieldtofiltermap[$group][$field_identifier] = $this->datatypemap[$params['control']];

             switch ($params['control']) {
                case 'datetime':
                    // TBD - options required for datetime fields?
                    $this->_choices[$field_identifier] =
                            array('startyear' => $params['startyear'],
                                  'stopyear'  => $params['stopyear'],
                                  'inctime'   => isset($params['inctime'])
                                                 ? $params['inctime'] : false);
                    break;

                case 'checkbox':
                    $this->_choices[$field_identifier] = $yesno;
                    break;

                case 'menu':
                    $choices = $owner->get_menu_options();
                    if (!empty($choices)) {
                        $this->_choices[$field_identifier] = array();
                        foreach ($choices as $key => $choice) {
                            $choice = trim($choice);
                            // preserve anyvalue key => ''
                            //$key = ($key === '') ? $key : $choice;
                            $this->_choices[$field_identifier][$key] = $choice;
                        }
                    } else {
                        error_log("multifilter::get_custom_fields() - empty menu options for fieldid = {$field->id} ... using: Yes, No");
                        $this->_choices[$field_identifier] = $yesno;
                    }
                    break;

                case 'text':
                    // fall-thru case!
                case 'textarea':
                    // no options required for text fields
                    break;


                default:
                    error_log("multifilter.php:: control = {$params['control']}, datatype = {$field->data_type()} not supported");
                    break;
            }
        }
        $this->sections[$group]['custom'] = $options;
    }

    /**
     * Load data
     *
     * Load data and stick it into class variables for later use.
     * To be overriden by child classes.
     */
    function load_data() {
    }

    /**
     * Make field list
     *
     * @param array $groups A two dimensional array of groups => choices => values
     */
    function make_field_list($groups) {

        // Force $groups to be an associative array
        foreach ($groups as $key => $choices) {
            if (!$this->is_assoc_array($choices)) {
                $groups[$key] = array_fill_keys($choices, '');
            }
        }

        if (get_class($this) != 'generalized_filter_userprofilematch') {
            // UPM filter uses Moodle profile, we should obey 'extra' option
            // Generate a list of custom fields
            foreach ($this->sections as $group => $section) {
                $ctxtlvl = context_elis_helper::get_level_from_name($section['name']);

                $this->sections[$group]['contextlevel'] = $ctxtlvl;

                // Add custom fields to array
                $extrafields = field::get_for_context_level($ctxtlvl);
                $this->get_custom_fields($group, $extrafields);
            }
        }

        // Generate the standard fields
        foreach ($groups as $group => $choices) {
            $custom_fields = isset($this->_fields[$group]) ? $this->_fields[$group]: array();
            $this->_fields[$group] = array();
            foreach ($choices as $name => $alias) {
                $label = $name;

                if (!empty($alias)) {
                    $label = get_string($alias, $this->languagefile);
                } else if (isset($this->labels[$group]) && array_key_exists($name, $this->labels[$group])) {
                    $label = get_string($this->labels[$group][$name], $this->languagefile);
                } else {
                    foreach ($this->sections as $section) {

                        if (array_key_exists($name, $section['custom'])) {
                            $label = $section['custom'][$name];
                        }
                    }
                }
                $this->_fields[$group][$name] = $label;
            }

            if (!empty($this->sections[$group]['custom'])) {
                $this->_fields[$group] = array_merge($this->_fields[$group], $this->sections[$group]['custom']);
            }
            if (!empty($custom_fields)) {
                $this->_fields[$group] = array_merge($this->_fields[$group], $custom_fields);
            }
        }
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
                    // || in_array($name, $section['custom']); // TBD

        if ($is_xfield) {
            if (array_key_exists($group, $this->_outerfield)) {
                $options['dbfield'] = $this->_outerfield[$group];
            } else {
                $options['dbfield'] = $this->_outerfield['default'];
            }
            $options['tables'] = $this->tables[$group];
            // ELIS-5295: elisuserprofile filter requires talias & contextlevel
            $options['talias'] = 'u'; // TBD: default?
            if (!empty($this->tables[$group]['crlm_user'])) {
                $options['talias'] = $this->tables[$group]['crlm_user'];
            }
            $options['contextlevel'] = $this->sections[$group]['contextlevel'];

            if (array_key_exists($name, $this->_fields[$group])) {
                if (isset($this->_fields[$group][$name]->id) &&
                    method_exists($this->_fields[$group][$name], 'data_type')) {
                    $options['fieldid']  = $this->_fields[$group][$name]->id;
                    $options['datatype'] = $this->_fields[$group][$name]->data_type();
                } else { // TBD !!!
                    $dashpos = strpos($name, '-');
                    $options['fieldid']  = ($dashpos !== false) ? substr($name, $dashpos + 1) : $name;
                    //$options['datatype'] = $this->fieldtofiltermap[$group][$name];
                }
            } else {
                error_log("multifilter.php:: ERROR: no this->_fields['{$group}']['{$name}']");
            }
        } else {
            $options['dbfield'] = $name;
            $options['talias']  = current($this->tables[$group]);  // Default to first value

            // default help for standard user profile fields
            if (empty($options['help'])) {
                $labeled = array_key_exists($name, $this->labels[$group]);
                $options['help'] =
                    array($labeled ? $this->labels[$group][$name] : $name,
                          ($labeled && get_string_manager()->string_exists($this->labels[$group][$name], $this->languagefile))
                           ? get_string($this->labels[$group][$name], $this->languagefile) : $name,
                          $this->languagefile);
            }
        }

        if (array_key_exists($name, $this->_choices)) {
            $options['choices'] = $this->_choices[$name];
            if (isset($options['choices'][''])) {
                $options['noany'] = true;
            }
        }

        $options = $this->make_filter_options_custom($options, $group, $name);
        $filtertype = $this->fieldtofiltermap[$group][$name];

        if ((!$this->allowempty) && array_key_exists($filtertype, $this->selects)
            && empty($options['choices'])) {
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

