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
 * Persists one additional activity date exposed by a report_editdates extractor.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class milestone_adapter extends base_adapter {
    /**
     * Select this adapter for point milestones only.
     *
     * @param string $modname Module name.
     * @param array $item Schedule item.
     * @return bool
     */
    public function supports(string $modname, array $item): bool {
        return !empty($item['ismilestone']);
    }

    /**
     * Validate the point date against the module's other date settings.
     *
     * @param array $item Schedule item.
     * @param int $newstart Proposed timestamp, or zero for a disabled optional date.
     * @param int $newend Same timestamp as newstart.
     * @return array Validation messages.
     */
    public function validate(array $item, int $newstart, int $newend): array {
        global $DB;

        $field = $item['startcol'];
        $extractor = editdates_bridge::get_extractor($item['table'], $this->course);
        $cm = $this->get_cm($item['table'], (int)$item['recordid']);
        if (!$extractor || !$cm || $newstart !== $newend) {
            return [get_string('errorinvaliddate', 'calendar')];
        }

        $settings = $extractor->get_settings($cm);
        if (!isset($settings[$field]) || $newstart < 0 ||
                ($newstart === 0 && empty($settings[$field]->isoptional))) {
            return [get_string('errorinvaliddate', 'calendar')];
        }

        $record = $DB->get_record($item['table'], ['id' => $cm->instance], '*', MUST_EXIST);
        $dates = [];
        foreach ($settings as $name => $setting) {
            $dates[$name] = isset($record->{$name}) ? (int)$record->{$name} : (int)$setting->currentvalue;
        }
        // Validate the final set when several dates of one activity change together.
        foreach ($item['proposeddates'] ?? [] as $name => $value) {
            if (isset($settings[$name])) {
                $dates[$name] = $value;
            }
        }
        $dates[$field] = $newstart;
        $errors = $extractor->validate_dates($cm, $dates);
        if (!$errors) {
            return [];
        }
        $messages = [];
        foreach ($errors as $message) {
            $messages[] = ($item['title'] ?? $field) . ': ' . $message;
        }
        return $messages;
    }

    /**
     * Save the date through the module's editdates extractor.
     *
     * @param array $item Schedule item.
     * @param int $newstart New timestamp, or zero for a disabled optional date.
     * @param int $newend Same timestamp as newstart.
     */
    public function save(array $item, int $newstart, int $newend): void {
        global $DB;

        $extractor = editdates_bridge::get_extractor($item['table'], $this->course);
        $cm = $this->get_cm($item['table'], (int)$item['recordid']);
        if (!$extractor || !$cm) {
            throw new \moodle_exception('invalidrecord', 'error');
        }
        $settings = $extractor->get_settings($cm);
        $field = $item['startcol'];
        if (!isset($settings[$field])) {
            throw new \moodle_exception('invalidrecord', 'error');
        }

        $record = $DB->get_record($item['table'], ['id' => $cm->instance], '*', MUST_EXIST);
        $dates = [];
        foreach ($settings as $name => $setting) {
            $dates[$name] = isset($record->{$name}) ? (int)$record->{$name} : (int)$setting->currentvalue;
        }
        $dates[$field] = $newstart;
        $extractor->save_dates($cm, $dates);
        $this->trigger_cm_updated($cm);
    }
}
