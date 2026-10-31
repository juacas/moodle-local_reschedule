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
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
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
        $this->assertTrue(manager::cm_has_subactivities($course->id, $quest->cmid));
        $this->assertFalse(manager::cm_has_subactivities($course->id, $page->cmid));
    }

    public function test_challenge_dates_are_saved_through_tournament_manager(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $start = time() + DAYSECS;
        $quest = $this->getDataGenerator()->create_module('quest', [
            'course' => $course->id,
            'datestart' => $start,
            'dateend' => $start + (10 * DAYSECS),
        ]);
        $updates = [];
        $submissionids = [];
        for ($index = 1; $index <= 2; $index++) {
            $submissionid = $DB->insert_record('quest_submissions', (object)[
                'questid' => $quest->id,
                'userid' => $USER->id,
                'title' => 'Challenge ' . $index,
                'description' => 'Challenge description',
                'descriptionformat' => FORMAT_HTML,
                'descriptiontrust' => 0,
                'datestart' => $start + ($index * DAYSECS),
                'dateend' => $start + (($index + 1) * DAYSECS),
            ]);
            $submissionids[] = $submissionid;
            $updates[] = [
                'id' => 'sub_quest_submissions_' . $submissionid,
                'datestart' => $start + ($index * DAYSECS) + HOURSECS,
                'dateend' => $start + (($index + 1) * DAYSECS) + HOURSECS,
                'startenabled' => true,
                'endenabled' => true,
            ];
        }

        $loaded = quest_adapter::get_challenges([(int)$quest->id]);
        $this->assertArrayHasKey($submissionids[0], $loaded);
        $this->assertSame((int)$quest->id, $loaded[$submissionids[0]]->parentid);

        $sink = $this->redirectEvents();
        $result = manager::save_schedule((int)$course->id, $updates);

        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame(2, $result['updated']);
        foreach ($updates as $index => $update) {
            $stored = $DB->get_record('quest_submissions', ['id' => $submissionids[$index]], '*', MUST_EXIST);
            $this->assertSame($update['datestart'], (int)$stored->datestart);
            $this->assertSame($update['dateend'], (int)$stored->dateend);
        }
        $challengeevents = array_filter($sink->get_events(), static fn($event): bool =>
            $event instanceof \mod_quest\event\challenge_updated);
        $this->assertCount(2, $challengeevents);
    }

    public function test_challenge_start_cannot_move_after_first_submitted_answer(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $start = time() + DAYSECS;
        $quest = $this->getDataGenerator()->create_module('quest', [
            'course' => $course->id,
            'datestart' => $start,
            'dateend' => $start + (10 * DAYSECS),
        ]);
        $answerdate = $start + HOURSECS;
        $lastanswerdate = $start + (2 * HOURSECS);
        $submissionid = $DB->insert_record('quest_submissions', (object)[
            'questid' => $quest->id,
            'userid' => $USER->id,
            'title' => 'Answered challenge',
            'description' => 'Challenge description',
            'descriptionformat' => FORMAT_HTML,
            'descriptiontrust' => 0,
            'datestart' => $start,
            'dateend' => $start + (2 * DAYSECS),
        ]);
        $DB->insert_record('quest_answers', (object)[
            'questid' => $quest->id,
            'submissionid' => $submissionid,
            'userid' => $USER->id,
            'title' => 'Answer',
            'description' => 'Submitted answer',
            'date' => $answerdate,
            'pointsmax' => 1,
            'grade' => 0,
            'phase' => 0,
            'commentforteacher' => '',
        ]);
        $strictersubmissionid = $DB->insert_record('quest_submissions', (object)[
            'questid' => $quest->id,
            'userid' => $USER->id,
            'title' => 'Stricter challenge',
            'description' => 'Challenge with tighter answer bounds',
            'descriptionformat' => FORMAT_HTML,
            'descriptiontrust' => 0,
            'datestart' => $start,
            'dateend' => $start + (3 * DAYSECS),
        ]);
        foreach ([$start + intdiv(HOURSECS, 2), $start + (4 * HOURSECS)] as $date) {
            $DB->insert_record('quest_answers', (object)[
                'questid' => $quest->id,
                'submissionid' => $strictersubmissionid,
                'userid' => $USER->id,
                'title' => 'Answer',
                'description' => 'Submitted answer',
                'date' => (int)$date,
                'pointsmax' => 1,
                'grade' => 0,
                'phase' => 0,
                'commentforteacher' => '',
            ]);
        }
        $DB->insert_record('quest_answers', (object)[
            'questid' => $quest->id,
            'submissionid' => $submissionid,
            'userid' => $USER->id,
            'title' => 'Later answer',
            'description' => 'Later submitted answer',
            'date' => $lastanswerdate,
            'pointsmax' => 1,
            'grade' => 0,
            'phase' => 0,
            'commentforteacher' => '',
        ]);

        $loaded = quest_adapter::get_challenges([(int)$quest->id]);
        $this->assertSame($answerdate, $loaded[$submissionid]->firstanswerdate);
        $this->assertSame($lastanswerdate, $loaded[$submissionid]->lastanswerdate);
        $this->assertNotEmpty($loaded[$submissionid]->answerstartvalidationmessage);
        $this->assertNotEmpty($loaded[$submissionid]->answerendvalidationmessage);
        $items = array_column(manager::get_course_items((int)$course->id), null, 'id');
        $challengekey = 'sub_quest_submissions_' . $submissionid;
        $this->assertCount(2, $items[$challengekey]['absolutelimits']);
        $this->assertSame(['start_max', 'end_min'], array_column($items[$challengekey]['absolutelimits'], 'type'));
        $this->assertSame([$answerdate, $lastanswerdate],
            array_column($items[$challengekey]['absolutelimits'], 'date'));
        $parentlimits = $items['main_quest_' . $quest->id]['absolutelimits'];
        $this->assertCount(2, $parentlimits);
        $this->assertSame(['start_max', 'end_min'], array_column($parentlimits, 'type'));
        $this->assertSame([$start + intdiv(HOURSECS, 2), $start + (4 * HOURSECS)],
            array_column($parentlimits, 'date'));

        $result = manager::save_schedule((int)$course->id, [[
            'id' => 'sub_quest_submissions_' . $submissionid,
            'datestart' => $answerdate + 1,
            'dateend' => $start + (3 * DAYSECS),
            'startenabled' => true,
            'endenabled' => true,
        ]]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString($loaded[$submissionid]->answerstartvalidationmessage, $result['message']);
        $this->assertSame('sub_quest_submissions_' . $submissionid, $result['itemerrors'][0]['id']);

        $result = manager::save_schedule((int)$course->id, [[
            'id' => $challengekey,
            'datestart' => $start,
            'dateend' => $lastanswerdate - 1,
            'startenabled' => true,
            'endenabled' => true,
        ]]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString($loaded[$submissionid]->answerendvalidationmessage, $result['message']);

        $stored = $DB->get_record('quest_submissions', ['id' => $submissionid], '*', MUST_EXIST);
        $this->assertSame($start, (int)$stored->datestart);
    }
}
