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

namespace local_reschedule\adapter\core;

/**
 * Native date adapter for the zoom activity.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zoom_adapter extends base_date_adapter {
    #[\Override]
    protected function get_module_name(): string {
        return 'zoom';
    }

    /**
     * Describe Zoom meeting start time.
     *
     * @return array<string, array{label:string, component?:string, type?:string, optional?:bool}>
     */
    protected function get_field_definitions(): array {
        return [
            'start_time' => ['label' => 'meeting_time', 'optional' => false],
        ];
    }

    /**
     * Decide whether a configured field is available on this activity.
     *
     * @param \stdClass $cmrecord Module record.
     * @param string $field Field name.
     * @return bool Whether to expose the date field.
     */
    protected function should_show_field(\stdClass $cmrecord, string $field): bool {
        return empty($cmrecord->recurring);
    }
    /**
     * Reject a changed Zoom meeting start time in the past.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Proposed dates.
     * @return array<string, string> Errors keyed by date field.
     */
    public function validate_date_fields(\cm_info $cm, array $dates): array {
        global $DB;
        $errors = parent::validate_date_fields($cm, $dates);
        if (!isset($dates['start_time'])) {
            return $errors;
        }
        $record = $DB->get_record('zoom', ['id' => $cm->instance]);
        if (
            $record && (int)$dates['start_time'] !== (int)$record->start_time &&
                (int)$dates['start_time'] < strtotime('today')
        ) {
            $errors['start_time'] = get_string_manager()->string_exists('err_start_time_past', 'zoom') ?
                get_string('err_start_time_past', 'zoom') : get_string('zoomstarttimepast', 'local_reschedule');
        }
        return $errors;
    }

    /**
     * Persist Zoom meeting dates through the module update API.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Final date values.
     */
    public function save_date_fields(\cm_info $cm, array $dates): void {
        global $DB, $CFG;
        $record = $DB->get_record('zoom', ['id' => $cm->instance], '*', MUST_EXIST);
        foreach ($dates as $field => $value) {
            if (property_exists($record, $field)) {
                $record->{$field} = (int)$value;
            }
        }
        $record->timemodified = time();
        $DB->update_record('zoom', $record);
        require_once($CFG->dirroot . '/mod/zoom/locallib.php');
        if (function_exists('zoom_update_instance')) {
            $record->instance = $cm->instance;
            $record->cmidnumber = $cm->id;
            zoom_update_instance($record);
        }
        $this->trigger_cm_updated($cm);
    }
}
