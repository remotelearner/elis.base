/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
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
 * @package    elis_core
 * @subpackage filters
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

YUI.add('moodle-elis_core-dependentselect', function(Y) {

    /**
     * The filterbase module
     * @property FILTERBASENAME
     * @type {String}
     * @default "core-dependentselect"
     */
    var FILTERBASENAME = 'core-dependentselect';

    /**
     * This method calls the base class constructor
     * @method FILTERBASE
     */
    var FILTERBASE = function() {
        FILTERBASE.superclass.constructor.apply(this, arguments);
    }

    /**
     * @class M.elis_core.filters.dependentselect
     */
    Y.extend(FILTERBASE, Y.Base, {
        /**
         * The parent select id
         * @property pid
         * @type {String}
         * @default ''
         */
        pid : '',

        /**
         * The child select id
         * @property id
         * @type {String}
         * @default ''
         */
        id : '',

        /**
         * The path to the script that returns options
         * @property path
         * @type {String}
         * @default ''
         */
        path : '',

        /**
         * Initialize the dependentselect module
         * @param array args function arguments: array(pid, id, path)
         */
        initializer : function(args) {
            this.pid = args[0];
            this.id = args[1];
            this.path = args[2];
            Y.on('change', this.updateoptions, '#id_'+this.pid, this);
            this.updateoptions();
        },

        /**
         * Update options on child pulldown for dependent select
         * @return boolean true on success, false otherwise
         */
        updateoptions : function() {
            var parent = document.getElementById('id_'+this.pid);
            var child  = document.getElementById('id_'+this.id);

            if (!parent || !child) {
                return false;
            }

            var option_success = function(transid, o) {
                var data = Y.JSON.parse(o.responseText);
                var selectCache = [];
                var selected    = false;
                var childId     = child.value;

                for (var i = 0; i < child.options.length; i++) {
                    if (child.options[i].selected) {
                        selectCache.push(child.options[i].value);
                    }
                }

                child.options.length = 0;
                for (i = 0; i < data.length; i++) {
                    // response text is an array of arrays, where each sub-array's
                    // first element is the element id and the second is the name
                    this.addoption(child, childId, data[i][0], data[i][1]);
                }

                for (i = 0; i < selectCache.length; i++) {
                    for (var h = 0; h < child.options.length; h++) {
                        if (selectCache[i] == child.options[h].value || childId == child.options[h].value) {
                            child.options[h].selected = 'selected';
                            selected = true;
                        }
                    }
                }

                if (!selected && (typeof child.options[0] !== 'undefined') && (child.options[0].value == 0 || child.options[0].value == '') ) {
                    child.options[0].selected = 'selected';
                }

                if ("fireEvent" in child) {
                    child.fireEvent("onchange");
                } else {
                    var evt = document.createEvent("HTMLEvents");
                    evt.initEvent("change", false, true);
                    child.dispatchEvent(evt);
                }
            };

            var option_failure = function(transid, o) {
                // Silently ignore failed AJAX requests, next line for debugging
                // alert("failure: " + o.responseText);
            };

            var requestURL = '';
            var selected = new Array();
            var index = 0;
            var join = '';
            for (var i = 0; i < parent.options.length; i += 1) {
                if (parent.options[i].selected) {
                    index = selected.length;
                    requestURL += join + "id[]=" + parent.options[i].value;
                    join = "&";
                }
            }

            var cfg = {
                method: 'GET',
                data: requestURL,
                on: {
                    success: option_success,
                    failure: option_failure
                },
                context: this
            };
            Y.io(this.path, cfg);
            return true;
        },

        /**
         * Add option to select element
         * @param object child the select object
         * @param mixed  childId the previous element setting
         * @param mixed  key element id
         * @param mixed  val the element value
         */
        addoption : function(child, childId, key, val) {
            var id = child.options.length;
            child.options[id] = new Option(val, key);
            if (key == childId) {
                child.options[id].selected = 'selected';
            }
        }

    },
    {
        NAME : FILTERBASENAME,
        ATTRS : { pid: '', id: '', path: ''}
    }
    );

    // Ensure that M.elis_core exists and that filterbase is initialised correctly
    M.elis_core = M.elis_core || {};

    /**
     * Entry point for dependent select module
     * @param string pid parent pulldown field id
     * @param int    id pulldown field id
     * @param string path web path to report instance callback
     * @return object the dependentselect object
     */
    M.elis_core.init_dependentselect = function(pid, id, path) {
        var args = [pid, id, path];
        return new FILTERBASE(args);
    }

}, '@VERSION@', { requires : ['base', 'event', 'io', 'json', 'node'] }
);
