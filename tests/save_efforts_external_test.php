<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_reschedule;

/**
 * Tests effort update permissions.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class save_efforts_external_test extends \advanced_testcase {
    /**
     * An effort update requires manage-activities permission on its module.
     */
    public function test_update_checks_module_context_permission(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $choice = $generator->create_module('choice', [
            'course' => $course->id,
            'timeopen' => 1700000000,
            'timeclose' => 1700000000 + DAYSECS,
        ]);
        $modulecontext = \context_module::instance((int)$choice->cmid);
        $roleid = (int)$DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/course:manageactivities', CAP_PROHIBIT, $roleid, $modulecontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        \local_reschedule\external\save_efforts::execute(
            (int)$course->id,
            true,
            [['id' => 'main_choice_' . $choice->id, 'hours' => 2.0]]
        );
    }
}
