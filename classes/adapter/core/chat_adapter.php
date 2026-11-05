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
 * Native date adapter for the chat activity.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chat_adapter extends base_date_adapter {
    #[\Override]
    protected function get_module_name(): string {
        return 'chat';
    }

    /**
     * Describe Chat meeting time.
     *
     * @return array<string, array{label:string, component?:string, type?:string, optional?:bool}>
     */
    protected function get_field_definitions(): array {
        return [
            'chattime' => ['label' => 'chattime', 'optional' => false],
        ];
    }

    /**
     * Save the meeting date through Chat's update API when available.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Final date values.
     */
    public function save_date_fields(\cm_info $cm, array $dates): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/chat/lib.php');
        if (!function_exists('chat_update_instance')) {
            parent::save_date_fields($cm, $dates);
            return;
        }
        $record = $DB->get_record('chat', ['id' => $cm->instance], '*', MUST_EXIST);
        foreach ($dates as $field => $value) {
            if (property_exists($record, $field)) {
                $record->{$field} = (int)$value;
            }
        }
        $record->instance = $cm->instance;
        $record->coursemodule = $cm->id;
        chat_update_instance($record);
        $this->trigger_cm_updated($cm);
    }
}
