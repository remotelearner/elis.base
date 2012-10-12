<?php
/**
 * Update asynchronous PHPUnit test progress/results ...
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

/// NOTE: CANNOT USE ANY MOODLE INCLUDES/FUNCTIONS
///       cannot use session data

// CGI arguments
if (!isset($_GET['fromtime']) || !isset($_GET['filename'])) {
    die("Requied parameter(s) are missing!<br>\n");
}

$fromtime = $_GET['fromtime'];
$filename = $_GET['filename'];

if (!function_exists('strscan')) {
/**
 * strscan() - method to parse string for attributes and return assoc. values
 *
 * @param string $str - the string to parse.
 * @param array $vararray - array of attributes to search string for.
 * @param array &$values - the returned array with parsed attributes as keys,
 *                         and parsed values as values.
 * @return int - the number of attributes successfully parsed.
 */
function strscan($str, $vararray, &$values )
{
    $args = 0;
    foreach ($vararray as $var) {
        if (($spos = strpos($str, $var)) !== false &&
            ($epos = strpos(substr($str, $spos), " ")) !== false) {
            $epos += $spos;
            $vstart = $spos + strlen($var) + 1;
            $values[$var] = substr($str, $vstart, $epos - $vstart);
            $values[$var] = trim($values[$var], "\"'= ");
            ++$args;
        }
    }
    return $args;
}
}

if (!function_exists('get_test_output')) {
/**
 * get_test_output() - Function to read test output file and return output
 * for current section (i.e. search, progress, results) from specified time.
 * NOTE: sets HTTP headers - 'divid', 'tftime' , 'percent' from test id tag(s)
 *       in test output file.
 *
 * @param string $fname - the name of the test output file.
 * @param float $fromtime - the time to start output from; where time is
 *                          time from epoch decimal milli/micro-seconds.
 * @return int  >= 0 is line count of output, -1 => testing complete.
 */
function get_test_output( $fname, $fromtime )
{
    $myvars = array('name', 'time', 'status');
    $testscomplete = false;
    $lcount = 0;
    $init = false;
    $cursection = '';
    $outstr = '';
    $lasttime = $fromtime;
    //error_log(time() . " - get_test_output( {$fname}, {$fromtime} ) - before file_exists()");
    if (!file_exists($fname)) {
        return false;
    }
    //error_log(time() . " - get_test_output( {$fname}, {$fromtime} ) - after file_exists()");
    $flines = file($fname); // TBD: read entire file or fgets/fscanf by line?
    if (empty($flines)) {
        return false;
    }
    //error_log(time() . " - get_test_output( {$fname}, {$fromtime} ) - after file()");
    foreach ($flines as $fline) {
        //error_log("get_test_output(): file-line: {$fline}");
        $myvalues = array();
        if ( strscan($fline, $myvars, $myvalues) >= 2 ) {
            $section = $myvalues['name'];
            if ($section == 'complete') {
                $testscomplete = true;
            }
            $time = $myvalues['time']; // floatval() ?
            $percentdone = $myvalues['status'];
            $output = substr($fline, strpos($fline, "-->") + 3);
            //error_log("get_test_output(): strscan >= 2 {$section}, {$time}, {$percentdone}");
            if ($time > $fromtime) {
                //error_log("get_test_output(): time > fromtime; {$section}, {$time}, {$percentdone}");
                if (!$init) {
                    $cursection = $section;
                    $init = true;
                    header('divid: '. $section);
                } else if ($cursection != $section) {
                    break;
                }
                $lasttime = $time;
                $outstr .= $output;
                ++$lcount;
            }
        } else if ($init) {
            $outstr .= $fline;
            ++$lcount;
        }
    }
    if ($cursection != '') {
        header('tftime: '. $lasttime);
        header('percent: '. $percentdone);
        //error_log("admin/tool/phpunittest/update.php: headers cursection = {$cursection}, tftime = {$lasttime}, percent = {$percentdone}  ");
        echo $outstr;
    }
    return((!$testscomplete || $lcount > 0) ? $lcount : -1);
}
}

ob_start();
$cnt = get_test_output($filename, $fromtime);
$sout = ob_get_contents();
ob_end_clean();

//$len = strlen($sout);
//error_log("admin/tool/phpunittest/update.php: filename={$filename} fromtime={$fromtime} - " . time() . " max time: " . ini_get('max_execution_time') . " ({$cnt}, $len) => {$sout}");

if ($cnt == -1) {
    // TBD: M$ IE get to abort requests when complete
    header("HTTP/1.0 404 Not Found");
    header("Status: 404 Not Found");
    exit;
}

echo $sout;
