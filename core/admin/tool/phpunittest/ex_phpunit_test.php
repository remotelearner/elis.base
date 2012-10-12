<?php
/**
 * A PHPUnit test GroupTest that automatically finds all the
 * test files in a directory tree according to certain rules.
 * From: /admin/tool/unittest/ex_simple_test.php
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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once('PHPUnit/Framework.php');
require_once(dirname(__FILE__).'/../../../config.php');

if (!defined('LANG_FILE')) {
    define('LANG_FILE', 'tool_phpunittest');
}

/**
 * This is a composite test class for finding test cases and
 * other RunnableTest classes in a directory tree and combining
 * them into a group test.
 * @package SimpleTestEx
 */
class AutoGroupTest extends PHPUnit_Framework_TestSuite {

    var $thorough;
    var $progress;
    var $showsearch;
    var $outhandle;
    var $async;

    function AutoGroupTest($outhandle, $showsearch, $progress, $async = false, $thorough = false, $test_name = null) {
        //$this->TestSuite($test_name);
        $this->outhandle = $outhandle;
        $this->setName(empty($test_name) ? "noname" : $test_name);
        $this->showsearch = $showsearch;
        $this->progress = $progress;
        $this->async = $async;
        $this->thorough = $thorough;
        //$incfiles = get_included_files();
        //print_object($incfiles);
    }

    function setLabel($test_name) {
        //:HACK: there is no GroupTest::setLabel, so access parent::_label.
        $this->_label = $test_name;
    }

    /**
     * @return resource - file handle object
     */
    function getHandle() {
        return $this->outhandle;
    }

    function addIgnoreFolder($ignorefolder) {
        $this->ignorefolders[]=$ignorefolder;
    }

    function _recurseFolders($path) {
        static  $recurse;
        if ($this->showsearch) {
            $num = $recurse % 3;
            test_output($this->outhandle, TEST_SEARCH_ID, "<li class=\"phpunittest testsearch dirlevel{$num}\">" . basename(realpath($path)) . "<ul class=\"phpunittest testsearch dirlevel{$num}\">");
        }

        $files = scandir($path);
        static $s_count = 0;

        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $file_path = $path . '/' . $file;
            if (is_dir($file_path)) {
                if ($file != 'CVS' && $file != '.git' && !in_array($file_path, $this->ignorefolders)) {
                    ++$recurse;
                    $this->_recurseFolders($file_path);
                    --$recurse;
                }
            } elseif (preg_match('/phpunit(\/|\\\\)test.*\.php$/', $file_path) ||
                    ($this->thorough && preg_match('/phpunit(\/|\\\\)slowtest.*\.php$/', $file_path))) {

                $s_count++;
                // OK, found: this shows as a 'Notice' for any 'simpletest/test*.php' file.
                //$this->addTestCase(new FindFileNotice($file_path, 'Found unit test file, '. $s_count));

                // addTestFile: Unfortunately this doesn't return fail/success (bool).
                $this->addTestFile($file_path, true);
            }
        }

        if ($this->showsearch) {
            test_output($this->outhandle, TEST_SEARCH_ID, '</ul>'. '</li>');
        }
        return $s_count;
    }

    function findTestFiles($dir) {
        if ($this->showsearch) {
            $param = new stdClass;
            $param->path = realpath($dir);
            test_output($this->outhandle, TEST_SEARCH_ID,
                get_string('searchingfolder', LANG_FILE, $param) .'<ul class="phpunittest testsearch">');
        }
        $path = $dir;
        $count = $this->_recurseFolders($path);
        // BJB101201: simpletest::addTestCase() => phpunit::addTest
        if ($count <= 0) {
            $this->addTest(new BadAutoGroupTest($this->outhandle, $path,
                                  get_string('nounittestsfound', LANG_FILE),
                                  $this->progress, $this->async));
        } else {
            $param = new stdClass;
            $param->count = $count;
            $this->addTest(new AutoGroupTestNotice($this->outhandle, $path,
                                  get_string('totalfound', LANG_FILE, $param),
                                  $this->progress, $this->async));
        }
        if ($this->showsearch) {
            test_output($this->outhandle, TEST_SEARCH_ID, '</ul>');
        }
        return $count;
    }

    function addTestFile($file, $internalcall = false) {
        if ($this->showsearch) {
            if ($internalcall) {
                test_output($this->outhandle, TEST_SEARCH_ID, '<li class="phpunittest testsearch"><b>' . basename($file) . '</b></li>');
            } else {
                test_output($this->outhandle, TEST_SEARCH_ID, '<li class="phpunittest testsearch">'. get_string('addingtest', LANG_FILE) . realpath($file) . '</li>');
            }

            // Make sure that syntax errors show up suring the search, otherwise you often
            // get blank screens because evil people turn down error_reporting elsewhere.
            //error_reporting(E_ALL); // causes PHPUnit tests to fail!?!
        }
        if(!is_file($file) ){
            parent::addTestCase(new BadTest($this->outhandle, $file,
                                get_string('notafile', LANG_FILE)));
        }
        parent::addTestFile($file);
    }
}


/* ======================================================================= */
// get_class_ex: Insert spaces to prettify the class-name.
function get_class_ex($object) {
    return preg_replace('/(.?)([A-Z])/', '${1} ${2}', get_class($object));
}


/**
 * A failing test base-class for when a test suite has NOT loaded properly.
 */
class BadTest implements PHPUnit_Framework_Test
{ // added implements for phpunit

    var $label;
    var $error;
    var $progress;
    var $outhandle;
    var $async;

    function BadTest($outhandle, $label, $error, $progress = false, $async = false) {
        $this->label = $label;
        $this->error = $error;
        $this->progress = $progress;
        $this->async = $async;
        $this->outhandle = $outhandle;
    }

    function getLabel() {
        return $this->label;
    }

    // Implement PHPUnit_Framework_Test::void run(PHPUnit_Framework_TestResult $result)
    // was: function run(&$reporter) {
    function run(PHPUnit_Framework_TestResult $reporter = NULL) {
         if ($this->progress && !$this->async) {
             test_output($this->outhandle, TEST_OUTTER_PROGRESS_ID,
                         '</div><br />', 100);
         }
         test_output($this->outhandle, TEST_RESULTS_ID, get_string('notestsfound', LANG_FILE) . '<hr />');
        //$reporter->paintGroupStart(basename(__FILE__), $this->getSize());
        //$reporter->paintFail(get_class_ex($this) .' ['. $this->getLabel() .'] with error ['. $this->error .']');
        //$reporter->paintGroupEnd($this->getLabel());
        //return $reporter->getStatus();

        // HACK: should do this once in index.php but $outhandle corrupt!?!
        if ($this->async) {
            test_output($this->outhandle, TEST_COMPLETE_ID, get_string('testscomplete', LANG_FILE), 100);
            // $outhandle now filename since update.php won't read open file!?!
            //fclose($this->outhandle);
        }
    }

    /**
     * @return int the number of test cases starting.
     */
    function getSize() {
        return 0;
    }

    /**
     * Implement PHPUnit_Framework_Test::int count()
     * @return # of tests
     */
    function count() { return $this->getSize(); }
}

/**
 * An informational notice base-class for when a test suite is being processed.
 * See class, simple_test.php: BadGroupTest.
 * @package SimpleTestEx
 */
class Notice implements PHPUnit_Framework_Test
{
    var $label;
    var $status;
    var $progress;
    var $outhandle;
    var $async;

    function Notice($outhandle, $label, $error, $progress = false, $async = false) {
        $this->label = $label;
        $this->status = $error;
        $this->progress = $progress;
        $this->async = $async;
        $this->outhandle = $outhandle;
    }

    function getLabel() {
        return $this->label;
    }

    // Implement PHPUnit_Framework_Test::void run(PHPUnit_Framework_TestResult $result)
    // was: function run(&$reporter) {
    function run(PHPUnit_Framework_TestResult $reporter = NULL)
    {
        global $CFG, $showpasses;

        if (!empty($reporter)) {
            if ($this->progress && !$this->async) {
                test_output($this->outhandle, TEST_OUTTER_PROGRESS_ID,
                            '</div><br />', 100);
            }
            if (method_exists($reporter, 'paintGroupStart'))
                $reporter->paintGroupStart(basename(__FILE__), $this->getSize());
            //else
            //    test_output($this->outhandle, TEST_RESULTS_ID, basename(__FILE__)." size(".$this->getSize().") <br/>\n");

            if (method_exists($reporter, 'paintNotice'))
                $reporter->paintNotice(get_class_ex($this) .
                ' ['. $this->getLabel() .'] with status [' . $this->status . ']');
            else {
                if (!$this->async ) {
                    test_output($this->outhandle, TEST_RESULTS_ID,
                                "<div id=\"na_results\">\n");
                }
                test_output($this->outhandle, TEST_RESULTS_ID,
                            $this->status ."<br/>&nbsp;<br/>\n", 100); // TBD
            }

            //print_object($reporter);

            if ($reporter->errorCount() > 0) {
                test_output($this->outhandle, TEST_RESULTS_ID, "<b>".get_string('errors', LANG_FILE).": ".$reporter->errorCount()
                     ."</b> <ul class=\"phpunittest testerror\">");
                foreach($reporter->errors() as $key => $value) {
                    $this->print_testfailure($value, 'phpunittest testerror');
                }
                test_output($this->outhandle, TEST_RESULTS_ID, "</ul><br/>", 100);
            } else {
                test_output($this->outhandle, TEST_RESULTS_ID, get_string('noerrors', LANG_FILE)." <br/>&nbsp;<br/>\n", 100);
            }

            if ($showpasses) {
                $passedtests = array_keys($reporter->passed());
                if (!empty($passedtests)) {
                    test_output($this->outhandle, TEST_RESULTS_ID, get_string('passedtests', LANG_FILE).": ".count($passedtests)
                         ."<ul class=\"phpunittest testpass\">", 100);
                    foreach($reporter->passed() as $key => $value) {
                        test_output($this->outhandle, TEST_RESULTS_ID, "<li class=\"phpunittest testpass\">$key =&gt; ".($value ? $value : get_string('testok', LANG_FILE))."</li>\n", 100);
                    }
                    test_output($this->outhandle, TEST_RESULTS_ID, "</ul><br/>", 100);
                } else {
                    test_output($this->outhandle, TEST_RESULTS_ID, get_string('nopassedtests', LANG_FILE)."<br/>&nbsp;<br/>\n", 100);
                }
            }

            if ($reporter->failureCount() > 0) {
                test_output($this->outhandle, TEST_RESULTS_ID, "<b>".get_string('failedtests', LANG_FILE).": ".$reporter->failureCount()
                     ."</b> <ul class=\"phpunittest testfail\">", 100);
                foreach($reporter->failures() as $key => $value) {
                    $this->print_testfailure($value, 'phpunittest testfail');
                }
                test_output($this->outhandle, TEST_RESULTS_ID, "</ul><br/>", 100);
            } else {
                test_output($this->outhandle, TEST_RESULTS_ID, get_string('nofailures', LANG_FILE)." <br/>&nbsp;<br/>\n", 100);
            }

            if ($reporter->skippedCount() > 0) {
                test_output($this->outhandle, TEST_RESULTS_ID, get_string('skippedtests', LANG_FILE).": ".$reporter->skippedCount()
                      ."<ul class=\"phpunittest testskipped\">\n", 100);
                foreach($reporter->skipped() as $key => $value) {
                    $this->print_testfailure($value, 'phpunittest testskipped');
                }
                test_output($this->outhandle, TEST_RESULTS_ID, "</ul><br/>", 100);
            } else {
                test_output($this->outhandle, TEST_RESULTS_ID, get_string('noskippedtests', LANG_FILE)."<br/>&nbsp;<br/>\n", 100);
            }

            if ($reporter->notImplementedCount() > 0) {
                test_output($this->outhandle, TEST_RESULTS_ID, get_string('notimplementedtests', LANG_FILE).": "
                     .$reporter->notImplementedCount()."<ul class=\"phpunittest testincomplete\">\n", 100);
                foreach($reporter->notImplemented() as $key => $value) {
                    $this->print_testfailure($value, 'phpunittest testincomplete');
                }
                test_output($this->outhandle, TEST_RESULTS_ID, "</ul><br/>", 100);
            } else {
                test_output($this->outhandle, TEST_RESULTS_ID, get_string('nonotimplementedtests', LANG_FILE)."<br/>&nbsp;<br/>\n", 100);
            }

            if (method_exists($reporter, 'paintGroupEnd'))
                $reporter->paintGroupEnd($this->getLabel());
            else {
                test_output($this->outhandle, TEST_RESULTS_ID, "<input type=\"button\" value=\"". get_string('clearresults', LANG_FILE)
                     ."\" onclick=\"cleartests();\"><hr/><br/>", 100);
                if (!$this->async) {
                    test_output($this->outhandle, TEST_RESULTS_ID, "</div>");
                }
            }
            //return $reporter->getStatus();
        }

        // HACK: should do this once in index.php but $outhandle corrupt!?!
        if ($this->async) {
            test_output($this->outhandle, TEST_COMPLETE_ID, get_string('testscomplete', LANG_FILE), 100);
            // $outhandle now filename since update.php won't read open file!?!
            //fclose($this->outhandle);
        }

    }

    function getSize() {
        return 0;
    }

    /**
     * Implement PHPUnit_Framework_Test::int count()
     * @return # of tests
     */
    function count() { return $this->getSize(); }

    function print_testfailure( $testfailure, $class )
    {
        test_output($this->outhandle, TEST_RESULTS_ID,
                    "<li class=\"$class\">".$testfailure->failedTest()->getName()." =&gt; ");
        test_output($this->outhandle, TEST_RESULTS_ID,
                    htmlspecialchars($testfailure->failedTest()->getStatusMessage())."<br/>\n");
        $tracefiles = $testfailure->thrownException()->getTrace();
        foreach ($tracefiles as $tfile) {
            if (!empty($tfile['file']) && !stristr($tfile['file'], '/lib/pear/PHPUnit')) {
                test_output($this->outhandle, TEST_RESULTS_ID,
                            htmlspecialchars($tfile['file']).":".$tfile['line']."<br/>\n");
            }
        }
        //test_output($this->outhandle, TEST_RESULTS_ID, nl2br(htmlspecialchars($testfailure->thrownException()->xdebug_message)))
        test_output($this->outhandle, TEST_RESULTS_ID, "</li>");
    }
}

/**
 * A failing folder test for when the test-user specifies an invalid directory
 * (run.php?folder=woops).
 * @package PHPUnitTestEx
 */
class BadFolderTest extends BadTest { }

/**
 * A failing auto test for when no unit test files are found.
 * @package PHPUnitTestEx
 */
class BadAutoGroupTest extends BadTest { }

/**
 * Auto group test notices - 1. Search complete. 2. A test file has been found.
 * @package PHPUNitTestEx
 */
class AutoGroupTestNotice extends Notice { }

class FindFileNotice extends Notice { }
