<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/core/lib/setup.php');
require_once($CFG->dirroot.'/elis/core/accesslib.php');
require_once(elis::lib('data/customfield.class.php'));

// Needed as we will create a user field.
if (!defined('CONTEXT_ELIS_USER')) {
    define('CONTEXT_ELIS_USER', 1005);
}

/**
 * Test the field_category::ensure_exists_for_contextlevel function
 * @group elis_core
 */
class custom_field_category_ensure_exists_for_contextlevel_testcase extends elis_database_test {

    /**
     * Get field category records for a given name and contextlevel.
     * @param string $name The category name.
     * @param int $contextlevel The category contextlevel.
     * @return array The resulting records.
     */
    public function get_for_name_and_contextlevel($name, $contextlevel) {
        global $DB;
        $sql = 'SELECT cat.id as cat_id, cat.name as cat_name, catctx.contextlevel as catctx_contextlevel
                  FROM {'.field_category::TABLE.'} cat
                  JOIN {'.field_category_contextlevel::TABLE.'} catctx ON cat.id = catctx.categoryid
                 WHERE cat.name = ? AND catctx.contextlevel = ?';
        return $DB->get_records_sql($sql, array($name, $contextlevel));
    }

    /**
     * Test that the function will create a new category if one doesn't exist for a given name and contextlevel.
     */
    public function test_create_new() {
        global $DB;

        $name = 'Test Category';
        $contextlevel = CONTEXT_ELIS_USER;

        $this->assertEmpty($this->get_for_name_and_contextlevel($name, $contextlevel));
        $cat = field_category::ensure_exists_for_contextlevel($name, $contextlevel);
        $cat = $this->get_for_name_and_contextlevel($name, $contextlevel);
        $this->assertNotEmpty($cat);
        $cat = current($cat);
        $this->assertEquals($name, $cat->cat_name);
        $this->assertEquals($contextlevel, $cat->catctx_contextlevel);
    }

    /**
     * Test that the function will return the existing category if one exists for the given name and contextlevel.
     */
    public function test_return_existing() {
        global $DB;

        $name = 'Test Category';
        $contextlevel = CONTEXT_ELIS_USER;

        $cat = new field_category;
        $cat->name = $name;
        $cat->save();

        $catctx = new field_category_contextlevel;
        $catctx->categoryid = $cat->id;
        $catctx->contextlevel = $contextlevel;
        $catctx->save();

        $this->assertNotEmpty($this->get_for_name_and_contextlevel($name, $contextlevel));
        $cat2 = field_category::ensure_exists_for_contextlevel($name, $contextlevel);

        $this->assertNotEmpty($cat2);
        $this->assertEquals($cat->id, $cat2->id);
        $this->assertEquals($cat->name, $cat2->name);
    }
}