<?php // $Id$

/**
 * ELIS_info Block is a Moodle block to display ELIS components
 * and their release version string.
 * 
 * @author Brent Boghosian <brent.boghosian@remote-learner.net>
 * @copyright Copyright (c) 2011 Remote-Learner
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package ELIS
 */ 

class block_elis_info extends block_base {

    function init() {
        $this->title = get_string('elis_info', 'block_elis_info');
        $this->version = 2011020100;
        $this->release = '1.0';
    }

    function applicable_formats() {
        return array('site' => true);
    }

    /**
     * Simply displays a link to ELIS Information page: blocks/elis_info.php
     *
     * @uses $CFG
     */
    function get_content() {
        global $CFG;

        if ($this->content !== NULL) {
            return $this->content;
        }

        // check user capabilities to view elis_info
        $context = get_context_instance(CONTEXT_SYSTEM, SITEID);
        if (!has_capability('block/elis_info:view', $context)) {
            return '';
        }

        $this->content = new stdClass;
        $this->content->text = '<center>'.
               "<a href=\"{$CFG->wwwroot}/blocks/elis_info/elis_info.php\">".
               get_string('elis_info','block_elis_info'). '</a></center>';
        $this->content->footer = '';

        return $this->content;
    }

}

?>
