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
use stdClass;
use local_reschedule\adapter\kuet_adapter;

/**
 * Unit tests for Kuet scheduled sessions adapter.
 *
 * @package    local_reschedule
 * @category   test
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(kuet_adapter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_reschedule\adapter_manager::class)]
final class kuet_adapter_test extends advanced_testcase {
    /**
     * Test adapter supports method for kuet and kuet_sessions.
     */
    public function test_supports(): void {
        $course = new stdClass();
        $course->id = 1;
        $adapter = new kuet_adapter($course);

        // Supports modname 'kuet'.
        $this->assertTrue($adapter->supports('kuet', ['table' => 'kuet']));
        // Supports table 'kuet'.
        $this->assertTrue($adapter->supports('unknown', ['table' => 'kuet']));
        // Supports subtable 'kuet_sessions'.
        $this->assertTrue($adapter->supports('kuet_sessions', ['table' => 'kuet_sessions']));
        $this->assertTrue($adapter->supports('unknown', ['table' => 'kuet_sessions']));

        // Does not support unrelated modules.
        $this->assertFalse($adapter->supports('quiz', ['table' => 'quiz']));
        $this->assertFalse($adapter->supports('assign', ['table' => 'assign']));
        $this->assertFalse($adapter->supports('workshop', ['table' => 'workshop']));
        $this->assertFalse($adapter->supports('quest', ['table' => 'quest']));

        $this->assertTrue($adapter->is_derived_item(['table' => 'kuet']));
        $this->assertFalse($adapter->is_derived_item(['table' => 'kuet_sessions']));
    }

    /**
     * Test resolution via adapter_manager.
     */
    public function test_adapter_manager_resolution(): void {
        $course = new stdClass();
        $course->id = 1;

        $adapter1 = adapter_manager::get_adapter('kuet', $course, ['table' => 'kuet']);
        $this->assertInstanceOf(kuet_adapter::class, $adapter1);

        $adapter2 = adapter_manager::get_adapter('kuet_sessions', $course, ['table' => 'kuet_sessions']);
        $this->assertInstanceOf(kuet_adapter::class, $adapter2);
    }

    /**
     * Test date range validation safeguards.
     */
    public function test_validate_dates(): void {
        $this->resetAfterTest(true);
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $adapter = new kuet_adapter($course);

        $now = time();

        // 1. Invalid / zero timestamps.
        $errs = $adapter->validate(['table' => 'kuet_sessions', 'recordid' => 9999], 0, $now + 3600);
        $this->assertNotEmpty($errs);

        $errs = $adapter->validate(['table' => 'kuet_sessions', 'recordid' => 9999], $now, 0);
        $this->assertNotEmpty($errs);

        // 2. End date before or equal to start date.
        $errs = $adapter->validate(['table' => 'kuet_sessions', 'recordid' => 9999], $now + 3600, $now);
        $this->assertNotEmpty($errs);

        $errs = $adapter->validate(['table' => 'kuet_sessions', 'recordid' => 9999], $now, $now);
        $this->assertNotEmpty($errs);

        // 3. Duration too short (< 60 seconds).
        $errs = $adapter->validate(['table' => 'kuet_sessions', 'recordid' => 9999], $now, $now + 30);
        $this->assertNotEmpty($errs);

        // 4. Non-existent session record.
        $errs = $adapter->validate(['table' => 'kuet_sessions', 'recordid' => 999999], $now, $now + 3600);
        $this->assertNotEmpty($errs);

        // 5. Valid session record.
        if ($DB->get_manager()->table_exists('kuet') && $DB->get_manager()->table_exists('kuet_sessions')) {
            $kuet = $this->getDataGenerator()->create_module('kuet', ['course' => $course->id]);

            $session = (object)[
                'name' => 'Session 1',
                'kuetid' => $kuet->id,
                'sessionmode' => 'podium_programmed',
                'automaticstart' => 0,
                'status' => 1,
                'startdate' => 0,
                'enddate' => 0,
                'sessiontime' => 0,
                'questiontime' => 10,
            ];
            $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');
            $sessionid = $generator->create_session($kuet, $session);

            $validerrs = $adapter->validate(
                [
                    'table' => 'kuet_sessions',
                    'recordid' => $sessionid,
                    'parentrecordid' => $kuet->id,
                    'title' => 'Session 1',
                ],
                $now + 1000,
                $now + 5000
            );
            $this->assertEmpty($validerrs);
        }
    }

    /**
     * Test saving a programmed kuet_sessions record updates its dates and activates it.
     */
    public function test_save_kuet_session(): void {
        $this->resetAfterTest(true);
        global $DB;

        if (!$DB->get_manager()->table_exists('kuet') || !$DB->get_manager()->table_exists('kuet_sessions')) {
            $this->markTestSkipped('mod_kuet tables not installed.');
        }

        $course = $this->getDataGenerator()->create_course();
        $adapter = new kuet_adapter($course);

        $kuet = $this->getDataGenerator()->create_module('kuet', ['course' => $course->id]);

        $session = (object)[
            'name' => 'Scheduled Session Alpha',
            'kuetid' => $kuet->id,
            'sessionmode' => 'podium_programmed',
            'automaticstart' => 0,
            'status' => 0, // Finished status previously.
            'startdate' => 0,
            'enddate' => 0,
            'sessiontime' => 0,
            'questiontime' => 10,
        ];
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kuet');
        $sessionid = $generator->create_session($kuet, $session);

        $newstart = time() + 7200;
        $newend = time() + 10800;

        $item = [
            'id' => 'sub_kuet_sessions_' . $sessionid,
            'table' => 'kuet_sessions',
            'recordid' => $sessionid,
            'title' => 'Scheduled Session Alpha',
        ];

        $adapter->save($item, $newstart, $newend);

        $updated = $DB->get_record('kuet_sessions', ['id' => $sessionid]);
        $this->assertNotNull($updated);
        $this->assertEquals($newstart, (int)$updated->startdate);
        $this->assertEquals($newend, (int)$updated->enddate);
        // Assert automaticstart flag set to 1.
        $this->assertEquals(1, (int)$updated->automaticstart);
        // The programmed mode remains unchanged.
        $this->assertEquals('podium_programmed', $updated->sessionmode);
        // Assert reactivated to active (1) because newstart is in the future.
        $this->assertEquals(1, (int)$updated->status);
    }

    /**
     * Parent KUET schedule is derived and is not persisted on the activity row.
     */
    public function test_save_kuet_parent(): void {
        $this->resetAfterTest(true);
        global $DB;

        if (!$DB->get_manager()->table_exists('kuet')) {
            $this->markTestSkipped('mod_kuet not installed.');
        }

        $course = $this->getDataGenerator()->create_course();
        $adapter = new kuet_adapter($course);

        $past = time() - 3600;
        $kuet = $this->getDataGenerator()->create_module('kuet', [
            'course' => $course->id,
            'timemodified' => $past,
        ]);
        $initialtimemodified = (int)$DB->get_field('kuet', 'timemodified', ['id' => $kuet->id]);

        $item = [
            'id' => 'main_kuet_' . $kuet->id,
            'table' => 'kuet',
            'recordid' => $kuet->id,
            'title' => 'Kuet Parent Activity',
        ];

        $adapter->save($item, time(), time() + 7200);

        $updated = $DB->get_record('kuet', ['id' => $kuet->id]);
        $this->assertSame($initialtimemodified, (int)$updated->timemodified);
    }

    /**
     * Test mapping rules include kuet and kuet_sessions.
     */
    public function test_mapping_rules_include_kuet(): void {
        $this->resetAfterTest(true);
        set_config('mapping', '', 'local_reschedule');

        $rules = manager::get_mapping_rules();
        $tables = array_column($rules, 'table');

        $this->assertContains('kuet', $tables);
        $this->assertContains('kuet_sessions', $tables);

        // Find kuet rule.
        $kuetrule = null;
        $sessionrule = null;
        foreach ($rules as $r) {
            if ($r['table'] === 'kuet') {
                $kuetrule = $r;
            } else if ($r['table'] === 'kuet_sessions') {
                $sessionrule = $r;
            }
        }

        $this->assertNotNull($kuetrule);
        $this->assertFalse($kuetrule['issubtype']);

        $this->assertNotNull($sessionrule);
        $this->assertTrue($sessionrule['issubtype']);
        $this->assertEquals('kuetid', $sessionrule['foreignkey']);
        $this->assertEquals('kuet', $sessionrule['parenttable']);
    }

    /**
     * Test that only programmed KUET sessions enter the reschedule view.
     */
    public function test_course_items_exclude_unprogrammed_sessions(): void {
        $this->resetAfterTest(true);
        global $DB;

        if (!$DB->get_manager()->table_exists('kuet') || !$DB->get_manager()->table_exists('kuet_sessions')) {
            $this->markTestSkipped('mod_kuet tables not installed.');
        }

        $course = $this->getDataGenerator()->create_course();
        $now = time() + 3600;
        $kuet = $this->getDataGenerator()->create_module('kuet', [
            'course' => $course->id,
            'name' => 'KUET scheduling filter',
        ]);
        $kuetid = (int)$kuet->id;

        foreach (['podium_programmed', 'podium_manual'] as $sessionmode) {
            $session = new stdClass();
            $session->name = $sessionmode;
            $session->kuetid = $kuetid;
            $session->sessionmode = $sessionmode;
            $session->automaticstart = 0;
            $session->status = 1;
            $session->startdate = $sessionmode === 'podium_programmed' ? $now : $now - DAYSECS;
            $session->enddate = $sessionmode === 'podium_programmed' ? $now + 3600 : $now + DAYSECS;
            $session->timecreated = time();
            $session->timemodified = time();
            $sessionid = $DB->insert_record('kuet_sessions', $session);

            if ($sessionmode === 'podium_programmed') {
                $programmedid = $sessionid;
            } else {
                $manualid = $sessionid;
            }
        }

        $items = manager::get_course_items($course->id);
        $sessionids = [];
        $parentitem = null;
        foreach ($items as $item) {
            if ($item['table'] === 'kuet_sessions') {
                $sessionids[] = (int)$item['recordid'];
            } else if ($item['table'] === 'kuet' && (int)$item['recordid'] === $kuetid) {
                $parentitem = $item;
            }
        }

        $this->assertContains($programmedid, $sessionids);
        $this->assertNotContains($manualid, $sessionids);
        $this->assertNotNull($parentitem);
        $this->assertSame($now, $parentitem['datestart']);
        $this->assertSame($now + 3600, $parentitem['dateend']);
    }
}
