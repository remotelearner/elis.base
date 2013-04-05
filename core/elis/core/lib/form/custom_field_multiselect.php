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

global $CFG;
require_once("{$CFG->libdir}/form/group.php");
require_once(elis::lib('data/customfield.class.php'));

class elis_custom_field_multiselect extends MoodleQuickForm_group {
    /**
     * the name used to identify the formslib element
     */
    const NAME = 'elis_custom_field_multiselect';

    /**
     * Options for the element
     *
     * 'contextlevel' => the context level to display fields for
     * 'fieldfilter' => a callback function for filtering out fields.  If this
     *     is set, it should be a callback function that takes a field as an
     *     argument, and returns true if the field should be shown, and false
     *     otherwise.
     */
    public $_options = array(
        'contextlevel' => CONTEXT_SYSTEM,
        'fieldfilter' => null,
    );

    /**
     * Construct a custom field multiselect.
     *
     * @param string $elementName Element's name
     * @param mixed $elementLabel Label(s) for an element
     * @param array $options Options to control the element's display
     * @param mixed $attributes Either a typical HTML attribute string or an associative array
     */
    public function elis_custom_field_multiselect($elementName=null, $elementLabel=null, $options=array(), $attributes=null) {
        $this->HTML_QuickForm_element($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_appendName = true;
        $this->_type = self::NAME;
        // set the options, do not bother setting bogus ones
        if (is_array($options)) {
            foreach ($options as $name => $value) {
                if (array_key_exists($name, $this->_options)) {
                    if (is_array($value) && is_array($this->_options[$name])) {
                        $this->_options[$name] = @array_merge($this->_options[$name], $value);
                    } else {
                        $this->_options[$name] = $value;
                    }
                }
            }
        }
    }

    /**
     * Create the elements of the group
     */
    public function _createElements() {
        global $PAGE, $OUTPUT;

        $attributes = $this->getAttributes();
        if (!$attributes) {
            $attributes = array();
        }

        $this->_generateId();
        $id = $this->getAttribute('id');

        $this->_elements = array();
        // a hidden element to contain the actual values to be submitted
        $this->_elements[] = MoodleQuickForm::createElement('hidden', 'value', '', array('id' => $id.'_value'));
        // a container that will be populated by JavaScript
        $this->_elements[] = MoodleQuickForm::createElement('static', '', '', "<div id=\"{$id}_container\"></div>");

        $options = array(
            'id' => $id,
            'up' => $OUTPUT->pix_url('t/up')->out(false),
            'down' => $OUTPUT->pix_url('t/down')->out(false),
            'del' => $OUTPUT->pix_url('t/delete')->out(false),
        );
        $fields = field::get_for_context_level($this->_options['contextlevel']);
        $fieldsbycategory = array();
        $filter = $this->_options['fieldfilter'];
        foreach ($fields as $field) {
            if ($filter && !call_user_func($filter, $field)) {
                continue;
            }
            if (!isset($fieldsbycategory[$field->categoryname])) {
                $fieldsbycategory[$field->categoryname] = array();
            }
            $fieldsbycategory[$field->categoryname][$field->id] = $field->name;
        }
        $options['fields'] = $fieldsbycategory;

        $PAGE->requires->js_init_call('M.elis_core.init_custom_field_multiselect', array($options), false, elis::$jsmodule);

        foreach ($this->_elements as $element){
            if (method_exists($element, 'setHiddenLabel')){
                $element->setHiddenLabel(true);
            }
        }

    }

    /**
     * Called by HTML_QuickForm whenever form event is made on this element
     *
     * @param string $event Name of event
     * @param mixed $arg event arguments
     * @param object $caller calling object
     * @return void
     */
    public function onQuickFormEvent($event, $arg, &$caller)
    {
        switch ($event) {
            case 'updateValue':
                // constant values override both default and submitted ones
                // default values are overriden by submitted
                $value = $this->_findValue($caller->_constantValues);
                if (null === $value) {
                    // if no boxes were checked, then there is no value in the array
                    // yet we don't want to display default value in this case
                    if ($caller->isSubmitted()) {
                        $value = $this->_findValue($caller->_submitValues);
                    } else {
                        $value = $this->_findValue($caller->_defaultValues);
                    }
                }
                $requestvalue=$value;
                if (!is_array($value)) {
                    $value = array(
                        'value' => $requestvalue,
                    );
                }
                if (null !== $value) {
                    $this->setValue($value);
                }
                break;
            default:
                return parent::onQuickFormEvent($event, $arg, $caller);
        }
    }

    /**
     * Return the formslib elements's value
     *
     * @param array $submitValues
     * @param bool $assoc
     * @return array
     */
    public function exportValue(&$submitValues, $assoc = false)
    {
        $value = null;
        $valuearray = array();
        foreach ($this->_elements as $element){
            $thisexport = $element->exportValue($submitValues[$this->getName()], true);
            if ($thisexport!=null){
                $valuearray += $thisexport;
            }
        }

        $value = array($this->getName() => $valuearray['value']);

        return $value;
    }
}

/* first argument is the string that will be used to identify the element.
 * second argument is the filename that contains the class definition
 * third argument is the class name
 */
MoodleQuickForm::registerElementType(elis_custom_field_multiselect::NAME, __FILE__, 'elis_custom_field_multiselect');
