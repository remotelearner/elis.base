<?php
/**
 * Run the unit tests.
 * From: /admin/tool/phpunittest/index.php
 *
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
 * @package    tool
 * @subpackage phpunitest
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once(dirname(__FILE__).'/../../../config.php');

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/phpunittestlib.php');
require_once('ex_phpunit_test.php');
require_once('ex_reporter.php');

// CGI arguments
$path = optional_param('path', null, PARAM_PATH);
$showpasses = optional_param('showpasses', false, PARAM_BOOL);
$showsearch = optional_param('showsearch', false, PARAM_BOOL);
$thorough = optional_param('thorough', false, PARAM_BOOL);
$progress = optional_param('progress', false, PARAM_BOOL);
$async = optional_param('async', false, PARAM_BOOL);

$filename = null;
$langfile = LANG_FILE;
$testdir = "{$CFG->dataroot}/phpunittests";
$debug_all = DEBUG_ALL; // require var for javascript
$debug_err = get_string('debugsettoalljs', $langfile);
$testinprogress = get_string('testinprogress', $langfile);
$strtitle = get_string('phpunittests', $langfile);
$progress_str = get_string('progress', $langfile) . '<br />';
$testscomplete = get_string('testscomplete', $langfile);

/* The UNITTEST constant can be checked elsewhere if you need to know
 * when your code is being run as part of a unit test. */
define('UNITTEST', true);

global $COURSE, $OUTPUT, $PAGE, $SITE;
$COURSE = clone($SITE);

$context = get_context_instance(CONTEXT_SYSTEM);
$PAGE->set_context($context);
$PAGE->requires->css('/admin/tool/phpunittest/styles.css');
$PAGE->set_url('/admin/tool/phpunittest/index.php');
$PAGE->set_title(get_string('phpunittests', $langfile));
$PAGE->set_heading(get_string('phpunittests', $langfile));

if ($async) { // force javascript not to wait for initial response!
    $progress = 1;
    $filename = required_param('filename', PARAM_PATH);
    header("Connection: close\r\n");
    header("Content-Encoding: none\r\n");
    header("Content-Length: 0\r\n");
    ignore_user_abort(true); // TBD?
    set_time_limit(0); // TBD: run script until completed
    flush();
} else {
    // Print the header.
    admin_externalpage_setup('tool_phpunittest', '', array('showpasses' => $showpasses,
        'showsearch' => $showsearch, 'progress' => $progress,
        'thorough' => $thorough));

    echo $OUTPUT->header();

    // required javascript code
    echo <<<EOT
<!-- Combo-handled YUI CSS files: -->
<link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/combo?2.8.2r1/build/progressbar/assets/skins/sam/progressbar.css&2.8.2r1/build/slider/assets/skins/sam/slider.css">
<!-- Combo-handled YUI JS files: -->
<script type="text/javascript" src="http://yui.yahooapis.com/combo?2.8.2r1/build/yahoo-dom-event/yahoo-dom-event.js&2.8.2r1/build/animation/animation-min.js&2.8.2r1/build/element/element-min.js&2.8.2r1/build/progressbar/progressbar-min.js&2.8.2r1/build/dragdrop/dragdrop-min.js&2.8.2r1/build/slider/slider-min.js"></script>
<script type="text/javascript">
//<![CDATA[

var testinprogress = false;
var timer = 0;
var refreshtime = 500; // TBD: half second requests ???
var updateurl = "{$CFG->wwwroot}/admin/tool/phpunittest/update.php";
var filename;
var lasttftime = 0;
var testcomplete = false;
var progressbar;
var review = false;
var updating_page = false;

function lpad(n, tot, ch)
{
    var snum = n.toString();
    while (tot > snum.length)
    {
        snum = ch + snum;
    }
    return snum;
}

function getupdate( fname, tftime )
{
    refreshdivs(window.updateurl + "?fromtime=" + tftime + "&filename=" + fname);
}

function getRequestObj() {
    var xmlObj = null;
    if ( "XMLHttpRequest" in window ) {
        return new XMLHttpRequest();
    } else if ( "ActiveXObject" in window ) {
        var prodId = [ "MSXML2.XMLHTTP.5.0", "MSXML2.XMLHTTP.4.0", "MSXML2.XMLHTTP.3.0", "MSXML2.XMLHTTP", "Microsoft.XMLHTTP" ];
        for ( x in prodId ) {
            if (xmlObj = new ActiveXObject(prodId[x])) {
                break;
            }
        } delete x;
        return xmlObj;
    } else {
        if ( "createRequest" in window ) {
            return window.createRequest();
        }
    }
    return xmlObj;
}

var AjaxUpdate = ( function() {
    this.xml = getRequestObj();
} ); AjaxUpdate.prototype = {
    Ready : ( function( url, sets ) {
        if ( this.xml ) {
            if (!window.testinprogress) { // TBD: M$ IE
                this.xml.abort();
                return false;
            }
            sets.method = sets.method.toUpperCase();
            (( "overrideMimeType" in this.xml ) ? this.xml.overrideMimeType("text/xml") : this.xml );
            this.xml.onreadystatechange = ( function( ) {
                if (document.all && (this.status != 200 || !window.testinprogress)) { // M$ IE
                    window.testinprogress = false;
                    this.abort();
                    return false;
                }
                if ( { 4 : 4, complete : 4 }[ this.readyState ] ) {
                    sets.onReady(
                                  this.getResponseHeader("divid"),
                                  this.getResponseHeader("tftime"),
                                  this.getResponseHeader("percent"),
                                  this.responseText
                    );
                    return true;
                }
                if (document.all && !window.updating_page && window.testinprogress) { // M$ IE
                    window.lasttftime = parseFloat(window.lasttftime) + 0.000001;
                    window.timer =
                        setTimeout( function () { getupdate(window.filename, window.lasttftime); },
                                    window.refreshtime);
                }
            } );
            this.xml.open( sets.method, url, true );
            (( sets.method === "POST" ) ? this.xml.setRequestHeader("Content-Type", "application/www-x-form-urlencoded; " + sets.charset + ";" ) : this.xml );
            this.xml.send(((sets.method === "POST") ? " " : null));
        } return false;
    } )
}; var HandleUpdate;
var refreshdivs = function( url ) {
    //alert("refreshdivs( " + url + " )");
    var xmlHttp = new AjaxUpdate();
    xmlHttp.Ready( url, {
        method : "GET",
        charset : "UTF-8",
        onReady : ( HandleUpdate = function( divid, tftime, percentdone, response ) {
            //alert('divid: ' + divid + ' tftime: ' + tftime + ' percent: ' + percentdone);
            // above tests new browser supports getResponseHeader
            window.updating_page = true;
            if (divid != null && divid != "" && tftime > window.lasttftime) { // M$ IE
                ipercent = (divid != 'complete') ? parseInt(percentdone) : 100; // M$ IE
                pbv_div = document.getElementById('pb_value');
                if (pbv_div && (!pbv_div.innerHTML || ipercent > parseInt(pbv_div.innerHTML))) {
                    window.progressbar.set('value', ipercent);
                    if (ipercent == 100) {
                        pbv_div.innerHTML = "100% - {$testscomplete}";
                        window.testcomplete = true;
                    } else {
                        pbv_div.innerHTML = ipercent + "%";
                    }
                }
                if (divid != 'complete') {
                   var div = document.getElementById(divid);
                   if (!div && document.all) {
                       div = document.all[divid];
                   }
                   if (div) {
                       var current = div.innerHTML;
                       div.innerHTML = current + response;
                       window.lasttftime = tftime; // TBD: moved from above
                       if (divid == 'progress') { // progress scrollbar to bottom
                           // scroll progress to bottom
                           div.scrollTop = (div.scrollHeight > 0) ? div.scrollHeight
                                                                  : div.offsetHeight;
                       } //else alert('divid: ' + divid + ' tftime: ' + tftime + ' percent: ' + percentdone);
                   }
                } else {
                   if (!window.review) {
                       var prevtests = document.getElementById('prevtests');
                       var testtime = window.filename.substring(
                                          window.filename.lastIndexOf('/') + 1,
                                          window.filename.lastIndexOf('.'));
                       //alert("Adding new test: " + testtime);
                       var newOpt = document.createElement('option');
                       newOpt.value = testtime + '.out';
                       var testDate = new Date(testtime * 1000);
                       var month = lpad(testDate.getMonth() + 1, 2, '0');
                       newOpt.text = testDate.getFullYear() + '-' +
                                     month + '-' + lpad(testDate.getDate(), 2, '0') +
                                     ' ' + lpad(testDate.getHours(), 2, '0') + ':' +
                                     lpad(testDate.getMinutes(), 2, '0') + ':' +
                                     lpad(testDate.getSeconds(), 2, '0');

                       try {
                           prevtests.add(newOpt, null); // Standards compliant
                       } catch (ex) {
                           prevtests.add(newOpt); // M$ IE
                       }
                   }
                   canceltests();
                   if (this.abort) {
                       this.abort(); // TBD: M$ IE
                   }
                   window.updating_page = false;
                   return;
                }
            }
            window.timer =
                setTimeout( function () { getupdate(window.filename, window.lasttftime); },
                           window.refreshtime);
            window.updating_page = false;
         } )
    } );
};

function canceltests()
{
    if (window.timer != 0) {
        clearTimeout(window.timer);
        window.timer = 0;
    }
    window.testinprogress = false;
    //alert('Testing complete!'); // TBD
}

function cleartests()
{
    // clear any previous test output
    if ((nadiv = document.getElementById('nonasync')) ||
        (document.all && (nadiv = document.all['nonasync']))) {
        nadiv.innerHTML = '';
    }

    if ((searchdiv = document.getElementById('outter_search')) ||
        (document.all && (searchdiv = document.all['outter_search']))) {
        searchdiv.innerHTML = '';
    }

    if ((progressbardiv = document.getElementById('progress_bar')) ||
        (document.all && (progressbardiv = document.all['progress_bar']))) {
        progressbardiv.innerHTML = '';
    }

    if ((pbvalue = document.getElementById('pb_value')) ||
        (document.all && (pbvalue = document.all['pb_value']))) {
        pbvalue.innerHTML = '';
    }

    if ((progressdiv = document.getElementById('outter_progress')) ||
        (document.all && (progressdiv = document.all['outter_progress']))) {
        progressdiv.innerHTML = '';
    }

    if ((resultsdiv = document.getElementById('results')) ||
        (document.all && (resultsdiv = document.all['results']))) {
        resultsdiv.innerHTML = '';
    }

    window.scroll(0,0);
}

function setTestFile( )
{
    var newTime = new Date() / 1000;
    newTime = newTime.toString().substring(0, newTime.toString().indexOf('.'));
    while ((newfile = "$testdir" + "/" + newTime + ".out") ==
           document.phpunittest_form.filename.value)
    {
        newTime += 1;
    }
    window.filename = document.phpunittest_form.filename.value = newfile;
}

function run__tests( prev )
{
    //if ( $CFG->debug < $debug_all ) {
    //    alert('{$debug_err}');
    //    return false;
    //}

    if (window.testinprogress) {
        alert('{$testinprogress}');
        return false;
    }

    cleartests();
    window.lasttftime = 0;
    window.testcomplete = false;
    var selopt = document.getElementById('prevtests');
    window.review = (prev != 0);
    //alert('window.review=' + window.review);
    if (window.review) {
        window.filename = '{$testdir}/' + selopt.options[prev].value;
        document.phpunittest_form.async.checked = true;
    } else {
        selopt.selectedIndex = 0;
        setTestFile();
    }

    if (document.phpunittest_form.async.checked == true) {
        //alert('Running PHPUnit tests asynchronously!');
        // setup progress bar
        if ((progressbardiv = document.getElementById('progress_bar')) ||
            (document.all && (progressbardiv = document.all['progress_bar']))) {
            progressbardiv.innerHTML = "{$progress_str}";
        }
        window.progressbar = new YAHOO.widget.ProgressBar({
                                     minValue:         0,
                                     maxValue:         100,
                                     value:            0,
                                     height:           50,
                                     width:            800,
                                     ariaTextTemplate: '{value}%'
        });
        window.progressbar.render("progress_bar");

        // run tests asynchronously
        window.testinprogress = true;
        if (window.review != true) {
            initurl = "{$CFG->wwwroot}/admin/tool/phpunittest/index.php?async=1&filename=" + window.filename
                + '&path=' + document.phpunittest_form.path.value
                + '&progress=' + (document.phpunittest_form.progress.checked ? 1 : 0)
                + '&showpasses=' + (document.phpunittest_form.showpasses.checked ? 1 : 0)
                + '&showsearch=' + (document.phpunittest_form.showsearch.checked ? 1 : 0)
                + '&thorough=' + (document.phpunittest_form.thorough.checked ? 1 : 0)
            ;
            initReq = getRequestObj();
            // send initial request - start tests - don't care about this response!
            initReq.open("GET", initurl, true);
            initReq.timeout = 65535; // M$ IE
            initReq.send(null);
        }
        window.timer =
            setTimeout( function () { getupdate(window.filename, 0); },
                        window.refreshtime);
        return false;
    }
    return true;
}

//]]>
</script>
EOT;

    echo "<div id=\"phpunittests\">";
}

if (!is_null($path)) {

    // Create/open test output file or stdout for non-async
    $outhandle = ($async) ? $filename : 'php://output';
    // NOTE: setting $outhandle to filename since update.php won't read from open file!
    // still must open/create test output file.
    if (($oh = fopen($outhandle, 'xb')) != false) {
        fclose($oh);
    } else {
        if ($async) { // TBD: IF file_exists -> test already in progress?
            error_log(__FILE__ .': '. get_string('createfileerr', $langfile));
        }
        print_error('createfileerr', $langfile);
    }

    if (!$async) { // require div around non-async output to clear in javascript
        echo "<div id=\"nonasync\">";
    } else if ($showsearch) {
        test_output($outhandle, TEST_OUTTER_SEARCH_ID, '<br />'. get_string('search', $langfile) ."<div id=\"search\" style=\"width:98%;height:200px;background-color:#ffffff;border:1px solid;overflow:auto;\"></div>\n");
    }

  /* *********************************************
    // PHPUnit3.5 behaves different when DEBUG_ALL or greater set
    // but only if set _before_ including config.php (setup.php)
    if ($CFG->debug < DEBUG_ALL) { // TBD: is problem in Moodle 2.0???
        $param = new stdClass;
        $param->href = $CFG->wwwroot .'/admin/settings.php?section=debugging';
        if ($async) {
            test_output($outhandle, TEST_SEARCH_ID,
                        get_string('debugsettoall', $langfile, $param));
            test_output($outhandle, TEST_COMPLETE_ID, '');
        }
        print_error('debugsettoall', $langfile, '', $param);
    }
  ********************************************** */

    // Create the group of tests.
    $test =& new AutoGroupTest($outhandle, $showsearch, $progress, $async, $thorough);

    // OU specific. We use the _nonproject folder for stuff we want to
    // keep in CVS, but which is not really relevant. It does no harm
    // to leave this here.
    $test->addIgnoreFolder($CFG->dirroot . '/_nonproject');

    // Make the reporter, which is what displays the test progress.
    $reporter = ($progress) ? new PHPUnit_Framework_TestResult
                            : NULL;

    if ($showsearch) {
        echo $OUTPUT->heading(get_string('searchfortests', $langfile));
    }
    flush();

    // Work out what to test.
    if (substr($path, 0, 1) == '/') {
        $path = substr($path, 1);
    }
    $path = $CFG->dirroot . '/' . $path;
    if (substr($path, -1) == '/') {
        $path = substr($path, 0, -1);
    }
    $displaypath = substr($path, strlen($CFG->dirroot) + 1);
    $ok = true;
    if (is_file($path)) {
        $test->addTestFile($path);
    } else if (is_dir($path)){
        $test->findTestFiles($path);
    } else {
        $param = new stdClass;
        $param->path = $path;
        echo $OUTPUT->box(get_string('pathdoesnotexist', $langfile, $param), 'errorbox');
        $ok = false;
    }

    // If we have something to test, do it.
    if ($ok) {
        $param = new stdClass;
        if ($path == $CFG->dirroot) {
            $param->path = get_string('all', $langfile);
            $title = get_string('moodleunittests', $langfile, $param);
        } else {
            $param->path = $displaypath;
            $title = get_string('moodleunittests', $langfile, $param);
        }
        echo $OUTPUT->heading($title);
        if ($progress) {
            test_output($outhandle, TEST_OUTTER_PROGRESS_ID, get_string('testprogress', $langfile) ."<div id=\"progress\" style=\"width:98%;height:200px;background-color:#ffffff;border:1px solid;overflow:auto;\">");
            if ($async) {
                test_output($outhandle, TEST_OUTTER_PROGRESS_ID, "</div>\n");
            }
        }
        if ($reporter != NULL) {
            $reporter->addListener(new PHPUnitTestListener($outhandle));
        }
        //print_object($outhandle);
        $test->run($reporter);
        //print_object($outhandle);
        /* ********
        NOTE: $outhandle becomes somehow corrupted, unset and unuseable after
              call to: $test->run($reporter);
        if ($async) {
            test_output($outhandle, TEST_COMPLETE_ID, $testscomplete, 100);
            fclose($outhandle);
        }
        ********* */
    }

    if (!$async) { // require div around non-async output to clear in javascript
       echo "</div>";
    }

    $formheader = get_string('retest', $langfile);
} else {
    $displaypath = '';
    $formheader = get_string('rununittests', $langfile);
    // create directory for async test file output
    if (!file_exists($testdir)) {
        // create phpunittests directory in dataroot
        if (!mkdir($testdir)) {
            echo "Error: creating test directory: $testdir<br />\n";
        }
    } else {
        // TBD: Check for still running test(s) ...
        // if running test then use its' filename !?!
    }
}
$filename = "{$testdir}/". time() .'.out';

if (!$async) {
    $prevtests = scandir($testdir);
    $prevoptions = '';
    if (!empty($prevtests)) {
        foreach ($prevtests as $prevtest) {
            if (($fdot = strpos($prevtest, '.')) != false) {
                $prevtime = substr($prevtest, 0, $fdot);
                if (is_numeric($prevtime) && $prevtime > 0 &&
                    ($prevdate = date('Y-m-d H:i:s', $prevtime)) != false) {
                    $prevoptions .= '<option value="'.$prevtest.'">'.$prevdate."</option>\n";
                }
            }
        }
    }
    echo "<div id=\"progress_bar\"></div><div id=\"pb_value\"></div>\n";
    echo "<div id=\"outter_search\"></div><br />\n";
    echo "<div id=\"outter_progress\"></div><br />\n";
    echo "<div id=\"results\"></div><br />\n";
    echo "</div>";

    // Print the form for adjusting options.
    echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
    //^Was: print_simple_box_start('center', '70%');
    echo '<form name="phpunittest_form" method="get" action="index.php" '.
         'onsubmit="return run__tests(0);">';
    echo '<fieldset class="invisiblefieldset">';
    echo $OUTPUT->heading($formheader);
    echo '<p>';
    echo html_writer::checkbox('showpasses', 1, $showpasses, get_string('showpasses', $langfile));
    echo '</p>';
    echo '<p>';
    echo html_writer::checkbox('showsearch', 1, $showsearch, get_string('showsearch', $langfile));
    echo '</p>';
    echo '<p>';
    echo html_writer::checkbox('progress', 1, $progress, get_string('showprogress', $langfile));
    echo '</p>';
    echo '<p>';
    echo html_writer::checkbox('async', 1, is_null($path), get_string('testasync', $langfile) ); //, '', 'if (document.phpunittest_form.async.checked == true) document.phpunittest_form.progress.checked = 1;');
    echo '</p>';
    echo '<p>';
    echo html_writer::checkbox('thorough', 1, $thorough, get_string('thorough', $langfile));
    echo '</p>';
    echo '<p>';
    echo '<label for="path">', get_string('onlytest', $langfile), '</label> ';
    echo '<input type="text" id="path" name="path" value="'. $displaypath .'" size="40" />';
    echo '</p>';
    echo '<p>'. get_string('reviewtest', $langfile);
    echo "<select id=\"prevtests\" onchange=\"if (this.selectedIndex > 0) run__tests(this.selectedIndex);\">\n";
    echo '<option selected="yes">['.get_string('new')."]</option>\n";
    if (!empty($prevoptions)) {
        echo $prevoptions;
    }
    echo '</select></p>';
    echo '<input type="hidden" id="filename" name="filename" value="'. $filename . '" />';
    echo '<input type="submit" value="'. get_string('runtests', $langfile) .'" />';
    echo '</fieldset>';
    echo '</form>';
    echo $OUTPUT->box_end();

    // Footer.
    echo $OUTPUT->footer();
}
