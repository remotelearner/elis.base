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

require_once("HTML/QuickForm/xbutton.php");

/**
 * HTML class for a push button
 */
class elis_MoodleQuickForm_xbutton extends HTML_QuickForm_xbutton {
    function elis_MoodleQuickForm_xbutton($elementName=null, $elementContent = null, $attributes = null) {
        parent::HTML_QuickForm_xbutton($elementName, $elementContent, $attributes);
    }

    /**
     * Slightly different container template when frozen. Don't want to display a submit
     * button if the form is frozen.
     *
     * @return string
     */
    function getElementTemplateType(){
        if ($this->_flagFrozen){
            return 'nodisplay';
        } else {
            return 'default';
        }
    }

    function freeze(){
        $this->_flagFrozen = true;
    }
}

global $CFG;
MoodleQuickForm::registerElementType('xbutton', "$CFG->dirroot/elis/core/lib/form/xbutton.php", 'elis_MoodleQuickForm_xbutton');
