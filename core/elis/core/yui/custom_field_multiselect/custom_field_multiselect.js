/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008 - 2013 Remote Learner.net Inc http://www.remote-learner.net
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
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

YUI.add('moodle-elis_core-custom_field_multiselect', function(Y) {

    M.elis_core = M.elis_core || {};

    var customfieldpickerinstance = null;
    var cf_debug = 0; // TBD: BJB - set for debugging only!

    /**
     * Show values in an alert for debugging.
     */
    function cf_show_values() {
        var values = window.customfieldpickerinstance.values;
        var tmp = '';
        for (var val in values) {
            tmp += ' '+val;
        }
        alert('update_values::values ='+tmp);
    }

    /**
     * Move custom field up.
     */
    function cf_up(index) {
        var tmp = window.customfieldpickerinstance.values[index];
        window.customfieldpickerinstance.values[index] = window.customfieldpickerinstance.values[index-1];
        window.customfieldpickerinstance.values[index-1] = tmp;
        window.customfieldpickerinstance.update_values();
    }

    /**
     * Move custom field down.
     */
    function cf_down(index) {
        var tmp = window.customfieldpickerinstance.values[index];
        window.customfieldpickerinstance.values[index] = window.customfieldpickerinstance.values[index+1];
        window.customfieldpickerinstance.values[index+1] = tmp;
        window.customfieldpickerinstance.update_values();
    }

    /**
     * Delete custom field.
     */
    function cf_delete(index) {
        window.customfieldpickerinstance.values.splice(index, 1);
        window.customfieldpickerinstance.update_values();
        window.customfieldpickerinstance.refresh_picker();
    }

    /**
     * Create and manage the custom field multiselector.
     */
    M.elis_core.init_custom_field_multiselect = function(options) {
        var container = Y.one('#'+options.id+'_container');

        // Add the selected fields table and button
        var tablecontainer = Y.Node.create('<div></div>');
        container.appendChild(tablecontainer);
        var button = Y.Node.create('<button type="button">'+M.str.moodle.add+'</button>');
        container.appendChild(button);

        // Create the helper class
        var MultiselectHelper = function(options) {
            MultiselectHelper.superclass.constructor.apply(this, arguments);
        };

        MultiselectHelper.NAME = "MultiselectHelper";
        MultiselectHelper.ATTRS = {
            options: {},
            lang: {}
        };

        Y.extend(MultiselectHelper, Y.Base, {
            /**
             * Initialize values.
             */
            initializer : function(options) {
                this.options = options;

                // Index the fields by ID, so that we can look them up
                var fieldsbyid = {};
                for (var category in options.fields) {
                    for (var fieldid in options.fields[category]) {
                        fieldsbyid[fieldid] = options.fields[category][fieldid];
                    }
                }
                this.options.fieldsbyid = fieldsbyid;

                var value = Y.one('#'+options.id+'_value').get('value');
                if (value) {
                    this.values = Y.Array.map(value.split(','), function(x) { return parseInt(x)});
                } else {
                    this.values = [];
                }
                if (window.cf_debug) {
                    var tmp = '';
                    for (var myfield in fieldsbyid) {
                        tmp += ' '+myfield;
                    }
                    alert('initializer::value = '+value+'; fields ='+tmp);
                }
                this.update_values();
            },

            /**
             * Destructor.
             */
            destructor : function() { },

            /**
             * Update the display and hidden element for the values.
             */
            update_values : function() {
                if (window.customfieldpickerinstance) {
                    this.values = window.customfieldpickerinstance.values;
                }
                var values = this.values;
                window.customfieldpickerinstance = this; // Init global required for IE7
                // Set the value of the hidden element
                var valueelem = Y.one('#'+this.options.id+'_value');
                valueelem.set('value', values.join(','));
                if (window.cf_debug) {
                    cf_show_values();
                }
                if (values.length) {
                    // Create a table with the selected fields
                    var table = document.createElement('table');
                    for (var i = 0; i < values.length; i++) {
                        var row = document.createElement('tr');
                        var cell = document.createElement('td');
                        cell.appendChild(document.createTextNode(this.options.fieldsbyid[values[i]]));
                        row.appendChild(cell);
                        // Down button
                        cell = document.createElement('td');
                        if (i != values.length - 1) {
                            var link = document.createElement('a');
                            link.href = 'javascript: cf_down('+i+');';
                            var img = document.createElement('img');
                            img.src = this.options.down;
                            img.alt = 'down';
                            link.appendChild(img);
                            var linkNode = Y.one(link);
                            linkNode.on('click', function(e, index) {
                                // Swap with next
                                var tmp = this.values[index];
                                this.values[index] = this.values[index + 1];
                                this.values[index + 1] = tmp;
                                this.update_values();
                                e.preventDefault();
                            }, this, i);
                            cell.appendChild(link);
                        }
                        row.appendChild(cell);
                        // Up button
                        cell = document.createElement('td');
                        if (i != 0) {
                            var link = document.createElement('a');
                            link.href = 'javascript: cf_up('+i+');';
                            var img = document.createElement('img');
                            img.src = this.options.up;
                            img.alt = 'up';
                            link.appendChild(img);
                            var linkNode = Y.one(link);
                            linkNode.on('click', function(e, index) {
                                // swap with previous
                                var tmp = this.values[index];
                                this.values[index] = this.values[index-1];
                                this.values[index-1] = tmp;
                                this.update_values();
                                e.preventDefault();
                            }, this, i);
                            cell.appendChild(link);
                        }
                        row.appendChild(cell);
                        // Delete button
                        cell = document.createElement('td');
                        var link = document.createElement('a');
                        link.href = 'javascript: cf_delete('+i+');';
                        var img = document.createElement('img');
                        img.src = this.options.del;
                        img.alt = 'delete';
                        link.appendChild(img);
                        var linkNode = Y.one(link);
                        linkNode.on('click', function(e, index) {
                            // Remove
                            this.values.splice(index, 1);
                            this.update_values();
                            this.refresh_picker();
                            e.preventDefault();
                        }, this, i);
                        cell.appendChild(link);
                        row.appendChild(cell);

                        table.appendChild(row);
                    }
                    if (Y.UA.ie > 0) { // IE (7)
                        tablecontainer.setContent('<table>'+table.innerHTML+'</table>');
                    } else { // Properly working browsers!
                        tablecontainer.setContent(table);
                    }
                } else {
                    tablecontainer.setContent(document.createTextNode(M.str.elis_core.nofieldsselected));
                }
            },

            /**
             * Update the picker with the values that have not been selected
             */
            refresh_picker : function() {
                if (!this.rendered) {
                    return;
                }

                window.customfieldpickerinstance = this; // Init global required for IE7
                var pickerid = this.options.id+'_picker';
                var listing = Y.one('#layout-'+pickerid);
                if (!listing) { // TBD: BJB
                    if (window.cf_debug) {
                        alert('Error cannot locate layout listing!');
                    }
                    return;
                }
                var values = this.values;
                var selected = {};
                for (var i = 0; i < values.length; i++) {
                    selected[values[i]] = true;
                }

                var table = document.createElement('table');
                var row = document.createElement('tr');
                var cell = document.createElement('th');
                cell.appendChild(document.createTextNode(M.str.elis_core.field_category));
                row.appendChild(cell);
                cell = document.createElement('th');
                cell.appendChild(document.createTextNode(M.str.elis_core.field_name));
                row.appendChild(cell);
                table.appendChild(row);
                var firstincategory = true;
                var empty = true;
                if (window.cf_debug) {
                    var tmp = '';
                    for (var cats in options.fields) {
                        for (var catfield in options.fields[cats]) {
                            tmp += ' '+options.fields[cats][catfield];
                            if (selected[catfield]) {
                                tmp += '(1)';
                            }
                        }
                    }
                    alert('refresh_picker: listing = '+listing+'; fields ='+tmp);
                }
                for (var category in options.fields) {
                    firstincategory = true;
                    var catfields = options.fields[category];
                    for (var fieldid in catfields) {
                        if (selected[fieldid]) {
                            // Don't show fields that have been selected
                            continue;
                        }
                        empty = false;
                        row = document.createElement('tr');
                        cell = document.createElement('td');
                        if (firstincategory) {
                            cell.appendChild(document.createTextNode(category));
                            firstincategory = false;
                        }
                        row.appendChild(cell);

                        cell = document.createElement('td');
                        var link = document.createElement('a');
                        link.id = fieldid;
                        link.href = 'javascript:window.customfieldpickerinstance.values.push('+fieldid+
                                ');window.customfieldpickerinstance.update_values();window.customfieldpickerinstance.refresh_picker();';
                        link.appendChild(document.createTextNode(options.fields[category][fieldid]));
                        var linkNode = Y.one(link);
                        linkNode.on('click', function(e, fieldid) {
                            this.values.push(fieldid);
                            this.update_values();
                            this.refresh_picker();
                            e.preventDefault();
                        }, this, fieldid);
                        cell.appendChild(link);
                        row.appendChild(cell);
                        table.appendChild(row);
                    }
                }

                if (empty) {
                    listing.setContent(document.createTextNode(M.str.elis_core.allitemsselected));
                } else {
                    this.panel.set('bodyContent', '<div id="layout-'+pickerid+'"><table>'+table.innerHTML+'</table></div>');
                }

                this.panel.move(button.getX(), button.getY());
            },

            /**
             * Create and initialize the popup panel.
             */
            render : function() {
                var pickerid = this.options.id+'_picker';
                // Detect if the panel has already been added and remove it -- ELIS-5956
                obj = document.getElementById(pickerid);
                if (obj) {
                    obj.parentNode.removeChild(obj);
                }
                var pickernode = Y.Node.create('<div class="custom-field-picker" id="'+pickerid+'"></div>');
                Y.one(document.body).appendChild(pickernode);

                var panel = new Y.Panel({
                    srcNode: '#'+pickerid,
                    draggable: false,
                    close: true,
                    underlay: 'none',
                    zindex: 9999990,
                    monitorresize: false,
                    xy: [button.getX(), button.getY()]
                });
                var layout = null;
                var scope = this;

                panel.set('headerContent', M.str.moodle.add);
                panel.set('bodyContent', '<div id="layout-'+pickerid+'"></div>');
                panel.render();
                this.panel = panel;
                this.rendered = true;
                Y.Event.onAvailable('#layout-'+pickerid, function() {
                    scope.refresh_picker();
                });
            },

            hide : function() {
                if (this.rendered) {
                    this.panel.hide();
                }
            },

            show : function() {
                if (this.rendered) {
                    this.panel.show();
                } else {
                    this.launch();
                }
            },

            launch : function() {
                this.render();
            }
        });

        var helper = new MultiselectHelper(options);

        // Show the panel when the button is clicked
        button.on('click', function(e) {
            helper.show();
        });
    };

}, '@VERSION@', { requires : [
        'base',
        'node',
        'node-event-simulate',
        'json',
        'async-queue',
        'io',
        'array-extras',
        'panel'
] }
);

/**
 * Reset custom fields.
 * Note: only used from outside of YUI
 */
function cf_reset() {
    // ELIS-4622: clear custom fields on filter form reset
    window.customfieldpickerinstance.values = [];
    window.customfieldpickerinstance.update_values();
    window.customfieldpickerinstance.refresh_picker();
}
