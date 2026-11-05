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
use local_reschedule\adapter\date_fields_interface;

/**
 * Safe adapter for mod_assign.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_adapter extends base_adapter implements date_fields_interface {
    #[\Override]
    public function supports(string $modname, array $item): bool {
        return $modname === 'assign' || ($item['table'] ?? '') === 'assign';
    }

    /**
     * Expose Assignment's submission range and separate date milestones.
     *
     * @param \cm_info $cm Course module.
     * @return array<string, array{label:string, value:int, type:string, optional:bool}>
     */
    public function get_date_fields(\cm_info $cm): array {
        global $DB;
        $assign = $DB->get_record('assign', ['id' => $cm->instance]);
        if (!$assign) {
            return [];
        }
        return [
            'allowsubmissionsfromdate' => [
                'label' => get_string('allowsubmissionsfromdate', 'assign'),
                'value' => (int)$assign->allowsubmissionsfromdate,
                'type' => 'datetime',
                'optional' => true,
            ],
            'duedate' => [
                'label' => get_string('duedate', 'assign'),
                'value' => (int)$assign->duedate,
                'type' => 'datetime',
                'optional' => true,
            ],
            'cutoffdate' => [
                'label' => get_string('cutoffdate', 'assign'),
                'value' => (int)$assign->cutoffdate,
                'type' => 'datetime',
                'optional' => true,
            ],
            'gradingduedate' => [
                'label' => get_string('gradingduedate', 'assign'),
                'value' => (int)$assign->gradingduedate,
                'type' => 'datetime',
                'optional' => true,
            ],
        ];
    }

    /**
     * Assignment due and grading due dates are point milestones.
     *
     * @param \cm_info $cm Course module.
     * @return string[]
     */
    public function get_point_date_fields(\cm_info $cm): array {
        return ['duedate', 'gradingduedate'];
    }

    /**
     * Assignments have an explicit submission opening date.
     *
     * @param \cm_info $cm Course module.
     * @return string[]
     */
    public function get_open_start_end_fields(\cm_info $cm): array {
        return [];
    }

    /**
     * Validate Assignment's cross-field date ordering.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Proposed date values.
     * @return array<string, string> Errors keyed by field.
     */
    public function validate_date_fields(\cm_info $cm, array $dates): array {
        $errors = [];
        if (!empty($dates['duedate']) && !empty($dates['allowsubmissionsfromdate']) &&
                $dates['duedate'] <= $dates['allowsubmissionsfromdate']) {
            $errors['duedate'] = get_string('duedateaftersubmissionvalidation', 'assign');
        }
        if (!empty($dates['cutoffdate']) && !empty($dates['allowsubmissionsfromdate']) &&
                $dates['cutoffdate'] < $dates['allowsubmissionsfromdate']) {
            $errors['cutoffdate'] = get_string('cutoffdatefromdatevalidation', 'assign');
        } else if (!empty($dates['cutoffdate']) && !empty($dates['duedate']) &&
                $dates['cutoffdate'] < $dates['duedate']) {
            $errors['cutoffdate'] = get_string('cutoffdatevalidation', 'assign');
        }
        return $errors;
    }

    /**
     * Persist Assignment dates and refresh its calendar and gradebook records.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Final date values.
     */
    public function save_date_fields(\cm_info $cm, array $dates): void {
        global $DB, $CFG;
        $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
        foreach ($dates as $field => $value) {
            if (property_exists($assign, $field)) {
                $assign->{$field} = (int)$value;
            }
        }
        $assign->timemodified = time();
        $DB->update_record('assign', $assign);

        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $assignment = new \assign(\context_module::instance($cm->id), null, null);
        $assignment->update_calendar($cm->id);
        $assignment->update_gradebook(false, $cm->id);
    }

    /**
     * Select field-specific validation errors relevant to a milestone edit.
     *
     * @param string $field Date field being edited.
     * @return string[]
     */
    private function get_relevant_validation_fields(string $field): array {
        if ($field === 'duedate') {
            return ['duedate', 'cutoffdate'];
        }
        if ($field === 'allowsubmissionsfromdate') {
            return ['allowsubmissionsfromdate', 'duedate', 'cutoffdate'];
        }
        return [$field];
    }

    #[\Override]
    public function validate(array $item, int $newstart, int $newend): array {
        $ismilestone = !empty($item['ismilestone']);
        $errors = $ismilestone ? [] : parent::validate($item, $newstart, $newend);
        if ($ismilestone && ($newstart !== $newend || $newstart < 0 ||
                ($newstart === 0 && empty($item['optional'])))) {
            $errors[] = get_string('errorinvaliddate', 'calendar');
        }
        if (!$ismilestone && $newend < $newstart) {
            $title = $item['title'] ?? 'Assignment';
            $errors[] = "{$title}: " . get_string('duedatevalidation', 'assign');
        }

        $cm = $this->get_cm('assign', (int)$item['recordid']);
        if (!$cm) {
            return $errors;
        }

        global $DB;
        $record = $DB->get_record('assign', ['id' => (int)$item['recordid']]);
        if (!$record) {
            return $errors;
        }
        $dates = array_map(static fn($field): int => (int)$record->{$field}, array_keys($this->get_date_fields($cm)));
        foreach ($item['proposeddates'] ?? [] as $field => $value) {
            if (array_key_exists($field, $dates)) {
                $dates[$field] = (int)$value;
            }
        }
        if ($ismilestone) {
            $dates[$item['startcol']] = !empty($item['startenabled']) ? $newstart : 0;
        } else {
            $dates[$item['startcol']] = !empty($item['startenabled']) ? $newstart : 0;
            $dates[$item['endcol']] = !empty($item['endenabled']) ? $newend : 0;
        }

        $dateerrors = $this->validate_date_fields($cm, $dates);
        if ($ismilestone) {
            $relevantfields = array_fill_keys($this->get_relevant_validation_fields($item['startcol']), true);
            $dateerrors = array_intersect_key($dateerrors, $relevantfields);
        }
        foreach ($dateerrors as $field => $message) {
            // If the invalid point date is also part of this save, its
            // milestone adapter reports the error with the child row context.
            if ($field !== $item['startcol'] && $field !== $item['endcol'] &&
                    array_key_exists($field, $item['proposeddates'] ?? [])) {
                continue;
            }
            $title = trim((string)($item['title'] ?? 'Assignment'));
            if ($ismilestone && !empty($item['parenttitle'])) {
                $title = $item['parenttitle'] . ' > ' . $title;
            }
            $errors[] = $title . ': ' . $message;
        }
        return $errors;
    }

    #[\Override]
    public function save(array $item, int $newstart, int $newend): void {
        $cm = $this->get_cm('assign', (int)$item['recordid']);
        if (!$cm) {
            return;
        }
        $dates = [];
        if (!empty($item['startcol'])) {
            $dates[$item['startcol']] = $newstart;
        }
        if (!empty($item['endcol'])) {
            $dates[$item['endcol']] = $newend;
        }
        $this->save_date_fields($cm, $dates);
        $this->trigger_cm_updated($cm);
    }
}
