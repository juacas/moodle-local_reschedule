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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Course restore support with subactivity remapping and orphan rejection.
 *
 * @copyright 2026 Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_reschedule_plugin extends restore_local_plugin {
    /** @var array Pending portable entries from the backup. */
    private array $efforts = [];
    /** @var string Effort model from the backup. */
    private string $effortmodel = \local_reschedule\effort_store::DEFAULT_MODEL;

    /**
     * Define the course plugin restore paths.
     * @return restore_path_element[] Restore paths.
     */
    protected function define_course_plugin_structure() {
        return [
            new restore_path_element('local_reschedule_settings', $this->get_pathfor('/settings')),
            new restore_path_element('local_reschedule_effort', $this->get_pathfor('/efforts/effort')),
        ];
    }

    /**
     * Collect effort settings from the backup.
     *
     * @param array $data Backup record.
     */
    public function process_local_reschedule_settings($data): void {
        $this->effortmodel = ($data['model'] ?? '') === 'ideal' ? 'ideal' : 'real';
    }

    /**
     * Collect one portable effort record.
     *
     * @param array $data Backup record.
     */
    public function process_local_reschedule_effort($data): void {
        $locator = json_decode((string)$data['locator'], true);
        if (!is_array($locator) || !is_numeric($data['hours'])) {
            return;
        }
        $this->efforts[] = [
            'oldcmid' => (int)$data['cmid'],
            'locator' => $locator,
            'hours' => max(0.0, (float)$data['hours']),
        ];
    }

    /**
     * Remap and save entries after course modules are restored.
     */
    public function after_execute_course(): void {
        $courseid = (int)$this->task->get_courseid();
        $items = \local_reschedule\manager::get_course_items($courseid);
        $locators = \local_reschedule\effort_item_mapper::map($items);
        $portable = [];
        foreach ($locators as $locator) {
            $portable[\local_reschedule\effort_item_mapper::key($locator, true)] = $locator;
        }
        $entries = [];
        foreach ($this->efforts as $effort) {
            $newcmid = (int)$this->get_mappingid('course_module', $effort['oldcmid']);
            if (!$newcmid) {
                continue;
            }
            $wanted = $effort['locator'];
            $wanted['cmid'] = $newcmid;
            $key = \local_reschedule\effort_item_mapper::key($wanted, true);
            if (!isset($portable[$key])) {
                continue;
            }
            $entries[] = ['locator' => $portable[$key], 'hours' => $effort['hours']];
        }
        \local_reschedule\effort_store::write($courseid, $entries, $this->effortmodel);
    }
}
