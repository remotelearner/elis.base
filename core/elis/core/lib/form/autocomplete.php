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
 * @package    elis-core
 * @subpackage form
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->dirroot.'/blocks/php_report/php_report_base.php');
require_once(dirname(__FILE__).'/autocompletelib.php');

//require a report and a filter
$requested_report = required_param('report',PARAM_CLEAN);
$requested_filter = required_param('filter',PARAM_CLEAN);
$q = trim(optional_param('q','',PARAM_CLEAN));
$mode = optional_param('mode','ui',PARAM_CLEAN);

//instantiate the report
$report = php_report::get_default_instance($requested_report);
if (empty($report)) {
    echo get_string('autocomplete_noreport', 'elis_core'), ' (Error0)';
    die();
}

//authenticate ability to view this report
if (!$report->is_available() || !$report->can_view_report()) {
    echo get_string('autocomplete_reportunavail', 'elis_core'), ' (Error1)';
    die();
}

//get the requested filter
$filters = $report->get_filters(false);
$found_filter = null;
foreach ($filters as $i => $filter) {
    if ($filter->uniqueid === $requested_filter) {
        $found_filter = $filter;
        break;
    }
}
if (empty($found_filter) || strpos($found_filter->type,'autocomplete') !== 0) {
    echo get_string('autocomplete_nofilterfound', 'elis_core'), ' (Error2)';
    die();
}

$filter_class = 'generalized_filter_'.$found_filter->type;

require_once($CFG->dirroot .'/elis/program/lib/filtering/'. $found_filter->type .'.php');

$filter = new $filter_class(
        $found_filter->uniqueid,
        $found_filter->tablealias,
        $found_filter->fieldname,
        $found_filter->displayname,
        $found_filter->advanced,
        $found_filter->fieldname,
        $found_filter->options);


if ($filter->_selection_enabled !== true) {
    echo get_string('autocomplete_filterdisabled', 'elis_core'), ' (Error3)';
    die();
}


if ($mode === 'search') {

    echo "<script>
          var autocelem = document.getElementById('id_{$filter->_uniqueid}');
          if (autocelem) {
              autocelem.value = '". ($filter->_useid ? '': $q) ."';
          }
          </script>\n";

    //search the database
    $results = '';
    if (!empty($q)) {
        $results = $filter->get_search_results($q);
        $q = explode(' ',$q);
    }

    if (empty($results)) {
        echo '<span class="no_results">'.get_string('filt_autoc_noresults','elis_core').'</span>';
        die();
    }

    echo '<table><tr>';
    $headers = $filter->get_results_headers();
    foreach ($headers as $field) {
        echo '<th style="padding:5px 7px;text-align:left">'.$field.'</th>';
    }
    echo '</tr>';

    $results_fields = $filter->get_results_fields();

    foreach ($results as $result) {
        $add_selection_params = array(
            $requested_filter,
            $result->id,
            addslashes($filter->get_results_label($result)),
            $filter->_ui,
            $filter->_useid
        );

        echo '<tr onclick="add_selection(\''.implode('\',\'',$add_selection_params).'\')" class="datarow">';

        foreach ($results_fields as $field) {
            $str = highlight_substrings($q,$result->$field);
            echo '<td>'.$str.'</td>';
        }
        echo '</tr>';
    }
    unset($results);

    echo '</table>';

} elseif ($mode === 'config') {

    $PAGE->set_context(CONTEXT_SYSTEM);
    require_login();

    if ($filter->config_allowed() === true) {
        $configform = $filter->get_config_form();
        if ($configdata = $configform->get_data()){
            $configdata = $filter->process_config_data($configdata);
            filt_autoc_set_config($filter->_parent_report,$filter->_uniqueid,$configdata);
            header('Location: '.qualified_me().'&saved=1');
            die();
        }
        $saved = optional_param('saved','0',PARAM_CLEAN);
        ?>
        <html>
        <head>
            <script src="<?php echo $CFG->wwwroot.'/elis/core/js/jquery-1.7.1.min.js'; ?>"></script>
            <link rel="stylesheet" type="text/css" href="<?php echo $CFG->wwwroot,'/theme/styles.php?theme='.$CFG->theme?>" />
            <style>
                tr:hover{background-color:#efe;}
                .mform {width:95%;}
                .mform .fitem {width:auto;}
                .saved {color:#0a0;}
            </style>
        </head>
        <body>
            <h2><?php echo get_string('filt_autoc_wcf_config_title','elis_core'); ?></h2>
            <?php
            if ($saved == '1') {
                echo '<h3 class="saved">'.get_string('filt_autoc_settings_saved','elis_core').'</h3>';
            }
            ?>
            <?php $configform->display(); ?>
        </body>
        </html>
        <?php
    }

} else {

    //show the interface
    $search_url = $CFG->wwwroot.'/elis/core/lib/form/autocomplete.php?report='.$requested_report.'&filter='.$requested_filter.'&mode=search&q=';
    ?>
    <html>
    <head>
        <script src="<?php echo $CFG->wwwroot.'/elis/core/js/jquery-1.7.1.min.js'; ?>"></script>
        <script src="<?php echo $CFG->wwwroot.'/elis/core/lib/form/autocomplete.js'; ?>"></script>
        <style>
            h2{padding:5px;margin-bottom:10px;}
            #search {width:60%;margin-left:5px;background-color:#fff;padding:5px;font-size:1.5em;border:1px solid #bbb;}
            form {margin:0;padding:0}
        </style>
        <link rel="stylesheet" type="text/css" href="<?php echo $CFG->wwwroot,'/theme/styles.php?theme='.$CFG->theme?>" />
        <link rel="stylesheet" type="text/css" href="<?php echo $CFG->wwwroot.'/elis/core/lib/form/autocomplete.css'; ?>" />
    </head>
    <body>
        <h2>
            <?php echo $filter->_popup_title; ?>
        </h2>
        <form>
            <b><?php echo get_string('filt_autoc_search','elis_core'); ?></b> <input type="text" id="search">
        </form>
        <h3><?php echo get_string('filt_autoc_results','elis_core'); ?></h3>
        <span id="search_status">&nbsp;</span>
        <div id="results" class="filt_ac_res filt_ac_res_popup">
            <?php echo get_string('filt_autoc_typetosearch','elis_core'); ?>
        </div>
        <script>
            var autocomplete = new autocomplete_ui('search','results','search_status','<?php echo $search_url; ?>');
            $('#search').focus();
        </script>
    </body>
    </html>
    <?php
}


