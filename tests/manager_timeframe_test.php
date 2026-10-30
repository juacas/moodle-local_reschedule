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
 * Tests for course timeline boundaries.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager_timeframe_test extends advanced_testcase {
    /**
     * A missing course end uses the latest enabled date from schedule rows.
     */
    public function test_missing_end_uses_latest_enabled_item_date(): void {
        $start = 1700000000;
        $course = (object)[
            'startdate' => $start,
            'enddate' => 0,
        ];
        $items = [
            [
                'startenabled' => true,
                'datestart' => $start + DAYSECS,
                'endenabled' => true,
                'dateend' => $start + (5 * DAYSECS),
            ],
            [
                'startenabled' => true,
                'datestart' => $start + (10 * DAYSECS),
                'endenabled' => false,
                'dateend' => $start + (500 * DAYSECS),
            ],
        ];

        $timeframe = manager::get_course_timeframe($course, $items);

        $this->assertSame($start, $timeframe['start']);
        $this->assertSame($start + (10 * DAYSECS), $timeframe['end']);
    }

    /**
     * A configured valid end remains authoritative.
     */
    public function test_configured_end_is_not_extended_to_item_dates(): void {
        $start = 1700000000;
        $courseend = $start + (30 * DAYSECS);
        $course = (object)[
            'startdate' => $start,
            'enddate' => $courseend,
        ];
        $items = [[
            'startenabled' => true,
            'datestart' => $start + (60 * DAYSECS),
            'endenabled' => true,
            'dateend' => $start + (70 * DAYSECS),
        ]];

        $timeframe = manager::get_course_timeframe($course, $items);

        $this->assertSame($courseend, $timeframe['end']);
    }

    /**
     * Completely undated courses retain the safe default visible range.
     */
    public function test_undated_course_uses_fallback_duration(): void {
        $start = 1700000000;
        $course = (object)[
            'startdate' => $start,
            'enddate' => 0,
        ];
        $items = [[
            'startenabled' => false,
            'datestart' => $start + (200 * DAYSECS),
            'endenabled' => false,
            'dateend' => $start + (300 * DAYSECS),
        ]];

        $timeframe = manager::get_course_timeframe($course, $items);

        $this->assertSame($start + (120 * DAYSECS), $timeframe['end']);
    }
}
