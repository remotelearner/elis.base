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

global $CFG;
require_once($CFG->dirroot . '/elis/core/lib/setup.php');
require_once(elis::lib('data/data_object.class.php'));
require_once(elis::lib('data/customfield.class.php'));

/**
 * Represents a database record object that may have associated custom fields.
 */
abstract class data_object_with_custom_fields extends elis_data_object {
    /**
     * Cache of fields objects by context level.  Each entry is an array of
     * fields, indexed by the field shortname.
     */
    private static $_fields = array();

    private $_field_data = array();

    private $_field_changed = array();

    private $_fields_loaded = false;

    private $_context;

    /**
     * The prefix used to access custom fields as ordinary members.
     */
    const CUSTOM_FIELD_PREFIX = 'field_';

    /**
     * Can't do fast bulk deletes, because we need to delete custom field data
     * as well.
     */
    static protected $delete_is_complex = true;

    /**
     * Return the context level associated with the object, for fetching custom
     * fields.
     */
    abstract protected function get_field_context_level();

    /**
     * Delete a record, plus all associated custom fields.
     */
    public function delete() {
        $this->_load_context();
        $filter = new field_filter('contextid', $this->_context->id);
        field_data_int::delete_records($filter);
        field_data_num::delete_records($filter);
        field_data_char::delete_records($filter);
        field_data_text::delete_records($filter);
        parent::delete();
    }

    /**
     * Save a record, plus all its custom fields.
     */
    public function save() {
        parent::save();

        //ELIS-6114 - this seems to fix issues related to the default value of a
        //multi-valued custom field not being saved
        $this->to_object();

        $this->_load_context();
        $contextlevel = $this->_context->contextlevel;
        // only save the custom field data that has been changed
        foreach ($this->_field_changed as $name => $changed) {
            if ($changed) {
                $field = self::$_fields[$contextlevel][$name];
                if (isset($this->_field_data[$name])) {
                    field_data::set_for_context_and_field($this->_context, $field, $this->_field_data[$name]);
                } else {
                    // field data was unset, so delete values
                    $fielddatatype = "field_data_{$field->data_type()}";
                    $fieldatatype::delete_records(array(new field_filter('contextid', $this->_context->id),
                                                        new field_filter('fieldid', $field->id)));
                }
            }
            unset($this->_field_changed[$name]);
        }
    }

    /**
     * Converts the data_object a dumb object representation (without
     * associations).  This is required when using the Moodle *_record
     * functions, or get_string.
     *
     * Overridden to add custom fields.
     */
    public function to_object() {
        $obj = parent::to_object();
        $this->_load_field_data();
        foreach ($this->_field_data as $name => $value) {
            $fieldname = "field_{$name}";
            $obj->$fieldname = $value;
        }
        return $obj;
    }

    /**************************************************************************
     * Magic Methods
     *************************************************************************/

    /**
     * Magic get method to returns custom field data when accessed as
     * $this->field_[fieldname], in addition to the parent class' magic getter
     * functionality.
     */
    public function __get($name) {
        $prefix_len = strlen(self::CUSTOM_FIELD_PREFIX);
        if (strncmp($name, self::CUSTOM_FIELD_PREFIX, $prefix_len) == 0) {
            $this->_load_fields();
            $shortname = substr($name, $prefix_len);
            if (isset(self::$_fields[$this->get_field_context_level()][$shortname])) {
                // we have a custom field of the same name
                if (isset($this->_field_data[$shortname])) {
                    // we have data
                    return $this->_field_data[$shortname];
                } else if (!$this->_fields_loaded && empty($this->_field_changed[$shortname])) {
                    // custom fields haven't been loaded yet (and the field
                    // hasn't been unset)
                    $this->_load_field_data();
                    if (isset($this->_field_data[$shortname])) {
                        return $this->_field_data[$shortname];
                    }
                }
                // we have no data for this field
                return null;
            }
            $trace = debug_backtrace();
            $classname = get_class($this);
            trigger_error(
                "Undefined property via __get(): $classname::\${$name} in {$trace[1]['file']} on line {$trace[1]['line']}",
                E_USER_NOTICE);
            return null;
        }
        return parent::__get($name);
    }

    /**
     * Magic set method to set the value of a custom field.  See __get.
     */
    public function __set($name, $value) {
        $prefix_len = strlen(self::CUSTOM_FIELD_PREFIX);
        if (strncmp($name, self::CUSTOM_FIELD_PREFIX, $prefix_len) == 0) {
            $this->_load_fields();
            $contextlevel = $this->get_field_context_level();
            $shortname = substr($name, $prefix_len);
            if (isset(self::$_fields[$contextlevel][$shortname])) {
                if (self::$_fields[$contextlevel][$shortname]->multivalued) {
                    // field is multivalued, so make sure that value is an array
                    if (!is_array($value)) {
                        $value = array($value);
                    }
                } else {
                    // field is not multivalued, so make sure that it isn't an
                    // array
                    if (is_array($value)) {
                        $value = current($value);
                    }
                }
                $this->_field_data[$shortname] = $value;
                $this->_field_changed[$shortname] = true;
                return;
            }
        }
        return parent::__set($name, $value);
    }

    /**
     * Magic set method to check if a custom field is set.  See __get.
     */
    public function __isset($name) {
        $prefix_len = strlen(self::CUSTOM_FIELD_PREFIX);
        if (strncmp($name, self::CUSTOM_FIELD_PREFIX, $prefix_len) == 0) {
            $this->_load_fields();
            $shortname = substr($name, $prefix_len);
            if (isset(self::$_fields[$this->get_field_context_level()][$shortname])) {
                // we have a custom field of the same name
                if (isset($this->_field_data[$shortname])) {
                    // we have data
                    return true;
                } else if (!$this->_fields_loaded && empty($this->_field_changed[$shortname])) {
                    // custom fields haven't been loaded yet (and the field
                    // hasn't been unset)
                    $this->_load_field_data();
                    return isset($this->_field_data[$shortname]);
                }
                return false;
            }
        }
        return parent::__isset($name);
    }

    /**
     * Magic set method to unset the value of a custom field.  See __get.
     */
    public function __unset($name) {
        $prefix_len = strlen(self::CUSTOM_FIELD_PREFIX);
        if (strncmp($name, self::CUSTOM_FIELD_PREFIX, $prefix_len) == 0) {
            $this->_load_fields();
            $shortname = substr($name, $prefix_len);
            if (isset(self::$_fields[$this->get_field_context_level()][$shortname])) {
                unset($this->_field_data[$shortname]);
            }
            $this->_field_changed[$shortname] = true;
            return;
        }
        return parent::__unset($name);
    }

    /**************************************************************************
     * Low-level methods
     *************************************************************************/

    /**
     * Ensure that the context is loaded for this record.
     */
    private function _load_context() {
        if (!isset($this->_context) && isset($this->id)) {
            $ctxclass = context_elis_helper::get_class_for_level($this->get_field_context_level());
            $this->_context = $ctxclass::instance($this->id);
        }
    }

    /**
     * Ensure that the fields that are defined for the context level.  This
     * does not load the field data, just the field definitions.  See
     * _load_field_data().
     */
    private function _load_fields() {
        $contextlevel = $this->get_field_context_level();
        if (!isset(self::$_fields[$contextlevel])) {
            $fields = field::get_for_context_level($contextlevel);
            if (!is_array($fields)) {
                $fields = $fields->to_array('shortname');
            }
            self::$_fields[$contextlevel] = $fields;
        }
    }

    /**
     * Reset the cached list of custom fields (mainly for testing purposes)
     */
    public function reset_custom_field_list() {
        $contextlevel = $this->get_field_context_level();
        unset(self::$_fields[$contextlevel]);
    }

    /**
     * Load the custom field values from the database.
     */
    private function _load_field_data() {
        if (!empty($this->id) && !$this->_fields_loaded) {
            $this->_load_context();
            $this->_load_fields();
            $data = field_data::get_for_context($this->_context);
            foreach (self::$_fields[$this->_context->contextlevel] as $name => $field) {
                if (empty($this->_field_changed[$name]) && isset($data[$name])) {
                    // only set the field data if it hasn't been changed
                    $this->_field_data[$name] = $data[$name];
                }
            }
            $this->_fields_loaded = true;
        }
    }

    protected function _load_data_from_record($rec, $overwrite=false, $field_map=null, $from_db=false, array $extradatafields=array()) {
        parent::_load_data_from_record($rec, $overwrite, $field_map, $from_db, $extradatafields);

        $this->_load_fields();
        $contextlevel = $this->get_field_context_level();
        foreach (self::$_fields[$contextlevel] as $name => $field) {
            // figure out the name of the field to copy from
            $rec_name = self::CUSTOM_FIELD_PREFIX.$name;
            if (is_string($field_map)) {
                // just a simple prefix
                $rec_name = $field_map.$rec_name;
            } else if (is_array($field_map)) {
                if (!isset($field_map[$rec_name])) {
                    // field isn't mapped -- skip it
                    continue;
                }
                $rec_name = $field_map[$rec_name];
            }

            // set the field from the record if:
            // - we don't have a value already set, or if we want to
            //   overwrite; and
            // - the value is set in the source record
            if ((isset($this->_field_data[$name]) || $overwrite)
                && isset($rec->$rec_name)) {
                $this->_field_data[$name] = $rec->$rec_name;
                if (!$from_db) {
                    $this->_field_changed[$name] = true;
                }
            }
        }

        // TODO: causes problems with custom fields
//        if ($from_db) {
//            $this->_fields_loaded = true;
//        }
    }
}