<?php
/**
 * A PHPUnit Test report format for Moodle.
 * From: /admin/tool/phpunittest/ex_reporter.php
 *
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

require_once($CFG->libdir . '/simpletestlib/reporter.php'); // TBD: use PHPUnit class

if (!defined('LANG_FILE')) {
    define('LANG_FILE', 'tool_phpunittest');
}

/**
 * PHPUnit_Framework_TestListener implementation for showing test progress
 * @package PHPUnitTests
 */
class PHPUnitTestListener implements PHPUnit_Framework_TestListener
{
  var $outhandle;
  var $numtests;
  var $curtest = 0;
  var $init = false;

  // Constructors
  function PHPUnitTestListener( $outhandle )
  {
      $this->outhandle = $outhandle;
      //parent::__construct();
  }

  function __construct( $outhandle )
  {
      $this->PHPUnitTestListener($outhandle);
  }

  public function
  addError(PHPUnit_Framework_Test $test,
           Exception $e,
           $time)
  {
    $param = new stdClass;
    $param->name = $test->getName();
    test_output($this->outhandle, TEST_PROGRESS_ID,
      get_string('testerror', LANG_FILE, $param) ."<br />\n",
      $this->percentdone());
  }

  public function
  addFailure(PHPUnit_Framework_Test $test,
             PHPUnit_Framework_AssertionFailedError $e,
             $time)
  {
    $param = new stdClass;
    $param->name = $test->getName();
    test_output($this->outhandle, TEST_PROGRESS_ID,
      get_string('testfail', LANG_FILE, $param) ."<br />\n",
      $this->percentdone());
  }

  public function
  addIncompleteTest(PHPUnit_Framework_Test $test,
                    Exception $e,
                    $time)
  {
    $param = new stdClass;
    $param->name = $test->getName();
    test_output($this->outhandle, TEST_PROGRESS_ID,
      get_string('testincomplete', LANG_FILE, $param) ."<br />\n",
      $this->percentdone());
  }

  public function
  addSkippedTest(PHPUnit_Framework_Test $test,
                 Exception $e,
                 $time)
  {
    $param = new stdClass;
    $param->name = $test->getName();
    test_output($this->outhandle, TEST_PROGRESS_ID,
      get_string('testskipped', LANG_FILE, $param) ."<br />\n",
      $this->percentdone());
  }

  public function startTest(PHPUnit_Framework_Test $test)
  {
    ++$this->curtest;
    $sparams = new stdClass;
    $sparams->num = $this->curtest;
    $sparams->total = $this->numtests;
    $sparams->name = $test->getName();
    test_output($this->outhandle, TEST_PROGRESS_ID,
      get_string('teststart', LANG_FILE, $sparams) . "<br />\n",
      $this->percentdone());
  }

  public function endTest(PHPUnit_Framework_Test $test, $time)
  {
    $param = new stdClass;
    $param->name = $test->getName();
    test_output($this->outhandle, TEST_PROGRESS_ID,
        get_string('testend', LANG_FILE, $param) .
        sprintf("%.6f ms.<br />\n", 1000.0 * $time), $this->percentdone());

    if (method_exists($test, 'hasFailed') && $test->hasFailed() == FALSE &&
        method_exists($test, 'getResult') && $test->getResult() == NULL &&
        method_exists($test, 'setResult') )
    {   // Bit of a hack, since TestResult for passed tests NULL set to string
        $test->setResult(sprintf("%s: %.6f ms.",
                         get_string('testok', LANG_FILE), 1000.0 * $time));
    }
  }

  public function
  startTestSuite(PHPUnit_Framework_TestSuite $suite)
  {
    //$param = new stdClass;
    //$param->name = $suite->getName();
    //test_output($this->outhandle, TEST_PROGRESS_ID, get_string('suitestart', LANG_FILE, $param) ."<br />\n");
    //print_object($suite);
    //print_object(get_class_methods($suite));
    if (!$this->init) { // Only want highest level suite count - total tests!
        $this->numtests = $suite->count();
        $this->init = true;
    }
  }

  public function
  endTestSuite(PHPUnit_Framework_TestSuite $suite)
  {
    //$param = new stdClass;
    //$param->name = $suite->getName();
    //$this->outhandle, TEST_PROGRESS_ID, get_string('suiteend', LANG_FILE, $param) ."<br />\n<hr />\n");
  }

  public function percentdone()
  {
      return( 100.0 * $this->curtest / $this->numtests );
  }
}

/**
 * Extended in-browser test displayer. HtmlReporter generates
 * only failure messages and a pass count. ExHtmlReporter also
 * generates pass messages and a time-stamp.
 *
 * @package SimpleTestEx
 */
if (!defined('BROKEN_CLASS')) {
class ExHtmlReporter extends PHPUnit_Framework_TestResult // ..._TestListener ???
{}
} else {
   // OLD simpletest class
class ExHtmlReporter extends HtmlReporter {
    // Options set when the class is created.
    var $showpasses;

    // Lang strings. Set in the constructor.
    var $strrunonlyfolder;
    var $strrunonlyfile;

    var $strseparator;

    /**
     * Constructor.
     *
     * @param bool $showpasses Whether this reporter should output anything for passes.
     */
    function ExHtmlReporter($showpasses) {
        global $CFG, $THEME;

        //$this->HtmlReporter();
        $this->showpasses = $showpasses;

        $this->strrunonlyfolder = $this->get_string('runonlyfolder', LANG_FILE);
        $this->strrunonlyfile = $this->get_string('runonlyfile', LANG_FILE);
        $this->strseparator = get_separator();
    }

    /**
     * Called when a pass needs to be output.
     */
    function paintPass($message) {
        //(Implicitly call grandparent, as parent not implemented.)
        parent::paintPass($message);
        if ($this->showpasses) {
            $this->_paintPassFail('pass', $message);
        }
    }

    /**
     * Called when a fail needs to be output.
     */
    function paintFail($message) {
        // Explicitly call grandparent, not parent::paintFail.
        SimpleScorer::paintFail($message);
        $this->_paintPassFail('fail', $message, debug_backtrace());
    }

    /**
     * Called when an error (uncaught exception or PHP error) needs to be output.
     */
    function paintError($message) {
        // Explicitly call grandparent, not parent::paintError.
        SimpleScorer::paintError($message);
        $this->_paintPassFail('exception', $message);
    }

    /**
     * Called when a caught exception needs to be output.
     */
    function paintException($exception) {
        // Explicitly call grandparent, not parent::paintException.
        SimpleScorer::paintException($exception);
        $message = 'Unexpected exception of type [' . get_class($exception) .
                '] with message ['. $exception->getMessage() .
                '] in ['. $exception->getFile() .
                ' line ' . $exception->getLine() . ']';
        $stacktrace = null;
        if (method_exists($exception, 'getTrace')) {
            $stacktrace = $exception->getTrace();
        }
        $this->_paintPassFail('exception', $message, $stacktrace);
    }

    /**
     * Private method. Used by printPass/Fail/Error/Exception.
     */
    function _paintPassFail($passorfail, $message, $stacktrace = null) {
        global $FULLME, $CFG, $OUTPUT;

        echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide '. $passorfail);
        //^Was: print_simple_box_start('', '100%', '', 5, $passorfail . ' generalbox');
        $url = $this->_htmlEntities($this->_stripParameterFromUrl($FULLME, 'path'));
        echo '<b class="', $passorfail, '">', $this->get_string($passorfail), '</b>: ';
        $breadcrumb = $this->getTestList();
        array_shift($breadcrumb);
        $file = array_shift($breadcrumb);
        $pathbits = preg_split('/\/|\\\\/', substr($file, strlen($CFG->dirroot) + 1));
        $file = array_pop($pathbits);
        $folder = '';
        foreach ($pathbits as $pathbit) {
            $folder .= $pathbit . '/';
            echo "<a href=\"{$url}path=$folder\" title=\"$this->strrunonlyfolder\">$pathbit</a>/";
        }
        echo "<a href=\"{$url}path=$folder$file\" title=\"$this->strrunonlyfile\">$file</a>";
        echo $this->strseparator, implode($this->strseparator, $breadcrumb);
        echo $this->strseparator, '<br />', $this->_htmlEntities($message), "\n\n";
        if ($stacktrace) {
            $dotsadded = false;
            $interestinglines = 0;
            $filteredstacktrace = array();
            foreach ($stacktrace as $frame) {
                if (empty($frame['file']) || (strpos($frame['file'], 'simpletestlib') === false
                        && strpos($frame['file'], 'report/unittest') === false)) {
                    $filteredstacktrace[] = $frame;
                    $interestinglines += 1;
                    $dotsadded = false;
                } else if (!$dotsadded) {
                    $filteredstacktrace[] = array('line' => '...', 'file' => '...');
                    $dotsadded = true;
                }
            }
            if ($interestinglines > 1 || $passorfail == 'exception') {
                echo '<div class="notifytiny">' . phpunit_format_backtrace($filteredstacktrace) . "</div>\n\n";
            }
        }
        echo $OUTPUT->box_end();
        flush();
    }

    /**
     * Called when a notice needs to be output.
     */
    function paintNotice($message) {
        $this->paintMessage($this->_htmlEntities($message));
    }

    /**
     * Paints a simple supplementary message.
     * @param string $message Text to display.
     */
    function paintMessage($message) {
        if ($this->showpasses) {
            echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
            //^Was: print_simple_box_start('', '100%');
            echo '<span class="notice">', $this->get_string('notice'), '</span>: ';
            $breadcrumb = $this->getTestList();
            array_shift($breadcrumb);
            echo implode($this->strseparator, $breadcrumb);
            echo $this->strseparator, '<br />', $message, "\n";
            echo $OUTPUT->box_end();
            flush();
        }
    }

    /**
     * Output anything that should appear above all the test output.
     */
    function paintHeader($test_name) {
        // We do this the moodle way instead.
    }

    /**
     * Output anything that should appear below all the test output, e.g. summary information.
     */
    function paintFooter($test_name) {
        $summarydata = new stdClass;
        $summarydata->run = $this->getTestCaseProgress();
        $summarydata->total = $this->getTestCaseCount();
        $summarydata->passes = $this->getPassCount();
        $summarydata->fails = $this->getFailCount();
        $summarydata->exceptions = $this->getExceptionCount();

        if ($summarydata->fails == 0 && $summarydata->exceptions == 0) {
            $status = "passed";
        } else {
            $status = "failed";
        }
        echo '<div class="unittestsummary ', $status, '">';
        echo $this->get_string('summary', $summarydata);
        echo '</div>';

        $param1 = new stdClass;
        $param1->date = date('<b>d-m-Y H:i T</b>');
        $param2 = new stdClass;
        $param2->version = SimpleTestOptions::getVersion();
        echo '<div class="performanceinfo">',
                $this->get_string('runat', $param1),
                $this->get_string('version', $param2),
                '</div>';
    }

    /**
     * Strip a specified parameter from the query string of a URL, if present.
     * Adds a separator to the end of the URL, so that a new parameter
     * can easily be appended. For example (assuming $param = 'frog'):
     *
     * http://example.com/index.php               -> http://example.com/index.php?
     * http://example.com/index.php?frog=1        -> http://example.com/index.php?
     * http://example.com/index.php?toad=1        -> http://example.com/index.php?toad=1&
     * http://example.com/index.php?frog=1&toad=1 -> http://example.com/index.php?toad=1&
     *
     * @param string $url the URL to modify.
     * @param string $param the parameter to strip from the URL, if present.
     *
     * @return string The modified URL.
     */
    function _stripParameterFromUrl($url, $param) {
        $url = preg_replace('/(\?|&)' . $param . '=[^&]*&?/', '$1', $url);
        if (strpos($url, '?') === false) {
            $url = $url . '?';
        } else {
            $url = $url . '&';
        }
        return $url;
    }

    /**
     * Look up a lang string in the appropriate file.
     */
    function get_string($identifier, $a = NULL) {
        return get_string($identifier, LANG_FILE, $a);
    }
}
} // END 'BROKEN_CLASS'

/**
 * Formats a backtrace ready for output.
 *
 * @param array $callers backtrace array, as returned by debug_backtrace().
 * @param boolean $plaintext if false, generates HTML, if true generates plain text.
 * @return string formatted backtrace, ready for output.
 */
function phpunit_format_backtrace($callers, $plaintext = false) {
    // do not use $CFG->dirroot because it might not be available in destructors
    $dirroot = dirname(dirname(__FILE__));

    if (empty($callers)) {
        return '';
    }

    $from = $plaintext ? '' : '<ul style="text-align: left">';
    foreach ($callers as $caller) {
        if (!isset($caller['line'])) {
            $caller['line'] = '?'; // probably call_user_func()
        }
        if (!isset($caller['file'])) {
            $caller['file'] = 'unknownfile'; // probably call_user_func()
        }
        $from .= $plaintext ? '* ' : '<li>';
        $from .= 'line ' . $caller['line'] . ' of ' . str_replace($dirroot, '', $caller['file']);
        if (isset($caller['function'])) {
            $from .= ': call to ';
            if (isset($caller['class'])) {
                $from .= $caller['class'] . $caller['type'];
            }
            $from .= $caller['function'] . '()';
        } else if (isset($caller['exception'])) {
            $from .= ': '.$caller['exception'].' thrown';
        }
        $from .= $plaintext ? "\n" : '</li>';
    }
    $from .= $plaintext ? '' : '</ul>';

    return $from;
}
