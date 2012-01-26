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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

class pm_moodle_profile {
    const sync_from_moodle = 1;
    const sync_to_moodle = 0;
}

// Synchronization functions

function sync_profile_field_with_moodle($field) {
    sync_profile_field_to_moodle($field);
    sync_profile_field_from_moodle($field);
}

function sync_profile_field_to_moodle($field) {
    global $DB;

    if (!isset($field->owners['moodle_profile'])
        || $field->owners['moodle_profile']->exclude == pm_moodle_profile::sync_from_moodle) {
        // not owned by the Moodle plugin, or set to sync from Moodle
        return true;
    }
    if (!$DB->record_exists('user_info_field', array('shortname'=>$field->shortname))) {
        // no Moodle field to sync with
        return true;
    }
    $level = context_level_base::get_custom_context_level('user', 'elis_program');

    $dest = 'user_info_data';
    $src = $field->data_table();
    $mfieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$field->shortname));

    $joins = 'JOIN {'.user::TABLE.'} cu ON usr.idnumber = cu.idnumber
              JOIN {context} ctx ON ctx.instanceid = cu.id AND ctx.contextlevel = '.$level.'
              JOIN {'.$src.'} src ON src.contextid = ctx.id AND src.fieldid = '.$field->id;

    // insert field values that don't already exist
    $sql = 'INSERT INTO {'.$dest.'}
                   (userid, fieldid, data)
            SELECT usr.id AS userid, '.$mfieldid.' AS fieldid, src.data
              FROM {user} usr
                   '.$joins.'
         LEFT JOIN {'.$dest.'} dest ON dest.userid = usr.id AND dest.fieldid = ?
             WHERE dest.id IS NULL';
    $DB->execute($sql, array($mfieldid));

    // update already-existing values
    $sql = 'UPDATE {'.$dest.'} dest
              JOIN {user} usr ON dest.userid = usr.id
                   '.$joins.'
               SET dest.data = src.data
               WHERE dest.fieldid = ?';
    $DB->execute($sql, array($mfieldid));
}

function sync_profile_field_from_moodle($field) {
    global $DB;

    $level = context_level_base::get_custom_context_level('user', 'elis_program');
    if (!isset($field->owners['moodle_profile'])
        || $field->owners['moodle_profile']->exclude == pm_moodle_profile::sync_to_moodle) {
        // not owned by the Moodle plugin, or set to sync to Moodle
        return true;
    }
    if (!$DB->record_exists('user_info_field', array('shortname'=>$field->shortname))) {
        // no Moodle field to sync with
        return true;
    }

    $dest = $field->data_table();
    $src = 'user_info_data';
    $mfieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$field->shortname));

    $joins = 'JOIN {'.user::TABLE.'} cu ON usr.idnumber = cu.idnumber
              JOIN {context} ctx ON ctx.instanceid = cu.id AND ctx.contextlevel = '.$level.'
              JOIN {'.$src.'} src ON src.userid = usr.id AND src.fieldid = '.$mfieldid;

    // insert field values that don't already exist
    $sql = 'INSERT INTO {'.$dest.'}
            (contextid, fieldid, data)
            SELECT ctx.id AS contextid, '.$field->id.' AS fieldid, src.data
              FROM {user} usr
                   '.$joins.'
         LEFT JOIN {'.$dest.'} dest ON dest.contextid = ctx.id AND dest.fieldid = ?
             WHERE dest.id IS NULL';
    $DB->execute($sql, array($field->id));

    // update already-existing values
    $sql = 'UPDATE {'.$dest.'} dest
              JOIN {user} usr
                   '.$joins.'
               SET dest.data = src.data
             WHERE dest.fieldid = ?
               AND dest.contextid = ctx.id';
    $DB->execute($sql, array($field->id));
}

// Form functions

function moodle_profile_field_edit_form_definition($form) {
    $level = required_param('level', PARAM_ACTION);
    if ($level != 'user') {
        return;
    }

    $form->addElement('header', '', get_string('field_moodlesync', 'elisfields_moodle_profile'));

    $choices = array(
        -1 => get_string('field_no_sync', 'elisfields_moodle_profile'),
        pm_moodle_profile::sync_to_moodle => get_string('field_sync_to_moodle', 'elisfields_moodle_profile'),
        pm_moodle_profile::sync_from_moodle => get_string('field_sync_from_moodle', 'elisfields_moodle_profile')
        );
    $form->addElement('select', 'moodle_profile_exclusive', get_string('field_syncwithmoodle', 'elisfields_moodle_profile'), $choices);
    $form->setType('moodle_profile_exclusive', PARAM_INT);
}

function moodle_profile_field_get_form_data($form, $field) {
    $level = required_param('level', PARAM_ACTION);
    if ($level != 'user') {
        return array();
    }

    if (!isset($field->owners['moodle_profile'])) {
        return array('moodle_profile_exclusive' => -1);
    } else {
        return array('moodle_profile_exclusive' => ($field->owners['moodle_profile']->exclude ? 1 : 0));
    }
}

function moodle_profile_field_save_form_data($form, $field, $data) {
    global $DB;

    $level = required_param('level', PARAM_ACTION);
    if ($level != 'user') {
        return;
    }

    if ($data->moodle_profile_exclusive == pm_moodle_profile::sync_to_moodle
        || $data->moodle_profile_exclusive == pm_moodle_profile::sync_from_moodle) {
        if (isset($field->owners['moodle_profile'])) {
            $owner = new field_owner($field->owners['moodle_profile']);
            $owner->exclude = $data->moodle_profile_exclusive;
        } else {
            $owner = new field_owner();
            $owner->fieldid = $field->id;
            $owner->plugin = 'moodle_profile';
            $owner->exclude = $data->moodle_profile_exclusive;
        }
        $owner->save();

        unset($field->owners); // force reload of owners field
        sync_profile_field_with_moodle($field);
    } else {
        $DB->delete_records(field_owner::TABLE, array('fieldid'=>$field->id, 'plugin'=>'moodle_profile'));
    }
}
