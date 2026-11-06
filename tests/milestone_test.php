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

use advanced_testcase;

/**
 * Additional activity dates are independent point milestones.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(manager::class)]
final class milestone_test extends advanced_testcase {
    /**
     * Assignment due dates are milestones outside the opening-to-cutoff interval.
     */
    public function test_assignment_extra_dates_are_discovered_and_saved(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $start = time() + DAYSECS;
        $due = $start + DAYSECS;
        $cutoff = $due + DAYSECS;
        $grading = $cutoff + DAYSECS;
        $DB->update_record('assign', (object)[
            'id' => $assign->id,
            'allowsubmissionsfromdate' => $start,
            'duedate' => $due,
            'cutoffdate' => $cutoff,
            'gradingduedate' => $grading,
        ]);

        $items = array_column(manager::get_course_items($course->id), null, 'id');
        $parentid = 'main_assign_' . $assign->id;
        $dueid = 'milestone_assign_' . $assign->id . '_duedate';
        $gradingid = 'milestone_assign_' . $assign->id . '_gradingduedate';
        $this->assertArrayHasKey($parentid, $items);
        $this->assertArrayHasKey($dueid, $items);
        $this->assertArrayHasKey($gradingid, $items);
        $this->assertTrue($items[$parentid]['haschildren']);
        $this->assertSame('allowsubmissionsfromdate', $items[$parentid]['startcol']);
        $this->assertSame('cutoffdate', $items[$parentid]['endcol']);
        $this->assertSame($parentid, $items[$dueid]['parentkey']);
        $this->assertTrue($items[$dueid]['ismilestone']);
        $this->assertSame($due, $items[$dueid]['datestart']);
        $this->assertSame($due, $items[$dueid]['dateend']);

        $result = manager::save_schedule($course->id, [
            ['id' => $dueid, 'datestart' => $due + HOURSECS,
                'dateend' => $due + HOURSECS, 'startenabled' => true, 'endenabled' => true],
            ['id' => $gradingid, 'datestart' => $grading + HOURSECS,
                'dateend' => $grading + HOURSECS, 'startenabled' => true, 'endenabled' => true],
        ]);
        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame(2, $result['updated']);
        $stored = $DB->get_record('assign', ['id' => $assign->id], '*', MUST_EXIST);
        $this->assertEquals($cutoff, $stored->cutoffdate);
        $this->assertEquals($grading + HOURSECS, $stored->gradingduedate);
        $this->assertEquals($start, $stored->allowsubmissionsfromdate);
        $this->assertEquals($due + HOURSECS, $stored->duedate);

        // Update the due milestone and interval end together to keep Moodle's
        // required ordering (opening <= due <= cutoff).
        $newdue = $due - (2 * HOURSECS);
        $newcutoff = $due - HOURSECS;
        $combined = manager::save_schedule($course->id, [
            ['id' => $dueid, 'datestart' => $newdue, 'dateend' => $newdue],
            ['id' => $parentid, 'datestart' => $start, 'dateend' => $newcutoff],
        ]);
        $this->assertTrue($combined['success'], $combined['message']);
        $this->assertEquals($newdue, $DB->get_field('assign', 'duedate', ['id' => $assign->id]));
        $this->assertEquals($newcutoff, $DB->get_field('assign', 'cutoffdate', ['id' => $assign->id]));

        $invalid = manager::save_schedule($course->id, [
            ['id' => $parentid, 'datestart' => -1, 'dateend' => -1],
        ]);
        $this->assertFalse($invalid['success']);
        $this->assertSame($parentid, $invalid['itemerrors'][0]['id']);
        $this->assertEquals($newcutoff, $DB->get_field('assign', 'cutoffdate', ['id' => $assign->id]));

        $disable = manager::save_schedule($course->id, [
            ['id' => $gradingid, 'datestart' => 0, 'dateend' => 0,
                'startenabled' => false, 'endenabled' => false],
        ]);
        $this->assertTrue($disable['success'], $disable['message']);
        $this->assertEquals(0, $DB->get_field('assign', 'gradingduedate', ['id' => $assign->id]));
    }

    /**
     * Activities without a configured interval still expose their editdates fields.
     */
    public function test_forum_dates_have_a_parent_row(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $due = time() + DAYSECS;
        $DB->set_field('forum', 'duedate', $due, ['id' => $forum->id]);

        $items = array_column(manager::get_course_items($course->id), null, 'id');
        $parentid = 'main_forum_' . $forum->id;
        $milestoneid = 'milestone_forum_' . $forum->id . '_duedate';
        $cutoffid = 'milestone_forum_' . $forum->id . '_cutoffdate';
        $this->assertArrayHasKey($parentid, $items);
        $this->assertArrayHasKey($milestoneid, $items);
        $this->assertArrayNotHasKey($cutoffid, $items);
        $this->assertSame('', $items[$parentid]['startcol']);
        $this->assertSame('cutoffdate', $items[$parentid]['endcol']);
        $this->assertTrue($items[$parentid]['isopenstart']);
        $this->assertFalse($items[$parentid]['startenabled']);
        $this->assertFalse($items[$parentid]['endenabled']);
        $this->assertSame($parentid, $items[$milestoneid]['parentkey']);
        $this->assertSame($due, $items[$milestoneid]['datestart']);
        $this->assertTrue($items[$milestoneid]['ismilestone']);

        $result = manager::save_schedule($course->id, [
            ['id' => $milestoneid, 'datestart' => $due + HOURSECS,
                'dateend' => $due + HOURSECS],
        ]);
        $this->assertTrue($result['success'], $result['message']);
        $this->assertEquals($due + HOURSECS, $DB->get_field('forum', 'duedate', ['id' => $forum->id]));

        $cutoff = $due + (2 * HOURSECS);
        $result = manager::save_schedule($course->id, [
            ['id' => $parentid, 'dateend' => $cutoff, 'startenabled' => false, 'endenabled' => true],
        ]);
        $this->assertTrue($result['success'], $result['message']);
        $this->assertEquals($cutoff, $DB->get_field('forum', 'cutoffdate', ['id' => $forum->id]));
        $this->assertFalse($items[$parentid]['startenabled']);
        $invalidstart = manager::save_schedule($course->id, [
            ['id' => $parentid, 'datestart' => $due, 'startenabled' => true],
        ]);
        $this->assertFalse($invalidstart['success']);
        $this->assertSame($parentid, $invalidstart['itemerrors'][0]['id']);
        $invalid = manager::save_schedule($course->id, [
            ['id' => $milestoneid, 'datestart' => $cutoff + HOURSECS, 'dateend' => $cutoff + HOURSECS],
        ]);
        $this->assertFalse($invalid['success']);
        $this->assertSame($milestoneid, $invalid['itemerrors'][0]['id']);
        $this->assertEquals($due + HOURSECS, $DB->get_field('forum', 'duedate', ['id' => $forum->id]));
    }

    /**
     * Reading a schedule again must use activity dates currently in the database.
     */
    public function test_date_settings_refresh_between_schedule_reads(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $assignment = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $milestoneid = 'milestone_assign_' . $assignment->id . '_duedate';

        $before = array_column(manager::get_course_items($course->id), null, 'id');
        $this->assertArrayHasKey($milestoneid, $before);
        $this->assertFalse($before[$milestoneid]['startenabled']);

        $cutoff = time() + (3 * DAYSECS);
        $DB->set_field('assign', 'duedate', $cutoff, ['id' => $assignment->id]);
        // Direct database writes bypass Moodle's module observers, so mirror
        // the invalidation performed by supported activity update APIs.
        course_items_cache::invalidate((int)$course->id);
        $after = array_column(manager::get_course_items($course->id), null, 'id');
        $this->assertTrue($after[$milestoneid]['startenabled']);
        $this->assertSame($cutoff, $after[$milestoneid]['datestart']);
    }
}
