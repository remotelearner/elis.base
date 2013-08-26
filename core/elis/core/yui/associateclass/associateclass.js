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
 * @copyright  2013 onwards Remote-Learner {@link http://www.remote-learner.ca/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

YUI.add('moodle-elis_core-associateclass', function(Y) {
    /**
     * Module name
     * @property ASSOCIATECLASSNAME
     * @type {String}
     * @default "core-associateclass"
     */
    var ASSOCIATECLASSNAME = 'core-associateclass';

    /**
     * This method calls the base class constructor
     * @method ASSOCIATECLASS
     */
    var ASSOCIATECLASS = function() {
        ASSOCIATECLASS.superclass.constructor.apply(this, arguments);
    };

    /**
     * @class M.elis_core.init_associateclass
     */
    Y.extend(ASSOCIATECLASS, Y.Base, {
        /**
         * A nodelist of elements whose name equals '_selection'
         * @property selectionfield
         * @type {Object}
         * @default ''
         */
        selectionfield: '',
        /**
         * The URL of the last request
         * @property lastrequest
         * @type {String}
         * @default ''
         */
        lastrequest: '',
        /**
         * Flag to note whether the innerhtml has been replaced with new markup
         * @property classinnerhtmlscriptsrun
         * @type {Bool}
         * @default false
         */
        classinnerhtmlscriptsrun: false,
        /**
         * context for loadform and loadlink
         * @property context
         * @type {Object}
         * @default null (this)
         */
        context: null,
        /**
         * link types to load into internal div
         * @property linktypes
         * @type {String}
         * @default 'a'
         */
        linktypes: 'a',
        /**
         * loadlink function
         * @property loadlink
         * @type {Function}
         * @default null
         */
        loadlink: null,
        /**
         * loadform function
         * @property loadform
         * @type {Function}
         * @default null
         */
        loadform: null,

        /**
         * Entry method into this module.  Acts on the domready event to retrieve an element by name
         * @method initializer
         * @param {Object} params parameters passed into the module class
         */
        initializer : function(params) {
            this.lastrequest = params.basepage;
            // Grab the _selection element in the nodelist and set it's value to an empty string.
            this.selectionfield = this.get_element_by_name('_selection');
            if (this.selectionfield) {
                this.selectionfield.value = '';
            }
            if (params.linktypes) {
                this.linktypes = params.linktypes;
            }
            this.context = params.context ? params.context : this;
            this.loadlink = params.loadlink ? params.loadlink : this.load_link;
            this.loadform = params.loadform ? params.loadform : this.load_form;
            this.make_links_internal(params.divid);
        },

        /**
         * Convert all links and forms within the list_display div to load within the
         * div.
         * @param {string} divid the reporting div element id
         */
        make_links_internal : function(divid) {
            var dividnode = Y.one('#'+divid);
            dividnode.delegate('click', this.loadlink, this.linktypes, this.context);
            dividnode.delegate('click', this.loadform, 'input[type=submit]', this.context);
        },

        /**
         * Show throbber animation
         * @return {Void} Returns void if the throbber element is not found
         */
        show_throbber : function() {
            // Return void if throbber attribute is empty
            if ('' == this.get('throbber')) {
                return;
            }

            // Show throbber
            var throbber = Y.one('#throbber');

            // If throbber doesn't exist, create one
            if (!throbber) {
                var reportblock = Y.one('#php_report_block');
                if (!reportblock || !reportblock.all('div') || !reportblock.all('div').item(0).hasChildNodes) {
                    return;
                }

                // Create throbber
                throbber = Y.Node.create('<div></div>');
                throbber.setAttribute('id', 'throbber');
                reportblock.all('div').item(0).prepend(throbber);
            }
            window.scrollTo(0, 0);
            throbber.getDOMNode().innerHTML = '<center><img src="'+this.get('throbber')+'" /></center>';
        },

        /**
         * Event handler for forms within the list_display div
         * @param {Object} e An event object
         */
        load_form : function(e) {
            var formdataoverride = '';
            // Because of this YUI Known issue {@link http://yuilibrary.com/yui/docs/io/#known-issues}, where multiple submit buttons on a form
            // are not supported... check for the button that was clicked and override the submitted data
            var targetid = e.target.getAttribute('id');
            // console.log('associateclass::load_form(): target id = '+targetid);
            switch (targetid) {
                case 'id_reset_form':
                    formdataoverride = 'show_report=canceltest=&mform_showadvanced=';
                    break;
                case 'id_show_report':
                    formdataoverride = 'reset_form=&canceltest=&mform_showadvanced=';
                    // Show throbber
                    this.show_throbber();
                    break;
                case 'id_canceltest':
                    formdataoverride = 'reset_form=&show_report=&mform_showadvanced=';
                    break;
                default:
                    // Check if the show refresh button is clicked
                    if (-1 != targetid.indexOf('refresh_')) {
                        // Show throbber
                        this.show_throbber();
                        formdataoverride = 'reset_form=&canceltest=&mform_showadvanced=&show_report=';
                    }
                    break;
            }

            // Get the Form node
            var formnode = e.target.ancestor('form');
            var actionurl = formnode.getAttribute('action')+'?mode=bare';

            // Create a configuration object for the form submission
            var cfg = {
                    method: 'POST',
                    data: formdataoverride,
                    form: {
                            id: formnode.getAttribute('id'),
                            useDisabled: false
                    },
                    on: {
                            success: this.form_complete_listener
                    },
                    context: this
            };

            Y.io(actionurl, cfg);
            e.preventDefault();
        },

        /**
         * When we receive new content from the server, replace the list_display div
         * with it.
         * @param {String} transid The ID of the transaction
         * @param {Object} resobj Object containing the response data.
         */
        form_complete_listener : function(transid, resobj) {
            // Create new divnode
            var newdivnode = Y.Node.create('<div></div>');
            newdivnode.setAttribute('id', this.get('divid'));
            // Create script node (not sure if this is still needed)
            var scriptnode = Y.Node.create('<script>class_innerhtml_scripts_run = true;</script>');

            // Set the inner HTML of the new divnode
            newdivnode.appendChild(scriptnode);
            newdivnode.getDOMNode().innerHTML = newdivnode.getDOMNode().innerHTML+resobj.responseText

            var olddivnode = Y.one('#'+this.get('divid'));

            // This needs to be included because some of the JS keys off the element's class
            newdivnode.setAttribute('class', olddivnode.getAttribute('class'));

            var parentnodedom = olddivnode.getDOMNode().parentNode;

            parentnodedom.replaceChild(newdivnode.getDOMNode(), olddivnode.getDOMNode())

            this.make_links_internal(this.get('divid'));
            this.run_inner_html_scripts(newdivnode.getAttribute('id'));
        },

        /**
         * Run innner html scripts
         * @param {String} elid The element id to run inner html scripts on
         */
        run_inner_html_scripts : function(elid) {
            var el = Y.one('#'+elid);
            if (el) {
                el.all('script').each(function(el) { eval(el.getHTML()); });
            }
        },

        /**
         * Event handler for links within the list_display div
         * @param {Object} e event object
         * @return {Void} Returns void if an unwanted element is clicked
         */
        load_link : function(e) {
            var parentnodedom = e.target.getDOMNode.parentNode;
            var parentnode = e.target.ancestor();

            if (parentnode.hasClass('external_report_link') || parentnode.hasClass('tooltip')) {
                return;
            }

            var request = e.target.getAttribute('href');
            var linktarget = e.target.getAttribute('target');
            var linkclick = e.target.getAttribute('onclick');

            // If we have anything other than an anchor tag, set to null
            if (request &&  e.target.get('tagName').toLowerCase() != 'a') {
                request = null;
            }

            if (!request) {
                if (parentnode.getAttribute("href")) {
                    request = parentnode.getAttribute("href");
                    linktarget = parentnode.getAttribute("target");
                    linkclick = parentnode.getAttribute("onclick");
                } else {
                    return;
                }
            }

            // If an onclick is being used, let it handle this event ...unless it's our own start_throbber() function
            if (e.target.hasClass('ygtvspacer') || linktarget || (linkclick && linkclick.indexOf('start_throbber') == -1)) {
                return;
            }

            this.lastrequest = request;

            // Display throbber when page link is clicked
            this.show_throbber();

            // Create a configuration object for the form submission
            var cfg = {
                method: 'GET',
                data: 'mode=bare',
                on: {
                    success: this.form_complete_listener
                },
                context: this
            };

            Y.io(this.lastrequest, cfg);
            e.preventDefault();
        },

        /**
         * This method searches document for element with specified name
         * @method get_element_by_name
         * @param {String} name the name to search for
         * @return {Object}|null the matching node or null if none found
         */
        get_element_by_name : function(name) {
            return Y.one('*[name="'+name+'"]');
        }

    }, {
        NAME : ASSOCIATECLASSNAME,
        ATTRS : {
            basepage: '',
            divid: '',
            throbber: ''
        }
    });

    M.elis_core = M.elis_core || {};
    M.elis_core.init_associateclass = function(params) {
        return new ASSOCIATECLASS(params);
    }
}, '@VERSION@', {
    requires:['base', 'event', 'node', 'io']
});
