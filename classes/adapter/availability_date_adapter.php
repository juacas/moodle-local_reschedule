<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_reschedule\adapter;

defined('MOODLE_INTERNAL') || die();

/**
 * Adapter for Moodle conditional availability date conditions.
 *
 * This adapter deliberately works with the core availability API. It never
 * edits the availability JSON by string replacement: the tree is decoded by
 * core_availability\info_module, date conditions are obtained through the
 * public tree API, and the tree is saved again through its public save API.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class availability_date_adapter {
    /** @var \stdClass Course record. */
    protected \stdClass $course;

    /**
     * Constructor.
     *
     * @param \stdClass $course Course record.
     */
    public function __construct(\stdClass $course) {
        $this->course = $course;
    }

    /**
     * Extract date restrictions from a course module.
     *
     * Conditions are returned in the same depth-first order used by Moodle's
     * availability tree. The generated condition IDs are therefore stable for
     * a save round-trip while the tree itself remains the source of truth.
     *
     * @param \cm_info $cm Course module.
     * @return array Availability metadata for the Gantt.
     */
    public function describe(\cm_info $cm): array {
        if (empty($cm->availability)) {
            return $this->empty_description();
        }

        try {
            $info = new \core_availability\info_module($cm);
            $tree = $info->get_availability_tree();
            $conditions = $tree->get_all_children('availability_date\\condition');
        } catch (\Throwable $e) {
            debugging('Could not read activity date availability: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return $this->empty_description();
        }

        if (empty($conditions)) {
            return $this->empty_description();
        }

        $savedconditions = [];
        foreach ($conditions as $index => $condition) {
            try {
                $saved = $condition->save();
                $direction = (string)($saved->d ?? '');
                $time = (int)($saved->t ?? 0);
                if (!in_array($direction, ['>=', '<'], true) || $time <= 0) {
                    continue;
                }
                $savedconditions[] = [
                    'id' => 'date-' . $index,
                    'direction' => $direction,
                    'time' => $time,
                ];
            } catch (\Throwable $e) {
                debugging('Could not serialise activity date availability: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        if (empty($savedconditions)) {
            return $this->empty_description();
        }

        $treejson = $tree->save();
        $manageable = $this->is_manageable_tree($treejson);
        $ranges = [];
        $conditionmap = [];
        foreach ($savedconditions as $condition) {
            $conditionmap[$condition['id']] = $condition;
        }
        foreach ($this->get_date_condition_groups($treejson) as $groupids) {
            $groupconditions = [];
            foreach ($groupids as $conditionid) {
                if (isset($conditionmap[$conditionid])) {
                    $groupconditions[] = $conditionmap[$conditionid];
                }
            }
            $ranges = array_merge($ranges, $this->build_ranges($groupconditions, count($ranges)));
        }

        return [
            'hasavailabilitydates' => true,
            'availabilityeditable' => $manageable,
            'availabilityconditions' => $savedconditions,
            'availabilityranges' => $ranges,
            'availabilityreason' => $manageable ? '' :
                get_string('availabilitynoteditable', 'local_reschedule'),
        ];
    }

    /**
     * Validate proposed condition times against the current tree.
     *
     * Unknown condition IDs, changed directions, malformed timestamps and
     * invalid ranges are rejected. This is important because the browser is
     * only a visual editor; the server must re-read the current CM state.
     *
     * @param array $item Current reschedule item.
     * @param array $updates Proposed condition data.
     * @return array Validation messages.
     */
    public function validate(array $item, array $updates): array {
        $title = $item['title'] ?? ('#' . ($item['recordid'] ?? ''));
        $description = $this->description_for_item($item);
        if (empty($description['hasavailabilitydates'])) {
            return ["{$title}: " . get_string('availabilitychanged', 'local_reschedule')];
        }
        if (empty($description['availabilityeditable'])) {
            return ["{$title}: " . get_string('availabilitynoteditable', 'local_reschedule')];
        }
        if (!is_array($updates)) {
            return ["{$title}: " . get_string('availabilityinvalid', 'local_reschedule')];
        }

        $current = [];
        foreach ($description['availabilityconditions'] as $condition) {
            $current[$condition['id']] = $condition;
        }
        if (empty($updates) && !empty($current)) {
            return ["{$title}: " . get_string('availabilityinvalid', 'local_reschedule')];
        }
        $proposed = $current;
        foreach ($updates as $update) {
            if (!is_array($update) || empty($update['id']) || !isset($current[$update['id']])) {
                return ["{$title}: " . get_string('availabilityinvalid', 'local_reschedule')];
            }
            $id = (string)$update['id'];
            $time = (int)($update['time'] ?? 0);
            if ($time <= 0 || (isset($update['direction']) &&
                    (string)$update['direction'] !== $current[$id]['direction'])) {
                return ["{$title}: " . get_string('availabilityinvalid', 'local_reschedule')];
            }
            $proposed[$id]['time'] = $time;
        }

        foreach ($description['availabilityranges'] as $range) {
            $start = !empty($range['startcondition']) ?
                $proposed[$range['startcondition']]['time'] : 0;
            $end = !empty($range['endcondition']) ?
                $proposed[$range['endcondition']]['time'] : 0;
            if ($start && $end && $end <= $start) {
                return ["{$title}: " . get_string('availabilityinvalidrange', 'local_reschedule')];
            }
        }

        return [];
    }

    /**
     * Save condition times in the course-module availability tree.
     *
     * @param array $item Reschedule item.
     * @param array $updates Validated condition updates.
     */
    public function save(array $item, array $updates): void {
        global $DB;

        $cm = $this->get_cm($item);
        if (!$cm || empty($cm->availability)) {
            return;
        }

        $info = new \core_availability\info_module($cm);
        $tree = $info->get_availability_tree();
        $conditions = $tree->get_all_children('availability_date\\condition');
        $times = [];
        foreach ($updates as $update) {
            $id = (string)($update['id'] ?? '');
            if (preg_match('/^date-(\\d+)$/', $id, $matches)) {
                $times[(int)$matches[1]] = (int)($update['time'] ?? 0);
            }
        }

        // The date condition class has no public setter. Mutating the decoded
        // API node through a bound closure keeps the mutation scoped to the
        // core class and lets tree::save() serialise the complete tree.
        $settime = \Closure::bind(static function($condition, int $time): void {
            $condition->time = $time;
        }, null, 'availability_date\\condition');
        foreach ($times as $index => $time) {
            if ($time > 0 && isset($conditions[$index])) {
                $settime($conditions[$index], $time);
            }
        }

        $DB->set_field('course_modules', 'availability', json_encode($tree->save()), ['id' => $cm->id]);
        $this->trigger_cm_updated($cm);
    }

    /**
     * Fetch the current course module for an item.
     *
     * @param array $item Reschedule item.
     * @return \cm_info|null
     */
    protected function get_cm(array $item): ?\cm_info {
        $cmid = (int)($item['cmid'] ?? 0);
        if ($cmid <= 0) {
            return null;
        }
        try {
            $modinfo = get_fast_modinfo($this->course->id);
            return $modinfo->get_cm($cmid);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Re-read availability metadata for server-side validation.
     *
     * @param array $item Reschedule item.
     * @return array
     */
    protected function description_for_item(array $item): array {
        $cm = $this->get_cm($item);
        return $cm ? $this->describe($cm) : $this->empty_description();
    }

    /**
     * Check whether dates can be edited without changing boolean semantics.
     *
     * In addition to a plain AND tree, a root OR made of simple AND blocks is
     * safe: changing a timestamp keeps every branch and operator unchanged.
     * Other OR/NOT shapes remain read-only because the visual editor does not
     * expose their full boolean structure.
     *
     * @param \stdClass $node Saved availability node.
     * @return bool
     */
    protected function is_manageable_tree(\stdClass $node, bool $root = true): bool {
        if (isset($node->type)) {
            return true;
        }
        if (!isset($node->op) || !isset($node->c) || !is_array($node->c)) {
            return false;
        }
        if ($root && $this->is_simple_or_of_and_blocks($node)) {
            return true;
        }

        // Boolean groups which do not contain dates are irrelevant to this
        // adapter. A date below unsupported OR/NOT, however, must remain
        // read-only.
        if ($node->op !== '&') {
            return !$this->contains_date($node);
        }
        foreach ($node->c as $child) {
            if ($this->contains_date($child) && !$this->is_manageable_tree($child, false)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check for the supported root OR shape: OR(AND(leaves...), AND(leaves...)).
     *
     * Every branch must contain at least one date condition. Other leaf
     * conditions are allowed because changing a date timestamp does not
     * change the boolean structure; nested groups are intentionally rejected.
     *
     * @param \stdClass $node Saved availability node.
     * @return bool
     */
    protected function is_simple_or_of_and_blocks(\stdClass $node): bool {
        if (($node->op ?? '') !== '|' || empty($node->c) || !is_array($node->c)) {
            return false;
        }
        foreach ($node->c as $block) {
            if (!is_object($block) || ($block->op ?? '') !== '&' ||
                    empty($block->c) || !is_array($block->c)) {
                return false;
            }
            $hasdate = false;
            foreach ($block->c as $condition) {
                if (!is_object($condition) || isset($condition->op)) {
                    return false;
                }
                if (($condition->type ?? '') === 'date') {
                    $hasdate = true;
                }
            }
            if (!$hasdate) {
                return false;
            }
        }
        return true;
    }

    /**
     * Return date condition IDs grouped by the ranges they can form.
     *
     * The IDs follow get_all_children()'s depth-first date-condition order.
     * For the supported root OR shape each AND branch is kept separate so an
     * end condition from one alternative can never be paired with a start
     * condition from another alternative.
     *
     * @param \stdClass $node Saved availability node.
     * @return array
     */
    protected function get_date_condition_groups(\stdClass $node): array {
        $nextid = 0;
        $groups = [];
        if ($this->is_simple_or_of_and_blocks($node)) {
            foreach ($node->c as $block) {
                $ids = [];
                $this->collect_date_condition_ids($block, $nextid, $ids);
                if (!empty($ids)) {
                    $groups[] = $ids;
                }
            }
            return $groups;
        }

        $ids = [];
        $this->collect_date_condition_ids($node, $nextid, $ids);
        return empty($ids) ? [] : [$ids];
    }

    /**
     * Collect date IDs in the same order as core availability's flattened API.
     *
     * @param \stdClass $node Saved availability node.
     * @param int $nextid Next date-condition index.
     * @param array $ids Output IDs.
     */
    protected function collect_date_condition_ids(\stdClass $node, int &$nextid, array &$ids): void {
        if (isset($node->type)) {
            if ($node->type === 'date') {
                $ids[] = 'date-' . $nextid;
                $nextid++;
            }
            return;
        }
        foreach (($node->c ?? []) as $child) {
            if (is_object($child)) {
                $this->collect_date_condition_ids($child, $nextid, $ids);
            }
        }
    }

    /**
     * Determine whether a saved node contains a date condition.
     *
     * @param \stdClass $node Saved availability node.
     * @return bool
     */
    protected function contains_date(\stdClass $node): bool {
        if (($node->type ?? '') === 'date') {
            return true;
        }
        foreach (($node->c ?? []) as $child) {
            if ($this->contains_date($child)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Pair lower and upper date conditions into visual ranges.
     *
     * @param array $conditions Date conditions.
     * @param int $rangeoffset Existing range count, used for unique IDs.
     * @return array
     */
    protected function build_ranges(array $conditions, int $rangeoffset = 0): array {
        $ranges = [];
        $openstarts = [];
        foreach ($conditions as $condition) {
            if ($condition['direction'] === '>=') {
                $openstarts[] = $condition;
                continue;
            }
            $start = array_shift($openstarts);
            $ranges[] = [
                'id' => 'range-' . ($rangeoffset + count($ranges)),
                'start' => $start['time'] ?? 0,
                'end' => $condition['time'],
                'startcondition' => $start['id'] ?? null,
                'endcondition' => $condition['id'],
            ];
        }
        foreach ($openstarts as $start) {
            $ranges[] = [
                'id' => 'range-' . ($rangeoffset + count($ranges)),
                'start' => $start['time'],
                'end' => 0,
                'startcondition' => $start['id'],
                'endcondition' => null,
            ];
        }
        return $ranges;
    }

    /**
     * Empty metadata shape, kept stable for the frontend.
     *
     * @return array
     */
    protected function empty_description(): array {
        return [
            'hasavailabilitydates' => false,
            'availabilityeditable' => false,
            'availabilityconditions' => [],
            'availabilityranges' => [],
            'availabilityreason' => '',
        ];
    }

    /**
     * Trigger the standard CM update event after availability changes.
     *
     * @param \cm_info $cm Course module.
     */
    protected function trigger_cm_updated(\cm_info $cm): void {
        try {
            \core\event\course_module_updated::create_from_cm($cm)->trigger();
        } catch (\Throwable $e) {
            debugging('Could not dispatch availability CM update event: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
