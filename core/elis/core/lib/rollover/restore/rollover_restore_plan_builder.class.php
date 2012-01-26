<?php
/**
 * Class that specifies the tasks that belong to the rollover's restore plan
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

require_once(elis::lib('rollover/restore/rollover_restore_final_task.class.php'));

/**
 * Class responsible for compiling the tasks associated with the plan used in the
 * restore portion of the rollover
 */
abstract class rollover_restore_plan_builder {

    /**
     * Dispatches, based on type to specialised builders
     */
    static public function build_plan($controller) {
        $plan = $controller->get_plan();

        //task for initializing restore
        $plan->add_task(new restore_root_task('root_task'));

        //task for main restore work
        restore_plan_builder::build_course_plan($controller, $controller->get_courseid());

        //customized cleanup task
        $plan->add_task(new rollover_restore_final_task('final_task'));
    }
}