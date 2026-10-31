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

/**
 * Tests Reschedule audit events at their integration points.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class event_test extends \advanced_testcase {
    /**
     * Saving an activity schedule records the affected course modules.
     */
    public function test_schedule_change_is_logged(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $choice = $this->getDataGenerator()->create_module('choice', ['course' => $course->id]);
        $start = time() + DAYSECS;
        $end = $start + DAYSECS;
        $DB->set_field('choice', 'timeopen', $start, ['id' => $choice->id]);
        $DB->set_field('choice', 'timeclose', $end, ['id' => $choice->id]);
        rebuild_course_cache($course->id, true);

        $items = array_column(manager::get_course_items((int)$course->id), null, 'id');
        $item = $items['main_choice_' . $choice->id];
        $sink = $this->redirectEvents();
        $result = manager::save_schedule((int)$course->id, [[
            'id' => $item['id'],
            'datestart' => $start + HOURSECS,
            'dateend' => $end,
            'startenabled' => true,
            'endenabled' => true,
        ]]);

        $allevents = $sink->get_events();
        $events = array_values(array_filter($allevents, static fn($event): bool =>
            $event instanceof \local_reschedule\event\changes_saved));
        $this->assertTrue($result['success']);
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\local_reschedule\event\changes_saved::class, $allevents[0]);
        $this->assertSame('schedule', $events[0]->other['operation']);
        $this->assertSame(1, $events[0]->other['affectedcount']);
        $this->assertSame((string)$choice->cmid, $events[0]->other['affectedmoduleids']);
        $this->assertStringContainsString((string)$choice->name, $events[0]->other['affectedmodulesummary']);
        $modules = json_decode($events[0]->other['affectedmodules'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame((int)$choice->cmid, $modules[0]['cmid']);
        $this->assertSame((string)$choice->name, $modules[0]['name']);
        $this->assertSame(0, $events[0]->other['affectedsubactivitycount']);
    }

    /**
     * A child row is identified in the event emitted before its module update.
     */
    public function test_subactivity_change_is_logged_before_module_update(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $assignment = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $start = time() + DAYSECS;
        $due = $start + DAYSECS;
        $cutoff = $due + DAYSECS;
        $DB->update_record('assign', (object)[
            'id' => $assignment->id,
            'allowsubmissionsfromdate' => $start,
            'duedate' => $due,
            'cutoffdate' => $cutoff,
        ]);
        rebuild_course_cache($course->id, true);
        $milestoneid = 'milestone_assign_' . $assignment->id . '_cutoffdate';

        $sink = $this->redirectEvents();
        $result = manager::save_schedule((int)$course->id, [[
            'id' => $milestoneid,
            'datestart' => $cutoff + HOURSECS,
            'dateend' => $cutoff + HOURSECS,
            'startenabled' => true,
            'endenabled' => true,
        ]]);

        $events = $sink->get_events();
        $this->assertTrue($result['success'], $result['message']);
        $this->assertInstanceOf(\local_reschedule\event\changes_saved::class, $events[0]);
        $this->assertSame(1, $events[0]->other['affectedsubactivitycount']);
        $subactivities = json_decode($events[0]->other['affectedsubactivities'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($milestoneid, $subactivities[0]['id']);
        $this->assertSame((int)$assignment->cmid, $subactivities[0]['cmid']);
        $this->assertSame('assign', $subactivities[0]['table']);
        $this->assertSame((int)$assignment->id, $subactivities[0]['recordid']);
        $this->assertSame('main_assign_' . $assignment->id, $subactivities[0]['parentid']);
        $this->assertSame(1, $subactivities[0]['milestone']);
    }

    /**
     * Saving effort estimates does not create a Reschedule audit event.
     */
    public function test_effort_change_is_not_logged(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $choice = $this->getDataGenerator()->create_module('choice', ['course' => $course->id]);
        $id = 'main_choice_' . $choice->id;

        $sink = $this->redirectEvents();
        $result = \local_reschedule\external\save_efforts::execute(
            (int)$course->id,
            true,
            [['id' => $id, 'hours' => 2.5]],
            'ideal'
        );

        $events = array_values(array_filter($sink->get_events(), static fn($event): bool =>
            $event instanceof \local_reschedule\event\changes_saved));
        $this->assertTrue($result['success']);
        $this->assertCount(0, $events);
    }

    /**
     * Weekify records every course module it moves successfully.
     */
    public function test_weekify_move_is_logged(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course([
            'format' => 'weeks',
            'numsections' => 3,
            'startdate' => time(),
        ], ['createsections' => true]);
        $assignment = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 1,
        ]);
        $weekthree = \course_get_format($course)->get_section_dates(3);
        $DB->set_field('assign', 'allowsubmissionsfromdate', $weekthree->start, ['id' => $assignment->id]);
        $DB->set_field('assign', 'duedate', $weekthree->start + DAYSECS, ['id' => $assignment->id]);
        rebuild_course_cache($course->id, true);

        $sink = $this->redirectEvents();
        $result = weekifier::apply($course, [(int)$assignment->cmid]);

        $allevents = $sink->get_events();
        $events = array_values(array_filter($allevents, static fn($event): bool =>
            $event instanceof \local_reschedule\event\changes_saved));
        $this->assertSame(1, $result['moved']);
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\local_reschedule\event\changes_saved::class, $allevents[0]);
        $this->assertSame('weekify', $events[0]->other['operation']);
        $this->assertSame((string)$assignment->cmid, $events[0]->other['affectedmoduleids']);
        $this->assertSame(3, (int)$DB->get_field('course_sections', 'section', [
            'id' => $DB->get_field('course_modules', 'section', ['id' => $assignment->cmid]),
        ]));
    }
}
