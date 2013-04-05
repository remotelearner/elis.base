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
 * @package    elis-core
 * @subpackage js
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

/**
 * Update options on child pulldown for dependent select
 * @param  int      id      pulldown field id
 * @param  string   path    web path to report instance
 * @return boolean	        true
 */
function dependentselect_updateoptions(pid, id, path) {
    var parent = document.getElementById('id_'+pid);
    var child  = document.getElementById('id_'+id);
    var childId = child.value;

    var option_success = function(o) {
        var data;
        var selectCache = [];
        var selected    = false;

        YUI().use('yui2-json', function(Y) {
            var YAHOO = Y.YUI2;
            data = YAHOO.lang.JSON.parse(o.responseText);
        });
        for (i = 0; i < child.options.length; i++) {
            if (child.options[i].select == true) {
                selectCache.push(child.options[i].value);
            }
        }
        
        child.options.length = 0;
        for (i = 0; i < data.length; i++) {
            //response text is an array of arrays, where each sub-array's
            //first element is the element id and the second is the name
            addOption(child, childId, data[i][0], data[i][1]);
        }
        // ELIS-3474/MAEOPPS - Do NOT reset selected option!
        //child.options[0].selected = true;

        if ("fireEvent" in child) {
            child.fireEvent("onchange");
        } else {
            var evt = document.createEvent("HTMLEvents");
            evt.initEvent("change", false, true);
            child.dispatchEvent(evt);
        }
        
        for (i = 0; i < selectCache.length; i++) {
            for (h = 0; h < child.options.length; h++) {
                if (selectCache[i].value == child.options[h].value) {
                    child.options[h].selected = true;
                    selected = true;
                }
            }
        }
        
        if ((! selected) && (typeof child.options[0] !== 'undefined')
                && ((child.options[0].value == 0) || (child.options[0].value == '')) ) {
            child.options[0].selected = true;
        }

        if ("fireEvent" in child) {
            child.fireEvent("onchange");
        } else {
            var evt = document.createEvent("HTMLEvents");
            evt.initEvent("change", false, true);
            child.dispatchEvent(evt);
        }
    };

    var option_failure = function(o) {
        //alert("failure: " + o.responseText);
    };

    var callback = {
        success: option_success,
        failure: option_failure,
        cache: false
    };

    var requestURL = path;
    var selected = new Array();
    var index = 0;
    var join  = "?";
    for (var i = 0; i < parent.options.length; i += 1) {
        if (parent.options[i].selected) {
            index = selected.length;
            requestURL += join + "id[]=" + parent.options[i].value;
            join = "&";
        }
    }

    YUI().use('yui2-connection', function(Y) {
        var YAHOO = Y.YUI2;
        YAHOO.util.Connect.asyncRequest('GET', requestURL, callback, null);
    });

    return true;
}

function addOption(child,childId,key,val) {
    var id = child.options.length;
    child.options[id] = new Option(val,key);
    if (key == childId) {
        child.options[id].selected = true;
    }
}
