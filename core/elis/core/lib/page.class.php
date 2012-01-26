<?php
/**
 * General class for displaying pages in ELIS.
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

require_once $CFG->libdir . '/weblib.php';

if (!defined('BLOCK_POS_CENTRE')) {
    define('BLOCK_POS_CENTRE', 'c');
}

class elis_page {
    var $params = false;
    var $section;

    /**
     * Constructor.
     *
     * @param array $params array of URL parameters.  If  $params is not
     * specified, the constructor for each subclass should load the parameters
     * from the current HTTP request.
     */
    public function __construct($params=false) {
        $this->params = $params;
    }

    public function required_param($name, $type=PARAM_CLEAN) {
        if ($this->params !== false) {
            if (isset($this->params[$name])) {
                return clean_param($this->params[$name], $type);
            } else {
                error('A required parameter ('.$name.') was missing');
            }
        } else {
            return required_param($name, $type);
        }
    }

    public function optional_param($name, $default=NULL, $type=PARAM_CLEAN) {
        if ($this->params !== false) {
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
     * Prints the page header.
     */
    public function print_header() {
        global $CFG, $USER, $PAGE;

        require_once($CFG->libdir.'/blocklib.php');
        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->dirroot.'/my/pagelib.php');


        /// My Moodle arguments:
        $edit        = $this->optional_param('edit', -1, PARAM_BOOL);
        $blockaction = $this->optional_param('blockaction', '', PARAM_ALPHA);

        $mymoodlestr = get_string('mymoodle','my');

        if (isguest()) {
            $wwwroot = $CFG->wwwroot.'/login/index.php';
            if (!empty($CFG->loginhttps)) {
                $wwwroot = str_replace('http:','https:', $wwwroot);
            }

            print_header($mymoodlestr);
            notice_yesno(get_string('noguest', 'my').'<br /><br />'.get_string('liketologin'),
                         $wwwroot, $CFG->wwwroot);
            print_footer();
            die();
        }

        /// Add ELIS stylesheets
        $CFG->stylesheets[] = $CFG->wwwroot.'/elis/core/styles.css';

        /// Fool the page library into thinking we're in My Moodle.
        $CFG->pagepath = $CFG->wwwroot.'/my/index.php';
        $PAGE = page_create_instance($USER->id);

        if ($section = $this->optional_param('section', '', PARAM_ALPHAEXT)) {
            $PAGE->section = $section;
        }

        $this->pageblocks = blocks_setup($PAGE,BLOCKS_PINNED_BOTH);


        /// Make sure that the curriculum block is actually on this
        /// user's My Moodle page instance.
        if ($cablockid = get_field('block', 'id', 'name', 'curr_admin')) {
            if (!record_exists('block_pinned', 'blockid', $cablockid, 'pagetype', 'my-index')) {
                blocks_execute_action($PAGE, $this->pageblocks, 'add', (int)$cablockid, true, false);
            }
        }


        if (($edit != -1) and $PAGE->user_allowed_editing()) {
            $USER->editing = $edit;
        }

        //$PAGE->print_header($mymoodlestr);
        $title = $this->get_title();
        print_header($title, $title, build_navigation($this->get_navigation()));

        echo '<table border="0" cellpadding="3" cellspacing="0" width="100%" id="layout-table">';
        echo '<tr valign="top">';


        $blocks_preferred_width = bounded_number(180, blocks_preferred_width($this->pageblocks[BLOCK_POS_LEFT]), 210);

        if(blocks_have_content($this->pageblocks, BLOCK_POS_LEFT) || $PAGE->user_is_editing()) {
            echo '<td style="vertical-align: top; width: '.$blocks_preferred_width.'px;" id="left-column">';
            blocks_print_group($PAGE, $this->pageblocks, BLOCK_POS_LEFT);
            echo '</td>';
        }

        echo '<td valign="top" id="middle-column">';

        if (blocks_have_content($this->pageblocks, BLOCK_POS_CENTRE) || $PAGE->user_is_editing()) {
            blocks_print_group($PAGE, $this->pageblocks, BLOCK_POS_CENTRE);
        }
    }

    /**
     * Prints the page footer.
     */
    public function print_footer() {
        global $PAGE;
        // Can only register if not logged in...
        echo '</td>';

        $blocks_preferred_width = bounded_number(180, blocks_preferred_width($pageblocks[BLOCK_POS_RIGHT]), 210);

        if (blocks_have_content($this->pageblocks, BLOCK_POS_RIGHT) || $PAGE->user_is_editing()) {
            echo '<td style="vertical-align: top; width: '.$blocks_preferred_width.'px;" id="right-column">';
            blocks_print_group($PAGE, $this->pageblocks, BLOCK_POS_RIGHT);
            echo '</td>';
        }

        /// Finish the page
        echo '</tr></table>';
        print_footer();
    }

    /**
     * Returns the title of the page.  Do not override this method.  Instead,
     * create get_title_<action> methods.
     */
    public function get_title() {
        $action = $this->optional_param('action', 'default', PARAM_ACTION);
        if (method_exists($this, 'get_title_' . $action)) {
            return call_user_func(array($this, 'get_title_' . $action));
        } else {
            return $this->get_title_default();
        }
    }

    /**
     * Returns the default page title.
     */
    public function get_title_default() {
        return get_string('elis', 'elis_core');
    }

    /**
     * Returns the navigation links, as used by the Moodle build_header
     * function.  Do not override this method.  Instead, create
     * get_navigation_<action> methods.
     */
    public function get_navigation() {
        $action = $this->optional_param('action', 'default', PARAM_ACTION);
        if (method_exists($this, 'get_navigation_' . $action)) {
            $navigation = call_user_func(array($this, 'get_navigation_' . $action));
        } else if (method_exists($this, 'get_navigation_default')) {
            $navigation = $this->get_navigation_default();
        } else {
            $navigation = array();
        }
        return $navigation;
    }

    /**
     * Prints the main body of the page.  By default, it calls the
     * action_<action> functions.
     */
    public function print_body() {
        $action = $this->optional_param('action', '', PARAM_ACTION);
        if ($action and method_exists($this, 'action_' . $action)) {
            return call_user_func(array($this, 'action_' . $action));
        } else if (!$action and method_exists($this, 'action_default')) {
            return $this->action_default();
        } else {
            echo get_string('unknown_action', 'elis_core', $action);
        }
    }

    /**
     * Determines whether or not the user can perform the specified action.  By
     * default, it calls the can_do_<action> functions.
     */
    public function can_do($action=NULL) {
        if ($action === NULL) {
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
     * Prints the full page.  This method first ensures that the user can
     * perform the specified action, before printing the page.
     */
    public function print_page() {
        $action = $this->optional_param('action', 'default', PARAM_ACTION);
        if ($this->can_do($action)) {
            $this->print_header();
            $this->print_body();
            $this->print_footer();
        } else {
            print_error('nopermissions');
        }
    }

    /**
     * Return the URL for the base page.
     */
    public function get_base_url() {
        global $ME;
        return $ME;
    }

    /**
     * Create a url to the current page.
     *
     * @return moodle_url
     */
    public function get_moodle_url($extra = array()) {
        global $CFG;
        $params = $this->params === false ? array() : $this->params;

        $url = new moodle_url($this->get_base_url(), $params);

        foreach($extra as $name=>$value) {
            $url->param($name, $value);
        }

        return $url;
    }

    /**
     * Returns the URL for the current page, including parameters passed in
     *
     * @param   string array  $extra  Extra parameters to add
     *
     * @return  moodle_url            The appropriate URL object
     */
    public function get_url($extra = array()) {
        return $this->get_moodle_url($extra)->out();
    }

    public function get_new_page($params=false) {
        $page_class = get_class($this);
        return new $page_class($params);
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
