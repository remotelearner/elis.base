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
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

YUI.add('moodle-elis_core-customfieldsform', function(Y) {

    /**
     * The module name
     * @property MODULEBASENAME
     * @type {String}
     * @default "core-customfieldsform"
     */
    var MODULEBASENAME = 'core-customfieldsform';

    /**
     * This method calls the base class constructor
     * @method MODULEBASE
     */
    var MODULEBASE = function() {
        MODULEBASE.superclass.constructor.apply(this, arguments);
    }

    /**
     * @class M.elis_core.customfieldsform
     */
    Y.extend(MODULEBASE, Y.Base, {
        /**
         * Required language strings
         * @property profiledefaultdatastring
         * @type {String}
         * @default ''
         */
        profiledefaultdatastring : '',

        /**
         * Initialize the customfieldform module
         * @param object args function arguments
         */
        initializer : function(args) {
            this.profiledefaultdatastring = args.profiledefaultdatastring;

            Y.on('click', this.multivalued_changed, '#id_multivalued', this);
            Y.on('click', this.time_fields_enabled, '#id_manual_field_inctime', this);
            Y.on('change', this.switch_default_data, '#id_manual_field_control', this);
            Y.on('change', this.update_default_years, '#id_manual_field_startyear', this);
            Y.on('change', this.update_default_years, '#id_manual_field_stopyear', this);
            Y.on('change', this.update_menu_options, '#id_manual_field_options_source', this);
            Y.on('change', this.update_menu_options, '#id_manual_field_options', this);
            this.init_customfield_default();
        },

        /**
         * initialize custom field default
         */
        init_customfield_default : function() {
            this.time_fields_enabled();
            this.switch_default_data();
        },

        /**
         * Time fields enabled
         */
        time_fields_enabled : function() {
            var hrid = document.getElementById("id_defaultdata_datetime_hour");
            var minid = document.getElementById("id_defaultdata_datetime_minute");
            var inctime = document.getElementById("id_manual_field_inctime");
            var ischecked = inctime ? inctime.checked : false;
            if (hrid) {
                if (!ischecked) {
                    hrid.value = 0;
                    hrid.disabled = "disabled";
                } else {
                    hrid.disabled = "";
                }
            }
            if (minid) {
                if (!ischecked) {
                    minid.value = 0;
                    minid.disabled = "disabled";
                } else {
                    minid.disabled = "";
                }
            }
        },

        /**
         * switch default data
         */
        switch_default_data : function() {
            var elem;
            var elemid;
            var fcontrol = document.getElementById("id_manual_field_control");
            var dttext = document.getElementById("datatype_text");
            var dtcheckbox = document.getElementById("datatype_checkbox");
            var dtradio = document.getElementById("datatype_radio");
            var dtdatetime = document.getElementById("datatype_datetime");
            elemid = "datatype_" + fcontrol.options[fcontrol.selectedIndex].value;
            // alert("switch_default_data(): elemid = " + elemid);
            if (!(elem = document.getElementById(elemid))) {
                elemid = "datatype_text";
                elem = dttext;
            }
            if (elemid == "datatype_checkbox") {
                dtradio.className = "accesshide custom_field_default_fieldset";
                dtcheckbox.className = "accesshide custom_field_default_fieldset";
                dttext.className = "accesshide custom_field_default_fieldset";
                dtdatetime.className = "accesshide custom_field_default_fieldset";
            } else if (elemid == "datatype_menu") {
                dtcheckbox.className = "accesshide custom_field_default_fieldset";
                dtradio.className = "accesshide custom_field_default_fieldset";
                dttext.className = "accesshide custom_field_default_fieldset";
                dtdatetime.className = "accesshide custom_field_default_fieldset";
            } else if (elemid == "datatype_datetime") {
                dtdatetime.className = "clearfix custom_field_default_fieldset";
                dtcheckbox.className = "accesshide custom_field_default_fieldset";
                dtradio.className = "accesshide custom_field_default_fieldset";
                dttext.className = "accesshide custom_field_default_fieldset";
            } else { // default: datatype_text
                dttext.className = "clearfix custom_field_default_fieldset";
                dtdatetime.className = "accesshide custom_field_default_fieldset";
                dtcheckbox.className = "accesshide custom_field_default_fieldset";
                dtradio.className = "accesshide custom_field_default_fieldset";
            }
            this.update_menu_options();
            this.switch_field_options();
        },

        /**
         * disable menu options
         */
        disable_menu_options : function() {
            var srcs = document.getElementById("id_manual_field_options_source");
            var dtmenu = document.getElementById("datatype_menu");
            var dtradio = document.getElementById("datatype_radio");
            var dtcheckbox = document.getElementById("datatype_checkbox");
            var i, elemid;
            if (dtmenu) {
                // alert("disable_menu_options(): datatype_menu");
                dtmenu.className = "accesshide custom_field_default_fieldset";
            }
            if (dtradio) {
                dtradio.className = "accesshide custom_field_default_fieldset";
            }
            if (dtcheckbox) {
                dtcheckbox.className = "accesshide custom_field_default_fieldset";
            }
            for (i = 1; i < srcs.options.length; ++i) {
                if (elemid = document.getElementById("datatype_menu_" + srcs.options[i].value)) {
                    // alert("disable_menu_options(): datatype_menu_" + srcs.options[i].value);
                    elemid.className = "accesshide custom_field_default_fieldset";
                }
                if (elemid = document.getElementById("datatype_radio_" + srcs.options[i].value)) {
                    // alert("disable_menu_options(): datatype_radio_" + srcs.options[i].value);
                    elemid.className = "accesshide custom_field_default_fieldset";
                }
            }
        },

        /**
         * update menu options
         */
        update_menu_options : function() {
            var srcs = document.getElementById("id_manual_field_options_source");
            var fcontrol = document.getElementById("id_manual_field_control");
            this.disable_menu_options();
            if (srcs && fcontrol) {
                var mopts, itemend, cur, iecr;
                var multivalued = document.getElementById("id_multivalued");
                var menu_options = document.getElementById("id_manual_field_options");
                if ((menu_options.value.length || srcs.selectedIndex > 0) && multivalued.checked &&
                        fcontrol.options[fcontrol.selectedIndex].value == "checkbox") {
                    // TBD: just change control type to menu???
                    for (var j = 0; j < fcontrol.options.length; ++j) {
                        if (fcontrol.options[j].value == "menu") {
                            fcontrol.selectedIndex = j;
                            break;
                        }
                    }
                }
                if (fcontrol.options[fcontrol.selectedIndex].value == "menu") {
                    var dtmenu;
                    if (srcs.selectedIndex == 0) {
                        dtmenu = document.getElementById("datatype_menu");
                        if (dtmenu) {
                            dtmenu.className = "clearfix custom_field_default_fieldset";
                            var defaultdata_menu = document.getElementById("id_defaultdata_menu");
                            if (defaultdata_menu && multivalued) {
                                defaultdata_menu.multiple = multivalued.checked ? "multiple" : "";
                            }
                            if (defaultdata_menu && menu_options) {
                                var i;
                                for (i = defaultdata_menu.options.length - 1; i >= 0; --i) {
                                    defaultdata_menu.options.remove(i);
                                }
                                mopts = menu_options.value;
                                do {
                                    itemend = mopts.indexOf("\n");
                                    if (itemend == -1) {
                                        cur = mopts;
                                    } else {
                                        cur = mopts.substr(0, itemend);
                                        iecr = cur.indexOf("\r"); // IE7
                                        if (iecr != -1) {
                                            cur = cur.substr(0, iecr);
                                        }
                                        mopts = mopts.substr(itemend + 1);
                                    }
                                    //alert("update_menu_options(): Adding option: " + cur);
                                    var elem = new Option(cur, cur);
                                    defaultdata_menu.options.add(elem);
                                } while (itemend != -1);
                            }
                        }
                    } else if ((dtmenu = document.getElementById("datatype_menu_" + srcs.options[srcs.selectedIndex].value))) {
                        dtmenu.className = "clearfix custom_field_default_fieldset";
                    }
                } else if (fcontrol.options[fcontrol.selectedIndex].value == "checkbox") {
                    var dtcheckbox = document.getElementById("datatype_checkbox");
                    if (multivalued.checked) {
                        dtcheckbox.className = "clearfix custom_field_default_fieldset";
                        return;
                    }
                    dtcheckbox.className = "accesshide custom_field_default_fieldset";
                    var dtradio = document.getElementById("datatype_radio");
                    if (dtradio) {
                        if (dtradio.children) {
                            // alert("update_menu_options(): RADIO: deleteing dtradio children " + dtradio.children.length);
                            //var dots = ".";
                            while (dtradio.children.length) {
                                // alert("update_menu_options(): deleteing radio child node "+ dots);
                                dtradio.children[0].parentNode.removeChild(dtradio.children[0]);
                                // dots += ".";
                            }
                        }
                        if (srcs.selectedIndex == 0) {
                            mopts = menu_options.value;
                            if (!mopts.length) {
                                //alert("update_menu_options(): RADIO: !mopts.length - returning!");
                                dtcheckbox.className = "clearfix custom_field_default_fieldset";
                                return;
                            }
                            dtradio.className = "clearfix custom_field_default_fieldset";
                            menu_options = document.getElementById("id_manual_field_options");
                            var radiolabel = this.profiledefaultdatastring;
                            var checked = "checked";
                            var count = 0;
                            do {
                                itemend = mopts.indexOf("\n");
                                if (itemend == -1) {
                                    cur = mopts;
                                } else {
                                    cur = mopts.substr(0, itemend);
                                    iecr = cur.indexOf("\r"); // IE7
                                    if (iecr != -1) {
                                        cur = cur.substr(0, iecr);
                                    }
                                    mopts = mopts.substr(itemend + 1);
                                }
                                // alert("update_menu_options(): Adding radio option: " + cur);
                                // <div id="fitem_id_defaultdata_radio_" class="fitem fitem_fradio">
                                //     <div class="fitemtitle">
                                //         <label for="id_defaultdata_radio_"> </label>
                                //     <div class="felement fradio">
                                //         <span>
                                //             <input id="id_defaultdata_radio_" type="radio" checked="checked" name="defaultdata_radio">
                                //             <label for="id_defaultdata_radio_">Option2</label>
                                var topdiv = document.createElement("div");
                                topdiv.id = "fitem_id_defaultdata_radio";
                                topdiv.className = "fitem fitem_fradio";
                                var labeldiv = document.createElement("div");
                                labeldiv.className = "fitemtitle";
                                var labelel = document.createElement("label");
                                // labelel.for = "id_defaultdata_radio";
                                labelel.setAttribute("for", "id_defaultdata_radio");
                                labelel.innerHTML = radiolabel;
                                labeldiv.appendChild(labelel);

                                var radiodiv = document.createElement("div");
                                radiodiv.className = "felement fradio";
                                var rspan = document.createElement("span");
                                var rinput = document.createElement("input");
                                rinput.type = "radio";
                                rinput.checked = checked;
                                checked = "";
                                rinput.id = "id_defaultdata_radio"+count;
                                rinput.name = "defaultdata_radio";
                                rinput.value = cur;
                                var labelrad = document.createElement("label");
                                // labelrad.for = "id_defaultdata_radio";
                                labelrad.setAttribute("for", "id_defaultdata_radio"+count);
                                labelrad.innerHTML = cur;
                                rspan.appendChild(rinput);
                                rspan.appendChild(labelrad);
                                radiodiv.appendChild(rspan);
                                topdiv.appendChild(labeldiv);
                                topdiv.appendChild(radiodiv);
                                dtradio.appendChild(topdiv);
                                radiolabel = "&nbsp;";
                                count++;
                            } while (itemend != -1);
                        } else if ((dtradio = document.getElementById("datatype_radio_" + srcs.options[srcs.selectedIndex].value))) {
                            dtradio.className = "clearfix custom_field_default_fieldset";
                        }
                    }
                }
            }
        },

        /**
         * update default years
         */
        update_default_years : function() {
            var yrid = document.getElementById("id_defaultdata_datetime_year");
            var startyr = document.getElementById("id_manual_field_startyear");
            var stopyr = document.getElementById("id_manual_field_stopyear");
            if (startyr && stopyr && yrid) {
                var i;
                for (i = yrid.options.length - 1; i >= 0; --i) {
                    yrid.options.remove(i);
                }
                for (i = startyr.options[startyr.selectedIndex].value;
                    i <= stopyr.options[stopyr.selectedIndex].value; ++i) {
                    // alert("update_default_years(); Adding yr = " + i);
                    var elem = new Option(i.toString(), i);
                    yrid.options.add(elem);
                }
            }
        },

        /**
         * multivalued_changed
         */
        multivalued_changed : function() {
            var mv = document.getElementById("id_multivalued");
            var checked = mv ? mv.checked : false;
            var fcontrol = document.getElementById("id_manual_field_control");
            var srcs = document.getElementById("id_manual_field_options_source");
            var defaultdata_menu;
            defaultdata_menu = (srcs.selectedIndex != 0)
                    ? document.getElementById("id_defaultdata_menu_"+ srcs.options[srcs.selectedIndex].value)
                    : document.getElementById("id_defaultdata_menu")
            if (defaultdata_menu) {
                defaultdata_menu.multiple = checked ? "multiple" : "";
            }
            if (fcontrol.options[fcontrol.selectedIndex].value == "checkbox") {
                this.update_menu_options();
            }
        },

        /**
         * switch field options
         */
        switch_field_options : function() {
            var fcontrol = document.getElementById("id_manual_field_control");
            if (fcontrol) {
                var fotext = document.getElementById("field_options_text");
                var fomenu = document.getElementById("field_options_menu");
                var fodatetime = document.getElementById("field_options_datetime");
                var cftype = fcontrol.options[fcontrol.selectedIndex].value;
                // alert("switch_field_options(): cftype = " + cftype);
                if (fotext && fomenu && fodatetime) {
                    if (cftype == "checkbox") {
                        fotext.className = "accesshide custom_field_options_fieldset";
                        fomenu.className = "clearfix custom_field_options_fieldset";
                        fodatetime.className = "accesshide custom_field_options_fieldset";
                    } else if (cftype == "menu") {
                        fotext.className = "accesshide custom_field_options_fieldset";
                        fomenu.className = "clearfix custom_field_options_fieldset";
                        fodatetime.className = "accesshide custom_field_options_fieldset";
                    } else if (cftype == "datetime") {
                        fotext.className = "accesshide custom_field_options_fieldset";
                        fomenu.className = "accesshide custom_field_options_fieldset";
                        fodatetime.className = "clearfix custom_field_options_fieldset";
                    } else {
                        fotext.className = "clearfix custom_field_options_fieldset";
                        fomenu.className = "accesshide custom_field_options_fieldset";
                        fodatetime.className = "accesshide custom_field_options_fieldset";
                    }
                }
            }
        }

    },
    {
        NAME : MODULEBASENAME,
        ATTRS : {}
    }
    );

    // Ensure that M.elis_core exists and is initialized correctly
    M.elis_core = M.elis_core || {};

    /**
     * Entry point for customfields form module
     * @param string profiledefaultdatastring the language string profiledefaultdata
     * @return object the customfieldsform object
     */
    M.elis_core.init_customfieldsform = function(profiledefaultdatastring) {
        args = {profiledefaultdatastring: profiledefaultdatastring};
        return new MODULEBASE(args);
    }

}, '@VERSION@', { requires : ['base', 'event', 'node'] }
);
