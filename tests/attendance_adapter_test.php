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

use local_reschedule\adapter\attendance_adapter;

/**
 * Attendance adapter integration tests.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_reschedule\adapter\attendance_adapter
 */
final class attendance_adapter_test extends \advanced_testcase {
    /**
     * Check session loading, log limits and date updates through module APIs.
     *
     * @covers ::get_activity_records
     * @covers ::get_subitems
     * @covers ::get_subitem_attributes
     * @covers ::save
     */
    public function test_attendance_sessions_use_module_api(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/attendance/classes/structure.php');
        require_once($CFG->dirroot . '/mod/attendance/classes/attendance_webservices_handler.php');
        $course = $this->getDataGenerator()->create_course(['startdate' => time() - DAYSECS]);
        $attendance = $this->getDataGenerator()->create_module('attendance', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('attendance', $attendance->cmid, $course->id, false, MUST_EXIST);
        $api = new \mod_attendance_structure($attendance, $cm, $course);
        $start = (int)(floor((time() - HOURSECS) / MINSECS) * MINSECS);
        $session = (object)[
            'sessdate' => $start,
            'duration' => 2 * HOURSECS,
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'descriptionitemid' => 0,
            'timemodified' => time(),
            'statusset' => 0,
            'groupid' => 0,
            'absenteereport' => 1,
            'calendarevent' => 0,
        ];
        $sessionid = $api->add_session($session);

        $adapter = new attendance_adapter($course);
        $rule = ['editable' => true];
        $parents = $adapter->get_activity_records($rule);
        $this->assertArrayHasKey((int)$attendance->id, $parents);
        $this->assertSame($start, (int)$parents[(int)$attendance->id]->datestart);
        $children = $adapter->get_subitems($rule, [(int)$attendance->id]);
        $this->assertArrayHasKey($sessionid, $children);
        $this->assertSame($start + 2 * HOURSECS, (int)$children[$sessionid]->dateend);
        $this->assertSame(get_string('modulename', 'attendance'), $children[$sessionid]->title);
        $items = manager::get_course_items((int)$course->id);
        $itemsbyid = array_column($items, null, 'id');
        $parentkey = 'main_attendance_' . (int)$attendance->id;
        $childkey = 'sub_attendance_sessions_' . $sessionid;
        $this->assertArrayHasKey($parentkey, $itemsbyid);
        $this->assertArrayHasKey($childkey, $itemsbyid);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $statuses = $api->get_statuses();
        $status = reset($statuses);
        \attendance_handler::update_user_status(
            $sessionid,
            $student->id,
            2,
            (int)$status->id,
            (int)$status->setnumber
        );
        $children = $adapter->get_subitems($rule, [(int)$attendance->id]);
        $limits = $adapter->get_subitem_attributes($children[$sessionid], $rule)['absolutelimits'];
        $this->assertCount(2, $limits);
        $items = manager::get_course_items((int)$course->id);
        $itemsbyid = array_column($items, null, 'id');
        $this->assertCount(2, $itemsbyid[$parentkey]['absolutelimits']);
        $boundary = (int)$children[$sessionid]->lasttaken;
        $item = [
            'table' => 'attendance_sessions',
            'recordid' => $sessionid,
            'parentrecordid' => (int)$attendance->id,
            'title' => get_string('modulename', 'attendance'),
        ];
        $this->assertNotEmpty($adapter->validate($item, $boundary + 1, $start + 2 * HOURSECS));

        $newstart = $start - 30 * MINSECS;
        $newend = $start + 5 * HOURSECS;
        $item['cmid'] = (int)$cm->id;
        $adapter->save($item, $newstart, $newend);
        $saved = \attendance_handler::get_sessions((int)$attendance->id)[$sessionid];
        $this->assertSame($newstart, (int)$saved->sessdate);
        $this->assertSame($newend - $newstart, (int)$saved->duration);
    }
}
