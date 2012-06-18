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

function highlight_res($needle, $haystack){
    $ind = stripos($haystack, $needle);
    $len = strlen($needle);
    if($ind !== false){
        return substr($haystack, 0, $ind) . '<span class="highlight">'.substr($haystack, $ind, $len).'</span>'.
            highlight($needle, substr($haystack, $ind + $len));
    } else return $haystack;
}

function highlight_substrings($needles, $haystack) {
    $highlight_areas = array();
    foreach ($needles as $substr) {
        $substr = stripslashes($substr);
        $offset = 0;
        $substr_loc = null;
        while($substr_loc !== false) {
            $substr_loc = stripos($haystack,$substr,$offset);
            if ($substr_loc !== false) {
                $substr_len = strlen($substr);
                $min = $substr_loc;
                $max = $substr_loc + $substr_len;
                $offset = $max;
                $existing_expanded = false;
                foreach ($highlight_areas as $i => $info) {

                    //minimum is contained within another highlight
                    if ($min >= $info['min'] && $min <= $info['max']) {
                        if ($max >= $info['max']) {
                            $highlight_areas[$i]['max'] = $max;
                        } else {
                            //this range is contained within a preexisting range
                        }
                        $existing_expanded = true;
                        break;
                    }

                    //maximum is contained withing another highlight
                    if ($max >= $info['min'] && $max <= $info['max']) {
                        if ($min <= $info['min']) {
                            $highlight_areas[$i]['min'] = $min;
                        }
                        $existing_expanded = true;
                        break;
                    }

                    //another highlight is contained within this range
                    if ($min < $info['min'] && $max > $info['max']) {
                        $highlight_areas[$i]['min'] = $min;
                        $highlight_areas[$i]['max'] = $max;
                        $existing_expanded = true;
                        break;
                    }
                }

                if ($existing_expanded === false) {
                    $highlight_areas[] = array('min'=>$min, 'max'=>$max);
                }
            }
        }
    }

    if (!empty($highlight_areas) && is_array($highlight_areas)) {
        $highlight_areas_indexed = array();
        foreach ($highlight_areas as $i => $range) {
            $highlight_areas_indexed[$range['min']]=$range;
        }
        $highlight_areas = $highlight_areas_indexed;
        unset($highlight_areas_indexed);
        sort($highlight_areas);

        $processed_substrs = array();
        $offset = 0;
        foreach ($highlight_areas as $i => $range) {

            $min = $range['min']-$offset;
            $max = $range['max']-$offset;
            $length = ($max-$min);

            $part = '';
            if ($min > 0) {
                $part = substr($haystack,0,$min);
            }
            $part .= '<span class="reshighlight">'.substr($haystack,$min,$length).'</span>';
            //$part .= '['.substr($haystack,$min,$length).']';

            $haystack = substr($haystack,$max);
            $offset+=$max;
            $processed_substrs[] = $part;
        }
        $processed_substrs[] = $haystack;
        $haystack = implode('',$processed_substrs);
    }

    return $haystack;
}

function generate_label($template,$fields,$result) {
    $label = $template;
    foreach ($fields as $field) {
        if (strpos($template,'[['.$field.']]') !== false) {
            if (isset($result->$field)) {
                $label = str_replace('[['.$field.']]',$result->$field,$label);
            }
        }
    }
    return $label;
}

//require a report and a filter
$requested_report = required_param('report',PARAM_CLEAN);
$requested_filter = required_param('filter',PARAM_CLEAN);
$q = trim(optional_param('q','',PARAM_CLEAN));
$mode = optional_param('mode','ui',PARAM_CLEAN);

//instantiate the report
$report = php_report::get_default_instance($requested_report);
if (empty($report)) {
    die();
}

//authenticate ability to view this report
if (!$report->is_available() || !$report->can_view_report()) {
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

//check if we found a filter
if (empty($found_filter) || $found_filter->options['selection_enabled'] !== true) {
    die();
}

//make sure we have the required options
if (empty($found_filter->options['table']) || empty($found_filter->options['fields'])
            || !is_array($found_filter->options['fields'])) {
    die();
}

if ($mode === 'search') {

    //search the database

    if (!empty($q)) {

        $search = array();
        $q = explode(' ',$q);
        foreach ($q as $q_word) {
            $this_word = array();
            foreach ($found_filter->options['fields'] as $i => $field) {
                $this_word[] = $field.' LIKE "%'.$q_word.'%"';
            }
            $search[] = $this_word;
        }

        foreach ($search as $i => $sqls) {
            $search[$i] = implode(' OR ',$sqls);
        }

        if (!empty($found_filter->options['restriction_sql'])) {
            $search[] = $found_filter->options['restriction_sql'];
        }

        $wherestr = '('.$search[0].')';

        $params = array();

        // obtain all course contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('user', $report->access_capability, $report->userid);
        $filter_obj = $contexts->get_filter('id', 'user');
        $filter_sql = $filter_obj->get_sql(false, '', SQL_PARAMS_NAMED);
        if (isset($filter_sql['where'])) {
            $wherestr .= ' AND '. $filter_sql['where'];
            $params    = $filter_sql['where_parameters'];
        }

        // ELIS-5807 -- Always be sure to include the user accessing the filter in the results!
        if ($cm_user_id = cm_get_crlmuserid($USER->id)) {
            $wherestr .= ' OR {'.$found_filter->options['table'].'}.id = :self_user_id';
            $params   += array('self_user_id' => $cm_user_id);
        }

        $sql = 'SELECT id,'.implode(',',$found_filter->options['fields'])
                .' FROM '.$CFG->prefix.$found_filter->options['table']
                .' WHERE '.$wherestr
                .' LIMIT 0,100';

        $results = $DB->get_records_sql($sql, $params);
    }

    if (empty($results)) {
        echo 'No results';
        die();
    }

    //return json
    //json_encode($results);

    echo '<table width="100%"><tr>';
    foreach ($found_filter->options['fields'] as $field) {
        echo '<th style="text-align:left">'.$field.'</th>';
    }
    echo '</tr>';

    $i=0;
    foreach ($results as $result) {
        $label = generate_label($found_filter->options['label_template'],$found_filter->options['fields'],$result);
        $onclick = 'onclick="add_selection(\'id_'.$requested_filter.'\',\''.$result->id.'\',\''.addslashes($label).'\')"';
        echo '<tr class="row'.($i&1).'" '.$onclick.'>';
        foreach ($found_filter->options['fields'] as $field) {
            $str = highlight_substrings($q,$result->$field);
            echo '<td>'.$str.'</td>';
        }
        echo '</tr>';
        $i++;
    }

    echo '</table>';


} else {

    //show the interface

    ?>
    <html>
    <head>
        <script src="<?php echo $CFG->wwwroot.'/elis/core/lib/form/jquery-1.7.1.min.js'; ?>"></script>
        <script>
            function add_selection(eleid_base,id,label) {
                var element=window.opener.document.getElementById(eleid_base);
                element.value=id;
                var element=window.opener.document.getElementById(eleid_base+'_labelsave');
                element.value=label;
                var element=window.opener.document.getElementById(eleid_base+'_label');
                element.innerHTML=label;
                window.close();
            }

            function autocomplete_ui(textbox,results,search_status) {
                var last_req = '';
                var next_search_timeout = '';
                input = $('#'+textbox);
                results = $('#'+results);
                search_status = $('#'+search_status);
                input.css('border','1px solid #05f');
                input.keyup(function(e) {
                    q = $(this).val();
                    if (q != '') {
                        search_status.html('searching for "'+q+'" in 500ms...');
                        clearTimeout(next_search_timeout);
                        q = escape(q);
                        next_search_timeout=setTimeout("do_search('"+q+"');",500);
                    } else {
                        clearTimeout(next_search_timeout);
                        search_status.html('&nbsp;');
                        results.html('Type above to search');
                    }
                });

                do_search = function(q) {
                    if (last_req!='') {
                        last_req.abort();
                    }
                    last_req = $.get('<?php echo $CFG->wwwroot.'/elis/core/lib/form/autocomplete.php?report='.$requested_report.'&filter='.$requested_filter.'&mode=search&q='; ?>'+q, function (data) {
                            search_status.html('&nbsp;');
                            results.html(data);
                    });
                };
            }
        </script>
        <style>
            h2{padding:5px;margin-bottom:10px;}
            #search {width:60%;margin-left:5px;background-color:#fff;padding:5px;font-size:1.5em;border:1px solid #bbb;}
            span.reshighlight{color:#c00;font-weight:bold;}
            form {margin:0;padding:0}
            #results {font-size:0.8em;margin-top:10px;background-color:#fff;padding:2px;border:1px solid #bbb;height:480px;overflow-y:scroll;}
            #results table {border-spacing:0;font-size:inherit;}
            th {border-right:1px solid #fff;}
            tr:hover {background-color:#9f9;}
            td, th {padding:5px;}
            td {border-right:1px solid rgba(0,0,0,0.2);cursor:pointer;}
            tr.row1 td {background-color:rgba(0,0,0,0.08);}
        </style>
        <link rel="stylesheet" type="text/css" href="<?php echo $CFG->wwwroot,'/theme/',$CFG->theme,'/styles.php'?>" />

    </head>
    <body>
        <h2>
            <?php echo $found_filter->options['popup_title']; ?>
        </h2>
        <form>
            <b>Search:</b> <input type="text" id="search">
        </form>
        <h3>Results:</h3>
        <span id="search_status">&nbsp;</span>
        <div id="results">
            Type above to search
        </div>
        <script>
                autocomplete = new autocomplete_ui('search','results','search_status');
                $('#search').focus();
        </script>
    </body>
    </html>
    <?php
}


