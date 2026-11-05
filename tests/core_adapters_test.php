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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_reschedule;

use local_reschedule\adapter\core\assign_adapter;
use local_reschedule\adapter\core\choice_adapter;
use local_reschedule\adapter\core\workshop_adapter;

/**
 * Tests for native date-field adapter migrations.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class core_adapters_test extends \advanced_testcase {
    /**
     * The local adapter owns date fields and semantic validation for migrated modules.
     */
    public function test_builtin_adapter_owns_choice_date_fields(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $choice = $this->getDataGenerator()->create_module('choice', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->instances['choice'][$choice->id];

        $adapter = adapter_manager::get_adapter('choice', $course, ['table' => 'choice', 'mappedrule' => true]);
        $this->assertInstanceOf(choice_adapter::class, $adapter);
        $fields = $adapter->get_date_fields($cm);
        $this->assertArrayHasKey('timeopen', $fields);
        $this->assertArrayHasKey('timeclose', $fields);
        $this->assertSame(['timeclose' => get_string('timeclose', 'local_reschedule')],
            $adapter->validate_date_fields($cm, ['timeopen' => 200, 'timeclose' => 100]));
    }

    /**
     * Assignment date validation uses the module's defined validation string.
     */
    public function test_assign_adapter_validates_due_date_against_open_date(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $assignment = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->instances['assign'][$assignment->id];
        $adapter = new assign_adapter($course);

        $this->assertSame(
            ['duedate' => get_string('duedateaftersubmissionvalidation', 'assign')],
            $adapter->validate_date_fields($cm, ['allowsubmissionsfromdate' => 100, 'duedate' => 100])
        );
    }

    /**
     * Workshop's range and phase dates resolve through its core adapter.
     */
    public function test_workshop_adapter_exposes_phase_dates(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $workshop = $this->getDataGenerator()->create_module('workshop', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->instances['workshop'][$workshop->id];
        $adapter = adapter_manager::get_adapter('workshop', $course, ['table' => 'workshop']);

        $this->assertInstanceOf(workshop_adapter::class, $adapter);
        $this->assertSame(
            ['submissionstart', 'submissionend', 'assessmentstart', 'assessmentend'],
            array_keys($adapter->get_date_fields($cm))
        );
    }
}
