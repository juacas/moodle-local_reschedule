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

use core_external\external_api;
use local_reschedule\external\save_schedule;

/**
 * Tests for the schedule save external contract.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(save_schedule::class)]
final class save_schedule_external_test extends \advanced_testcase {
    /**
     * The core availability end operator must survive external validation.
     *
     * PARAM_TEXT treats the less-than operator as malformed HTML, even though
     * it is the value emitted by availability_date for an upper date bound.
     *
     */
    public function test_less_than_availability_direction_is_accepted(): void {
        $params = external_api::validate_parameters(save_schedule::execute_parameters(), [
            'courseid' => 6,
            'forceeditmode' => true,
            'scopecmid' => 878,
            'items' => [[
                'id' => 'main_attendance_52',
                'datestart' => 1793602800,
                'dateend' => 1793610000,
                'startenabled' => true,
                'endenabled' => true,
                'availabilityconditions' => [[
                    'id' => 'date-1',
                    'direction' => '<',
                    'time' => 1792623600,
                ]],
            ]],
        ]);

        $this->assertSame('<', $params['items'][0]['availabilityconditions'][0]['direction']);
    }
}
