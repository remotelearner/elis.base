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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

function xmldb_local_upgrade($oldversion) {
    global $CFG, $db;
 
    $result = true;
 
    if ($result && $oldversion < 2010110800) {
        $table = new XMLDBTable('user_info_data');
        
        $index = new XMLDBIndex('useridx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $result = $result && add_index($table, $index);
        
        $index = new XMLDBIndex('fieldidx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('fieldid'));
        $result = $result && add_index($table, $index);
    }

    if ($result && $oldversion < 2011033100) {
        // Changing size of field 'name' on 'user_preferences' from 50 to 255
        $table = new XMLDBTable('user_preferences');
        $field = new XMLDBField('name');
        $field->setAttributes(XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null, 'userid');

        // Launch change of precision for field name
        $result = $result && change_field_precision($table, $field);
    }

    return $result;
}

?>
