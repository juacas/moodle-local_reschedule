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
 * Native date adapter for the questionnaire activity.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionnaire_adapter extends base_date_adapter {
    #[\Override]
    protected function get_module_name(): string {
        return 'questionnaire';
    }

    /**
     * Describe Questionnaire availability dates.
     *
     * @return array<string, array{label:string, component?:string, type?:string, optional?:bool}>
     */
    protected function get_field_definitions(): array {
        return [
            'opendate' => ['label' => 'opendate', 'optional' => true],
            'closedate' => ['label' => 'closedate', 'optional' => true],
        ];
    }

    /**
     * Return this module's date ordering rules.
     *
     * @return array<int, array{0:string, 1:string, 2:string, 3?:bool}>
     */
    protected function get_date_relationships(): array {
        return [['opendate', 'closedate', 'closedate']];
    }

    /**
     * Save date fields through Questionnaire's update API when available.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Final date values.
     */
    public function save_date_fields(\cm_info $cm, array $dates): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/questionnaire/lib.php');
        if (!function_exists('questionnaire_update_instance')) {
            parent::save_date_fields($cm, $dates);
            return;
        }
        $record = $DB->get_record('questionnaire', ['id' => $cm->instance], '*', MUST_EXIST);
        foreach ($dates as $field => $value) {
            if (property_exists($record, $field)) {
                $record->{$field} = (int)$value;
            }
            if ((int)$value !== 0) {
                $record->{'use' . $field} = 1;
            }
        }
        $record->instance = $cm->instance;
        $record->cmidnumber = $cm->id;
        questionnaire_update_instance($record);
        $this->trigger_cm_updated($cm);
    }
}
