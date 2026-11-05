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

use local_reschedule\adapter\base_adapter;
use local_reschedule\adapter\date_dependencies_interface;
use local_reschedule\adapter\date_fields_interface;

/**
 * Safe adapter for mod_workshop and its phases.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workshop_adapter extends base_adapter implements date_fields_interface, date_dependencies_interface {
    #[\Override]
    public function supports(string $modname, array $item): bool {
        return ($modname === 'workshop');
    }

    /**
     * Get Workshop phase date fields.
     *
     * @param \cm_info $cm Course module.
     * @return array<string, array{label:string, value:int, type:string, optional:bool}>
     */
    public function get_date_fields(\cm_info $cm): array {
        global $DB;
        $workshop = $DB->get_record('workshop', ['id' => $cm->instance]);
        if (!$workshop) {
            return [];
        }
        $fields = [];
        foreach (['submissionstart', 'submissionend', 'assessmentstart', 'assessmentend'] as $field) {
            $fields[$field] = [
                'label' => get_string($field, 'workshop'),
                'value' => (int)$workshop->{$field},
                'type' => 'datetime',
                'optional' => true,
            ];
        }
        return $fields;
    }

    /**
     * Workshop defines two phases and the hand-off between them.
     *
     * @param \cm_info $cm Course module.
     * @param array $settings Exposed date fields.
     * @return array<int, array{startfield:string, endfield:string}>
     */
    public function get_date_dependencies(\cm_info $cm, array $settings): ?array {
        return [
            ['startfield' => 'submissionstart', 'endfield' => 'submissionend'],
            ['startfield' => 'assessmentstart', 'endfield' => 'assessmentend'],
            ['startfield' => 'submissionend', 'endfield' => 'assessmentstart'],
        ];
    }

    /**
     * Validate Workshop phase relationships.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Proposed dates.
     * @return array<string, string>
     */
    public function validate_date_fields(\cm_info $cm, array $dates): array {
        $errors = [];
        foreach ([
            ['submissionstart', 'submissionend'],
            ['assessmentstart', 'assessmentend'],
            ['submissionend', 'assessmentstart'],
        ] as [$start, $end]) {
            if (!empty($dates[$start]) && !empty($dates[$end]) && $dates[$end] < $dates[$start]) {
                $errors[$end] = get_string('timeclose', 'local_reschedule');
            }
        }
        return $errors;
    }

    /**
     * Save Workshop phase date fields.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Final dates.
     */
    public function save_date_fields(\cm_info $cm, array $dates): void {
        global $DB, $CFG;
        $workshop = $DB->get_record('workshop', ['id' => $cm->instance], '*', MUST_EXIST);
        foreach ($dates as $field => $value) {
            if (property_exists($workshop, $field)) {
                $workshop->{$field} = (int)$value;
            }
        }
        $workshop->timemodified = time();
        $DB->update_record('workshop', $workshop);
        require_once($CFG->dirroot . '/mod/workshop/lib.php');
        if (function_exists('workshop_calendar_update')) {
            workshop_calendar_update($workshop, $cm->id);
        }
        $this->trigger_cm_updated($cm);
    }

    /**
     * Get point date fields.
     *
     * @param \cm_info $cm Course module.
     * @return string[]
     */
    public function get_point_date_fields(\cm_info $cm): array {
        return [];
    }

    /**
     * Get open-start interval end fields.
     *
     * @param \cm_info $cm Course module.
     * @return string[]
     */
    public function get_open_start_end_fields(\cm_info $cm): array {
        return [];
    }

    #[\Override]
    public function validate(array $item, int $newstart, int $newend): array {
        $errors = parent::validate($item, $newstart, $newend);
        $title = $item['title'] ?? 'Workshop';

        if ($newend < $newstart) {
            $errors[] = "{$title}: " . get_string('submissionendbeforestart', 'mod_workshop');
        }

        $cm = $this->get_cm('workshop', (int)($item['recordid'] ?? 0));
        if ($cm) {
            global $DB;
            $record = $DB->get_record('workshop', ['id' => $cm->instance]);
            if ($record) {
                $dates = [];
                foreach ($this->get_date_fields($cm) as $field => $definition) {
                    $dates[$field] = (int)$definition['value'];
                }
                foreach ($item['proposeddates'] ?? [] as $field => $value) {
                    if (array_key_exists($field, $dates)) {
                        $dates[$field] = (int)$value;
                    }
                }
                if (!empty($item['startcol']) && array_key_exists($item['startcol'], $dates)) {
                    $dates[$item['startcol']] = !empty($item['startenabled']) ? $newstart : 0;
                }
                if (!empty($item['endcol']) && array_key_exists($item['endcol'], $dates)) {
                    $dates[$item['endcol']] = !empty($item['endenabled']) ? $newend : 0;
                }
                foreach ($this->validate_date_fields($cm, $dates) as $message) {
                    $errors[] = $title . ': ' . $message;
                }
            }
        }

        return $errors;
    }

    #[\Override]
    public function save(array $item, int $newstart, int $newend): void {
        global $DB, $CFG;

        $recordid = (int)$item['recordid'];
        $workshop = $DB->get_record('workshop', ['id' => $recordid]);
        if (!$workshop) {
            return;
        }

        $startcol = $item['startcol'];
        $endcol = $item['endcol'];

        $workshop->{$startcol} = $newstart;
        $workshop->{$endcol} = $newend;
        $workshop->timemodified = time();

        $DB->update_record('workshop', $workshop);

        $cm = $this->get_cm('workshop', $recordid);
        if ($cm) {
            require_once($CFG->dirroot . '/mod/workshop/lib.php');
            if (function_exists('workshop_calendar_update')) {
                workshop_calendar_update($workshop, $cm->id);
            }
            $this->trigger_cm_updated($cm);
        }
    }
}
