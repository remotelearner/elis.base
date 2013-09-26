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
 * @copyright  (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 */

/**
 * Validate parameter and make sure it is a positive number
 * @param int $value The value passed as an argument to the script.
 * @return bool True if the parameter is valid, otherwise false.
 */
function validate_parameter($value) {
    if (!is_numeric($value) || 0 >= (int) $value) {
        return false;
    }

    return true;
}

/**
 * Convert time in minutes and hours into number of seconds
 * @param object $period An object with 'minutes' and 'hours' as properties.
 * @return int|bool Returns the number of seconds the period represents. Or false if something went wrong.
 */
function convert_time_to_seconds($period) {
    if (!isset($period->minutes) || !isset($period->hours)) {
        return false;
    }

    $totalseconds = $period->minutes * 60;
    $totalseconds += $period->hours * 3600;

    return $totalseconds;
}
