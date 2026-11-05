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
 * Tests for the cross-course student workload report.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class global_report_test extends \advanced_testcase {
    /**
     * Courses are associated through the active students of the source course.
     */
    public function test_course_weights_follow_shared_active_enrolments(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $current = $generator->create_course(['fullname' => 'Current course']);
        $shared = $generator->create_course(['fullname' => 'Shared course']);
        $unrelated = $generator->create_course(['fullname' => 'Unrelated course']);
        $firststudent = $generator->create_and_enrol($current, 'student');
        $secondstudent = $generator->create_and_enrol($current, 'student');
        $generator->enrol_user($firststudent->id, $shared->id, 'student');
        $generator->create_and_enrol($unrelated, 'student');
        $start = 1700000000;
        $generator->create_module('choice', [
            'course' => $current->id,
            'name' => 'Current dated activity',
            'timeopen' => $start,
            'timeclose' => $start + WEEKSECS,
        ]);
        $generator->create_module('choice', [
            'course' => $shared->id,
            'name' => 'Shared dated activity',
            'timeopen' => $start + WEEKSECS,
            'timeclose' => $start + (2 * WEEKSECS),
        ]);
        $PAGE->set_context(\context_course::instance($current->id));

        $report = global_report::get_data($current);
        $courses = array_column($report['courses'], null, 'id');
        $students = array_column($report['students'], null, 'id');
        $currentitems = manager::get_course_items((int)$current->id);
        $currenttimeframe = manager::get_course_timeframe($current, $currentitems);

        $this->assertSame(2, $report['studentcount']);
        $this->assertArrayHasKey($current->id, $courses);
        $this->assertArrayHasKey($shared->id, $courses);
        $this->assertArrayNotHasKey($unrelated->id, $courses);
        $this->assertSame(2, $courses[$current->id]['studentcount']);
        $this->assertSame(1, $courses[$shared->id]['studentcount']);
        $this->assertSame(1, $courses[$current->id]['activitycount']);
        $this->assertSame(1, $courses[$shared->id]['activitycount']);
        $this->assertSame($currenttimeframe['start'], $report['rangestart']);
        $this->assertSame($currenttimeframe['end'], $report['rangeend']);
        $this->assertCount(1, $courses[$current->id]['items']);
        $this->assertCount(1, $courses[$shared->id]['items']);
        $this->assertEquals(1.0, $courses[$current->id]['items'][0]['effort']);
        $this->assertTrue($courses[$current->id]['items'][0]['reportefforteditable']);
        $this->assertFalse($courses[$shared->id]['items'][0]['reportefforteditable']);
        $this->assertNotSame(
            $courses[$current->id]['items'][0]['reportsourceid'],
            $courses[$current->id]['items'][0]['id']
        );
        $this->assertContains((int)$shared->id, $students[$firststudent->id]['courseids']);
        $this->assertNotContains((int)$shared->id, $students[$secondstudent->id]['courseids']);
    }
}
