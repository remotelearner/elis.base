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

YUI.add('moodle-elis_core-gradebook_popup', function(Y) {

    M.elis_core = M.elis_core || {};

    /**
     * Set the value of an element.
     */
    M.elis_core.set_value = function(itemid, value) {
        var item = document.getElementById(itemid);
        item.value = value;
        return false;
    };

    /**
     * Create and manage the gradebook idnumber selector popup.
     */
    M.elis_core.init_gradebook_popup = function(options) {
        var textelem = Y.one('#'+options.textelemid);

        // Add the Browse button after the text element
        var parent = textelem.ancestor();
        parent.appendChild(document.createTextNode(" "));
        var button = Y.Node.create('<button type="button">'+M.str.editor.browse+'</button>');
        parent.appendChild(button);

        // Create the panel helper class
        var GradebookPickerHelper = function(options) {
            GradebookPickerHelper.superclass.constructor.apply(this, arguments);
        };

        GradebookPickerHelper.NAME = "GradebookPickerHelper";
        GradebookPickerHelper.ATTRS = {
            options: {},
            lang: {}
        };

        Y.extend(GradebookPickerHelper, Y.Base, {
            api: M.cfg.wwwroot+'/elis/core/lib/form/gradebookidnumber_ajax.php',

            /**
             * Initialize values.
             */
            initializer : function(options) {
                this.options = options;
            },

            /**
             * Destructor.
             */
            destructor : function() { },

            /**
             * IO handler to show the course dropdown after it has been retrieved via AJAX.
             */
            show_course_list_handler : function(id, o, args) {
                var pickerid = this.options.textelemid+'_picker';
                var courseselectorcontainer = Y.one('#courseselector-'+pickerid);
                courseselectorcontainer.set('innerHTML', '');
                var courseselector = Y.Node.create(o.response);

                courseselectorcontainer.appendChild(courseselector);
                if (!this.options.lockcourse) {
                    var scope = this;
                    courseselector.on('change', function() {
                        scope.get_gradebook_items(courseselector.get('value'));
                    });
                }
            },

            /**
             * Show the activities and gradebook items for the specified course.
             */
            get_gradebook_items : function(course) {
                var pickerid = this.options.textelemid+'_picker';
                var panel = Y.one('#panel-'+pickerid);
                panel.set('innerHTML', '');

                var commonparams = '?textelemid='+this.options.textelemid;
                if (course) {
                    commonparams += '&course='+course;
                }

                var tabView = new Y.TabView({
                    children: [{
                        label: M.str.grades.activities,
                        content: '<div id="activitiestab">'+M.str.repository.loading+'</div>',
                        cacheData: true,
                        active: true
                    }, {
                        label: M.str.grades.gradeitems,
                        content: '<div id="gradeitemstab">'+M.str.repository.loading+'</div>',
                        cacheData: true
                    }]
                });

                tabView.render('#panel-'+pickerid);

                // Populate the activities tab
                Y.io(this.api+commonparams+'&mode=activities', {
                    method: 'GET',
                    on: {
                        success: function(id, o) {
                            Y.one('#activitiestab').set('innerHTML', o.responseText);
                        }
                    }
                });

                // Populate the grade items tab
                Y.io(this.api+commonparams+'&mode=gradebook', {
                    method: 'GET',
                    on: {
                        success: function(id, o) {
                            Y.one('#gradeitemstab').set('innerHTML', o.responseText);
                        }
                    }
                });
            },

            /**
             * Create and initialize the popup panel.
             */
            render : function() {
                var pickerid = this.options.textelemid+'_picker';
                var pickernode = Y.Node.create('<div class="grade-picker" id="'+pickerid+'"></div>');
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

                panel.set('headerContent', M.str.editor.browse);
                panel.set('bodyContent', '<div id="layout-'+pickerid+'"></div>');
                panel.render();
                this.panel = panel;
                this.rendered = true;

                Y.Event.onAvailable('#layout-'+pickerid, function() {
                    // Using YUI2 Layout here as this isn't implemented natively in YUI3 yet
                    var layout = new Y.YUI2.widget.Layout('layout-'+pickerid, {
                        height: 500, width: 500,
                        units: [
                            {
                                position: 'top', height: 32, resize: false,
                                body: '<div class="gradebookpicker-panel" id="courseselector-'+pickerid+'"></div>',
                                scroll: false,
                                gutter: '0 0 0 0'
                            },
                            {
                                position: 'center', body: '<div class="gradebookpicker-panel" id="panel-'+pickerid+'"></div>',
                                scroll: true,
                                gutter: '0 0 0 0'
                            }
                        ]
                    });
                    layout.render();

                    // Load the initial list of gradebook elements
                    scope.get_gradebook_items(scope.options.courseid);

                    // Show the course list
                    var courseselectorcontainer = Y.one('#courseselector-'+pickerid);
                    courseselectorcontainer.set('innerHTML', M.str.repository.loading);
                    var cfg = {
                        on: {
                            complete: scope.show_course_list_handler
                        },
                        context: scope
                    };
                    var uri = scope.api+'?mode=course&textelemid='+scope.options.textelemid;
                    if (scope.options.courseid) {
                        uri += '&course='+scope.options.courseid;
                    }
                    if (scope.options.lockcourse) {
                        uri += '&lockcourse=1';
                    }
                    Y.io(uri, cfg);
                });
            },

            /**
             * Hide panel.
             */
            hide : function() {
                this.panel.hide();
            },

            /**
             * Show panel.
             */
            show : function() {
                if (this.rendered) {
                    this.panel.show();
                } else {
                    this.launch();
                }
            },

            /**
             * Render panel.
             */
            launch : function() {
                this.render();
            }
        });

        // Create the picker panel
        var panel = new GradebookPickerHelper(options);

        // Show the panel when the button is clicked
        if (!options.courseid && options.lockcourse) {
            // No course ID set, and locked, so user can't do anything.  So display a message.
            button.on('click', function(e) {
                alert(options.nocoursestring);
            });
        } else {
            button.on('click', function(e) {
                panel.show();
            });
        }
    };

}, '@VERSION@', { requires : [
        'base',
        'node',
        'node-event-simulate',
        'json',
        'async-queue',
        'io',
        'array-extras',
        'yui2-container',
        'yui2-layout',
        'yui2-dragdrop',
        'tabview',
        'panel'
] }
);
