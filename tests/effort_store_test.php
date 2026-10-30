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

/**
 * Tests durable effort storage and orphan handling.
 *
 * @copyright 2026 Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class effort_store_test extends \advanced_testcase {
    public function test_applies_current_rows_and_ignores_deleted_rows(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $items = $this->items(21, 101, 102);
        effort_store::write((int)$course->id, [
            ['locator' => effort_item_mapper::map($items)['sub_child_101'], 'hours' => 4.5],
            ['locator' => effort_item_mapper::map($items)['sub_child_102'], 'hours' => 7.0],
        ]);

        array_pop($items); // Simulate a child deleted or omitted during restore.
        effort_store::apply((int)$course->id, $items);

        $this->assertEquals(4.5, $items[1]['effort']);
        $this->assertSame('manual', $items[1]['effortsource']);
        $this->assertCount(2, $items);
    }

    public function test_save_rebuilds_document_from_current_rows(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $items = $this->items(31, 201, 202);
        effort_store::write((int)$course->id, [[
            'locator' => ['scope' => 'activity', 'cmid' => 99999],
            'hours' => 99,
        ]]);

        $saved = effort_store::save_updates((int)$course->id, $items, [
            ['id' => 'sub_child_201', 'hours' => 2.25],
        ]);
        $document = effort_store::read((int)$course->id);

        $this->assertSame(1, $saved);
        $this->assertCount(1, $document['entries']);
        $this->assertEquals(2.25, $document['entries'][0]['hours']);
        $this->assertSame(31, $document['entries'][0]['locator']['cmid']);
    }

    public function test_model_is_persisted_and_preserved_by_effort_updates(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $items = $this->items(51, 401, 402);

        $this->assertSame('real', effort_store::get_model((int)$course->id));
        effort_store::save_model((int)$course->id, 'ideal');
        effort_store::save_updates((int)$course->id, $items, [
            ['id' => 'sub_child_401', 'hours' => 3.5],
        ]);

        $document = effort_store::read((int)$course->id);
        $this->assertSame('ideal', $document['model']);
        $this->assertCount(1, $document['entries']);
    }

    public function test_portable_child_locator_does_not_contain_database_record_id(): void {
        $items = $this->items(41, 301, 302);
        $locator = effort_item_mapper::map($items)['sub_child_301'];
        $portable = effort_item_mapper::portable($locator);

        $this->assertArrayNotHasKey('recordid', $portable);
        $this->assertSame('subactivity', $portable['scope']);
        $this->assertArrayHasKey('occurrence', $portable);
    }

    /**
     * Build representative parent and child rows.
     *
     * @param int $cmid Course module id.
     * @param int $firstid First child id.
     * @param int $secondid Second child id.
     * @return array
     */
    private function items(int $cmid, int $firstid, int $secondid): array {
        return [
            ['id' => 'main_parent', 'cmid' => $cmid, 'recordid' => 10, 'table' => 'sample',
                'issubtype' => false, 'ismilestone' => false, 'effort' => 2, 'effortsource' => 'children'],
            ['id' => 'sub_child_' . $firstid, 'cmid' => $cmid, 'recordid' => $firstid, 'table' => 'sample_child',
                'startcol' => 'timeopen', 'endcol' => 'timeclose', 'title' => 'First', 'issubtype' => true,
                'ismilestone' => false, 'effort' => 1, 'effortsource' => 'default'],
            ['id' => 'sub_child_' . $secondid, 'cmid' => $cmid, 'recordid' => $secondid, 'table' => 'sample_child',
                'startcol' => 'timeopen', 'endcol' => 'timeclose', 'title' => 'Second', 'issubtype' => true,
                'ismilestone' => false, 'effort' => 1, 'effortsource' => 'default'],
        ];
    }
}
