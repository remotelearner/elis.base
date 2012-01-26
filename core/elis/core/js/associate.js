/**
 * Generic JavaScript methods for a association/selection page.  Allows
 * multiple items to be selected using checkboxes, and use AJAX to do
 * paging/searching while maintaining the selection.  The selection will be
 * submitted as a form fieled called '_selection', which will be a JSON-encoded
 * array.
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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

/**
 * Returns the first element found that has the given name attribute
 */
function get_element_by_name(name) {
    return YAHOO.util.Dom.getElementsBy(function(el) { return el.getAttribute("name") == name; })[0];
}


/**
 * Convert all links and forms within the list_display div to load within the
 * div.
 */
function make_links_internal() {
    var list_display = document.getElementById('list_display');
    // catch any click events, to catch user clicking on a link
    YAHOO.util.Event.addListener(list_display, "click", load_link);
    // catch any form submit events
    // IE doesn't bubble submit events, so we have to listen on each form
    // element (which hopefully isn't too many)
    YAHOO.util.Dom.getElementsBy(function(el) { return true; },
				 'form', 'list_display',
				 function(el) {
				     YAHOO.util.Event.addListener(el, "submit", load_form, el.getAttribute('id'));
				 });
}

YAHOO.util.Event.onDOMReady(make_links_internal);

// compatibility function for browsers that don't suuport indexOf method
var array_index_of;
if (Array().indexOf) {
    array_index_of = function(haystack, needle) {
	return haystack.indexOf(needle);
    };
} else {
    array_index_of = function(haystack, needle) {
	for (var i = 0; i < haystack.length; i++) {
	    if (haystack[i] == needle) {
		return i;
	    }
	}
	return -1;
    };
}

function array_contains(haystack, needle) {
    return array_index_of(haystack, needle) != -1;
}

// whether or not the scripts from the innerhtml have already been run
var innerhtml_scripts_run = false;

/**
 * When we receive new content from the server, replace the list_display div
 * with it.
 */
function set_content(resp) {
    var div = document.createElement('div');
    div.id = 'list_display';
    innerhtml_scripts_run = false;
    div.innerHTML = '<script>innerhtml_scripts_run = true;</script>' + resp.responseText;
    var olddiv = document.getElementById('list_display');
    olddiv.parentNode.replaceChild(div, olddiv);
    make_links_internal();
    mark_selected();
    if (!innerhtml_scripts_run) {
	YAHOO.util.Dom.getElementsBy(function(el) { return true; },
                                     'script', div.id,
                                     function(el) {
					 eval(el.text);
                                     });
    }
}

var set_content_callback = {
    success: set_content
};

var lastrequest = basepage;

/**
 * event handler for links within the list_display div
 */
function load_link(ev) {
    var target = YAHOO.util.Event.getTarget(ev);
    if (!target.getAttribute("href")) return;
    lastrequest = target.getAttribute("href");
    YAHOO.util.Connect.asyncRequest("GET", lastrequest + "&mode=bare", set_content_callback, null);
    YAHOO.util.Event.preventDefault(ev);
}

/**
 * event handler for forms within the list_display div
 */
function load_form(ev) {
    var target = YAHOO.util.Event.getTarget(ev);
    var data = YAHOO.util.Connect.setForm(target);
    var link = target.getAttribute('action');
    lastrequest = link + '?' + data;
    YAHOO.util.Connect.asyncRequest("POST", link + "?mode=bare", set_content_callback, null);
    YAHOO.util.Event.preventDefault(ev);
}

/**
 * event handler for "show selected only" checkbox
 */
function change_selected_display() {
    var selected_only = get_element_by_name("selectedonly");
    if (selected_only.checked) {
	var _selection = selection_field.value;
	if (!_selection) {
	    _selection = "[]";
	}
	YAHOO.util.Connect.asyncRequest("GET", basepage + "&mode=bare&_showselection="+_selection, set_content_callback, null);
    } else {
	YAHOO.util.Connect.asyncRequest("GET", basepage + "&mode=bare", set_content_callback, null);
    }
}


var selection = new Array();
var selection_field = null;

YAHOO.util.Event.onDOMReady(function() {
    selection_field = get_element_by_name("_selection");
    selection_field.value = '';
});

/**
 * event handler for (de)selecting an item
 * - update the selected items list
 */
function select_item(id) {
    var value = selection_field.value;
    if (get_element_by_name("select"+id).checked) {
        // add the id to the selection list
        if (!array_contains(selection, id)) {
	    selection.push(id);
	}
    } else {
        // remove the id from the selection list
        var pos = array_index_of(selection, id);
	if (pos != -1) {
	  if (pos == selection.length-1) {
	      // if the id is the last element, just pop it
	      selection.pop();
	  } else {
	      // otherwise, replace it with the last element from the list
	      selection[pos] = selection.pop();
	  }
	}
    }
    // AJAX-encoded array
    selection_field.value = '[' + selection.join(',') + ']';
    document.getElementById("numselected").innerHTML = selection.length;
}

/**
 * when the table is loaded, mark which elements have already been selected
 */
function mark_selected() {
    var table = document.getElementById('selectiontable');
    var numselected = 0;
    if (table) {
	YAHOO.util.Dom.getElementsBy(function(el) { return true; },
				     'input', table,
				     function(el) {
					 var id = el.name.substr(6);
					 el.checked = array_contains(selection, id);
					 if (el.checked) numselected++;
				     });
    }
    document.getElementById("numonotherpages").innerHTML = (selection.length - numselected);
    if (selection.length != numselected) {
	document.getElementById("selectedonotherpages").style.display = 'inline';
    } else {
	document.getElementById("selectedonotherpages").style.display = 'none';
    }
}

YAHOO.util.Event.onDOMReady(mark_selected);

/**
 * event handler for "select all" checkbox
 */
function select_all() {
    var table = document.getElementById('selectiontable');
    if (table) {
	YAHOO.util.Dom.getElementsBy(function(el) { return true; },
				     'input', table,
				     function(el) {
					 el.checked = true;
					 id = el.name.substr(6);
					 select_item(id);
				     });
    }
    var button = get_element_by_name('selectall');
    button.checked = false;
}
