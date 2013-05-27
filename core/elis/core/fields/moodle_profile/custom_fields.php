<?php
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
 * @package    elis
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
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

/**
 * get_invalid_sync_controls returns an array of invalid ELIS control settings given to Moodle field datatype
 *
 * @param  string $datatype the Moodle field datatype
 * @return array  the invalid ELIS field control settings for the given datatype
 */
function get_invalid_sync_controls($datatype) {
    static $allinvalidcontrols = array(
    // Moodle fields types NOT allowed to sync with ELIS field control types
    // format: array( moodlefielddatatype => array(elisfieldcontroltype [,...])
    // this should be enforced when editing ELIS custom user fields
        'checkbox' => array('datetime', 'menu', 'textarea', 'text', 'password'),
        'datetime' => array('checkbox', 'menu', 'textarea', 'text', 'password'),
        'menu'     => array('datetime', 'textarea', 'text', 'password'),
        'textarea' => array('datetime'),
        'text'     => array('datetime')
    );

    $result = array();
    if (!empty($allinvalidcontrols[$datatype])) {
        $result = $allinvalidcontrols[$datatype];
    }
    return $result;
}

/**
 * get_owner_sync_fields returns an array of Moodle field parameters mapped to ELIS field parameters for specified Moodle field datatype
 * owners fields to sync for specific datatype/controltype settings
 *
 * @param  string $datatype the Moodle field datatype, i.e. 'checkbox', 'datetime', 'menu', 'textarea', 'text'
 * @return array  the field settings to sync for the given datatype
 */
function get_owner_sync_fields($datatype) {
    // array format: array( moodlefieldtype => array( moodle_param => elis_owner_param [,...] )[,...] )
    static $allsyncfields = array(
        'datetime' => array('param1' => 'startyear', 'param2' => 'stopyear', 'param3' => 'inctime'),
        'menu'     => array('param1' => 'options'),
        // the following don't effect data only display
        //'textarea' => array('param1' => 'columns', 'param2' => 'rows'),
        //'text'     => array('param1' => 'columns', 'param2' => 'maxlength')
    );

    $result = array();
    if (!empty($allsyncfields[$datatype])) {
        $result = $allsyncfields[$datatype];
    }
    return $result;
}

/**
 * sync_profile_field_settings_to_moodle function synchronizes ELIS custom user field settings to corresponding Moodle field if possible.
 *
 * @param  object $field  the field object to sync
 * @return mixed          void or true (may throw DB exceptions)
 * @uses   $DB
 */
function sync_profile_field_settings_to_moodle($field) {
    global $DB;

    if (!isset($field->owners['moodle_profile'])
        || $field->owners['moodle_profile']->exclude == pm_moodle_profile::sync_from_moodle) {
        // not owned by the Moodle plugin, or set to sync from Moodle
        return true;
    }
    // check if sync is possible with current field settings
    if (!moodle_profile_can_sync($field->shortname)) {
        return true;
    }

    // Sync field settings first, since they could prevent field data sync
    $moodlefield = $DB->get_record('user_info_field', array('shortname' => $field->shortname));
    if (empty($moodlefield)) { // pre-caution
        return true;
    }
    // Check if we have settings to sync
    $ownersyncfields = get_owner_sync_fields($moodlefield->datatype);
    if (!empty($ownersyncfields)) {
        if (empty($field->owners) || !isset($field->owners['manual'])) {
            return true; // TBD
        }
        $fieldowner = new field_owner($field->owners['manual']);
        if (!empty($fieldowner)) {
            $sqlparts = array();
            $params = array();
            foreach ($ownersyncfields as $key => $val) {
                $sqlparts[] = "{$key} = ?";
                $optionsrc = '';
                if (!empty($fieldowner->param_options_source)) {
                    $optionsrc = $fieldowner->param_options_source;
                }
                if ($val == 'options' && !empty($optionsrc)) {
                    // special case where options from another source
                    $srcfile = elis::plugin_file('elisfields_manual','sources')."/{$optionsrc}.php";
                    $nooptions = true;
                    if (is_readable($srcfile)) {
                        require_once elis::plugin_file('elisfields_manual','sources.php');
                        require_once($srcfile);
                        $classname = "manual_options_{$optionsrc}";
                        $plugin = new $classname();
                        if (!empty($plugin) && ($options = $plugin->get_options(array())) && !empty($options)) {
                            $nooptions = false;
                            $params[] = implode("\n", $options);
                        }
                    }
                    if ($nooptions) {
                        array_pop($sqlparts);
                    }
                } else {
                    // temp fix for CRs in options
                    if ($val == 'options') {
                        $fieldowner->{'param_'.$val} = str_replace("\r", '', $fieldowner->{'param_'.$val});
                    }
                    $params[] = $fieldowner->{'param_'.$val};
                }
            }
            if (!empty($sqlparts)) {
                $select = implode(' AND ', array_merge(array('shortname = ?'), $sqlparts));
                if (!$DB->count_records_select('user_info_field', $select, array_merge(array($field->shortname), $params))) {
                    // settings not correct, must update
                    $sql = 'UPDATE {user_info_field} SET '.implode(', ', $sqlparts).' WHERE shortname = ?';
                    $DB->execute($sql, array_merge($params, array($field->shortname)));
                }
            }
        }
    }
}

/**
 * sync_profile_field_to_moodle function synchronizes ELIS custom user field to corresponding Moodle field if possible.
 * also syncs relevant field settings
 *
 * @param  object $field  the field object to sync
 * @return mixed          void or true (may throw DB exceptions)
 * @uses   $DB
 */
function sync_profile_field_to_moodle($field) {
    global $DB;

    if (!isset($field->owners['moodle_profile'])
        || $field->owners['moodle_profile']->exclude == pm_moodle_profile::sync_from_moodle) {
        // not owned by the Moodle plugin, or set to sync from Moodle
        return true;
    }
    // check if sync is possible with current field settings
    if (!moodle_profile_can_sync($field->shortname)) {
        return true;
    }

    // Sync field settings first, since they could prevent field data sync
    sync_profile_field_settings_to_moodle($field);

    $dest = 'user_info_data';
    $src = $field->data_table();
    $mfieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$field->shortname));

    $joins = 'JOIN {'.user::TABLE.'} cu ON usr.idnumber = cu.idnumber
              JOIN {context} ctx ON ctx.instanceid = cu.id AND ctx.contextlevel = '.CONTEXT_ELIS_USER.'
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

/**
 * sync_profile_field_settings_from_moodle function synchronizes ELIS custom user field settings from corresponding Moodle field if possible.
 *
 * @param  object $field  the field object to sync
 * @return mixed          void or true (may throw DB exceptions)
 * @uses   $DB
 */
function sync_profile_field_settings_from_moodle($field) {
    global $DB;

    if (!isset($field->owners['moodle_profile'])
        || $field->owners['moodle_profile']->exclude == pm_moodle_profile::sync_to_moodle) {
        // not owned by the Moodle plugin, or set to sync to Moodle
        return true;
    }
    // check if sync is possible with current field settings
    if (!moodle_profile_can_sync($field->shortname)) {
        return true;
    }

    // Sync field settings first, since they could prevent field data sync
    $moodlefield = $DB->get_record('user_info_field', array('shortname' => $field->shortname));
    if (empty($moodlefield)) { // pre-caution
        return true;
    }
    // Check if we have settings to sync
    $datatype = $moodlefield->datatype;
    $ownersyncfields = get_owner_sync_fields($datatype);
    if (!empty($ownersyncfields)) {
        if (empty($field->owners) || !isset($field->owners['manual'])) {
            return true; // TBD
        }
        $fieldowner = new field_owner($field->owners['manual']);
        if (!empty($fieldowner)) {
            $changes = false;
            if ($datatype == 'menu' && !empty($fieldowner->param_options_source)) {
                $fieldowner->param_options_source = '';
                $changes = true;
            }
            foreach ($ownersyncfields as $key => $val) {
                if ($fieldowner->{'param_'.$val} != $moodlefield->$key) {
                    $fieldowner->{'param_'.$val} = $moodlefield->$key;
                    $changes = true;
                }
            }
            if ($changes) {
                $fieldowner->save();
            }
        }
    }
}

/**
 * sync_profile_field_from_moodle function synchronizes ELIS custom user field from corresponding Moodle field if possible.
 * also syncs relevant field settings
 *
 * @param  object $field  the field object to sync
 * @return mixed          void or true (may throw DB exceptions)
 * @uses   $DB
 */
function sync_profile_field_from_moodle($field) {
    global $DB;

    if (!isset($field->owners['moodle_profile'])
        || $field->owners['moodle_profile']->exclude == pm_moodle_profile::sync_to_moodle) {
        // not owned by the Moodle plugin, or set to sync to Moodle
        return true;
    }
    // check if sync is possible with current field settings
    if (!moodle_profile_can_sync($field->shortname)) {
        return true;
    }

    // Sync field settings first, since they could prevent field data sync
    sync_profile_field_settings_from_moodle($field);

    $dest = $field->data_table();
    $src = 'user_info_data';
    $mfieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$field->shortname));

    $joins = 'JOIN {'.user::TABLE.'} cu ON usr.idnumber = cu.idnumber
              JOIN {context} ctx ON ctx.instanceid = cu.id AND ctx.contextlevel = '.CONTEXT_ELIS_USER.'
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

/**
 * Custom moodle profile field plugin definition_after_data function
 *
 * @param object $form        the custom field form object to embelish
 * @param string $level       the ELIS context level name: user, track, userset ...
 * @param string $shortname   the custom field shortname
 * @uses  $DB
 */
function moodle_profile_field_edit_form_definition_after_data($form, $level, $shortname) {
    global $DB;
    if ($level != 'user') {
        return;
    }

    $moodlefield = $DB->get_record('user_info_field', array('shortname' => $shortname));
    if (empty($moodlefield)) {
        return;
    }
    $fieldtypenosync = get_invalid_sync_controls($moodlefield->datatype);
    if (!empty($fieldtypenosync)) {
        foreach ($fieldtypenosync as $nosync) {
            $form->disabledIf('moodle_profile_exclusive', 'manual_field_control', 'eq', $nosync);
        }
    }
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

/**
 * moodle_profile_field_save_form_data - saves form specific sync settings for moodle profile fields
 *
 * @param object $form        the custom field form object submitted
 * @param object $field       the field object to save moodle_profile field_owner object for
 * @param object $data        the submitted form data to save
 * @uses  $DB
 */
function moodle_profile_field_save_form_data($form, $field, $data) {
    global $DB;

    $level = required_param('level', PARAM_ACTION);
    if ($level != 'user' || !isset($data->moodle_profile_exclusive)) {
        return; // not user context or sync was disabled
    }

    if ($data->moodle_profile_exclusive == pm_moodle_profile::sync_to_moodle ||
        $data->moodle_profile_exclusive == pm_moodle_profile::sync_from_moodle) {
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

/**
 * moodle_profile_can_sync function to determine if syncing custom field to/from Moodle is possible
 *
 * @param  string $shortname   the custom field shortname
 * @param  string $eliscontrol (optional) the custom field control type: checkbox, datetime, menu, ...
                               If not specified it's looked-up in DB
 * @return bool   true if the custom field can be synced, false otherwise
 * @uses   $DB
 */
function moodle_profile_can_sync($shortname, $eliscontrol = null) {
    global $DB;

    $moodlefield = $DB->get_record('user_info_field', array('shortname' => $shortname));
    if (empty($moodlefield)) {
        return false;
    }

    $fieldowner = null;
    if (empty($eliscontrol)) {
        // eliscontrol not specified so look it up ...
        $elisfield = field::get_for_context_level_with_name(CONTEXT_ELIS_USER, $shortname);
        if (empty($elisfield->id) || empty($elisfield->owners) || !isset($elisfield->owners['manual'])) {
            return false; // no elis field data found
        }
        $fieldowner = new field_owner($elisfield->owners['manual']);
        $eliscontrol = $fieldowner->param_control;
        if (empty($eliscontrol)) {
            return false; // invalid control type found
        }
    }

    $mdldatatype = $moodlefield->datatype;
    $fieldtypenosync = get_invalid_sync_controls($mdldatatype);
    if (!empty($fieldtypenosync) && is_array($fieldtypenosync) && in_array($eliscontrol, $fieldtypenosync)) {
        return false; // sync not permitted
    }

    // ELIS-8363: Check for multi-valued fields that can't sync to Moodle
    if (empty($elisfield)) {
        $elisfield = field::get_for_context_level_with_name(CONTEXT_ELIS_USER, $shortname);
    }
    if (!empty($elisfield) && !empty($elisfield->multivalued) && isset($elisfield->owners['moodle_profile']) &&
        $elisfield->owners['moodle_profile']->exclude == pm_moodle_profile::sync_to_moodle) {
        return false; // sync not permitted
    }

    // Handle special cases of ELIS checkbox using list
    if ($eliscontrol == 'checkbox') {
        if ($mdldatatype == 'checkbox') {
            if (empty($fieldowner)) {
                $elisfield = field::get_for_context_level_with_name(CONTEXT_ELIS_USER, $shortname);
                if (!empty($elisfield->owners) && isset($elisfield->owners['manual'])) {
                    $fieldowner = new field_owner($elisfield->owners['manual']);
                }
            }
            if (!empty($fieldowner) && (!empty($fieldowner->param_options) || !empty($fieldowner->param_options_source))) {
                return false;
            }
        } else {
            if (empty($fieldowner)) {
                $elisfield = field::get_for_context_level_with_name(CONTEXT_ELIS_USER, $shortname);
                if (!empty($elisfield->owners) && isset($elisfield->owners['manual'])) {
                    $fieldowner = new field_owner($elisfield->owners['manual']);
                }
            }
            if (!empty($fieldowner) && empty($fieldowner->param_options) && empty($fieldowner->param_options_source)) {
                return false;
            }
        }
    }

    return true; // sync is ok
}

