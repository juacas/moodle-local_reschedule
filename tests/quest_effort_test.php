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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace local_reschedule;

use advanced_testcase;
use local_reschedule\adapter\quest_adapter;

/**
 * Challenge effort comes from the estimated answer time and perceived difficulty.
 *
 * @package    local_reschedule
 * @category   test
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(quest_adapter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(manager::class)]
final class quest_effort_test extends advanced_testcase {
    public function test_challenge_effort_uses_minutes_and_difficulty(): void {
        $this->resetAfterTest(true);
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $now = time();
        $quest = $this->getDataGenerator()->create_module('quest', [
            'course' => $course->id,
            'datestart' => $now,
            'dateend' => $now + 7 * DAYSECS,
        ]);
        $cases = [
            ['Easy', 120, 0, 1.4],
            ['Attainable', 90, 1, 1.5],
            ['Hard', 150, 2, 3.25],
            ['Difficulty unspecified', 60, -1, 1.0],
            ['No estimate', null, null, 1.0],
            ['Unselected duration', -1, -1, 1.0],
            ['Difficulty without duration', -1, 2, 1.0],
        ];
        $ids = [];
        foreach ($cases as [$title, $minutes, $difficulty]) {
            $id = $DB->insert_record('quest_submissions', (object)[
                'questid' => $quest->id,
                'userid' => 2,
                'title' => $title,
                'description' => $title,
                'descriptionformat' => FORMAT_HTML,
                'descriptiontrust' => 0,
                'datestart' => $now + DAYSECS,
                'dateend' => $now + 2 * DAYSECS,
                'predictedduration' => $minutes,
                'perceiveddifficulty' => $difficulty,
            ]);
            $ids[$title] = 'sub_quest_submissions_' . $id;
        }

        $items = array_column(manager::get_course_items($course->id), null, 'id');
        foreach ($cases as [$title, $minutes, , $expected]) {
            $this->assertArrayHasKey($ids[$title], $items);
            $this->assertEqualsWithDelta($expected, $items[$ids[$title]]['effort'], 0.0001);
            $this->assertSame($minutes === null || $minutes <= 0 ? 'default' : 'adapter',
                $items[$ids[$title]]['effortsource']);
        }
        $this->assertEqualsWithDelta(10.15, $items['main_quest_' . $quest->id]['effort'], 0.0001);
        $this->assertSame('children', $items['main_quest_' . $quest->id]['effortsource']);
    }
}
