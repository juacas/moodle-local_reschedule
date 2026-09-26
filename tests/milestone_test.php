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
     * Assignment deadlines beyond the main interval are independent editable rows.
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
        $cutoffid = 'milestone_assign_' . $assign->id . '_cutoffdate';
        $gradingid = 'milestone_assign_' . $assign->id . '_gradingduedate';
        $this->assertArrayHasKey($parentid, $items);
        $this->assertArrayHasKey($cutoffid, $items);
        $this->assertArrayHasKey($gradingid, $items);
        $this->assertTrue($items[$parentid]['haschildren']);
        $this->assertSame($parentid, $items[$cutoffid]['parentkey']);
        $this->assertTrue($items[$cutoffid]['ismilestone']);
        $this->assertSame($cutoff, $items[$cutoffid]['datestart']);
        $this->assertSame($cutoff, $items[$cutoffid]['dateend']);

        $result = manager::save_schedule($course->id, [
            ['id' => $cutoffid, 'datestart' => $cutoff + HOURSECS,
                'dateend' => $cutoff + HOURSECS, 'startenabled' => true, 'endenabled' => true],
            ['id' => $gradingid, 'datestart' => $grading + HOURSECS,
                'dateend' => $grading + HOURSECS, 'startenabled' => true, 'endenabled' => true],
        ]);
        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame(2, $result['updated']);
        $stored = $DB->get_record('assign', ['id' => $assign->id], '*', MUST_EXIST);
        $this->assertEquals($cutoff + HOURSECS, $stored->cutoffdate);
        $this->assertEquals($grading + HOURSECS, $stored->gradingduedate);
        $this->assertEquals($start, $stored->allowsubmissionsfromdate);
        $this->assertEquals($due, $stored->duedate);

        // The cutoff would be invalid against the old due date, but is valid
        // alongside the new due date. Explicit edits win over automatic shifts.
        $newdue = $due - (2 * HOURSECS);
        $newcutoff = $due - HOURSECS;
        $combined = manager::save_schedule($course->id, [
            ['id' => $cutoffid, 'datestart' => $newcutoff, 'dateend' => $newcutoff],
            ['id' => $parentid, 'datestart' => $start, 'dateend' => $newdue],
        ]);
        $this->assertTrue($combined['success'], $combined['message']);
        $this->assertEquals($newdue, $DB->get_field('assign', 'duedate', ['id' => $assign->id]));
        $this->assertEquals($newcutoff, $DB->get_field('assign', 'cutoffdate', ['id' => $assign->id]));

        $invalid = manager::save_schedule($course->id, [
            ['id' => $cutoffid, 'datestart' => -1, 'dateend' => -1],
        ]);
        $this->assertFalse($invalid['success']);
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
        $this->assertArrayHasKey($parentid, $items);
        $this->assertArrayHasKey($milestoneid, $items);
        $this->assertSame($parentid, $items[$milestoneid]['parentkey']);
        $this->assertSame($due, $items[$milestoneid]['datestart']);

        $result = manager::save_schedule($course->id, [
            ['id' => $milestoneid, 'datestart' => $due + HOURSECS,
                'dateend' => $due + HOURSECS],
        ]);
        $this->assertTrue($result['success'], $result['message']);
        $this->assertEquals($due + HOURSECS, $DB->get_field('forum', 'duedate', ['id' => $forum->id]));
    }
}
