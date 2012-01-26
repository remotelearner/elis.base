<?php
/**
 * Factory for creating the rollover's backup plan
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

require_once(elis::lib('rollover/backup/rollover_backup_plan_builder.class.php'));

/**
 * Factory used to delegate the building of the rollover's backup plan to an appropriate
 * plan builder
 */
class rollover_backup_factory {

    /**
     * Dispatches the creation of the @backup_plan to the proper format builder
     *
     * @param object $controller The backup controller we are adding the plan to
     */
    static public function build_plan($controller) {
        rollover_backup_plan_builder::build_plan($controller);
    }
}