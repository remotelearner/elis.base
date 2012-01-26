<?php
/**
 * Base ELIS page class
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

/**
 * Base ELIS page class.  Provides a framework for displaying a standard page
 * and performing actions.
 *
 * Subclasses must have a do_<foo>() or display_<foo>() method for each action
 * <foo> that it supports.  The default action (if none is specified) is called
 * "default", and so is handled by display_default() (or do_default(), though
 * you really shouldn't do that).
 */
abstract class elis_page extends moodle_page {
    /**
     * Page parameters (if null, use the HTTP parameters)
     */
    private $params = null;

    /**
     * Constructor.
     *
     * Subclasses must override this and set the Moodle page parameters
     * (e.g. context, url, pagetype, title, etc.).
     *
     * @param array $params array of URL parameters.  If  $params is not
     * specified, the constructor for each subclass should load the parameters
     * from the current HTTP request.
     */
    public function __construct(array $params=null) {
        $this->params = $params;
        $this->set_context($this->_get_page_context());
        $this->set_url($this->_get_page_url(), $this->_get_page_params());
        //set up a CSS hook for styling all ELIS pages
        $this->add_body_class('elis_page');
    }

    /**
     * Return the context that the page is related to.  Used by the constructor
     * for calling $this->set_context().
     */
    protected function _get_page_context() {
        return get_context_instance(CONTEXT_SYSTEM);
    }

    /**
     * Return the base URL for the page.  Used by the constructor for calling
     * $this->set_url().  Although the default behaviour is somewhat sane, this
     * method should be overridden by subclasses if the page may be created to
     * represent a page that is not the current page.
     */
    protected function _get_page_url() {
        global $ME;
        return $ME;
    }

    /**
     * Return the page parameters for the page.  Used by the constructor for
     * calling $this->set_url().
     *
     * @return array
     */
    protected function _get_page_params() {
        $params = isset($this->params) ? $this->params : $_GET;
        return $params;
    }

    /**
     * Return the page type.  Used by the constructor for calling
     * $this->set_pagetype().
     */
    protected function _get_page_type() {
        return 'elis';
    }

    /**
     * Return the page layout.  Used by the constructor for calling
     * $this->set_pagelayout().
     */
    protected function _get_page_layout() {
        return 'standard';
    }

    /**
     * Create a new page object of the same class with the given parameters.
     *
     * @param array $params array of URL parameters.
     * @param boolean $replace_params whether the page URL parameters should be
     * replaced by $params (true) or whether the page URL parameters should be
     * $params appended to the original page parameters (false).
     */
    public function get_new_page(array $params=null, $replace_params=false) {
        $pageclass = get_class($this);
        if (!$replace_params) {
            if ($params === null) {
                $params = $this->params;
            } else if ($this->url->params() !== null) {
                $params += $this->url->params();
            }
        }
        return new $pageclass($params);
    }

    /**
     * Get required page parameters.
     */
    public function required_param($name, $type=PARAM_CLEAN) {
        if ($this->params !== null) {
            if (isset($this->params[$name])) {
                return clean_param($this->params[$name], $type);
            } else {
                print_error('missingparam', '', '', $name);
            }
        } else {
            return required_param($name, $type);
        }
    }

    /**
     * Get optional page parameters.
     */
    public function optional_param($name, $default, $type) {
        if ($this->params !== null) {
            if (isset($this->params[$name])) {
                return clean_param($this->params[$name], $type);
            } else {
                return $default;
            }
        } else {
            return optional_param($name, $default, $type);
        }
    }

    /**
     * Return the page title.  Used by the constructor for calling
     * $this->set_title().
     */
    public function get_page_title($action=null) {
        if ($action === null) {
            $action = $this->optional_param('action', '', PARAM_ACTION);
        }
        if (method_exists($this, "get_page_title_{$action}")) {
            return call_user_func(array($this, "get_page_title_{$action}"));
        } else {
            return $this->get_page_title_default();
        }
    }

    public function get_page_title_default() {
        return get_string('elis', 'elis_core');
    }

    /**
     * Build the navigation bar object
     */
    public function build_navbar($action=null) {
        if ($action === null) {
            $action = $this->optional_param('action', '', PARAM_ACTION);
        }
        if (method_exists($this, "build_navbar_{$action}")) {
            return call_user_func(array($this, "build_navbar_{$action}"));
        } else {
            return $this->build_navbar_default();
        }
    }

    public function build_navbar_default() {
        // Do nothing (default to empty navbar)
    }

    /**
     * Return the page heading.  Used by the constructor for calling
     * $this->set_heading().
     */
    protected function get_page_heading($action=null) {
        return $this->get_page_title();
    }


    /**
     * Main page entry point.  Dispatches based on the action parameter.
     */
    public function run() {
        global $OUTPUT;
        $action = $this->optional_param('action', 'default', PARAM_ACTION);
        if ($this->can_do($action)) {
            $this->_init_display();
            if (method_exists($this, "do_{$action}")) {
                return call_user_func(array($this, "do_{$action}"));
            } else if (method_exists($this, 'display_' . $action)) {
                $this->display($action);
            } else {
                print_error('unknown_action', 'elis_core', '', $action);
            }
        } else {
            print_error('nopermissions', '', '', $action);
        }
    }

    /**
     * Initialize the page variables needed for display.
     */
    protected function _init_display() {
        $this->set_pagelayout($this->_get_page_layout());
        $this->set_pagetype($this->_get_page_type());
        $this->set_title($this->get_page_title());
        $this->set_heading($this->get_page_heading());
        $this->build_navbar();
    }

    /**
     * Print the page header.
     */
    public function print_header() {
        global $OUTPUT;
        echo $OUTPUT->header();
    }

    /**
     * Print the page footer.
     */
    public function print_footer() {
        global $OUTPUT;
        echo $OUTPUT->footer();
    }

    /**
     * Display the page.
     */
    public function display($action=null) {
        if ($action === null) {
            $action = $this->optional_param('action', 'default', PARAM_ACTION);
        }
        $this->print_header();
        call_user_func(array($this, 'display_' . $action));
        $this->print_footer();
    }

    /**
     * Determines whether or not the user can perform the specified action.  By
     * default, it calls the can_do_<action> functions.
     */
    public function can_do($action=null) {
        if ($action === null) {
            $action = $this->optional_param('action', '', PARAM_ACTION);
        }
        if (method_exists($this, 'can_do_' . $action)) {
            return call_user_func(array($this, 'can_do_' . $action));
        } else if (method_exists($this, 'can_do_default')) {
            return $this->can_do_default();
        } else {
            return false;
        }
    }

    /**
     * Specifies a unique shortname for the entity represented by
     * a page of this type, transforming the supplied value if necessary
     *
     * @param   string       $parent_path  Path of all parent elements, or the empty string
     *                                     if none
     * @param   string       $name         Initial name provided for the element
     *
     * @return  string|NULL                A valid name to identify the item with, or NULL if
     *                                     not applicable
     */
    static function get_entity_name($parent_path, $name) {
        //implement in child class if necessary
        return NULL;
    }
}
