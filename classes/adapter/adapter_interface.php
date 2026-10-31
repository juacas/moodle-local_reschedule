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

namespace local_reschedule\adapter;

/**
 * Interface for activity date adapters.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface adapter_interface {
    /**
     * Check if this adapter supports the given activity/item.
     *
     * @param string $modname Activity module name (e.g. 'assign', 'quiz').
     * @param array $item Reschedule item metadata.
     * @return bool
     */
    public function supports(string $modname, array $item): bool;

    /**
     * Load parent activity records for one mapping rule.
     *
     * @param array $rule Normalised mapping rule.
     * @return \stdClass[] Records keyed by record ID.
     */
    public function get_activity_records(array $rule): array;

    /**
     * Return adapter-specific display and editing attributes for a parent item.
     *
     * @param \stdClass $record Loaded parent record.
     * @param array $rule Normalised mapping rule.
     * @return array Attributes to merge into the reschedule item.
     */
    public function get_activity_attributes(\stdClass $record, array $rule): array;

    /**
     * Load mapped child records for one mapping rule.
     *
     * @param array $rule Normalised subtype mapping rule.
     * @param int[] $parentids Parent record IDs.
     * @return \stdClass[] Records keyed by record ID.
     */
    public function get_subitems(array $rule, array $parentids): array;

    /**
     * Return adapter-specific display and editing attributes for a mapped child.
     *
     * @param \stdClass $record Loaded child record.
     * @param array $rule Normalised subtype mapping rule.
     * @return array Attributes to merge into the reschedule item.
     */
    public function get_subitem_attributes(\stdClass $record, array $rule): array;

    /**
     * Check whether the item's dates are derived from its child items.
     *
     * @param array $item Reschedule item metadata.
     * @return bool True when the item is a calculated parent range.
     */
    public function is_derived_item(array $item): bool;

    /**
     * Get absolute date limits supplied by this adapter.
     *
     * Parent adapters may also reduce limits from their child items.
     *
     * @param array $item Reschedule item metadata.
     * @param array $children Child item metadata, if any.
     * @return array Absolute limits in the Gantt data format.
     */
    public function get_absolute_limits(array $item, array $children = []): array;

    /**
     * Validate the proposed dates for this activity item.
     *
     * @param array $item Reschedule item metadata.
     * @param int $newstart Proposed start timestamp.
     * @param int $newend Proposed end timestamp.
     * @return array Array of validation error messages. Empty array if valid.
     */
    public function validate(array $item, int $newstart, int $newend): array;

    /**
     * Save the dates and update satellite subsystems (calendar, gradebook, events).
     *
     * @param array $item Reschedule item metadata.
     * @param int $newstart New start timestamp.
     * @param int $newend New end timestamp.
     */
    public function save(array $item, int $newstart, int $newend): void;

    /**
     * Save one or more schedule tasks using this adapter.
     *
     * @param array $tasks Planned changes with item, newstart and newend keys.
     */
    public function save_batch(array $tasks): void;
}
