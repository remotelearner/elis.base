<?php
/**
 * Common code to display a table.
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
 * @subpackage core
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

class display_table {
    protected $table = null;
    protected $attributes;
    protected $items;
    protected $columns;
    protected $base_url;

    const ASC = 'ASC';
    const DESC = 'DESC';

    /**
     * Create a new table object.
     *
     * @param mixed $items array (or other iterable) of items to be displayed
     * in the table.  Each element in the array should be an object, with
     * fields matching the names in {@var $columns} containing the data.
     * @param array $columns mapping of column IDs to column configuration.
     * The column configuration is an array with the following entries:
     * - header (optional): the plain-text name, used for the table header.
     *   Can either be a string or an array (for headers that allow sorting on
     *   multiple values, e.g. first-name/last-name).  If it is an array, then
     *   the key is an ID used for sorting (if applicable), and the value is
     *   an array of similar form to the $columns array, but only the 'header'
     *   and 'sortable' keys are used.  (Defaults to empty.)
     * - display_function (optional): the function used to display the column
     *   entry.  This can either be the name of a method, or a PHP callback.
     *   Takes two arguments: the column ID, and the item (from the $items
     *   array).  Returns a string (or something that can be cast to a string).
     *   (Defaults to the get_item_display_default method, which just returns
     *   htmlspecialchars($item->$column), where $column is the column ID.)
     * - decorator (optional): a function used to decorate the column entry
     *   (e.g. add links, change the text style, etc).  This is a PHP callback
     *   that takes three arguments: the column contents (the return value from
     *   display_function), the column ID, and the item.  (Defaults to doing
     *   nothing.)
     * - sortable (optional): whether the column can be used for sorting.  Can
     *   be either true, false, display_table::ASC (which indicates that
     *   the table is sorted by this column in the ascending direction), or
     *   display_table::DESC.  This has no effect if the header entry is an
     *   array.  (Defaults to the return value of is_sortable_default, which
     *   defaults to true unless overridden by a  subclass.)
     * - wrapped (optional): whether the column data should be wrapped if it is
     *   too long.  (Defaults to the return value of is_column_wrapped_default,
     *   which defaults to true unless overridden by a subclass.)
     * - align (optional): how the column data should be aligned (left, right,
     *   center).  (Defaults to the return value of get_column_align_default(),
     *   which defaults to left unless overridden by a subclass.)
     * @param moodle_url $base_url base url to the page, for changing sort
     * order. Only needed if the table can be sorted.
     * @param string $sort_param the name of the URL parameter to add to
     * $pageurl to specify the column to sort by.
     * @param string $sortdir_param the name of the URL parameter to add to
     * $pageurl to specify the direction of the sort.
     * @param array $attributes associative array of table attributes like:
     * 'id' => 'tableid', 'width' => '90%', 'cellpadding', 'cellspacing' ...
     */
    public function __construct($items, $columns, moodle_url $base_url=null, $sort_param='sort', $sortdir_param='dir', array $attributes = array()) {
        $this->items = $items;
        $this->columns = $columns;
        $this->base_url = $base_url;
        $this->sort_param = $sort_param;
        $this->sortdir_param = $sortdir_param;
        $this->attributes = $attributes;
    }

    protected function column_to_header($columnid, $column) {
        global $OUTPUT;
        if (empty($column['header'])) {
            return '';
        } else if (is_string($column['header'])) {
            if (isset($column['sortable'])) {
                if ($column['sortable'] === true) {
                    return html_writer::link(new moodle_url($this->base_url,
                                                            array($this->sort_param => $columnid,
                                                                  $this->sortdir_param => self::ASC)),
                                             $column['header']);
                } else if ($column['sortable'] === self::ASC) {
                    return html_writer::link(new moodle_url($this->base_url,
                                                            array($this->sort_param => $columnid,
                                                                  $this->sortdir_param => self::DESC)),
                                             $column['header'])
                        . ' ' . html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/down')));
                } else if ($column['sortable'] === self::DESC) {
                    return html_writer::link(new moodle_url($this->base_url,
                                                            array($this->sort_param => $columnid,
                                                                  $this->sortdir_param => self::ASC)),
                                             $column['header'])
                        . ' ' . html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/up')));
                } else {
                    return $column['header'];
                }
            } else if ($this->is_sortable_default()) {
                return html_writer::link(new moodle_url($this->base_url,
                                                        array($this->sort_param => $columnid,
                                                              $this->sortdir_param => self::ASC)),
                                         $column['header']);
            } else {
                return $column['header'];
            }
        } else if (is_array($column['header'])) {
            $subheaders = array();
            foreach ($column['header'] as $headerid => $header) {
                $subheaders[] = $this->column_to_header($headerid, $header);
            }
            return implode(' / ', $subheaders);
        } else {
            return '';
        }
    }

    /**
     * Creates the Moodle html_table object to display the table.
     */
    protected function build_table() {
        if ($this->table !== null) {
            return;
        }

        $table = new html_table;
        $table->attributes = array_merge(array('width' => '90%'),
                                         $this->attributes);
        // We have to copy attributes to table properties because
        // html_writer::table() overrides some attributes with these!
        foreach ($table->attributes as $key => $val) {
            if (property_exists($table, $key)) {
                $table->{$key} = $val;
            }
        }
        $head = array();
        $align = array();
        $wrap = array();

        foreach ($this->columns as $columnid => $column) {
            $head[] = $this->column_to_header($columnid, $column);
            $align[] = $this->get_column_align($columnid);
            $wrap[] = $this->is_column_wrapped($columnid) ? null : 'nowrap';
        }
        $table->head = $head;
        $table->align = $align;
        $table->wrap = $wrap;

        $table->data = array();
        // FIXME: Eventually, we may want to set $table->data to a new iterable
        // class, so that we don't have to construct the whole array in
        // advance.  Maybe.  If we need to display massive tables...
        foreach ($this->items as $item) {
            $row = array();

            foreach (array_keys($this->columns) as $column) {
                $row[] = $this->get_item_display($column, $item);
            }

            $table->data[] = $row;
        }
        if ($this->items instanceof Iterator) {
            $this->items->close();
        }
        $this->table = $table;
    }

    /**
     * Gets the HTML for the table.
     */
    public function get_html() {
        $this->build_table();
        return html_writer::table($this->table);
    }

    /**
     * Gets the string (i.e. HTML) representation.  This is just a convenience
     * wrapper around get_html.
     */
    public function __toString() {
        return $this->get_html();
    }

    /**
     * Returns the alignment for a given column.
     */
    protected function get_column_align($column) {
        return isset($this->columns[$column]['align']) ? isset($columns[$column]['align']) : $this->get_column_align_default();
    }

    /**
     * Returns the default column alignment.
     */
    protected function get_column_align_default() {
        return 'left';
    }

    /**
     * Returns whether a given column is wrapped.
     */
    protected function is_column_wrapped($column) {
        return isset($this->columns[$column]['wrapped']) ? isset($columns[$column]['wrapped']) : $this->is_column_wrapped_default();
    }

    /**
     * Returns the default column wrapping.
     */
    protected function is_column_wrapped_default() {
        return true;
    }

    /**
     * Returns whether the table can be sorted by the given column.
     */
    protected function is_sortable($column) {
        return isset($this->columns[$column]['sortable']) ? isset($columns[$column]['sortable']) : $this->is_sortable_default();
    }

    /**
     * Returns the default column sortability.
     */
    protected function is_sortable_default() {
        return true;
    }

    /**
     * Gets the display text for a column, for an item.
     */
    protected function get_item_display($column, $item) {
        if (isset($this->columns[$column]['display_function'])) {
            if (is_string($this->columns[$column]['display_function'])
                && method_exists($this, $this->columns[$column]['display_function'])) {
                $text = call_user_func(array($this, $this->columns[$column]['display_function']), $column, $item);
            } else {
                $text = call_user_func($this->columns[$column]['display_function'], $column, $item);
            }
        } else if (method_exists($this, "get_item_display_$column")) {
            // old-style mechanism
            $text = call_user_func(array($this, "get_item_display_$column"), $column, $item);
        } else {
            $text = $this->get_item_display_default($column, $item);
        }
        if (isset($this->columns[$column]['decorator'])) {
            $text = call_user_func($this->columns[$column]['decorator'], $text, $column, $item);
        }
        return $text;
    }

    /**
     * The default item display function.
     */
    protected function get_item_display_default($column, $item) {
        return htmlspecialchars($item->$column);
    }

    /**
     * Display function for a yes/no element.
     */
    public static function display_yesno_item($column, $item) {
        if ($item->$column) {
            return get_string('yes');
        } else {
            return get_string('no');
        }
    }

    /**
     * Display function for a country code element
     */
    public static function display_country_item($column, $item) {
        static $countries;

        if (!isset($countries)) {
            $countries = get_string_manager()->get_list_of_countries();
        }

        return isset($countries[$item->$column]) ? $countries[$item->$column] : '?';
    }

    /**
     * Display function for a country code element
     */
    public static function display_language_item($column, $item) {
        static $languages;

        if (!isset($languages)) {
            $languages = get_string_manager()->get_list_of_languages(null, 'iso6391');
        }

        return isset($languages[$item->$column]) ? $languages[$item->$column] : '?';
    }

    /**
     * Display a user's full name using Moodle's fullname function
     */
    public static function display_user_fullname_item($column, $item) {
        if (method_exists($item, 'fullname')) {
            return $item->fullname();
        }
        if (method_exists($item, 'to_object')) {
            $item = $item->to_object();
        }
        return fullname($item);
    }
}

/**
 * Convert a date format to a strftime format
 * from: http://php.net/manual/en/function.strftime.php
 * Timezone conversion is done for unix. Windows users must exchange %z and %Z.
 *
 * Unsupported date formats : S, n, t, L, B, G, u, e, I, P, Z, c, r
 * Unsupported strftime formats : %U, %W, %C, %g, %r, %R, %T, %X, %c, %D, %F, %x
 *
 * @param string $dateformat a date format
 * @return string the equivalent strftime format
 */
function date_format_to_strftime($dateformat) {
    static $caracs = array(
        // Day - no strf eq : S
        'd' => '%d', 'D' => '%a', 'j' => '%e', 'l' => '%A', 'N' => '%u', 'w' => '%w', 'z' => '%j',
        // Week - no date eq : %U, %W
        'W' => '%V',
        // Month - no strf eq : n, t
        'F' => '%B', 'm' => '%m', 'M' => '%b',
        // Year - no strf eq : L; no date eq : %C, %g
        'o' => '%G', 'Y' => '%Y', 'y' => '%y',
        // Time - no strf eq : B, G, u; no date eq : %r, %R, %T, %X
        'a' => '%P', 'A' => '%p', 'g' => '%l', 'h' => '%I', 'H' => '%H', 'i' => '%M', 's' => '%S',
        // Timezone - no strf eq : e, I, P, Z
        'O' => '%z', 'T' => '%Z',
        // Full Date / Time - no strf eq : c, r; no date eq : %c, %D, %F, %x
        'U' => '%s'
    );

    return strtr((string)$dateformat, $caracs);
}

/**
 * Display function for a date element
 */
class display_date_item {
    /**
     * constructor for display_date_item
     * @param string $format optional format for userdate(), defaults to '%b %d, %Y'
     */
    public function __construct($format = '%b %d, %Y') {
        // Check if the old 'date' format was passed and try to convert it
        if (strpos($format, '%') === false) {
            debugging('display_date_item class no longer uses date() format. Please update your code to use strftime() format strings.');
            $format = date_format_to_strftime($format);
        }
        $this->format = $format;
    }

    /**
     * display method for display_date_item
     * @param string $column the column's field name
     * @param object $item the current record object
     * @return string the formatted date string or '-' if 'empty' date
     */
    public function display($column, $item) {
        if (empty($item->$column)) {
            return '-';
        } else {
            return userdate($item->$column, $this->format);
        }
    }
}

/**
 * Converts an item to a link to a page to view the item, if the user has
 * capabilities to view.
 */
class record_link_decorator {
    /**
     * @param string $page the base page object to use for constructing the
     * link
     * $param string $id_field_name the field in the item data that contains
     * the ID to use
     * $param string $param_name the URL parameter to use to specify the ID
     */
    public function __construct($pageclass, array $pageparams, $id_field_name, $param_name='id') {
        $this->pageclass = $pageclass;
        $this->pageparams = $pageparams;
        $this->id_field_name = $id_field_name;
        $this->param_name = $param_name;
    }

    public function decorate($text, $column, $item) {
        $id_field_name = $this->id_field_name;

        if (isset($item->$id_field_name) && $item->$id_field_name) {
            $page = new $this->pageclass($this->pageparams + array($this->param_name => $item->$id_field_name));
            if ($page->can_do()) {
                return html_writer::link($page->url, $text);
            }
        }
        return $text;
    }
}
