<?php
/**
 * Select an ID Number for a gradebook item from a Moodle course
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
require_once("$CFG->libdir/form/text.php");

class elis_gradebook_idnumber_selector extends MoodleQuickForm_text {
    /**
     * the name used to identify the formslib element
     */
    const NAME = 'elis_gradebook_idnumber_selector';

    /**
     * Options for the element
     *
     * 'courseid' => the Moodle course ID to get the items from
     * 'lockcourse' => don't allow the user to select a different course
     */
    public $_options = array(
        'courseid' => '',
        'lockcourse' => false,
    );

    /**
     * Construct a gradebook ID number selector.
     *
     * @param string $elementName Element's name
     * @param mixed $elementLabel Label(s) for an element
     * @param array $options Options to control the element's display
     * @param mixed $attributes Either a typical HTML attribute string or an associative array
     */
    public function elis_gradebook_idnumber_selector($elementName=null, $elementLabel=null, $options=array(), $attributes=null) {
        $this->MoodleQuickForm_text($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_appendName = true;
        $this->_type = self::NAME;
        $this->_options['nocoursestring'] = get_string('nocourseselected', 'elis_core');
        // set the options, do not bother setting bogus ones
        if (is_array($options)) {
            foreach ($options as $name => $value) {
                if (isset($this->_options[$name])) {
                    if (is_array($value) && is_array($this->_options[$name])) {
                        $this->_options[$name] = @array_merge($this->_options[$name], $value);
                    } else {
                        $this->_options[$name] = $value;
                    }
                }
            }
        }
    }

    public function toHtml() {
        if (!$this->_flagFrozen) {
            $this->_generateId();
            $id = $this->getAttribute('id');

            global $PAGE;
            $options = array('textelemid' => $id,
                             'courseid' => $this->_options['courseid'],
                             'lockcourse' => $this->_options['lockcourse'],
                             'nocoursestring' => $this->_options['nocoursestring'],
            );
            $PAGE->requires->js_init_call("M.elis_core.init_gradebook_popup", array($options), false, elis::$jsmodule);
        }

        return parent::toHtml();
    }
}

/* first argument is the string that will be used to identify the element.
 * second argument is the filename that contains the class definition
 * third argument is the class name
 */
MoodleQuickForm::registerElementType(elis_gradebook_idnumber_selector::NAME, __FILE__, 'elis_gradebook_idnumber_selector');
