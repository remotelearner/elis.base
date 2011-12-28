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
 * @package    elis-core
 * @subpackage js
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

/**
 * Update options on child pulldown for dependent select
 * @param  int      id      pulldown field id
 * @param  string   path    web path to report instance
 * @return boolean	        true
 */
function dependentselect_updateoptions(id, path) {
    var parent = document.getElementById('id_'+id+'_parent');
    var parentId = parent.value;
    var child = document.getElementById('id_'+id);
    var childId = child.value;

    var option_success = function(o) {
        var data = YAHOO.lang.JSON.parse(o.responseText);
        child.options.length = 0;

        for (i = 0; i < data.length; i++) {
            //response text is an array of arrays, where each sub-array's
            //first element is the element id and the second is the name
            addOption(child,childId,data[i][0],data[i][1]);
        }
    };

    var option_failure = function(o) {
        alert("failure: " + o.responseText);
    };

    var callback = {
        success: option_success,
        failure: option_failure,
        cache: false
    };

    var requestURL = path + "childoptions.php?id=" + parentId;

    YAHOO.util.Connect.asyncRequest('GET', requestURL, callback, null);

    if (parentId == 0 || parentId == '') {
        child.options[0].selected = true;
    }

    return true;
}

function addOption(child,childId,key,val) {
    var id = child.options.length;
    child.options[id] = new Option(val,key);
    if (key == childId) {
        child.options[id].selected = true;
    }
}

