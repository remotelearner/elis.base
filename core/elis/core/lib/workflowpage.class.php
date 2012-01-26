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
 * @package    elis
 * @subpackage core
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/elis/core/lib/page.class.php';
require_once $CFG->dirroot . '/elis/core/lib/workflow.class.php';

/**
 * Base class for the workflow page.  A workflow page responds to the following
 * URLs:
 * - ?_wfid=<workflow_id>&_step=<step> (step is optional)
 *   - display form for editing data for a step
 *   - if step is not specified, default to first step
 * - ?_wfid=<workflow_id>&_step=<step>&action=save&_next_step=<next> (next step
 *   is optional)
 *   - save workflow data
 *   - if next step is not specified, default to the next step as defined by
 *     the data class
 *   - on success, redirect user to the page for the next step.  On failure,
 *     redisplay form.
 * - ?_wfid=<workflow_id>&action=finish
 *   - complete the workflow
 * - ?_wfid=<workflow_id>&action=finished
 *   - display the result of the workflow
 *
 * In addition to the abstract methods, implementations must define methods,
 * for each step, of the form ([foo] is the step ID):
 * - display_step_[foo]($errors): The $errors parameter is optional (default
 *   to "null"), and if specified, indicates that there were errors in the
 *   data.  The format for the $errors parameter is not defined, though it is
 *   suggested to use something similar to the return value for formslib's
 *   validate method.
 * - get_submitted_values_for_step_[foo](): return the submitted values in a
 *   form suitable for the save_values_for_step_[foo] method in the data
 *   class.
 *
 * The "display_step_[foo]" methods must display a "Next" button (or "Finish",
 * if the user is on the last step), and a "Previous" button (if not on the
 * first step).  These buttons when pressed should call URLs as specified
 * above.
 */
abstract class workflowpage extends elis_page {
    /**************************************************************************
     * Methods/members that must be implemented/set by subclasses
     *************************************************************************/

    /**
     * Name of data class for workflow data object
     */
    var $data_class;

    /**
     * URL to redirect to when cancelling.
     */
    var $cancel_url = '/';

    /**
     * Displays the finish screen.
     */
    abstract function display_finished();

    /**
     * Display a summary of what the user has already done (for resuming an
     * interrupted workflow)
     */
    abstract function print_summary();


    /**************************************************************************
     * Helper functions
     *************************************************************************/

    /**
     * The workflow data object
     */
    var $workflow;

    function __construct(array $params=null) {
        global $USER;
        parent::__construct($params);
        $workflow_id = $this->optional_param('_wfid', null, PARAM_INT);
        if ($workflow_id) {
            $this->workflow = _workflow_instance::load_instance($workflow_id);
            if ($this->workflow->type !== $this->data_class
                || $this->workflow->userid != $USER->id) {
                print_error('invalidid', 'elis_core');
            }
        } else {
            $this->workflow = $this->new_workflow();
        }
    }

    /**
     * Returns a new workflow object.
     */
    function new_workflow() {
        global $USER;
        $workflow = new $this->data_class;
        $workflow->type = $this->data_class;
        $workflow->userid = $USER->id;
        return $workflow;
    }

    /**
     * Gets the current step.
     */
    function get_current_step() {
        $curr_step = $this->optional_param('_step', null, PARAM_CLEANFILE);
        $steps = $this->workflow->get_steps();
        if (empty($curr_step)) {
            // if not specified, default to the first step
            reset($steps);
            $curr_step = key($steps);
        }
        if (!isset($steps[$curr_step])) {
            print_error('workflow_invalidstep', 'elis_core');
        }
        return $curr_step;
    }

    /**
     * Gets the next step.
     */
    function get_next_step() {
        $step = $this->optional_param('_next_step', workflow::STEP_NEXT, PARAM_CLEANFILE);
        if ($step === workflow::STEP_NEXT) {
            $curr_step = $this->get_current_step();
            $steps = $this->workflow->get_steps();
            while (($step = each($steps)) !== false) {
                // use while (...each...) because it isn't documented how foreach
                // interacts with key()
                $key = $step['key'];
                $value = $step['value'];
                if ($curr_step === $key) {
                    $step = key($steps);
                    if ($step === null) {
                        $step = workflow::STEP_FINISH;
                    }
                    return $step;
                }
            }
            print_error('workflow_invalidstep', 'elis_core');
        }
        return $step;
    }

    /**
     * Saves the submitted values.
     */
    function save_submitted_values() {
        $step = $this->get_current_step();
        $values = call_user_func(array($this, "get_submitted_values_for_step_{$step}"));
        return call_user_func(array($this->workflow, "save_values_for_step_{$step}"), $values);
    }

    /**
     * Displays the user's progress in the workflow.
     */
    function display_progress() {
        $steps = $this->workflow->get_steps();
        $current = $this->get_current_step();
        $last = $this->workflow->get_last_completed_step();
        $state = $last === null ? 1 : 0;
        $first = true;

        echo '<ul class="workflow_progress clearfix">';
        foreach ($steps as $name => $display) {
            $link = false;
            switch ($state) {
            case 0:
                // the last-completed step and before
                $class = 'workflow_step workflow_step_completed';
                $link = true;
                if ($name === $last) {
                    $state = 1;
                }
                break;
            case 1:
                // the next step to be completed
                $state = 2;
                $link = true;
                $class = 'workflow_step';
                break;
            default:
                // not-yet reachable steps
                $class = 'workflow_step workflow_step_incomplete';
            }
            if ($name === $current) {
                $class .= ' workflow_step_current';
                $link = false;
            }
            echo "<li class=\"$class\">";
            if ($first) {
                $first = false;
            } else {
                echo '<span class="accesshide"><span class="arrowtext">/ </span></span><span class="arrow sep">►</span></span> ';
            }
            if ($link) {
                $target = $this->get_new_page(array('_wfid' => $this->workflow->id,
                                                    '_step' => $name));
                echo "<a href=\"{$target->url}\">";
            }
            echo htmlspecialchars($display);
            if ($link) {
                echo '</a>';
            }
            echo '</li> ';
        }
        echo '</ul>';
    }

    /**
     * Displays the requested step.  By default, it just calls the
     * display_step_x method, where x is the step ID.
     */
    function display_step($errors=null) {
        $step = $this->get_current_step();
        return call_user_func(array($this, "display_step_{$step}"), $errors);
    }

    /**************************************************************************
     * Main UI functions
     *************************************************************************/

    /**
     * Display the requested step
     */
    function display_default() {
        $this->display_progress();
        $this->display_step();
    }

    /**
     * Save submitted values
     */
    function do_save() {
        $errors = $this->save_submitted_values();
        if ($errors !== null) {
            $this->print_header();
            $this->display_progress();
            $this->display_step($errors); // show the same step, with an error code
            $this->print_footer();
        } else {
            $next = $this->get_next_step();
            if ($next !== workflow::STEP_FINISH) {
                $target = $this->get_new_page(array('_wfid' => $this->workflow->id,
                                                    '_step' => $next));
                return redirect($target->url);
            } else {
                $target = $this->get_new_page(array('_wfid' => $this->workflow->id,
                                                    'action' => 'finish'));
                return $target->action_finish();
            }
        }
    }

    /**
     * Finish the workflow
     */
    function do_finish() {
        $this->workflow->finish();
        $target = $this->get_new_page(array('_wfid' => $this->workflow->id,
                                            'action' => 'finished'));
        return redirect($target->url);
    }

    /**
     * Display the final summary
     */
    function print_footer() {
        parent::print_footer();
        if ($this->optional_param('action', '', PARAM_ACTION) == 'finished') {
            $this->workflow->delete();
        }
    }

    function do_cancel() {
        global $CFG;
        if (!empty($this->workflow->id)) {
            $this->workflow->delete();
        }
        return redirect($CFG->wwwroot . $this->cancel_url, get_string('workflow_cancelled', 'elis_core'));
    }

    function print_page() {
        // override the default print_page, so that the redirects don't get in
        // the way
        $action = optional_param('action', 'default', PARAM_ACTION);
        if ($this->can_do($action)) {
            switch ($action) {
            case 'finish':
                $this->action_finish();
                break;
            case 'save':
                $this->action_save();
                break;
            default:
                $this->display();
            }
        } else {
            print_error('nopermissions');
        }
    }

    /***************************************************************************
     * Helper functions
     **************************************************************************/

    /**
     * Add Previous/Next buttons to a formslib form.
     *
     * @param MoodleQuickForm $mform the MoodleQuickForm object (that is,
     * $form->_form) to add the buttons to
     * @param string $prevstep the ID of the previous step
     * @param string $nextstep the ID of the next step.  If null, go to the
     * next step as defined by the workflow.  If it is workflow::STEP_FINISH,
     * it will submit call the workflow's finish method, rather than the save
     * method.
     * @param string $nextlabel the label for the Next button.  If null,
     * default to the language string for "Next" (or "Finish").
     * @uses $CFG
     * @uses $FULLME
     */
    public static function add_navigation_buttons(MoodleQuickForm $mform, $prevstep = null, $nextstep = null, $nextlabel = null) {
        global $CFG, $FULLME;
        require_once ($CFG->dirroot.'/elis/core/lib/form/xbutton.php');
        // ELIS-3501: Previous button was broken in IE7, changed to onclick
        $target = null;
        if ($prevstep && ($workflow_id = optional_param('_wfid', 0, PARAM_INT))) {
            $target = new moodle_url($FULLME, array('_wfid' => $workflow_id,
                                                    '_step' => $prevstep));
            $target = $target->out(false);
        }
        $buttonarray = array();
        $cancelbutton = $mform->createElement('xbutton', 'action', get_string('cancel'), array('value' => 'cancel', 'type' => 'submit'));
        $buttonarray[] = $cancelbutton;
        if ($nextstep === workflow::STEP_FINISH) {
            if ($target) {
                $prevbutton = $mform->createElement('xbutton', 'previous', get_string('previous'), array('value' => $prevstep, 'type' => 'button',
                         'onclick' => "window.location = '{$target}';"));
                $buttonarray[] = $prevbutton;
            }
            $nextbutton = $mform->createElement('xbutton', 'action', $nextlabel === null ? get_string('finish', 'elis_core') : $nextlabel, array('value' => 'finish', 'type' => 'submit'));
        } else {
            if ($target) {
                $prevbutton = $mform->createElement('xbutton', 'previous', get_string('previous'), array('value' => $prevstep, 'type' => 'button',
                         'onclick' => "window.location = '{$target}';"));
                $buttonarray[] = $prevbutton;
            }
            $nextbutton = $mform->createElement('xbutton', $nextstep === null ? '' : '_next_step', $nextlabel === null ? get_string('next') : $nextlabel, array('value' => $nextstep, 'type' => 'submit'));
        }
        $buttonarray[] = $nextbutton;
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }
}

