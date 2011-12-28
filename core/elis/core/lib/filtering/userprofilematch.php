<?php //$Id$
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
 * @author     Remote-Learner.net Inc
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 * @version    $Id$
 * @package    elis-core
 * @subpackage filtering
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 * userprofilematch.php:
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
 * 3. Class constructor returns userprofilematch filter object and 
 *    get_filters() methods returns the array of componenet filters!
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
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/user/filters/lib.php');
require_once($CFG->dirroot .'/elis/core/lib/filtering/simpleselect.php');
require_once($CFG->dirroot .'/elis/core/lib/filtering/text.php');
require_once($CFG->dirroot .'/elis/core/lib/filtering/date.php');
require_once($CFG->dirroot .'/elis/core/lib/filtering/userprofileselect.php');
require_once($CFG->dirroot .'/elis/core/lib/filtering/userprofiletext.php');
require_once($CFG->dirroot .'/elis/core/lib/filtering/userprofiledatetime.php');

define('MAX_FILTER_SUFFIX_LEN', 30); // TBD: limited by table field

/**
 * Generic userprofilematch filter class - standalone, no parent!
 */
class generalized_filter_userprofilematch {

    /**
     * Class contants: required sub-filter types
     */
    const filtertypetext = 'text';
    const filtertypecountry = 'simpleselect'; // any, equal, not equal (TBD: country)
    const filtertypetristate = 'simpleselect'; // any, No, Yes (yesno, tristate)
    const filtertypeselect = 'simpleselect'; // any + choices
    const filtertypedate = 'date';
    const filtertype_userprofiletext = 'userprofiletext';
    const filtertype_userprofileselect = 'userprofileselect';
    const filtertype_userprofiledatetime = 'userprofiledatetime';

    /**
     * Class properties
     */
    // Array $fieldtofiltermap maps fields to filter type
    var $fieldtofiltermap = array(
         //fullname_field => generalized_filter_userprofilematch::filtertypetext,
        // fullname_field must be added later, requires user table alias
         'lastname'     => generalized_filter_userprofilematch::filtertypetext,
         'firstname'    => generalized_filter_userprofilematch::filtertypetext,
         'idnumber'     => generalized_filter_userprofilematch::filtertypetext,
         'email'        => generalized_filter_userprofilematch::filtertypetext,
         'city'         => generalized_filter_userprofilematch::filtertypetext,
         'country'      => generalized_filter_userprofilematch::filtertypecountry,
         'username'     => generalized_filter_userprofilematch::filtertypetext,
         'lang'         => generalized_filter_userprofilematch::filtertypeselect,
         'confirmed'    => generalized_filter_userprofilematch::filtertypetristate,
         'crsrole'      => generalized_filter_userprofilematch::filtertypeselect,
         'crscat'       => generalized_filter_userprofilematch::filtertypeselect,
         'sysrole'      => generalized_filter_userprofilematch::filtertypeselect,
         'firstaccess'  => generalized_filter_userprofilematch::filtertypedate,
         'lastaccess'   => generalized_filter_userprofilematch::filtertypedate,
         'lastlogin'    => generalized_filter_userprofilematch::filtertypedate,
         'timemodified' => generalized_filter_userprofilematch::filtertypedate,
          'auth'        => generalized_filter_userprofilematch::filtertypeselect
    );

    // Array $defaultlabels are default user profile field labels
    // - maybe overridden in $options array
    var $defaultlabels =
        array(
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
        );

    var $defaulttables =
        array(          'user' => 'u',
              'user_info_data' => 'uidata',
            'role_assignments' => 'ra'
        );

    var $_fields; // array, key is field id, value is filter return object
    var $_label;
    var $_heading;
    var $_footer;

    /**
     * Constructor
     * @param  string $uniqueid - unique id for filter
     * @param  string $label - filter label
     * @param  array $options - filter options (see above)
     * @uses   $DB
     * @return  compound filter object
     */
    function generalized_filter_userprofilematch($uniqueid, $label, $options = array()) {
        global $DB;

        // initialize
        $this->_label = $label;
        if (!empty($options['heading'])) {
            $this->_heading = $options['heading'];
        }
        if (!empty($options['footer'])) {
            $this->_footer = $options['footer'];
        }
        $langfile = empty($options['langfile']) ? 'elis_core' : $options['langfile'];

        // Get table aliases
        if (empty($options['tables'])) {
            $options['tables'] = array();
        }

        $shortfields = array(); // to store/check truncated field names used for filterids
        foreach ($this->defaultlabels as $key => $val) {
            $shortfieldname = substr($key, 0, MAX_FILTER_SUFFIX_LEN);
            if (in_array($shortfieldname, $shortfields)) {
                error_log("generalized_filter_userprofilematch::non-unique field name: '{$shortfieldname}' in main user profile - modify code!");
            } else {
                $shortfields[] = $shortfieldname;
            }
        }

        // Check for & assign table aliases
        foreach ($this->defaulttables as $key => $val) {
            if (empty($options['tables'][$key])) {
                // use defaults table aliases if not specified
                $options['tables'][$key] = $val;
            }
        }

        // Setup fullname_field w/ user table alias
        $firstname = $options['tables']['user'] .'.firstname';
        $lastname  = $options['tables']['user'] .'.lastname';
        $fullname_field = $DB->sql_concat($firstname, "' '", $lastname);
        $this->fieldtofiltermap[$fullname_field] =
                    generalized_filter_userprofilematch::filtertypetext;

        // Add custom user profile fields to array
        $extrafields = $DB->get_records('user_info_field', null, 'sortorder ASC', 'id, shortname, name, datatype');
        $datatypemap = 
            array(
               'text'
                   => generalized_filter_userprofilematch::filtertype_userprofiletext,
               'textarea'
                   => generalized_filter_userprofilematch::filtertype_userprofiletext,
               'checkbox'
                   => generalized_filter_userprofilematch::filtertype_userprofileselect,
               'menu'
                   => generalized_filter_userprofilematch::filtertype_userprofileselect,
               'datetime'
                   => generalized_filter_userprofilematch::filtertype_userprofiledatetime,
            );

        // Array $xoptions to append to existing options['choices']
        $xoptions = array();
        if (!empty($extrafields)) {
            // Array $xchoices to hold sub options for extra field ($myoptions)
            $xchoices = array();
            foreach ($extrafields as $xfield) {
                $shortfieldname = substr($xfield->shortname, 0,
                                         MAX_FILTER_SUFFIX_LEN);
                if (in_array($shortfieldname, $shortfields)) {
                    error_log("generalized_filter_userprofilematch::non-unique field name: '{$shortfieldname}' in extra profile fields - skipped.");
                    continue;
                }
                $shortfields[] = $shortfieldname;
                $this->fieldtofiltermap[$xfield->shortname]
                    = array_key_exists($xfield->datatype, $datatypemap)
                      ? $datatypemap[$xfield->datatype]
                      : generalized_filter_userprofilematch::filtertype_userprofiletext;
                // ^default to text input - TBD??? Or create userprofiledatetime filter ???
                $xoptions[$xfield->shortname] = $xfield->name;
                switch ($xfield->datatype) {
                    case 'datetime':
                        // no options required for datetime fields
                        break;
                    case 'text':
                        // fall-thru case!
                    case 'textarea':
                        // no options required for text fields
                        break;

                    case 'checkbox':
                        $xchoices[$xfield->shortname] = 
                            array('0' => 'No', 1 => 'Yes');
                        break;

                    case 'menu':
                        $fieldvals = $DB->get_field('user_info_field', 'param1', array('shortname' => $xfield->shortname));
                        if (!empty($fieldvals)) {
                            $valarray = explode("\n", $fieldvals);
                            $xchoices[$xfield->shortname] = array();
                            foreach($valarray as $opt) {
                                $xchoices[$xfield->shortname][$opt] = $opt;
                            }
                        }
                        if (empty($fieldvals) ||
                            empty($xchoices[$xfield->shortname])) 
                        {
                            error_log("userprofilematch.php:: error getting menu choices for user_info_field: {$xfield->shortname}");
                        }
                        break;

                    default:
                        error_log("userprofilematch.php::user_info_field datatype = {$xfield->datatype} not supported");
                }
            }
        }

        $this->_fields = array();
        $allfields = array();
        // First check if $options['choices'] is associative array with labels
        if (!$this->is_assoc_array($options['choices'])) {
            foreach ($options['choices'] as $upfield) {
                $allfields[$upfield] = array_key_exists($upfield,
                                                        $this->defaultlabels)
                                       ? get_string($this->defaultlabels[$upfield], 'elis_core' /* TBD */)
                                       : (array_key_exists($upfield, $xoptions)
                                          ? $xoptions[$upfield] : $upfield);
            }
        } else { // just fillin empty labels
            foreach ($options['choices'] as $key => $val) {
                $allfields[$key] = !empty($val)
                                   ? get_string($val, $langfile)
                                   : (array_key_exists($key,
                                                       $this->defaultlabels)
                                      ? get_string($this->defaultlabels[$key],
                                                   'elis_core' /* TBD */)
                                      : (array_key_exists($key, $xoptions)
                                         ? $xoptions[$key] : $key));
            }
        }
        if (!empty($options['extra']) && !empty($xoptions)) {
            $allfields += $xoptions;
        }
        foreach ($allfields as $userfield => $fieldlabel) {
            // must setup select choices for specific fields
            $myoptions = array();
            $myoptions['numeric'] = 0; // TBD: default ???
            $is_xfield = array_key_exists($userfield, $xoptions);
            if ($is_xfield) {
                $dbfield = 'data';
                $talias = ''; // $options['tables']['user_info_data'];
                $myoptions['tables'] = $options['tables'];
                foreach ($extrafields as $xfield) {
                    if ($userfield == $xfield->shortname) {
                        $myoptions['fieldid'] = $xfield->id;
                        break;
                    }
                }
            } else {
                $dbfield = ($userfield == 'fullname')
                           ? $fullname_field: $userfield;
                $talias = ($userfield == 'fullname')
                          ? '' // CONCAT cannot have table alias prepended!
                          : $options['tables']['user'];
            }
            switch ($userfield) {
                case 'country': // TBD: new 'country' filter spec???
                    $countries = get_string_manager()->get_list_of_countries();
                    $myoptions['choices'] = $countries; // TBD: foreach assoc.
                    //$this->err_dump($countries, '$countries');
                    break;
                case 'confirmed': // TBD: yesno filter???
                    $myoptions['choices'] = array('0' => 'No', 1 => 'Yes');
                    $myoptions['numeric'] = 1;
                    //$this->err_dump($myoptions['choices'],'options for confirmed');
                    break;
                case 'crsrole':
                    $roles = $DB->get_records('role', null, '', 'id, name');
                    $myoptions['choices'] = array();
                    foreach ($roles as $role) {
                        $myoptions['choices'][$role->id] = $role->name; 
                    }
                    $myoptions['numeric'] = 1;
                    $talias = $options['tables']['role_assignments'];
                    $dbfield = 'roleid';
                    break;
                case 'lang':
                    $myoptions['choices'] = get_string_manager()->get_list_of_translations(true); // TBD
                    //$this->err_dump($myoptions['choices'], 'list_of_translations');
                    break;
                //case 'crscat':
                //    break;
                //case 'sysrole':
                //    break;
                case 'auth':
                    $auths = get_list_of_plugins('auth');
                    //$this->err_dump($auths, '$auths');
                    $myoptions['choices'] = array();
                    foreach ($auths as $auth) {
                        $myoptions['choices'][$auth] = $auth; // TBD
                    }
                    break;
            }
            if ($is_xfield && !empty($xchoices[$userfield])) {
                $myoptions['choices'] = $xchoices[$userfield];
            }
            $myfield = $is_xfield ? $userfield : $dbfield;
            if ($this->fieldtofiltermap[$myfield] !=
                 generalized_filter_userprofilematch::filtertypetext && 
                $this->fieldtofiltermap[$myfield] !=
                 generalized_filter_userprofilematch::filtertypedate &&
                $this->fieldtofiltermap[$myfield] !=
                 generalized_filter_userprofilematch::filtertype_userprofiledatetime &&
                $this->fieldtofiltermap[$myfield] !=
                 generalized_filter_userprofilematch::filtertype_userprofiletext
                && empty($myoptions['choices']))
            {
                $this->err_dump($myoptions, '$myoptions');
                error_log("curriculum/lib/filtering/userprofilematch.php: empty options['choices'] - requested for user field: $userfield");
                continue;
            }
            $filterid = $uniqueid . substr($userfield, 0, MAX_FILTER_SUFFIX_LEN);
            $ftype = (string)$this->fieldtofiltermap[$myfield];
            $advanced = (!empty($options['advanced']) &&
                         in_array($userfield, $options['advanced']))
                        || (!empty($options['notadvanced']) &&
                            !in_array($userfield, $options['notadvanced']));
            if (!$is_xfield) {
                // default help for standard user profile fields
                if (empty($options['help'][$userfield])) {
                    $helpid = isset($this->defaultlabels[$userfield])
                              ? $this->defaultlabels[$userfield] : $ftype;
                    $options['help'][$userfield] =
                        array($helpid,
                              array_key_exists($userfield, $this->defaultlabels)
                              ? get_string($this->defaultlabels[$userfield],
                                           'elis_core' /* TBD */)
                              : $userfield, $langfile /* TBD */);
                }
            }
            if (!empty($options['help']) && array_key_exists($userfield, $options['help'])) {
                $myoptions['help'] = $options['help'][$userfield];
            }
            //error_log("userprofilematch.php: creating filter using: new generalized_filter_entry( $filterid, $talias, $dbfield, $fieldlabel, $advanced, $ftype, $myoptions)");
            // Create the filter
            $this->_fields[$myfield] =
                new generalized_filter_entry($filterid, $talias, $dbfield,
                    $fieldlabel, $advanced, $ftype, $myoptions);
        }

    }

    /**
     * Method to return all sub-filters as array
     *
     * @param none
     * @uses none
     * @return array of sub-filters
     */
    function get_filters() {
        $filters = array();
        foreach ($this->_fields as $fieldfilter) {
            if (!empty($fieldfilter)) {
                $filters[] = $fieldfilter;
            }
        }
        //$this->err_dump($filters, 'get_filters()::$filters');
        return $filters;
    }

    // Test whether array is associative
    function is_assoc_array( $a ) {
        return(is_array($a) && 
                (count($a) == 0 || 
                 0 !== count(array_diff_key($a, array_keys(array_keys($a)))))
        );
    }

    // Debug helper function
    function err_dump($obj, $name = '') {
        ob_start();
        var_dump($obj);
        $tmp = ob_get_contents();
        ob_end_clean();
        error_log('err_dump:: '.$name." = {$tmp}");
    }
}

