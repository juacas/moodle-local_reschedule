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
 * Safe adapter for mod_assign.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_adapter extends base_adapter {
    #[\Override]
    public function supports(string $modname, array $item): bool {
        return ($modname === 'assign' && empty($item['issubtype']));
    }

    #[\Override]
    public function validate(array $item, int $newstart, int $newend): array {
        $errors = parent::validate($item, $newstart, $newend);
        if ($newend < $newstart) {
            $title = $item['title'] ?? 'Assignment';
            $errors[] = "{$title}: " . get_string('duedatevalidation', 'assign');
        }

        // Assignment dates have cross-field rules (for example cutoffdate must
        // follow duedate). Validate the complete proposed date set even when
        // only the main interval row is being moved.
        $extractor = editdates_bridge::get_extractor('assign', $this->course);
        $cm = $this->get_cm('assign', (int)$item['recordid']);
        if (!$extractor || !$cm || !method_exists($extractor, 'validate_dates')) {
            return $errors;
        }

        global $DB;
        $record = $DB->get_record('assign', ['id' => (int)$item['recordid']]);
        if (!$record) {
            return $errors;
        }
        $settings = $extractor->get_settings($cm);
        if (!is_array($settings)) {
            return $errors;
        }
        $dates = [];
        foreach ($settings as $field => $setting) {
            $dates[$field] = isset($record->{$field}) ? (int)$record->{$field} :
                (int)($setting->currentvalue ?? 0);
        }
        foreach ($item['proposeddates'] ?? [] as $field => $value) {
            if (array_key_exists($field, $dates)) {
                $dates[$field] = (int)$value;
            }
        }
        $dates[$item['startcol']] = !empty($item['startenabled']) ? $newstart : 0;
        $dates[$item['endcol']] = !empty($item['endenabled']) ? $newend : 0;

        foreach ($extractor->validate_dates($cm, $dates) as $field => $message) {
            // If the invalid point date is also part of this save, its
            // milestone adapter reports the error with the child row context.
            if ($field !== $item['startcol'] && $field !== $item['endcol'] &&
                    array_key_exists($field, $item['proposeddates'] ?? [])) {
                continue;
            }
            $errors[] = ($item['title'] ?? 'Assignment') . ': ' . $message;
        }
        return $errors;
    }

    #[\Override]
    public function save(array $item, int $newstart, int $newend): void {
        global $DB, $CFG;

        $recordid = (int)$item['recordid'];
        $assignrecord = $DB->get_record('assign', ['id' => $recordid]);
        if (!$assignrecord) {
            return;
        }

        $up = new \stdClass();
        $up->id = $recordid;
        if (!empty($item['startcol'])) {
            $up->{$item['startcol']} = $newstart;
        }
        if (!empty($item['endcol'])) {
            $up->{$item['endcol']} = $newend;
        }
        $up->timemodified = time();

        $DB->update_record('assign', $up);

        // Update calendar and gradebook using official Assign API.
        $cm = $this->get_cm('assign', $recordid);
        if ($cm) {
            require_once($CFG->dirroot . '/mod/assign/locallib.php');
            try {
                $assignment = new \assign(\context_module::instance($cm->id), null, null);
                $assignment->update_calendar($cm->id);
                $assignment->update_gradebook(false, $cm->id);
            } catch (\Throwable $e) {
                // Fallback to helper function if class instantiation failed.
                if (function_exists('assign_prepare_update_events')) {
                    assign_prepare_update_events($up, $this->course, $cm);
                }
            }
            $this->trigger_cm_updated($cm);
        }
    }
}
