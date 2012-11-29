<?php
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
 * @package    elis
 * @subpackage core
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Override the base Moodle 'context' class to allow for defining the component name and for overriding a couple core
 * Moodle methods that will only work with core context classes.
 */
class context_elis extends context {
    public static $component = 'elis_core';

    public static function get_component() {
        return self::$component;
    }

    public static $alllevels = array();
    public static $namelevelmap = array();

    /**
     * not used
     */
    public function get_url() {
    }

    /**
     * not used
     */
    public function get_capabilities() {
    }

    /**
    * Get a context instance as an object, from a given context id.
    *
    * @static
    * @param int $id context id
    * @param int $strictness IGNORE_MISSING means compatible mode, false returned if record not found, debug message if more found;
    *                        MUST_EXIST means throw exception if no record found
    * @return context|bool the context object or false if not found
    */
    public static function instance_by_id($id, $strictness = MUST_EXIST) {
        global $DB;

        if (is_array($id) or is_object($id) or empty($id)) {
            throw new coding_exception('Invalid context id specified context::instance_by_id()');
        }

        if ($context = context::cache_get_by_id($id)) {
            return $context;
        }

        if ($record = $DB->get_record('context', array('id'=>$id), '*', $strictness)) {
            return context_elis::create_instance_from_record($record);
        }

        return false;
    }

    /**
     * Creates a context instance from a context record
     * @static
     * @param stdClass $record
     * @return context instance
     */
    protected static function create_instance_from_record(stdClass $record) {
        $classname = context_elis_helper::get_class_for_level($record->contextlevel);

        if ($context = context::cache_get_by_id($record->id)) {
            return $context;
        }

        $context = new $classname($record);
        context::cache_add($context);

        return $context;
    }
}


/**
 * Implement some of the methods from the Moodle 'context_helper' class that are specific to the properties defined
 * within this class.
 */
class context_elis_helper extends context_elis {

    public static $alllevels = array();

    public static $namelevelmap = array();

    /**
     * Instance does not make sense here, only static use
     */
    protected function __construct() {
    }

    /**
     * Returns a class name of the context level class
     *
     * @static
     * @param int $contextlevel (CONTEXT_SYSTEM, etc.)
     * @return string class name of the context class
     */
    public static function get_class_for_level($contextlevel) {
        if (isset(self::$alllevels[$contextlevel])) {
            return self::$alllevels[$contextlevel];
        } else if (empty(self::$alllevels)) {
            return 'context_system';
        } else {
            throw new coding_exception('Invalid context level specified');
        }
    }

    /**
     * Returns a context level given a context 'name'
     *
     * @static
     * @param string $contextname ('curriculum', 'track', etc)
     * @return int context level
     */
    public static function get_level_from_name($ctxname) {
        if (isset(self::$namelevelmap[$ctxname])) {
            return self::$namelevelmap[$ctxname];
        } else if (empty(self::$alllevels)) {
            return CONTEXT_SYSTEM;
        } else {
            throw new coding_exception('Invalid context level specified');
        }
    }

    /**
     * Returns a list of all context levels
     *
     * @static
     * @return array int=>string (level=>level class name)
     */
    public static function get_all_levels() {
        return self::$alllevels;
    }

    /**
     * Returns a list of legacy context names and their associated context level int
     *
     * @static
     * @return array string=>int (level legacy name=>level)
     */
    public static function get_legacy_levels() {
        return self::$namelevelmap;
    }

    /**
     * not used
     */
    public function get_url() {
    }

    /**
     * not used
     */
    public function get_capabilities() {
    }

    /**
     * Rebuild paths and depths in all context levels.
     *
     * @static
     * @param bool $force false means add missing only
     * @param mixed $levels array of context level contexts, or NULL for all
     * @return void
     */
    public static function build_all_paths($force = false, $levels = NULL) {
        //TODO: validate this this works for contexts levels other than "user set"

        if ($levels === NULL) {
            //use array keys from $alllevels
            $levels = array_keys(context_elis_helper::$alllevels);
        }

        foreach (self::$alllevels as $key => $classname) {
            if (in_array($key, $levels)) {
                $classname::build_paths($force);
            }
        }

        // reset static course cache - it might have incorrect cached data
        accesslib_clear_all_caches(true);
    }
}

$pmaccesslib = dirname(__FILE__) .'/../program/accesslib.php';
if (file_exists($pmaccesslib)) {
    require_once($pmaccesslib);
}
