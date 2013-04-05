M.elis_core = {};

/**
 * Set the value of an element
 */
M.elis_core.set_value = function(itemid, value) {
    var item = document.getElementById(itemid);
    item.value = value;
    return false;
};

/**
 * Create and manage the gradebook idnumber selector popup.
 */
M.elis_core.init_gradebook_popup = function(Y, options) {
    var YAHOO = Y.YUI2;
    var textelem = Y.one('#'+options.textelemid);

    // add the Browse button after the text element
    var parent = textelem.ancestor();
    parent.appendChild(document.createTextNode(" "));
    var button = Y.Node.create('<button type="button">'+M.str.editor.browse+'</button>');
    parent.appendChild(button);

    // create the panel helper class
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

	initializer: function(options) {
	    this.options = options;
	},

	destructor: function() { },

	/**
	 * IO handler to show the course dropdown after it has been retrieved
	 * via AJAX.
	 */
	show_course_list_handler: function(id, o, args) {
	    var pickerid = this.options.textelemid + '_picker';
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
	get_gradebook_items: function(course) {
	    var pickerid = this.options.textelemid + '_picker';
	    var panel = Y.one('#panel-'+pickerid);
	    panel.set('innerHTML', '');

	    var tabView = new YAHOO.widget.TabView();
	    var common_params = '?textelemid=' + this.options.textelemid;
	    if (course) {
		common_params += '&course=' + course;
	    }
	    tabView.addTab(new YAHOO.widget.Tab({
		label: M.str.grades.activities,
		content: M.str.repository.loading,
		dataSrc: this.api + common_params + '&mode=activities',
		cacheData: true,
		active: true
	    }));
	    tabView.addTab(new YAHOO.widget.Tab({
		label: M.str.grades.gradeitems,
		content: M.str.repository.loading,
		dataSrc: this.api + common_params + '&mode=gradebook',
		cacheData: true
	    }));

	    tabView.appendTo('panel-'+pickerid);
	},

	/**
	 * Create and initialize the popup panel.
	 */
	render: function() {
	    var pickerid = this.options.textelemid + '_picker';
	    var pickernode = Y.Node.create('<div class="grade-picker" id="'+pickerid+'"></div>');
	    Y.one(document.body).appendChild(pickernode);
	    var panel = new YAHOO.widget.Panel(pickerid, {
		draggable: false,
		close: true,
		underlay: 'none',
		zindex: 9999990,
		monitorresize: false,
		xy: [button.getX(), button.getY()]
	    });
	    var layout = null;
	    var scope = this;
	    panel.beforeRenderEvent.subscribe(function() {
		YAHOO.util.Event.onAvailable('layout-'+pickerid, function() {
		    var layout = new YAHOO.widget.Layout('layout-'+pickerid, {
			height: 500, width: 500,
			units: [
			    {position: 'top', height: 32, resize: false,
			     body: '<div class="gradebookpicker-panel" id="courseselector-'+pickerid+'"></div>',
			     scroll: false,
			     gutter: '0 0 0 0' },
			    {position: 'center', body: '<div class="gradebookpicker-panel" id="panel-'+pickerid+'"></div>',
			     scroll: true,
			     gutter: '0 0 0 0' }
			]
		    });
		    layout.render();

		    // load the initial list of gradebook elements
		    scope.get_gradebook_items(scope.options.courseid);

		    // show the course list
		    var courseselectorcontainer = Y.one('#courseselector-'+pickerid);
		    courseselectorcontainer.set('innerHTML', M.str.repository.loading);
		    var cfg = {
			on: {
			    complete: scope.show_course_list_handler
			},
			context: scope
		    };
		    var uri = scope.api + '?mode=course&textelemid=' + scope.options.textelemid;
		    if (scope.options.courseid) {
			uri += '&course=' + scope.options.courseid;
		    }
		    if (scope.options.lockcourse) {
			uri += '&lockcourse=1';
		    }
		    Y.io(uri, cfg);
		});
	    });

	    panel.setHeader(M.str.editor.browse);
	    panel.setBody('<div id="layout-'+pickerid+'"></div>');
	    panel.render();
	    this.panel = panel;
	    this.rendered = true;
	},

	hide: function() {
	    this.panel.hide();
	},

	show: function() {
	    if (this.rendered) {
		this.panel.show();
	    } else {
		this.launch();
	    }
	},

	launch: function() {
	    this.render();
	}
    });

    // create the picker panel
    var panel = new GradebookPickerHelper(options);

    // show the panel when the button is clicked
    if (!options.courseid && options.lockcourse) {
	// No course ID set, and locked, so user can't do anything.  So display
	// a message.
	button.on('click', function(e) {
	    alert(options.nocoursestring);
	});
    } else {
	button.on('click', function(e) {
	    panel.show();
	});
    }
};

var customfieldpickerinstance = null;
var cf_debug = 0; // TBD: BJB - set for debugging only!

function cf_show_values() {
    var values = window.customfieldpickerinstance.values;
    var tmp    = '';
    for (var val in values) {
        tmp += ' ' + val;
    }
    alert('update_values::values =' + tmp);
}

function cf_up(index) {
    var tmp = window.customfieldpickerinstance.values[index];
    window.customfieldpickerinstance.values[index] = window.customfieldpickerinstance.values[index-1];
    window.customfieldpickerinstance.values[index-1] = tmp;
    window.customfieldpickerinstance.update_values();
}

function cf_down(index) {
    var tmp = window.customfieldpickerinstance.values[index];
    window.customfieldpickerinstance.values[index] = window.customfieldpickerinstance.values[index+1];
    window.customfieldpickerinstance.values[index+1] = tmp;
    window.customfieldpickerinstance.update_values();
}

function cf_delete(index) {
    window.customfieldpickerinstance.values.splice(index, 1);
    window.customfieldpickerinstance.update_values();
    window.customfieldpickerinstance.refresh_picker();
    //cf_show_values();
}

function cf_reset() {
    // ELIS-4622: clear custom fields on filter form reset
    window.customfieldpickerinstance.values = [];
    //cf_show_values();
    window.customfieldpickerinstance.update_values();
    window.customfieldpickerinstance.refresh_picker();
}

M.elis_core.init_custom_field_multiselect = function(Y, options) {
    var YAHOO = Y.YUI2;
    var container = Y.one('#'+options.id+'_container');

    // add the selected fields table and button
    var tablecontainer = Y.Node.create('<div></div>');
    container.appendChild(tablecontainer);
    var button = Y.Node.create('<button type="button">'+M.str.moodle.add+'</button>');
    container.appendChild(button);

    // create the helper class
    var MultiselectHelper = function(options) {
        MultiselectHelper.superclass.constructor.apply(this, arguments);
    };

    MultiselectHelper.NAME = "MultiselectHelper";
    MultiselectHelper.ATTRS = {
        options: {},
        lang: {}
    };

    Y.extend(MultiselectHelper, Y.Base, {
	initializer: function(options) {
	    this.options = options;

	    // index the fields by ID, so that we can look them up
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
		// why doesn't this work? this.values = Y.Array.map(value.split(','), parseInt);
	    } else {
		this.values = [];
	    }
            if (window.cf_debug) {
                var tmp = '';
                for (var myfield in fieldsbyid) {
                    tmp += ' ' + myfield;
                }
                alert('initializer::value = ' + value + '; fields =' + tmp);
            }
	    this.update_values();
	},

	destructor: function() { },

	/**
	 * Update the display and hidden element for the values.
	 */
	update_values: function() {
            if (window.customfieldpickerinstance) {
                this.values = window.customfieldpickerinstance.values;
            }
	    var values = this.values;
            window.customfieldpickerinstance = this; // init global required for IE7
	    // set the value of the hidden element
	    var valueelem = Y.one('#'+this.options.id+'_value');
	    valueelem.set('value', values.join(','));
            if (window.cf_debug) {
                cf_show_values();
            }
	    if (values.length) {
		// create a table with the selected fields
		var table = document.createElement('table');
		for (var i=0; i < values.length; i++) {
		    var row = document.createElement('tr');
		    var cell = document.createElement('td');
		    cell.appendChild(document.createTextNode(this.options.fieldsbyid[values[i]]));
		    row.appendChild(cell);
		    // down button
		    cell = document.createElement('td');
		    if (i != values.length-1) {
			var link = document.createElement('a');
			link.href = 'javascript: cf_down(' + i + ');';
			var img = document.createElement('img');
			img.src = this.options.down;
			img.alt = 'down';
			link.appendChild(img);
			var linkNode = Y.one(link);
			linkNode.on('click', function(e, index) {
			    // swap with next
			    var tmp = this.values[index];
			    this.values[index] = this.values[index+1];
			    this.values[index+1] = tmp;
			    this.update_values();
			    //this.refresh_picker(); // BJB was commented-out
			    e.preventDefault();
			}, this, i);
			cell.appendChild(link);
		    }
		    row.appendChild(cell);
		    // up button
		    cell = document.createElement('td');
		    if (i != 0) {
			var link = document.createElement('a');
			link.href = 'javascript: cf_up(' + i + ');';
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
			    //this.refresh_picker(); // BJB: was commented-out
			    e.preventDefault();
			}, this, i);
			cell.appendChild(link);
		    }
		    row.appendChild(cell);
		    // delete button
		    cell = document.createElement('td');
		    var link = document.createElement('a');
                    link.href = 'javascript: cf_delete(' + i + ');';
		    var img = document.createElement('img');
		    img.src = this.options.del;
		    img.alt = 'delete';
		    link.appendChild(img);
		    var linkNode = Y.one(link);
		    linkNode.on('click', function(e, index) {
			// remove
			this.values.splice(index, 1);
			this.update_values();
			this.refresh_picker();
			e.preventDefault();
		    }, this, i);
		    cell.appendChild(link);
		    row.appendChild(cell);

		    table.appendChild(row);
		}
                if (Y.YUI2.env.ua.ie > 0) { // IE (7)
                    tablecontainer.setContent('<table>' + table.innerHTML + '</table>');
                } else { // properly working browsers!
		    tablecontainer.setContent(table);
                }
	    } else {
		tablecontainer.setContent(document.createTextNode(M.str.elis_core.nofieldsselected));
	    }
	},

	/**
	 * Update the picker with the values that have not been selected
	 */
	refresh_picker: function() {
	    if (!this.rendered) {
                return;
	    }

            window.customfieldpickerinstance = this; // init global required for IE7
	    var pickerid = this.options.id + '_picker';
	    var listing = Y.one('#layout-'+pickerid);
            if (!listing) { // TBD: BJB
                if (window.cf_debug) {
                    alert('Error cannot locate layout listing!');
                }
                return;
            }
	    var values = this.values;
	    var selected = {};
	    for (var i=0; i < values.length; i++) {
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
                        tmp += ' ' + options.fields[cats][catfield];
                        if (selected[catfield]) {
                            tmp += '(1)';
                        }
                    }
                }
                alert('refresh_picker: listing = ' + listing + '; fields =' + tmp);
            }
	    for (var category in options.fields) {
		firstincategory = true;
                var catfields = options.fields[category];
                for (var fieldid in catfields) {
                    if (selected[fieldid]) {
                        // don't show fields that have been selected
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
		    link.href = 'javascript:window.customfieldpickerinstance.values.push(' + fieldid + ');window.customfieldpickerinstance.update_values();window.customfieldpickerinstance.refresh_picker();';
		    link.appendChild(document.createTextNode(options.fields[category][fieldid]));
		    var linkNode = Y.one(link);
		    linkNode.on('click', function(e, fieldid) {
			this.values.push(fieldid);
			this.update_values();
			//this.panel.hide();
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
                //Y.DOM.addHTML(listing, table);
		//listing.setContent(table);
	        this.panel.setBody('<div id="layout-'+pickerid+'"><table>' + table.innerHTML + '</table></div>');
	    }

	    this.panel.moveTo(button.getX(), button.getY());
	},

	/**
	 * Create and initialize the popup panel.
	 */
	render: function() {
	    var pickerid = this.options.id + '_picker';
        // Detect if the panel has already been added and remove it -- ELIS-5956
        obj = document.getElementById(pickerid);
        if (obj) {
            obj.parentNode.removeChild(obj);
        }
	    var pickernode = Y.Node.create('<div class="custom-field-picker" id="'+pickerid+'"></div>');
	    Y.one(document.body).appendChild(pickernode);
	    var panel = new YAHOO.widget.Panel(pickerid, {
		draggable: false,
		close: true,
		underlay: 'none',
		zindex: 9999990,
		monitorresize: false,
		xy: [button.getX(), button.getY()]
	    });
	    var layout = null;
	    var scope = this;
	    panel.beforeRenderEvent.subscribe(function() {
		YAHOO.util.Event.onAvailable('layout-'+pickerid, function() {
                    //alert('Event.onAvailable(layout-'+ pickerid +')');
		    scope.refresh_picker();
		});
	    });

	    panel.setHeader(M.str.moodle.add);
	    panel.setBody('<div id="layout-'+pickerid+'"></div>');
	    panel.render();
	    this.panel = panel;
	    this.rendered = true;
	},

	hide: function() {
            if (this.rendered) {
                this.panel.hide();
            }
	},

	show: function() {
	    if (this.rendered) {
		this.panel.show();
	    } else {
		this.launch();
	    }
	},

	launch: function() {
	    this.render();
	}
    });

    var helper = new MultiselectHelper(options);

    // show the panel when the button is clicked
    button.on('click', function(e) {
	helper.show();
    });
};
