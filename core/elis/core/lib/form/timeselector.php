<?php

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once "$CFG->libdir/form/group.php";

/**
 * Class for a group of elements used to input a date and time.
 *
 * Emulates moodle print_date_selector function and also allows you to select a time.
 *
 * @author Jamie Pratt <me@jamiep.org>
 * @access public
 */
class elis_time_selector extends MoodleQuickForm_group {
    /**
    * Options for the element
    *
    * timezone => float/string timezone
    * applydst => apply users daylight savings adjustment?
    * step     => step to increment minutes by
    */
    var $_options = array('timezone'=>99, 'applydst'=>true,
                          'step'=>5, 'optional'=>false,
                          'checked' => 'notchecked', 'year' => 1971);

   /**
    * These complement separators, they are appended to the resultant HTML
    * @access   private
    * @var      array
    */
    var $_wrap = array('', '');

    /**
     * display time in either 24h or am/pm format
     * values are 12h or 24h
     * @access  private
     * @var     string
     */
    var $display_12h;

   /**
    * Class constructor
    *
    * @access   public
    * @param    string  Element's name
    * @param    mixed   Label(s) for an element
    * @param    array   Options to control the element's display
    * @param    mixed   Either a typical HTML attribute string or an associative array
    */
    function elis_time_selector($elementName = null, $elementLabel = null, $options = array(), $attributes = null)
    {
        //error_log("timeselector.php::elis_time_selector()");
        $this->HTML_QuickForm_element($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_appendName = true;
        $this->_type = 'date_time_selector';
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

    // }}}
    // {{{ _createElements()

    function _createElements()
    {
        $this->_elements = array();

        if(!empty($this->display_12h)) {
            $display_hours = range(1,11);
            array_unshift($display_hours, 12);

            $hours = array();
            foreach($display_hours as $i) {
                $hours[] = sprintf("%02d am",$i);
            }

            foreach($display_hours as $i) {
                $hours[] = sprintf("%02d pm",$i);;
            }
        } else {
            for ($i=0; $i<=23; $i++) {
                $hours[$i] = sprintf("%02d",$i);
            }
        }

        for ($i=0; $i<60; $i+=$this->_options['step']) {
            $minutes[$i] = sprintf("%02d",$i);
        }

		if (right_to_left()) {   // Switch order of elements for Right-to-Left
			$this->_elements[] =& MoodleQuickForm::createElement('select', 'minute', get_string('minute', 'form'), $minutes, $this->getAttributes(), true);
			$this->_elements[] =& MoodleQuickForm::createElement('select', 'hour', get_string('hour', 'form'), $hours, $this->getAttributes(), true);
		} else {
			$this->_elements[] =& MoodleQuickForm::createElement('select', 'hour', get_string('hour', 'form'), $hours, $this->getAttributes(), true);
			$this->_elements[] =& MoodleQuickForm::createElement('select', 'minute', get_string('minute', 'form'), $minutes, $this->getAttributes(), true);
		}
        // If optional we add a checkbox which the user can use to turn if on
        if($this->_options['optional']) {

            if (0 == strcmp('checked', $this->_options['checked'])) {
                $this->updateAttributes(array('checked'=>'checked'));
            }

            $this->_elements[] =& MoodleQuickForm::createElement('checkbox', 'timeenable',
                                  'null', get_string('disable'), $this->getAttributes(), true);

        }
        foreach ($this->_elements as $element){
            if (method_exists($element, 'setHiddenLabel')){
                $element->setHiddenLabel(true);
            }
        }

    }

    // }}}
    // {{{ onQuickFormEvent()

    /**
     * Called by HTML_QuickForm whenever form event is made on this element
     *
     * @param     string    $event  Name of event
     * @param     mixed     $arg    event arguments
     * @param     object    $caller calling object
     * @since     1.0
     * @access    public
     * @return    void
     */
    function onQuickFormEvent($event, $arg, &$caller)
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
                if ($value == 0) {
                    $value = time();
                }
                if (!is_array($value)) {
                    $currentdate = usergetdate($value, $this->_options['timezone']);
                    // Round minutes to the previous multiple of step.
                    $currentdate['minutes'] -= $currentdate['minutes'] % $this->_options['step'];
                    $value = array(
                        'minute' => $currentdate['minutes'],
                        'hour' => $currentdate['hours']);
                    // If optional, default to off, unless a date was provided
                    if($this->_options['optional']) {
                        $value['off'] = ($requestvalue == 0) ? true : false;
                    }
                } else {
                    $value['off'] = (isset($value['off'])) ? true : false;
                }

                if (null !== $value){
                    $this->setValue($value);
                }
                break;
            case 'createElement':
                if (isset($arg[2]['checked']) and 0 == strcmp('checked', $arg[2]['checked'])) {
                      $caller->setDefault($arg[0].'[timeenable]', true);
                }

                if(!empty($arg[2]['optional'])) {
                      $caller->disabledIf($arg[0], $arg[0].'[timeenable]', 'checked');
                }

                if(!empty($arg[2]['display_12h'])) {
                    $this->display_12h = true;
                }

                return parent::onQuickFormEvent($event, $arg, $caller);
                break;
            default:
                return parent::onQuickFormEvent($event, $arg, $caller);
        }
    }

    // }}}
    // {{{ toHtml()

    function toHtml()
    {
        include_once('HTML/QuickForm/Renderer/Default.php');
        $renderer = new HTML_QuickForm_Renderer_Default();
        $renderer->setElementTemplate('{element}');
        parent::accept($renderer);
        return $this->_wrap[0] . $renderer->toHtml() . $this->_wrap[1];
    }

    // }}}
    // {{{ accept()

    function accept(&$renderer, $required = false, $error = null)
    {
        // 2 is the index where the checkbox is located
        if($this->isFrozen() && $this->_options['optional'] && $this->_elements[2]->getChecked()){
            $this->_elements = array();
            $this->_elements[] =& MoodleQuickForm::createElement('static', 'disabled', '', get_string('disabled', 'filters'));
        } else if($this->isFrozen() && isset($this->_elements[2])) {
            // Remove the frozen checkbox when frozen
            unset($this->_elements[2]);

            // output in a more readable format if frozen
            if (!empty($this->display_12h)) {
                $value = $this->getValue();
                $hour = isset($value['hour']) ? current($value['hour']) : 0;
                $minute = sprintf('%02d', isset($value['minute']) ? current($value['minute']) : 0);
                $ampm = $hour >= 12 ? 'pm' : 'am';
                if ($hour > 12) {
                    $hour -= 12;
                } else if ($hour == 0) {
                    $hour = 12;
                }
                $output = "{$hour}:{$minute} {$ampm}";
            } else {
                $value = $this->getValue();
                $hour = sprintf('%02d', isset($value['hour']) ? current($value['hour']) : 0);
                $minute = sprintf('%02d', isset($value['minute']) ? current($value['minute']) : 0);
                $output = "{$hour}:{$minute}";
            }
            $renderer->renderElement(MoodleQuickForm::createElement('static', $this->getName(), $this->getLabel(), $output), $required, $error);
            return;
        }

        $renderer->renderElement($this, $required, $error);
    }

    // }}}

    /**
     * Output a timestamp. Give it the name of the group.
     *
     * @param array $submitValues
     * @param bool $assoc
     * @return array
     */
    function exportValue(&$submitValues, $assoc = false)
    {
        $value = null;
        $valuearray = array();
        foreach ($this->_elements as $element){
            $thisexport = $element->exportValue($submitValues[$this->getName()], true);
            if ($thisexport!=null){
                $valuearray += $thisexport;
            }
        }
        if (count($valuearray)){
            if($this->_options['optional']) {
                // If checkbox is on, the value is zero, so go no further
                if(!empty($valuearray['off'])) {
                    $value[$this->getName()]=0;
                    return $value;
                }
            }
            $valuearray=$valuearray + array('year'=> $this->_options['year'],
                            'month'=>1, 'day'=>1, 'hour'=>0, 'minute'=>0);
            $value[$this->getName()] = make_timestamp(
                                   $valuearray['year'],
                                   $valuearray['month'],
                                   $valuearray['day'],
                                   $valuearray['hour'],
                                   $valuearray['minute'],
                                   0,
                                   $this->_options['timezone'],
                                   $this->_options['applydst']);
            //error_log("/elis/core/lib/form/timeselector.php::exportValue(): calling make_timestamp( ..., hour => {$valuearray['hour']}, minute => {$valuearray['minute']} [, timezone => {$this->_options['timezone']}, applydst => {$this->_options['applydst']} ]) = {$value[$this->getName()]}");
            return $value;
        } else {
            return null;
        }
    }

    // }}}
}

MoodleQuickForm::registerElementType('time_selector', "{$CFG->dirroot}/elis/core/lib/form/timeselector.php", 'elis_time_selector');
