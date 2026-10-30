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
 * Course backup support for effort overrides.
 *
 * @copyright 2026 Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_reschedule_plugin extends backup_local_plugin {
    /**
     * Define the course plugin backup structure.
     * @return backup_plugin_element Backup structure.
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element(null);
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $settings = new backup_nested_element('settings', null, ['model']);
        $efforts = new backup_nested_element('efforts');
        $effort = new backup_nested_element('effort', ['id'], ['cmid', 'locator', 'hours']);
        $plugin->add_child($wrapper);
        $wrapper->add_child($settings);
        $wrapper->add_child($efforts);
        $efforts->add_child($effort);

        $rows = [];
        $document = \local_reschedule\effort_store::read((int)$this->task->get_courseid());
        $settings->set_source_array([(object)['model' => $document['model']]]);
        foreach ($document['entries'] as $index => $entry) {
            $locator = $entry['locator'] ?? [];
            if (empty($locator['cmid']) || !is_numeric($entry['hours'] ?? null)) {
                continue;
            }
            $rows[] = (object)[
                'id' => $index + 1,
                'cmid' => (int)$locator['cmid'],
                'locator' => json_encode(\local_reschedule\effort_item_mapper::portable($locator),
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'hours' => (float)$entry['hours'],
            ];
        }
        $effort->set_source_array($rows);
        return $plugin;
    }
}
