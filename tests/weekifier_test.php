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

use advanced_testcase;

/**
 * Tests the weekly move plan and its execution.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(weekifier::class)]
final class weekifier_test extends advanced_testcase {
    /** A dated subactivity can supply the module start when the parent has none. */
    public function test_earliest_dated_subactivity_sets_the_week(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'weeks',
            'numsections' => 3,
            'startdate' => time(),
        ], ['createsections' => true]);
        $assignment = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'section' => 1]);
        $weekthree = \course_get_format($course)->get_section_dates(3);
        $DB->update_record('assign', (object)[
            'id' => $assignment->id,
            'allowsubmissionsfromdate' => 0,
            'duedate' => 0,
            'cutoffdate' => $weekthree->start + HOURSECS,
        ]);

        $plan = weekifier::plan($course, [$assignment->cmid]);
        $this->assertCount(1, $plan['moves'], json_encode($plan));
        $this->assertSame((int)\get_fast_modinfo($course)->get_section_info(3)->id,
            $plan['moves'][0]['targetsectionid']);
    }

    /**
     * An activity on a weekly boundary moves into that week, while the preview stays read-only.
     */
    public function test_preview_and_apply_move_only_selected_activity(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'weeks',
            'numsections' => 4,
            'startdate' => time(),
        ], ['createsections' => true]);
        $format = \course_get_format($course);
        $weekthree = $format->get_section_dates(3);
        $first = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 1,
        ]);
        $second = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 1,
        ]);
        foreach ([$first, $second] as $assignment) {
            $DB->update_record('assign', (object)[
                'id' => $assignment->id,
                'allowsubmissionsfromdate' => $weekthree->start,
                'duedate' => $weekthree->start + DAYSECS,
            ]);
        }

        $targetid = (int)\get_fast_modinfo($course)->get_section_info(3)->id;
        $originalsection = (int)$DB->get_field('course_modules', 'section', ['id' => $first->cmid]);
        $plan = weekifier::plan($course, [$first->cmid]);
        $this->assertCount(1, $plan['moves'], json_encode($plan));
        $this->assertSame($targetid, $plan['moves'][0]['targetsectionid']);
        $this->assertSame($originalsection,
            (int)$DB->get_field('course_modules', 'section', ['id' => $first->cmid]));

        $result = weekifier::apply($course, [$first->cmid]);
        $this->assertSame(1, $result['moved']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame($targetid, (int)$DB->get_field('course_modules', 'section', ['id' => $first->cmid]));
        $this->assertSame($originalsection,
            (int)$DB->get_field('course_modules', 'section', ['id' => $second->cmid]));
        $this->assertSame(1, weekifier::plan($course, [$first->cmid])['skipped']['already']);
    }

    /** Weekify does not run on a non-weekly course. */
    public function test_other_formats_are_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['format' => 'topics']);
        $this->expectException(\moodle_exception::class);
        weekifier::plan($course, []);
    }

    /** Activities without dates or an existing matching section stay where they are. */
    public function test_undated_and_out_of_range_activities_are_skipped(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'weeks',
            'numsections' => 2,
            'startdate' => time(),
        ], ['createsections' => true]);
        $undated = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'section' => 1]);
        $late = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'section' => 1]);
        $DB->update_record('assign', (object)[
            'id' => $undated->id,
            'allowsubmissionsfromdate' => 0,
            'duedate' => 0,
            'cutoffdate' => 0,
            'gradingduedate' => 0,
        ]);
        $lateweek = \course_get_format($course)->get_section_dates(4);
        $DB->update_record('assign', (object)[
            'id' => $late->id,
            'allowsubmissionsfromdate' => $lateweek->start,
            'duedate' => $lateweek->start + DAYSECS,
        ]);

        $plan = weekifier::plan($course, [$undated->cmid, $late->cmid]);
        $this->assertCount(0, $plan['moves']);
        $this->assertSame(1, $plan['skipped']['undated']);
        $this->assertSame(1, $plan['skipped']['outofrange']);
    }


}
