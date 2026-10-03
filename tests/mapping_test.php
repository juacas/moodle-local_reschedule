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

namespace local_reschedule;

use advanced_testcase;

/** JSON mapping, owned adapters, and form-based field suggestions. */
final class mapping_test extends advanced_testcase {
    public function test_json_rules_cover_hierarchy_milestones_and_constraints(): void {
        $raw = json_encode([
            ['module' => 'custom', 'title' => 'name', 'label' => 'Custom',
                'kind' => 'range', 'start' => 'opensat', 'end' => 'closesat'],
            ['module' => 'custom_phase', 'title' => 'title', 'label' => 'Phase',
                'kind' => 'milestone', 'start' => 'deadline', 'parent' => 'custom',
                'fk' => 'customid', 'optional' => true, 'boundstart' => false,
                'availability' => 'off', 'effort' => ['model' => 'fixed', 'hours' => 2.5]],
        ]);
        $rules = mapping::parse($raw);
        $this->assertCount(2, $rules);
        $this->assertSame('custom', $rules[1]['parenttable']);
        $this->assertSame('customid', $rules[1]['foreignkey']);
        $this->assertSame('milestone', $rules[1]['kind']);
        $this->assertSame('deadline', $rules[1]['endcol']);
        $this->assertFalse($rules[1]['boundtoparentstart']);
        $this->assertTrue($rules[1]['optional']);
        $this->assertSame(['model' => 'fixed', 'value' => 2.5], $rules[1]['effortestimator']);
        $this->assertSame(2.5, mapping::estimate($rules[1]['effortestimator'], 100000, 100000 + 2 * DAYSECS));
        $this->assertSame([], mapping::parse('[{"module":"x","start":"a","end":"b","effort":{"model":"perday","hoursperday":-2}}]'));
        $this->assertSame([], mapping::parse('choice,name,Choice,timeopen,timeclose'));
        $this->assertSame([], mapping::parse('[{"module":"bad;drop","start":"x","end":"y"}]'));
    }

    public function test_owned_adapter_rules_are_not_duplicated_by_setting(): void {
        $this->resetAfterTest(true);
        set_config('mapping', json_encode([
            ['module' => 'assign', 'title' => 'name', 'label' => 'Duplicate',
                'kind' => 'range', 'start' => 'allowsubmissionsfromdate', 'end' => 'duedate'],
            ['module' => 'choice', 'title' => 'name', 'label' => 'Choice',
                'kind' => 'range', 'start' => 'timeopen', 'end' => 'timeclose'],
        ]), 'local_reschedule');
        $rules = manager::get_mapping_rules();
        $assign = array_values(array_filter($rules, static fn(array $rule): bool => $rule['table'] === 'assign'));
        $this->assertCount(1, $assign);
        $this->assertSame('Assignment', $assign[0]['label']);
        $this->assertCount(1, array_filter($rules, static fn(array $rule): bool => $rule['table'] === 'choice'));
    }

    public function test_form_discovery_suggests_real_choice_date_pair(): void {
        $modules = mapping_discovery::modules();
        $this->assertArrayHasKey('choice', $modules);
        $this->assertContains(['start' => 'timeopen', 'end' => 'timeclose'], $modules['choice']['pairs']);
        $this->assertContains(['name' => 'timeopen', 'dateonly' => false, 'origin' => 'mod_form'],
            $modules['choice']['fields']);
    }

    public function test_open_rule_updates_only_its_start_field(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        global $DB;

        set_config('mapping', json_encode([
            ['module' => 'choice', 'title' => 'name', 'label' => 'Choice',
                'kind' => 'open', 'start' => 'timeopen'],
        ]), 'local_reschedule');
        $course = $this->getDataGenerator()->create_course();
        $choice = $this->getDataGenerator()->create_module('choice', ['course' => $course->id]);
        $start = time() + DAYSECS;
        $DB->set_field('choice', 'timeopen', $start, ['id' => $choice->id]);
        $DB->set_field('choice', 'timeclose', $start + DAYSECS, ['id' => $choice->id]);
        rebuild_course_cache($course->id, true);

        $id = 'main_choice_' . $choice->id;
        $items = array_column(manager::get_course_items($course->id), null, 'id');
        $this->assertTrue($items[$id]['isopenended']);
        $this->assertSame('', $items[$id]['endcol']);
        $result = manager::save_schedule($course->id, [
            ['id' => $id, 'datestart' => $start + 3600, 'dateend' => $items[$id]['dateend']],
        ]);
        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame($start + 3600, (int)$DB->get_field('choice', 'timeopen', ['id' => $choice->id]));
        $this->assertSame($start + DAYSECS, (int)$DB->get_field('choice', 'timeclose', ['id' => $choice->id]));
    }

    public function test_proportional_effort_uses_actual_activity_duration(): void {
        $this->resetAfterTest(true);
        global $DB;

        set_config('mapping', json_encode([
            ['module' => 'choice', 'title' => 'name', 'label' => 'Choice',
                'kind' => 'range', 'start' => 'timeopen', 'end' => 'timeclose',
                'effort' => ['model' => 'perday', 'hoursperday' => 2]],
        ]), 'local_reschedule');
        $course = $this->getDataGenerator()->create_course();
        $choice = $this->getDataGenerator()->create_module('choice', ['course' => $course->id]);
        $start = time() + DAYSECS;
        $DB->set_field('choice', 'timeopen', $start, ['id' => $choice->id]);
        $DB->set_field('choice', 'timeclose', $start + 36 * HOURSECS, ['id' => $choice->id]);
        rebuild_course_cache($course->id, true);

        $items = array_column(manager::get_course_items($course->id), null, 'id');
        $item = $items['main_choice_' . $choice->id];
        $this->assertSame(3.0, $item['effort']);
        $this->assertSame('mapping', $item['effortsource']);
        $this->assertSame(['model' => 'perday', 'value' => 2.0], $item['effortestimator']);
    }
}
