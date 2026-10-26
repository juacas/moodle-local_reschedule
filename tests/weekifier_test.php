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
    /**
     * A dated child supplies the week when its parent has no start date.
     */
    public function test_missing_start_uses_earliest_dated_subactivity(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'weeks',
            'numsections' => 3,
            'startdate' => time(),
        ], ['createsections' => true]);
        $assignment = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'section' => 2]);
        $weekthree = \course_get_format($course)->get_section_dates(3);
        $DB->update_record('assign', (object)[
            'id' => $assignment->id,
            'allowsubmissionsfromdate' => 0,
            'duedate' => 0,
            'cutoffdate' => $weekthree->start + HOURSECS,
        ]);

        $plan = weekifier::plan($course, [$assignment->cmid]);
        $this->assertCount(1, $plan['moves'], json_encode($plan));
        $targetsectionid = (int)\get_fast_modinfo($course)->get_section_info(3)->id;
        $this->assertSame($targetsectionid,
            $plan['moves'][0]['targetsectionid']);
        $result = weekifier::apply($course, [$assignment->cmid]);
        $this->assertSame(1, $result['moved']);
        $this->assertSame($targetsectionid,
            (int)$DB->get_field('course_modules', 'section', ['id' => $assignment->cmid]));
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
        $items = manager::get_course_items((int)$course->id);
        $firstrow = array_values(array_filter($items, static function(array $item) use ($first): bool {
            return (int)$item['cmid'] === (int)$first->cmid && empty($item['issubtype']);
        }));
        $this->assertCount(1, $firstrow);
        $this->assertSame($targetid, (int)$firstrow[0]['sectionid']);
        $this->assertSame(3, (int)$firstrow[0]['sectionnum']);
        $this->assertSame(1, weekifier::plan($course, [$first->cmid])['skipped']['already']);
    }

    /**
     * The course Announcements forum stays in place while ordinary forums move.
     */
    public function test_course_announcements_forum_is_excluded(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'weeks',
            'numsections' => 2,
            'newsitems' => 5,
            'startdate' => time(),
        ], ['createsections' => true]);
        $newsforum = $DB->get_record('forum', ['course' => $course->id, 'type' => 'news'], '*', MUST_EXIST);
        $newscm = \get_fast_modinfo($course)->instances['forum'][$newsforum->id];
        $newssection = (int)$DB->get_field('course_modules', 'section', ['id' => $newscm->id]);
        $ordinary = $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'section' => 2,
            'name' => 'Ordinary forum',
        ]);

        $plan = weekifier::plan($course, [$newscm->id, $ordinary->cmid]);
        $this->assertCount(1, $plan['moves'], json_encode($plan));
        $this->assertSame((int)$ordinary->cmid, $plan['moves'][0]['cmid']);
        $this->assertSame(1, $plan['skipped']['unsupported']);

        $result = weekifier::apply($course, [$newscm->id, $ordinary->cmid]);
        $this->assertSame(1, $result['moved']);
        $this->assertSame($newssection,
            (int)$DB->get_field('course_modules', 'section', ['id' => $newscm->id]));
        $this->assertSame((int)\get_fast_modinfo($course)->get_section_info(1)->id,
            (int)$DB->get_field('course_modules', 'section', ['id' => $ordinary->cmid]));
    }

    /**
     * Weekify does not run on a non-weekly course.
     */
    public function test_other_formats_are_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['format' => 'topics']);
        $this->expectException(\moodle_exception::class);
        weekifier::plan($course, []);
    }

    /**
     * An undated activity moves to week one; an out-of-range start is skipped.
     */
    public function test_undated_moves_to_first_week_and_out_of_range_is_skipped(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'weeks',
            'numsections' => 2,
            'startdate' => time(),
        ], ['createsections' => true]);
        $undated = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'section' => 2]);
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
        $this->assertCount(1, $plan['moves']);
        $this->assertSame($undated->cmid, $plan['moves'][0]['cmid']);
        $this->assertSame((int)\get_fast_modinfo($course)->get_section_info(1)->id,
            $plan['moves'][0]['targetsectionid']);
        $this->assertSame(0, $plan['skipped']['undated']);
        $this->assertSame(1, $plan['skipped']['outofrange']);
    }


}
