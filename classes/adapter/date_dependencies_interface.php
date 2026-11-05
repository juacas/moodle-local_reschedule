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
 * Optional contract for explicit relationships between an activity's dates.
 *
 * Returning an empty array means the adapter has deliberately declared no
 * interval relationships. Returning null delegates relationship discovery to
 * the legacy date-validation heuristics.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface date_dependencies_interface {
    /**
     * Describe date fields that form work intervals.
     *
     * Each pair is directed from its interval start to its interval end. Field
     * names must match keys returned by date_fields_interface::get_date_fields().
     * Point milestones should not be included.
     *
     * Return null only when the adapter cannot describe relationships and the
     * validation heuristics should be used instead. An empty array explicitly
     * states that no interval pairs should be inferred.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, array{label:string, value:int, type:string, optional:bool}> $settings
     *     Date fields exposed by the adapter.
     * @return array<int, array{startfield:string, endfield:string}>|null
     */
    public function get_date_dependencies(\cm_info $cm, array $settings): ?array;
}
