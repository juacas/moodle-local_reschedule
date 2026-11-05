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
 * Optional adapter contract for composing a module's date rows.
 *
 * Adapters own the interpretation of their explicit date-field metadata.
 * The generic builder remains available for integration providers that do
 * not implement this interface.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface date_schedule_interface {
    /**
     * Whether this provider exposes any schedule fields for the module.
     *
     * @param \cm_info $cm Course module.
     * @return bool True when at least one date field is available.
     */
    public function has_date_fields(\cm_info $cm): bool;

    /**
     * Build the parent interval and any extra interval or milestone rows.
     *
     * @param \cm_info $cm Course module.
     * @param array $parent Existing Gantt parent row.
     * @param array<string, bool> $rangedatefields Fields already described by mapping rules.
     * @param int $coursestart Course Gantt start timestamp.
     * @param int $courseend Course Gantt end timestamp.
     * @return array{parent:array, children:array}
     */
    public function build_date_schedule(
        \cm_info $cm,
        array $parent,
        array $rangedatefields,
        int $coursestart,
        int $courseend
    ): array;
}
