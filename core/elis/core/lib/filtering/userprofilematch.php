<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
 * @author     Remote-Learner.net Inc
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 * @version    $Id$
 * @package    elis-core
 * @subpackage filtering
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 * userprofilematch.php - PHP Report filter
 *
 * Group of filters for matching user profile fields
 *
 * Configuration options include:
 *  ['choices'] => array, defines included DB user fields [as key],
 *                 *optional* value (string) as form field label
 *                 language string key for options['langfile'] (below).
 * fields (keys) may include:
 * fullname, lastname, firstname, idnumber, email, city, country,
 * username. confirmed, crsrole, crscat, sysrole,
 * firstaccess, lastaccess, lastlogin, timemodified, auth
 * + any user_info_field.shortname custom profile fields
 * * filters displayed in the order of this array!
 *
 * ['advanced'] => array, DB user feilds which are advanced form elements.
 * ['notadvanced'] => array, DB user fields which are NOT advanced form elements
 * (use only one of keys 'advanced' or 'notadvanced')
 * ['langfile'] => string optional language file, default: 'elis_core'
 *   ['tables'] => optional array of tables as key, table alias as value
 * default values show below: array(
 *             'user' => 'u',
 *   'user_info_data' => 'uidata', // only required for extra profile fields
 * 'role_assignments' => 'ra' // only required for crsrole and sysrole
 * );
 *    ['extra'] => boolean : true includes all extra profile fields,
 *                 false (default) does not auto include them.
 *  ['heading'] => optional string (raw HTML) - NOT yet IMPLEMENTED
 *   ['footer'] => optional string (raw HTML) - NOT yet IMPLEMENTED
 *     ['help'] => optional array of arrays:
                   keys are user fields (above, or extra profile fields shortnames)
 *                 values are arrays to pass to setHelpButton($helpbuttonargs)
                   - not implemented in all sub-filters!
 *
 * (TBD: add layout options, columns/row designation ...)
 *
 * NOTES:
 * ======
 * 1. Since this is a compund filter it must be used a bit different then
 *    standard filters.
 *
 * 2. Call constructor method directly _NOT_ using:
 *        new generalized_filter_entry( ... ) - INCORRECT!
 *    Instead call:
 *        new generalized_filter_userprofilematch( ... ) - CORRECT!
 *
 * 3. Class constructor and get_filters() methods return an array!
 *    Therefore, do _NOT_ put return inside another array in your report's
 *    get_filters() method.
 *
 * E.g.
 * // CORRECT
 * class some_user_report extends table_report {
 *     ...
 *     function get_filters() {
 *         $userprofilefilters = new generalized_filter_userprofilematch( ... );
 *         return array_merge(
 *             $userprofilefilters->get_filters(),
 *             array(
 *                 new generalized_filter_entry( ... ),
 *                 new generalized_filter_entry( ... ) [, ...]
 *             )
 *         );
 *     }
 *     ...
 * }
 *
 * // INCORRECT
 * class some_user_report extends table_report {
 *     ...
 *     function get_filters() {
 *         return(
 *             array(
 *                 new generalized_filter_userprofilematch( ... ),
 *                 new generalized_filter_entry( ... ),
 *                 new generalized_filter_entry( ... ) [, ...]
               )
 *         );
 *     }
 *     ...
 * }
 *
 * // CORRECT: Report case with only the User Profile filters
 * class some_user_report extends table_report {
 *     ...
 *     function get_filters() {
 *         $userprofilefilters = new generalized_filter_userprofilematch( ... );
 *         return $userprofilefilters->get_filters();
 *     }
 *     ...
 * }
 *
 * @author Brent Boghosian <brent.boghosian@remote-learner.net>
 * @package elis-core
 * @subpackage filtering
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once($CFG->dirroot.'/user/filters/lib.php');

require_once(elis::lib('filtering/multifilter.php'));
require_once(elis::lib('filtering/userprofileselect.php'));
require_once(elis::lib('filtering/userprofiletext.php'));
require_once(elis::lib('filtering/userprofiledatetime.php'));

/**
 * Generic userprofilematch filter class
 */
class generalized_filter_userprofilematch extends generalized_filter_multifilter {

    /**
     * Class contants: Additional required sub-filter types
     */
    const filtertype_userprofiledatetime = 'userprofiledatetime';
    const filtertype_userprofiletext     = 'userprofiletext';
    const filtertype_userprofileselect   = 'userprofileselect';

    //const languagefile = 'elis_core';

    // Data type map
    protected $datatypemap = array(
        'char'     => self::filtertype_userprofiletext, // TBD
        'text'     => self::filtertype_userprofiletext,
        'textarea' => self::filtertype_userprofiletext,
        'checkbox' => self::filtertype_userprofileselect,
        'bool'     => self::filtertype_userprofileselect,
        'menu'     => self::filtertype_userprofileselect,
        'datetime' => self::filtertype_userprofiledatetime,
    );

    protected $selects = array(
        self::filtertypeselect => 1,
        self::filtertype_userprofileselect => 1,
    );

    protected $_fieldids = array();

    /**
     * Class properties
     */
    // Array $fieldtofiltermap maps fields to filter type
    protected $fieldtofiltermap = array(
        'up' => array(
            // fullname_field must be added later, requires user table alias
            'lastname'     => self::filtertypetext,
            'firstname'    => self::filtertypetext,
            'idnumber'     => self::filtertypetext,
            'email'        => self::filtertypetext,
            'city'         => self::filtertypetext,
            'country'      => self::filtertypecountry,
            'username'     => self::filtertypetext,
            'lang'         => self::filtertypeselect,
            'confirmed'    => self::filtertypetristate,
            'crsrole'      => self::filtertypeselect,
            'crscat'       => self::filtertypeselect,
            'sysrole'      => self::filtertypeselect,
            'firstaccess'  => self::filtertypedate,
            'lastaccess'   => self::filtertypedate,
            'lastlogin'    => self::filtertypedate,
            'timemodified' => self::filtertypedate,
            'auth'         => self::filtertypeselect
        )
    );

    // Array $labels are default user profile field labels
    // - maybe overridden in $options array
    protected $labels = array(
        'up' => array(
            'fullname'     => 'fld_fullname',
            'lastname'     => 'fld_lastname',
            'firstname'    => 'fld_firstname',
            'idnumber'     => 'fld_idnumber',
            'email'        => 'fld_email',
            'city'         => 'fld_city',
            'country'      => 'fld_country',
            'username'     => 'fld_username',
            'lang'         => 'fld_lang',
            'confirmed'    => 'fld_confirmed',
            // TBD: not sure if following 3 fields are part of user profile?
            //'crsrole'    => 'fld_courserole',
            //'crscat'     => 'fld_coursecat',
            //'sysrole'    => 'fld_systemrole',
            'firstaccess'  => 'fld_firstaccess',
            'lastaccess'   => 'fld_lastaccess',
            'lastlogin'    => 'fld_lastlogin',
            'timemodified' => 'fld_timemodified',
            'auth'         => 'fld_auth',
        )
    );

    protected $tables = array(
        'up' => array(
            'user'             => 'u',
            'user_info_data'   => 'uidata',
            'role_assignments' => 'ra'
        )
    );

    protected $_label;
    protected $_heading;
    protected $_footer;
    protected $_choices = array();

    protected $sections = array('up' => array('name' => 'user'));

    //specify that we use the "data" column to filter user profile data
    var $_outerfield = array('up' => 'data');

    /**
     * Constructor
     *
     * @param string $uniqueid Unique id for filter
     * @param string $label    Filter label
     * @param array  $options  Filter options (see above)
     * @return array of sub-filters
     * @uses $DB
     */
    function __construct($uniqueid, $label, $options = array()) {
        global $DB;

        parent::__construct($uniqueid, $label, $options);

        if (empty($options['help'])) {
            $options['help'] = array();
        }

        // Get table aliases
        if (empty($options['tables'])) {
            $options['tables'] = array();
        }

        $this->fieldtofiltermap['up']['fullname'] = self::filtertypetext;

        if (!empty($options['extra'])) {
            $extrafields = $DB->get_recordset('user_info_field', null, 'sortorder ASC', 'id, shortname, name, datatype');
            $this->get_custom_fields('up', $extrafields);
        }

        $this->_filters['up'] = array();
        foreach ($this->_fields as $group => $fields) {
            foreach ($fields as $userfield => $fieldlabel) {
                //error_log("userprofilematch.php: creating filter for {$userfield} => {$fieldlabel}");
                // must setup select choices for specific fields
                $myoptions = $this->make_filter_options_custom(array(), $group, $userfield);
                $filterid = $uniqueid . substr($userfield, 0, MAX_FILTER_SUFFIX_LEN);
                $ftype = (string)$this->fieldtofiltermap[$group][$userfield];
                $advanced = (!empty($options['advanced']) &&
                             in_array($userfield, $options['advanced']))
                            || (!empty($options['notadvanced']) &&
                                !in_array($userfield, $options['notadvanced']));
                //error_log("userprofilematch.php: creating filter using: new generalized_filter_entry( $filterid, $talias, $dbfield, $fieldlabel, $advanced, $ftype, $myoptions)");
                // Create the filter
                $this->_filters[$group][$userfield] =
                    new generalized_filter_entry($filterid, $myoptions['talias'], $myoptions['dbfield'],
                        $fieldlabel, $advanced, $ftype, $myoptions);
            }
        }
    }

    /**
     * Get Extra Options
     *
     * Created this function to simplify the constructor.
     *
     * @param array $fields An array of db records representing custom fields
     * @return array
     * @uses $DB
     */
    function get_custom_fields($group, $fields) {
        global $DB;

        $options = array();

        if (!empty($fields)) {

            foreach ($fields as $field) {
                $this->_fieldids[$field->shortname] = $field->id;
                $this->_fields[$group][$field->shortname] = $field->name; // TBD???
                $this->record_short_field_name($field->shortname);
                $this->fieldtofiltermap[$group][$field->shortname] = $this->datatypemap[$field->datatype];
                $options[$field->shortname] = $field->name;
                switch ($field->datatype) {
                    case 'char': // TBD
                    case 'text':
                        // fall-thru case!
                    case 'textarea':
                        // no options required for text fields
                        break;

                    case 'bool':
                    case 'checkbox':
                        $this->_choices[$field->shortname] =
                            array('0' => get_string('no'),
                                   1  => get_string('yes'));
                        break;

                    case 'datetime': // start, stop year => param1, param2
                        $this->_choices[$field->shortname] = array();
                        $this->_choices[$field->shortname]['startyear'] = $DB->get_field('user_info_field', 'param1', array('shortname' => $field->shortname));
                        $this->_choices[$field->shortname]['stopyear'] = $DB->get_field('user_info_field', 'param2', array('shortname' => $field->shortname));
                        $this->_choices[$field->shortname]['inctime'] = $DB->get_field('user_info_field', 'param3', array('shortname' => $field->shortname));
                        break;

                    case 'menu':
                        $fieldvals = $DB->get_field('user_info_field', 'param1', array('shortname' => $field->shortname));
                        if (!empty($fieldvals)) {
                            $valarray = explode("\n", $fieldvals);
                            $this->_choices[$field->shortname] = array();
                            foreach($valarray as $opt) {
                                $this->_choices[$field->shortname][$opt] = $opt;
                            }
                        }
                        if (empty($fieldvals) || empty($this->_choices[$field->shortname])) {
                            error_log("userprofilematch.php:: error getting menu choices for field: {$field->shortname}");
                        }
                        break;

                    default:
                        error_log("userprofilematch.php:: datatype = {$field->datatype} not supported");
                }
            }
        }

        $this->sections[$group]['custom'] = $options;
    }

    /**
     * Make Custom Filter Options
     *
     * This function handles filters that require custom values (languages, countries, etc).
     *
     * @param string $group  The index of the group to which the sub filter belongs to.
     * @param string $name   The name of the sub filter to process.
     * @param array  $help   An array representing the help icon for the filter
     * @return array The customized options for the selected sub-filter
     * @uses $DB
     */
    function make_filter_options_custom($options, $group, $name) {
        global $DB;

        $manager = get_string_manager();

        $options['tables'] = $this->tables[$group]; // TBD: default?
        $options['dbfield'] = $name; // TBD: default?
        if (isset($this->tables[$group]['user'])) {
            $options['talias'] = $this->tables[$group]['user']; // default table?
        } else {
            $options['talias'] = ''; // TBD???
            error_log("userprofilematch::make_filter_options_custom(options, $group, $name) ... setting 'talias' empty!");
        }
        switch ($name) {
            case 'fullname':
                $firstname = $this->tables[$group]['user'] .'.firstname';
                $lastname  = $this->tables[$group]['user'] .'.lastname';
                $options['dbfield'] = $DB->sql_concat($firstname, "' '", $lastname);
                $options['talias'] = '';
                $this->fieldtofiltermap[$group][$options['dbfield']] = self::filtertypetext;
                break;
            case 'country': // TBD: new 'country' filter spec???
                $countries = $manager->get_list_of_countries();
                $options['choices'] = $countries; // TBD: foreach assoc.
                //$this->err_dump($countries, '$countries');
                break;
            case 'confirmed': // TBD: yesno filter???
                $options['choices'] = array('0' => 'No', 1 => 'Yes');
                $options['numeric'] = 1;
                //$this->err_dump($myoptions['choices'],'options for confir
                break;
            case 'crsrole':
                $roles = $DB->get_recordset('role', array(), '', 'id,name');
                $options['choices'] = array();
                foreach ($roles as $role) {
                    $options['choices'][$role->id] = $role->name;
                }
                unset($roles);
                $options['numeric'] = 1;
                $options['talias'] = $this->tables[$group]['role_assignments'];
                $options['dbfield'] = 'roleid';
                break;
            case 'lang':
                $options['choices'] = $manager->get_list_of_translations(true); // TBD
                //$this->err_dump($myoptions['choices'], 'list_of_languages
                break;
            case 'crscat':
                break;
            case 'sysrole':
                break;
            case 'auth':
                $auths = get_list_of_plugins('auth');
                //$this->err_dump($auths, '$auths');
                $options['choices'] = array();
                foreach ($auths as $auth) {
                    $options['choices'][$auth] = $auth; // TBD
                }
                break;
        }

        if (array_key_exists($name, $this->_choices)) {
            $options['choices'] = $this->_choices[$name];
        }
        if (array_key_exists($name, $this->_fieldids)) {
            $options['fieldid'] = $this->_fieldids[$name];
        }

        $is_xfield = array_key_exists($name, $this->sections[$group]['custom']);
        if ($is_xfield) {
            // custom profile field
            $options['talias'] = '';
            $options['dbfield'] = 'data';
        }

        return $options;
    }

    /**
     * Get Filter Values
     *
     * Created this function to get the values of the filters that have been set.
     *
     * @param string $report_name Short name of report as required by php_report_filtering_get_active_filter_values
     * @param object $filter      Filter object
     * @param array  $fields      array of non-custom user profile fields selected in the report
     * @return array
     */
     public function get_filter_values($report_name, $filter, $fields) {
         global $CFG;

        // Loop through the filter fields and process selected filters
        if (!empty($filter->_fields)) {
            $filter_values = array();
            $count = 0;

            $operator_array = $this->getOperators();
            foreach ($filter->_fields as $field) {
                $filter_name = null;
                $operator_string = '';

                // Check that we are not looking at a custom field
                if (isset($fields['up'][$count])) {
                    $filter_name = $fields['up'][$count];
                }

                // Check to see if this is a date and then retrieve and format appropriately
                // Currently we are only formatting non_custom field dates
                if ($filter_name && ($this->fieldtofiltermap['up'][$filter_name] === generalized_filter_userprofilematch::filtertypedate)) {
                    // Get and format date
                    $value = $this->get_date_filter_values($report_name, $filter, $field->_uniqueid);
                } else {
                    $value = php_report_filtering_get_active_filter_values(
                           $report_name,
                           $field->_uniqueid,
                           $filter);
                }
                // Filter was selected, so format the label and the value to return to the report
                if (!empty($value)) {
                    if (isset($field->_options)) {
                        if (isset($value[0])) {
                            $option_value = $value[0]['value'];
                            $filter_values[$field->_uniqueid]['value'] = $field->_options[$option_value];
                        } else {
                            $filter_values[$field->_uniqueid]['value'] = $field->_options[$value];
                        }
                    } else {
                        $operator = php_report_filtering_get_active_filter_values(
                           $report_name,
                           $field->_uniqueid.'_op',
                           $filter);
                        if (is_array($operator)) {
                            // Get string for operator
                            $operator_int  = $operator[0]['value'];
                            $operator_string = '('.$operator_array[$operator_int].')';
                        } else {
                            $operator_string = ':';
                        }
                        if (is_array($value[0])) {
                            $option_value = $value[0]['value'];
                            $filter_values[$field->_uniqueid]['value'] = $option_value;
                        } else {
                            $filter_values[$field->_uniqueid]['value'] = $value;
                        }
                    }
                    if (empty($operator_string)) {
                        $operator_string = ':';
                    }
                    // Just sent back an empty label
                    $filter_values[$field->_uniqueid]['label'] = '';
                    $filter_values[$field->_uniqueid]['value'] = $field->_label.' '.$operator_string.' '.$filter_values[$field->_uniqueid]['value'];
                } else if (!isset($field->_options)) {
                    // Check for the is empty drop-down
                    $operator_string = '';
                    $operator = php_report_filtering_get_active_filter_values(
                                $report_name,
                                $field->_uniqueid.'_op',
                                $filter);
                    if (is_array($operator)) {
                        // Get string for operator
                        $operator_int  = $operator[0]['value'];
                        $operator_string = '('.$operator_array[$operator_int].')';
                        // Just sent back an empty label
                        $filter_values[$field->_uniqueid]['label'] = '';
                        $filter_values[$field->_uniqueid]['value'] = $field->_label.' '.$operator_string;
                    }
                }
                $count++;
            }
            return $filter_values;
        }
    }

    /*
     * Get Date Filter Values
     * Retrieves start and end settings from active filter (if exists)
     * and return: startdate and enddate
     *
     * @uses none
     * @param none
     * @return none
     */
     public function get_date_filter_values($report_shortname, $filter, $uniqueid) {

        $start_enabled =  php_report_filtering_get_active_filter_values(
                              $report_shortname,
                              $uniqueid . '_sck',
                              $filter);
        $start = 0;
        if (!empty($start_enabled) && is_array($start_enabled)
            && !empty($start_enabled[0]['value'])) {
            $start = php_report_filtering_get_active_filter_values(
                         $report_shortname,
                         $uniqueid . '_sdt',
                         $filter);
        }

        $end_enabled = php_report_filtering_get_active_filter_values(
                           $report_shortname,
                           $uniqueid . '_eck',
                           $filter);
        $end = 0;
        if (!empty($end_enabled) && is_array($end_enabled)
            && !empty($end_enabled[0]['value'])) {
            $end = php_report_filtering_get_active_filter_values(
                       $report_shortname,
                       $uniqueid . '_edt',
                       $filter);
        }

        $startdate = (!empty($start) && is_array($start))
                           ? $start[0]['value'] : 0;
        $enddate = (!empty($end) && is_array($end))
                         ? $end[0]['value'] : 0;
        $sdate = userdate($startdate, get_string('date_format', $this->languagefile));
        $edate = !empty($enddate)
                 ? userdate($enddate, get_string('date_format', $this->languagefile))
                 : get_string('present', $this->languagefile);

        if (empty($startdate) && empty($enddate)) {
            // Don't return a value if neither date is selected
            return false;
        } else {
            $date_range_display = "{$sdate} - {$edate}";
        }

        return $date_range_display;
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

}
