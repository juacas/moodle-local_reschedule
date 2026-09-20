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
     * Check whether the item's dates are derived from its child items.
     *
     * @param array $item Reschedule item metadata.
     * @return bool True when the item is a calculated parent range.
     */
    public function is_derived_item(array $item): bool;

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
}
