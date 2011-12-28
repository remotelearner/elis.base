<?php

/**
 * Common functions for writing test cases with PHPUnit
 *
 * @author Brent Boghosian <brent.boghosian@remote-learner.net>
 * @version 1.0
 * @package phpunittests
 *
 **/

/**
 * This function gets test user from DB user table
 * creates the test user if it doesn't already exist
 *
 * @param string $username  - optional username for test user
 *                            defaults to 'testuser'
 * @param string $email - optional email address for test user
 *                        defaults to 'testuser@remote-learner.net'
 * @return object user - the testuser object or false on error.
 *
 **/
function get_test_user($username = '', $email = '')
{
    global $DB;
    if ($username == '')
        $username = 'testuser';

    $testuser = $DB->get_record('user', array('username' => $username));
    if (empty($testuser)) {
        $testuser = new stdClass;
        $testuser->username = $username;
        $testuser->password = md5('Test1234!'); // arbitrary not used
        $testuser = create_user_record($testuser->username,
                                       $testuser->password);
        if (empty($testuser))
            return false;

        // setup fields to get thru: require_login()
        $testuser->firstname = "Test";
        $testuser->lastname = "User";
        if (empty($email)) {
            $email = "{$username}@remote-learner.net";
        }
        $testuser->email = $email;
        $testuser->confirmed = true;
        $testuser->deleted = false;
        $testuser->country = "CA";
        $testuser->city = "Waterloo";
    }
    $testuser->deleted = false;
    $DB->update_record('user', $testuser);
    return $testuser;
}

/**
 * This function deletes the test user. 
 *
 * @param string $username  - optional username of test user to delete
 *                            defaults to 'testuser'
 * @param boolean $remove  - optional flag to actually remove test user from DB
 *                           'user' table.
 * @return boolean - true on success, false on error.
 *
 **/
function delete_test_user($username = '', $remove = false)
{
    global $DB;
    if ($username == '')
        $username = 'testuser';

    $testuser = $DB->get_record('user', array('username' => $username));
    if (!empty($testuser)) {
        if ($remove) {
            return $DB->delete_records('user', array('id' => $testuser->id));
        } else {
            $testuser->deleted = true;
            return $DB->update_record('user', $testuser);
        }
    }
    return false;
}

