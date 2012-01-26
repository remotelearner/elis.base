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

/**
 * Base class for workflow instance data record.  This class should not be used except
 * in the workflow and workflow_page classes.  Use the workflow class for data
 * functions.
 */
class _workflow_instance {
    static $table_name = 'elis_workflow_instances';

    public function __construct($data=false) {
        if (is_numeric($data)) {
            $data = get_record(self::$table_name, 'id', $data);
        }

        if (is_object($data)) {
            if (isset($data->id)) {
                $this->id = $data->id;
            }
            if (isset($data->type)) {
                $this->type = $data->type;
            } else {
                $this->type = get_class($this);
            }
            if (isset($data->subtype)) {
                $this->subtype = $data->subtype;
            }
            if (isset($data->userid)) {
                $this->userid = $data->userid;
            }
            if (isset($data->data)) {
                $this->data = $data->data;
            }
            if (isset($data->timemodified)) {
                $this->timemodified = $data->timemodified;
            }
        }
    }

    /**
     * Load a workflow data record from the database.  Returns an object of the
     * appropriate type.
     *
     * @param mixed $data a record ID or a record object to load the data from
     */
    static public function load($data) {
        $obj = new self($data);
        $type = $obj->type;
        $obj = new $type($obj);
        return $obj;
    }

    /**
     * Saves (inserts or updates) a workflow data record to the database.
     */
    public function save() {
        $this->timemodified = time();
        if (!empty($this->id)) {
            update_record(self::$table_name, addslashes_recursive($this));
        } else {
            $this->id = insert_record(self::$table_name, addslashes_recursive($this));
        }
    }

    /**
     * Deletes the current workflow data record from the database.
     */
    public function delete() {
        return delete_records(self::$table_name, 'id', $this->id);
    }

    /**
     * Helper function: unserializes $this->data if non-empty.  Otherwise
     * returns $default.
     */
    public function unserialize_data($default = null) {
        if (!empty($this->data)) {
            return unserialize($this->data);
        }
        return $default;
    }

    /**
     * Clean up stale (> 1 week old) workflow instances.
     */
    static public function cleanup() {
        $oneweekago = time() - (7*DAYSECS);
        delete_records_select(self::$table_name, "timemodified < $oneweekago");
    }
}

/**
 * This class represents a the data for a workflow (a wizard-like interface).
 *
 * In addition to the abstract methods, implementations must define methods of
 * the form "save_values_for_step_[foo]($values)" for each step, where [foo] is
 * the step ID.  It should return true if it succeeded, or some error data that
 * can be interpreted by the "display_step_[foo]" method from the corresponding
 * page class.  The $values parameter is in a format defined by the
 * get_submitted_values_for_step_[foo] method in the workflow page class.
 */
abstract class workflow extends _workflow_instance {
    /**
     * step name constant for confirmation step
     */
    const STEP_CONFIRM = 'confirm';
    /**
     * step name constant for the special "next" step
     */
    const STEP_NEXT = '_next';
    /**
     * step name constant for the special "finish" step
     */
    const STEP_FINISH = '_finish';

    /**
     * Returns an array of steps.  The array keys are IDs for the steps, and
     * the array values are the display strings.  Step IDs must be able to be
     * validated as PARAM_CLEANFILE, and must not be the special strings
     * "_next" or "_finish".  It is highly recommended that the last step is a
     * confirmation screen.  The display string SHOULD contain a step number.
     */
    abstract function get_steps();

    /**
     * Returns the ID of the last completed step, or null if no step has been
     * completed.
     */
    abstract function get_last_completed_step();

    /**
     * Completes the workflow.
     */
    abstract function finish();
}

?>
