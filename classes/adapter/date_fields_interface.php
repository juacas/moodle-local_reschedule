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
 * Optional adapter contract for discovering additional date fields.
 *
 * Built-in and module-owned adapters can implement this interface to expose
 * date fields that are not represented by the main mapping rule. The legacy
 * report_editdates bridge also implements this API for external fallbacks.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface date_fields_interface {
    /**
     * Get the date fields exposed by a course module.
     *
     * Each associative entry is keyed by the database field name and contains
     * `label`, `value`, `type` (`date` or `datetime`) and `optional` values.
     *
     * @param \cm_info $cm Course module.
     * @return array<string, array{label:string, value:int, type:string, optional:bool}>
     */
    public function get_date_fields(\cm_info $cm): array;

    /**
     * Validate all exposed date fields together and key messages by field.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Proposed date values keyed by field.
     * @return array<string, string> Validation messages keyed by field.
     */
    public function validate_date_fields(\cm_info $cm, array $dates): array;

    /**
     * Persist all exposed date fields and synchronize module side effects.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Final date values keyed by field.
     */
    public function save_date_fields(\cm_info $cm, array $dates): void;

    /**
     * Get date fields that are point milestones rather than work intervals.
     *
     * @param \cm_info $cm Course module.
     * @return string[] Database field names.
     */
    public function get_point_date_fields(\cm_info $cm): array;

    /**
     * Get date fields that close an interval with no stored start.
     *
     * @param \cm_info $cm Course module.
     * @return string[] Database field names.
     */
    public function get_open_start_end_fields(\cm_info $cm): array;
}
