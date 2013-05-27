<?php
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
 * @package    elis
 * @subpackage core
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once('PHPUnit/Extensions/Database/DataSet/IDataSet.php');
require_once('PHPUnit/Extensions/Database/DataSet/DefaultTable.php');
require_once('PHPUnit/Extensions/Database/DataSet/DefaultTableMetaData.php');

/**
 * Load a PHPUnit data set into the Moodle database.
 *
 * @params PHPUnit_Extensions_Database_DataSet_IDataSet $dataset the PHPUnit
 * data set
 * @params bool $replace whether to replace the contents of the database tables
 * @params moodle_database $db the Moodle database to use
 */
function load_phpunit_data_set(PHPUnit_Extensions_Database_DataSet_IDataSet $dataset, $replace=false, moodle_database $db = null) {
    if ($db === null) {
        global $DB;
        $db = $DB;
    }

    foreach ($dataset as $tablename => $table) {
        if ($replace) {
            $db->delete_records($tablename);
        }
        $rows = $table->getRowCount();
        for ($i = 0; $i < $rows; $i++) {
            $row = $table->getRow($i);
            if (isset($row['id'])) {
                $db->import_record($tablename, $row);
            } else {
                $db->insert_record($tablename, $row, false, true);
            }
        }
    }
}

/**
 * Overlay certain tables in a Moodle database with dummy tables that can be
 * modified without affecting the original tables.
 *
 * This class will try to prevent writes to non-overlay tables, and throw an
 * exception, but not all types of writes will be caught (in particular, calls
 * to $db->execute()).
 *
 * WARNING: This will only affect database calls made through this database
 * object.  So you either need to be able to pass a database object to
 * function/object that you are testing, or you need to replace the global $DB
 * object.
 */
class overlay_database extends moodle_database {
    /**
     * @var array Holds any tables that have been changed.
     */
    protected $changed_tables = array();

    /**
     * Create a new object.
     *
     * @param moodle_database $basedb the base database object
     * @param array $overlaytables an array of tables that will be overlayed
     * with the dummy tables.  The array is an associative array where the key
     * is the table name (without prefix), and the value is the component where
     * the table's structure is defined (in its db/install.xml file),
     * e.g. 'moodle', or 'block_foo'.
     * @param array $ignoretables an array of tables that will be ignored for
     * writes.  Normally, writes to non-overlay tables will result in an
     * exception being thrown.  If a table is specified here, the write will be
     * ignored instead.  Only use this if you are certain that all writes to
     * this table will be no-ops.  The array is an associative array where the
     * key is the table name (without prefix).  The value can be anything (but
     * it's probably best to do use the same format as $overlaytables)
     * @param string $overlayprefix the prefix to use for the dummy tables
     */
    public function __construct(moodle_database $basedb, array $overlaytables, array $ignoretables=array(), $overlayprefix='ovr_') {
        parent::__construct($basedb->external);
        $this->basedb = $basedb;
        $this->overlaytables = $overlaytables;
        $this->ignoretables = $ignoretables;
        $this->pattern = '/{('.implode('|', array_keys($this->overlaytables)).')}/';
        $this->overlayprefix = $overlayprefix;
        $this->temptables = $basedb->temptables;

        // create temp DB tables
        $manager = $this->get_manager();
        $xmldbfiles = array();
        foreach ($overlaytables as $tablename => $component) {
            if (!isset($xmldbfiles[$component])) {
                $filename = get_component_directory($component)."/db/install.xml";
                $xmldb_file = new xmldb_file($filename);

                if (!$xmldb_file->fileExists()) {
                    throw new ddl_exception('ddlxmlfileerror', null, 'File does not exist');
                }

                $loaded = $xmldb_file->loadXMLStructure();
                if (!$loaded || !$xmldb_file->isLoaded()) {
                    /// Show info about the error if we can find it
                    if ($structure =& $xmldb_file->getStructure()) {
                        if ($errors = $structure->getAllErrors()) {
                            throw new ddl_exception('ddlxmlfileerror', null, 'Errors found in XMLDB file: '. implode (', ', $errors));
                        }
                    }
                    throw new ddl_exception('ddlxmlfileerror', null, 'not loaded??');
                }

                $xmldbfiles[$component] = $xmldb_file;
            } else {
                $xmldb_file = $xmldbfiles[$component];
            }
            $structure = $xmldb_file->getStructure();
            $table = $structure->getTable($tablename);
            // FIXME: when http://bugs.mysql.com/bug.php?id=10327 gets fixed,
            // we can switch this back to create_temp_table
            if ($manager->table_exists($table)) {
                $manager->drop_table($table);
            }
            $manager->create_table($table);
        }
        $this->xmldbfiles = $xmldbfiles;

        $this->donesetup = true;
    }

    /**
     * Truncate tables that have been changed. This is run after every test to ensure tests don't conflict with eachother.
     */
    public function reset_overlay_tables() {
        foreach ($this->changed_tables as $i => $tablename) {
            if (isset($this->overlaytables[$tablename])) {
                $this->delete_records($tablename);
            }
            unset($this->changed_tables[$i]);
        }
    }

    /**
     * Clean up the temporary tables.  You'd think that if this method was
     * called dispose, then the cleanup would happen automatically, but it
     * doesn't.
     */
    public function cleanup() {
        $manager = $this->get_manager();
        foreach ($this->overlaytables as $tablename => $component) {
            $xmldb_file = $this->xmldbfiles[$component];
            $structure = $xmldb_file->getStructure();
            $table = $structure->getTable($tablename);
            // FIXME: when http://bugs.mysql.com/bug.php?id=10327 gets fixed,
            // we can switch this back to drop_temp_table
            $manager->drop_table($table);
        }
    }

    public function driver_installed() {
        return true;
    }

    public function get_prefix() {
        //return $this->basedb->get_prefix();
        // the database manager needs the overlay prefix for creating the
        // temporary tables
        return $this->overlayprefix;
    }

    public function get_dbfamily() {
        return $this->basedb->get_dbfamily();
    }

    protected function get_dbtype() {
        return 'overlay';
    }

    protected function get_dblibrary() {
        return 'test';
    }

    public function get_dbengine() {
        return $this->basedb->get_dbengine();
    }

    public function get_dbcollation() {
        return $this->basedb->get_dbcollation();
    }

    public function get_name() {
        return get_string('overlaydbname', 'elis_core', $this->basedb->get_name());
    }

    public function get_configuration_help() {
        return '';
    }

    public function get_configuration_hints() {
        return '';
    }

    public function connect($dbhost, $dbuser, $dbpass, $dbname, $prefix, array $dboptions=null) {
        // do nothing (assume that base DB object is already connected)
    }

    public function get_server_info() {
        return $this->basedb->get_server_info();
    }

    public function allowed_param_types() {
        return $this->basedb->allowed_param_types();
    }

    public function get_last_error() {
        return $this->basedb->get_last_error();
    }

    public function get_tables($usecache=true) {
        $tables = $this->basedb->get_tables($usecache);
        if (empty($this->donesetup)) {
            // not done creating the tables: remove the overlay tables
            $tables = array_diff($tables, array_keys($this->overlaytables));
            // re-add the ones that have already been created
            $cacheprefix = $this->basedb->prefix;
            $this->basedb->prefix = $this->overlayprefix; // HACK!!!
            $tables = array_merge($tables, $this->basedb->get_tables(false));
            $this->basedb->prefix = $cacheprefix;
            // fetch again, to refresh the cache using the real prefix
            $this->basedb->get_tables(false);
        }
        return $tables;
    }

    public function get_indexes($table) {
        return $this->basedb->get_indexes($table);
    }

    public function get_columns($table, $usecache=true) {
        return $this->basedb->get_columns($table, $usecache);
    }

    public function normalise_value($column, $value) {
        return $this->basedb->normalise_value($column, $value);
    }

    public function reset_caches() {
        $this->basedb->reset_caches();
    }

    public function setup_is_unicodedb() {
        return $this->basedb->setup_is_unicodedb();
    }

    public function change_database_structure($sql) {
        // FIXME: or should we just do nothing?
        return $this->basedb->change_database_structure($sql);
    }

    protected function fix_overlay_table_names($sql) {
        return preg_replace($this->pattern, $this->overlayprefix.'$1', $sql);
    }

    /**
     * Add a table name to the internal array of changed tables. Only tables in the changed table array will be
     * truncated after every test
     * @param string $table The name of the changed table.
     */
    protected function record_changed_table($table) {
        $this->changed_tables[$table] = $table;
    }

    public function execute($sql, array $params=null) {
        if (stripos($sql, 'INSERT') === 0 || stripos($sql, 'UPDATE') === 0 || stripos($sql, 'DELETE') === 0) {
            if (preg_match($this->pattern, $sql, $matches)) {
                $this->record_changed_table($matches[1]);
            }
        }
        return $this->basedb->execute($this->fix_overlay_table_names($sql), $params);
    }

    public function get_recordset_sql($sql, array $params=null, $limitfrom=0, $limitnum=0) {
        return $this->basedb->get_recordset_sql($this->fix_overlay_table_names($sql), $params, $limitfrom, $limitnum);
    }

    public function get_records_sql($sql, array $params=null, $limitfrom=0, $limitnum=0) {
        return $this->basedb->get_records_sql($this->fix_overlay_table_names($sql), $params, $limitfrom, $limitnum);
    }

    public function get_fieldset_sql($sql, array $params=null) {
        return $this->basedb->get_fieldset_sql($this->fix_overlay_table_names($sql), $params);
    }

    private function check_table_writable($table) {
        if (!isset($this->overlaytables[$table])) {
            if (isset($this->ignoretables[$table])) {
                return null;
            }
            throw new overlay_database_exception('write_to_non_overlay_table', 'elis_core', '', $table);
        }
        return true;
    }

    public function insert_record_raw($table, $params, $returnid=true, $bulk=false, $customsequence=false) {
        if ($this->check_table_writable($table) === null) {
            return true;
        }
        $this->record_changed_table($table);
        $cacheprefix = $this->basedb->prefix;
        $this->basedb->prefix = $this->overlayprefix; // HACK!!!
        $result = $this->basedb->insert_record_raw($table, $params, $returnid, $bulk, $customsequence);
        $this->basedb->prefix = $cacheprefix;
        return $result;
    }

    public function insert_record($table, $dataobject, $returnid=true, $bulk=false) {
        if ($this->check_table_writable($table) === null) {
            return true;
        }
        $this->record_changed_table($table);
        $cacheprefix = $this->basedb->prefix;
        $this->basedb->prefix = $this->overlayprefix; // HACK!!!
        $result = $this->basedb->insert_record($table, $dataobject, $returnid, $bulk);
        $this->basedb->prefix = $cacheprefix;
        return $result;
    }

    public function import_record($table, $dataobject) {
        if ($this->check_table_writable($table) === null) {
            return true;
        }
        $this->record_changed_table($table);
        $cacheprefix = $this->basedb->prefix;
        $this->basedb->prefix = $this->overlayprefix; // HACK!!!
        $result = $this->basedb->import_record($table, $dataobject);
        $this->basedb->prefix = $cacheprefix;
        return $result;
    }

    public function update_record_raw($table, $params, $bulk=false) {
        if ($this->check_table_writable($table) === null) {
            return true;
        }
        $this->record_changed_table($table);
        $cacheprefix = $this->basedb->prefix;
        $this->basedb->prefix = $this->overlayprefix; // HACK!!!
        $result = $this->basedb->update_record_raw($table, $params, $bulk);
        $this->basedb->prefix = $cacheprefix;
        return $result;
    }

    public function update_record($table, $dataobject, $bulk=false) {
        if ($this->check_table_writable($table) === null) {
            return true;
        }
        $this->record_changed_table($table);
        $cacheprefix = $this->basedb->prefix;
        $this->basedb->prefix = $this->overlayprefix; // HACK!!!
        $result = $this->basedb->update_record($table, $dataobject, $bulk);
        $this->basedb->prefix = $cacheprefix;
        return $result;
    }

    public function set_field_select($table, $newfield, $newvalue, $select, array $params=null) {
        if ($this->check_table_writable($table) === null) {
            return true;
        }
        $this->record_changed_table($table);
        $cacheprefix = $this->basedb->prefix;
        $this->basedb->prefix = $this->overlayprefix; // HACK!!!
        $result = $this->basedb->set_field_select($table, $newfield, $newvalue, $select, $params);
        $this->basedb->prefix = $cacheprefix;
        return $result;
    }

    public function delete_records_select($table, $select, array $params=null) {
        if ($this->check_table_writable($table) === null) {
            return true;
        }
        $this->record_changed_table($table);
        $cacheprefix = $this->basedb->prefix;
        $this->basedb->prefix = $this->overlayprefix; // HACK!!!
        $result = $this->basedb->delete_records_select($table, $select, $params);
        $this->basedb->prefix = $cacheprefix;
        return $result;
    }

    // SQL constructs -- just hand everything over to the base DB
    public function sql_null_from_clause() {
        return $this->basedb->sql_null_from_clause();
    }

    public function sql_bitand($int1, $int2) {
        return $this->basedb->sql_bitand($int1, $int2);
    }

    public function sql_bitnot($int1) {
        return $this->basedb->sql_bitnot($int1);
    }

    public function sql_bitor($int1, $int2) {
        return $this->basedb->sql_bitor($int1, $int2);
    }

    public function sql_bitxor($int1, $int2) {
        return $this->basedb->sql_bitxor($int1, $int2);
    }

    public function sql_modulo($int1, $int2) {
        return $this->basedb->sql_modulo($int1, $int2);
    }

    public function sql_ceil($fieldname) {
        return $this->basedb->sql_ceil($fieldname);
    }

    public function sql_cast_char2int($fieldname, $text=false) {
        return $this->basedb->sql_cast_char2int($fieldname, $text);
    }

    public function sql_cast_char2real($fieldname, $text=false) {
        return $this->basedb->sql_cast_char2real($fieldname, $text);
    }

    public function sql_cast_2signed($fieldname) {
        return $this->basedb->sql_cast_char2int($fieldname);
    }

    public function sql_compare_text($fieldname, $numchars=32) {
        return $this->basedb->sql_compare_text($fieldname, $numchars);
    }

    public function sql_like($fieldname, $param, $casesensitive = true, $accentsensitive = true, $notlike = false, $escapechar = '\\') {
        return $this->basedb->sql_like($fieldname, $param, $casesensitive, $accentsensitive, $notlike, $escapechar);
    }

    public function sql_like_escape($text, $escapechar = '\\') {
        return $this->basedb->sql_like_escape($text, $escapechar);
    }

    public function sql_ilike() {
        return $this->basedb->sql_ilike();
    }

    public function sql_concat() {
        //pass along the arguments dynamically
        return call_user_func_array(array($this->basedb, 'sql_concat'), func_get_args());
    }

    public function sql_concat_join($separator="' '", $elements=array()) {
        return $this->basedb->sql_concat_join($separator, $elements);
    }

    public function sql_fullname($first='firstname', $last='lastname') {
        return $this->basedb->sql_fullname($first, $last);
    }

    public function sql_order_by_text($fieldname, $numchars=32) {
        return $this->basedb->sql_order_by_text($fieldname, $numchars);
    }

    public function sql_length($fieldname) {
        return $this->basedb->sql_length($fieldname);
    }

    public function sql_substr($expr, $start, $length=false) {
        return $this->basedb->sql_substr($expr, $start, $length);
    }

    public function sql_position($needle, $haystack) {
        return $this->basedb->sql_position($needle, $haystack);
    }

    public function sql_empty() {
        return $this->basedb->sql_empty();
    }

    public function sql_isempty($tablename, $fieldname, $nullablefield, $textfield) {
        return $this->basedb->sql_isempty($tablename, $fieldname, $nullablefield, $textfield);
    }

    public function sql_isnotempty($tablename, $fieldname, $nullablefield, $textfield) {
        return $this->basedb->sql_isnotempty($tablename, $fieldname, $nullablefield, $textfield);
    }

    public function sql_regex($positivematch=true) {
        return $this->basedb->sql_regex($positivematch);
    }

    // transactions -- just hand everything over to the base DB
    protected function transactions_supported() {
        return $this->basedb->transactions_supported();
    }

    public function is_transaction_started() {
        return $this->basedb->is_transaction_started();
    }

    public function transactions_forbidden() {
        return $this->basedb->transactions_forbidden();
    }

    public function start_delegated_transaction() {
        return $this->basedb->start_delegated_transaction();
    }

    protected function begin_transaction() {
        return $this->basedb->begin_transaction();
    }

    public function commit_delegated_transaction(moodle_transaction $transaction) {
        return $this->basedb->commit_delegated_transaction($transaction);
    }

    protected function commit_transaction() {
        return $this->basedb->commit_transaction();
    }

    public function rollback_delegated_transaction(moodle_transaction $transaction, Exception $e) {
        return $this->basedb->rollback_delegated_transaction($transaction, $e);
    }

    protected function rollback_transaction() {
        return $this->basedb->rollback_transaction();
    }

    public function force_transaction_rollback() {
        return $this->basedb->force_transaction_rollback();
    }

    // session locking -- just hand everything over to the base DB
    public function session_lock_supported() {
        return $this->basedb->session_lock_supported();
    }

    public function get_session_lock($rowid, $timeout) {
        return $this->basedb->get_session_lock($rowid, $timeout);
    }

    public function release_session_lock($rowid) {
        return $this->basedb->release_session_lock($rowid);
    }

    // performance and logging -- for now, just use the base DB's numbers
    public function perf_get_reads() {
        return $this->basedb->perf_get_reads();
    }

    public function perf_get_writes() {
        return $this->basedb->perf_get_writes();
    }

    public function perf_get_queries() {
        return $this->basedb->perf_get_queries();
    }
}

class overlay_database_exception extends moodle_exception {
}

/**
 * PHPUnit DataTable for a Moodle recordset (or record array, or ELIS data
 * collection)
 */
class moodle_recordset_phpunit_datatable extends PHPUnit_Extensions_Database_DataSet_DefaultTable {
    public function __construct($tablename, $rs) {
        // try to get the column names from an entry
        if (is_array($rs)) {
            if (!empty($rs)) {
                $ref_obj = current($rs);
            }
        } else if ($rs->valid()) {
            $ref_obj = $rs->current();
        }
        if (isset($ref_obj)) {
            if (method_exists($ref_obj, 'to_array')) {
                $ref_obj = $ref_obj->to_array();
            } else if (method_exists($ref_obj, 'to_object')) {
                $ref_obj = $ref_obj->to_object();
            }
            if (is_array($ref_obj)) {
                $columns = array_keys($ref_obj);
            } else {
                $columns = array_keys(get_object_vars($ref_obj));
            }
        } else {
            $columns = array();
        }

        $metadata = new PHPUnit_Extensions_Database_DataSet_DefaultTableMetaData($tablename, $columns);

        parent::__construct($metadata);

        foreach ($rs as $record) {
            if (is_array($record)) {
                $this->addRow($record);
            } else if (method_exists($record, 'to_array')) {
                $this->addRow($record->to_array());
            } else {
                $this->addRow((array)$record);
            }
        }
    }
}

abstract class elis_database_test extends PHPUnit_Framework_TestCase {
    /**
     * The overlay database object set up by a test.
     */
    protected static $overlaydb;
    /**
     * The original global $DB object.
     */
    protected static $origdb;

    /**
     * Return the list of tables that should be overlayed.
     */
    static protected function get_overlay_tables() {
        return array();
    }

    /**
     * Return the list of tables that should be ignored for writes.
     */
    static protected function get_ignored_tables() {
        return array();
    }

    /**
     * Clean up the temporary database tables.
     */
    public static function tearDownAfterClass() {
        if (!empty(self::$overlaydb)) {
            self::$overlaydb->cleanup();
            self::$overlaydb = null;
        }
        if (!empty(self::$origdb)) {
            self::$origdb = null;
        }
    }

    public static function setUpBeforeClass() {
        // called before each test function
        global $DB;
        self::$origdb = $DB;
        self::$overlaydb = new overlay_database($DB, static::get_overlay_tables(), static::get_ignored_tables());
    }

    /**
     * reset the $DB global
     */
    protected function tearDown() {
        global $DB;
        $DB = self::$origdb;
    }

    protected function setUp() {
        // called before each test method
        global $DB;
        self::$overlaydb->reset_overlay_tables();
        $DB = self::$overlaydb;
    }

    /**
     * The following two functions are stolen from PHPUnit_Extensions_Database_TestCase
     */
    /**
     * Asserts that two given tables are equal.
     *
     * @param PHPUnit_Extensions_Database_DataSet_ITable $expected
     * @param PHPUnit_Extensions_Database_DataSet_ITable $actual
     * @param string $message
     */
    public static function assertTablesEqual(PHPUnit_Extensions_Database_DataSet_ITable $expected, PHPUnit_Extensions_Database_DataSet_ITable $actual, $message = '')
    {
        $constraint = new PHPUnit_Extensions_Database_Constraint_TableIsEqual($expected);

        self::assertThat($actual, $constraint, $message);
    }

    /**
     * Asserts that two given datasets are equal.
     *
     * @param PHPUnit_Extensions_Database_DataSet_ITable $expected
     * @param PHPUnit_Extensions_Database_DataSet_ITable $actual
     * @param string $message
     */
    public static function assertDataSetsEqual(PHPUnit_Extensions_Database_DataSet_IDataSet $expected, PHPUnit_Extensions_Database_DataSet_IDataSet $actual, $message = '')
    {
        $constraint = new PHPUnit_Extensions_Database_Constraint_DataSetIsEqual($expected);

        self::assertThat($actual, $constraint, $message);
    }
}
