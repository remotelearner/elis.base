function add_selection(eleid_base,id,label,ui,useid) {
    if (ui == 'inline') {
        $('#id_'+eleid_base).val(useid ? id : label);
        $('#id_grp_'+eleid_base).val(label);
    } else {
        eleid_base = 'id_'+eleid_base;
        var element = window.opener.document.getElementById(eleid_base);
        element.value = id;
        element = window.opener.document.getElementById(eleid_base+'_labelsave');
        element.value = label;
        element = window.opener.document.getElementById(eleid_base+'_label');
        element.innerHTML = label;
        window.close();
    }
}

function autocomplete_ui(textbox,results,search_status,search_url,results_wrpr) {
    var last_req = '';
    var next_search_timeout = '';
    var input = $('#'+textbox);
    var this_autoc = this;
    var row_selector = 'tr.datarow'; //what counts as a selectable row
    var last_q = '';

    //NOTE: these are language string defaults - you MUST set these properties with Moodle language strings when
    //instantiating this object! :)
    this.str_typetosearch = 'Type Above to Search';
    this.str_searching = 'Searching for "[[q]]" in 500ms...';

    results = $('#'+results);
    search_status = $('#'+search_status);

    this.keycontrol = function(e) {
        //only do something if we have a wrapper element and the up (38), down (40), or enter(13) key was pressed
        if (typeof results_wrpr != 'undefined' && (e.keyCode == 40 || e.keyCode == 38 || e.keyCode == 13)) {
            var nrow = null;

            var actv_class = 'hovered'; //class to add to indicate selection
            var results_wrpr_e = $('#'+results_wrpr);
            var actvrow = results.find('.'+actv_class);

            if (actvrow.length >= 1) {
                //there is an item selected
                if (e.keyCode == 40 || e.keyCode ==38) { //up or down
                    actvrow.removeClass(actv_class);
                    if (e.keyCode == 40) { //down
                        nrow = actvrow.next(row_selector);
                        if (nrow.length < 1) {
                            nrow = results.find(row_selector).eq(0);
                        }
                    } else if (e.keyCode == 38) { //up
                        nrow = actvrow.prev(row_selector);
                        if (nrow.length < 1) {
                            nrow = results.find(row_selector).last();
                        }
                    }
                }
                if (e.keyCode == 13) { //enter
                    e.preventDefault();
                    actvrow.click();
                }
            } else {
                //there is no item selected
                if (e.keyCode == 40) { //down key pressed, select first row
                    nrow = results.find(row_selector).eq(0);
                } else if (e.keyCode == 38) { //up key pressed, select last row
                    nrow = results.find(row_selector).last();
                }
            }
            nrow.addClass(actv_class);
            this_autoc.scroll_wrpr_for_keypress(nrow,results_wrpr_e);
        }
    }

    this.livesearch = function(e) {
        if (typeof results_wrpr == 'undefined' || (e.keyCode != 38 && e.keyCode != 40 && e.keyCode != 13)) {
            var q = input.val();
            if (q != last_q) {
                clearTimeout(next_search_timeout);
                if (q != '') {
                    var searchforstr = this.str_searching.replace('[[q]]',q);
                    search_status.show().html(searchforstr);
                    q = escape(q);
                    next_search_timeout=setTimeout(function() { this_autoc.do_search(q);},500);
                } else {
                    search_status.html('&nbsp;');
                    if (search_status.hasClass('filt_ac_status_inline')) {
                        search_status.hide();
                    }
                    results.html('<span class="no_results">'+this.str_typetosearch+'</span>');
                }
                last_q = q;
            }
        }
    }

    input.keydown(function(e) { this_autoc.keycontrol(e); });

    input.keyup(function(e) { this_autoc.livesearch(e); });

    this.scroll_wrpr_for_keypress = function(row_e,wrpr_e) {
        var nrow_pos = row_e.position();
        var nrow_realtop = nrow_pos.top + wrpr_e.scrollTop()
        var nrow_realbottom = nrow_realtop + row_e.outerHeight();
        var wrpr_scrollBottom = wrpr_e.scrollTop() + wrpr_e.innerHeight();
        var newscrolltop = wrpr_e.scrollTop();

        if (nrow_realbottom > wrpr_scrollBottom) {
            newscrolltop = nrow_realbottom-wrpr_e.innerHeight();
        } else if (wrpr_e.scrollTop() > nrow_realtop) {
            newscrolltop = nrow_realtop;
        }

        if (newscrolltop < 1) { newscrolltop = 0; }

        wrpr_e.scrollTop(newscrolltop);
    }

    this.do_search = function(q) {
        if (last_req!='') { last_req.abort(); }
        last_req = $.get(search_url+q, function (data) {
                search_status.html('&nbsp;');
                if (search_status.hasClass('filt_ac_status_inline')) {
                    search_status.hide();
                }
                results.html(data);

                if (typeof results_wrpr != 'undefined') {
                    results_wrpr_e = $('#'+results_wrpr);
                    results.find(row_selector).click(function (e) {e.preventDefault();results_wrpr_e.hide();});
                    var reswidth = (results.children('table').length >= 1) ? results.children('table').outerWidth() : 400;
                    reswidth = (reswidth > 400) ? reswidth : 400;
                    results_wrpr_e.css('width',reswidth+20);
                }
        });
    };
}
