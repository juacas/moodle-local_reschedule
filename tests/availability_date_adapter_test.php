<?php
// This file is part of Moodle - http://moodle.org/

namespace local_reschedule;

use advanced_testcase;
use local_reschedule\adapter\availability_date_adapter;

/**
 * Tests for the core availability date adapter.
 *
 * @package    local_reschedule
 * @category   test
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(availability_date_adapter::class)]
final class availability_date_adapter_test extends advanced_testcase {
    /**
     * Date conditions are read through core's availability tree and paired as
     * independent visual ranges.
     */
    public function test_describe_and_save_multiple_ranges(): void {
        $this->resetAfterTest(true);
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'name' => 'Availability adapter test',
        ]);
        $start1 = time() + 3600;
        $end1 = $start1 + 7200;
        $start2 = $end1 + 7200;
        $end2 = $start2 + 7200;
        $availability = \core_availability\tree::get_root_json([
            \availability_date\condition::get_json('>=', $start1),
            \availability_date\condition::get_json('<', $end1),
            \availability_date\condition::get_json('>=', $start2),
            \availability_date\condition::get_json('<', $end2),
        ]);
        $DB->set_field('course_modules', 'availability', json_encode($availability), ['id' => $module->cmid]);
        \course_modinfo::purge_course_module_cache($course->id, $module->cmid);

        get_fast_modinfo($course->id, 0, true);
        $cm = get_fast_modinfo($course->id)->get_cm($module->cmid);
        $adapter = new availability_date_adapter($course);
        $description = $adapter->describe($cm);

        $this->assertTrue($description['hasavailabilitydates']);
        $this->assertTrue($description['availabilityeditable']);
        $this->assertCount(2, $description['availabilityranges']);
        $this->assertSame($start1, $description['availabilityranges'][0]['start']);
        $this->assertSame($end2, $description['availabilityranges'][1]['end']);

        $updates = $description['availabilityconditions'];
        $updates[0]['time'] = $start1 + 3600;
        $this->assertEmpty($adapter->validate([
            'cmid' => $module->cmid,
            'title' => 'Availability adapter test',
        ], $updates));
        $adapter->save([
            'cmid' => $module->cmid,
            'title' => 'Availability adapter test',
        ], $updates);

        $stored = json_decode($DB->get_field('course_modules', 'availability', ['id' => $module->cmid]));
        $this->assertSame($start1 + 3600, $stored->c[0]->t);
    }

    /**
     * Date conditions in OR trees are shown but not offered as draggable
     * edits, because changing one leaf could alter the access expression.
     */
    public function test_or_tree_is_read_only(): void {
        $this->resetAfterTest(true);
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $availability = \core_availability\tree::get_root_json([
            \availability_date\condition::get_json('>=', time() + 3600),
            \availability_date\condition::get_json('<', time() + 7200),
        ], \core_availability\tree::OP_OR, true);
        $DB->set_field('course_modules', 'availability', json_encode($availability), ['id' => $module->cmid]);
        \course_modinfo::purge_course_module_cache($course->id, $module->cmid);

        $adapter = new availability_date_adapter($course);
        get_fast_modinfo($course->id, 0, true);
        $description = $adapter->describe(get_fast_modinfo($course->id)->get_cm($module->cmid));
        $this->assertTrue($description['hasavailabilitydates']);
        $this->assertFalse($description['availabilityeditable']);
    }

    /**
     * A root OR containing simple AND date blocks is editable per branch.
     */
    public function test_root_or_with_and_blocks_is_editable(): void {
        $this->resetAfterTest(true);
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $start1 = time() + 3600;
        $end1 = $start1 + 7200;
        $start2 = $end1 + 7200;
        $end2 = $start2 + 7200;
        $block1 = \core_availability\tree::get_nested_json([
            \availability_date\condition::get_json('>=', $start1),
            \availability_date\condition::get_json('<', $end1),
        ]);
        $block2 = \core_availability\tree::get_nested_json([
            \availability_date\condition::get_json('>=', $start2),
            \availability_date\condition::get_json('<', $end2),
        ]);
        $availability = \core_availability\tree::get_root_json(
            [$block1, $block2], \core_availability\tree::OP_OR, true);
        $DB->set_field('course_modules', 'availability', json_encode($availability), ['id' => $module->cmid]);
        \course_modinfo::purge_course_module_cache($course->id, $module->cmid);

        get_fast_modinfo($course->id, 0, true);
        $cm = get_fast_modinfo($course->id)->get_cm($module->cmid);
        $adapter = new availability_date_adapter($course);
        $description = $adapter->describe($cm);

        $this->assertTrue($description['hasavailabilitydates']);
        $this->assertTrue($description['availabilityeditable']);
        $this->assertCount(2, $description['availabilityranges']);
        $this->assertSame($start1, $description['availabilityranges'][0]['start']);
        $this->assertSame($start2, $description['availabilityranges'][1]['start']);

        $updates = $description['availabilityconditions'];
        $updates[2]['time'] = $start2 + 3600;
        $this->assertEmpty($adapter->validate(['cmid' => $module->cmid], $updates));
        $adapter->save(['cmid' => $module->cmid], $updates);

        $stored = json_decode($DB->get_field('course_modules', 'availability', ['id' => $module->cmid]));
        $this->assertSame($start2 + 3600, $stored->c[1]->c[0]->t);
    }
}
