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
 * Shared support for native adapters exposing secondary activity date fields.
 *
 * Each concrete adapter declares only its module, fields and date relationships.
 * Module-specific update APIs are implemented by overriding save_date_fields().
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_date_adapter extends base_adapter implements date_fields_interface, date_dependencies_interface {
    /**
     * Get the activity module name handled by this adapter.
     *
     * @return string Module name.
     */
    abstract protected function get_module_name(): string;

    /**
     * Get field definitions keyed by stored date field.
     *
     * Each definition has a language string key, optional language component,
     * timestamp type and optional flag.
     *
     * @return array<string, array{label:string, component?:string, type?:string, optional?:bool}>
     */
    abstract protected function get_field_definitions(): array;

    /**
     * Get date-field relationships as [start, end, error string, strict] tuples.
     *
     * @return array<int, array{0:string, 1:string, 2:string, 3?:bool}>
     */
    protected function get_date_relationships(): array {
        return [];
    }

    /**
     * Return the interval relationships explicitly declared by this adapter.
     *
     * @param \cm_info $cm Course module.
     * @param array $settings Exposed date fields.
     * @return array<int, array{startfield:string, endfield:string}>
     */
    public function get_date_dependencies(\cm_info $cm, array $settings): ?array {
        $dependencies = [];
        foreach ($this->get_date_relationships() as $relationship) {
            $dependencies[] = [
                'startfield' => $relationship[0],
                'endfield' => $relationship[1],
            ];
        }
        return $dependencies;
    }

    /**
     * Determine whether a declared field should be shown for this record.
     *
     * @param \stdClass $cmrecord Module record.
     * @param string $field Field name.
     * @return bool True when the field is available.
     */
    protected function should_show_field(\stdClass $cmrecord, string $field): bool {
        return true;
    }

    #[\Override]
    public function supports(string $modname, array $item): bool {
        $module = $this->get_module_name();
        return $modname === $module || ($item['table'] ?? '') === $module;
    }

    /**
     * Return available date fields for this activity.
     *
     * @param \cm_info $cm Course module.
     * @return array<string, array{label:string, value:int, type:string, optional:bool}>
     */
    public function get_date_fields(\cm_info $cm): array {
        global $DB;
        $record = $DB->get_record($this->get_module_name(), ['id' => $cm->instance]);
        if (!$record) {
            return [];
        }
        $fields = [];
        foreach ($this->get_field_definitions() as $field => $definition) {
            if (!property_exists($record, $field) || !$this->should_show_field($record, $field)) {
                continue;
            }
            $component = $definition['component'] ?? $this->get_module_name();
            $labelkey = $definition['label'];
            if ($this->get_module_name() === 'zoom' && $labelkey === 'meeting_time' &&
                    !get_string_manager()->string_exists($labelkey, $component)) {
                $label = get_string('zoommeetingtime', 'local_reschedule');
            } else {
                $label = get_string($labelkey, $component);
            }
            $fields[$field] = [
                'label' => $label,
                'value' => (int)$record->{$field},
                'type' => $definition['type'] ?? 'datetime',
                'optional' => (bool)($definition['optional'] ?? true),
            ];
        }
        return $fields;
    }

    /**
     * Validate date relationships declared by the concrete activity adapter.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Proposed date values.
     * @return array<string, string> Errors keyed by field.
     */
    public function validate_date_fields(\cm_info $cm, array $dates): array {
        $errors = [];
        foreach ($this->get_date_relationships() as $relationship) {
            [$start, $end, $errorstring] = $relationship;
            $strict = $relationship[3] ?? false;
            if (!isset($dates[$start], $dates[$end]) || (int)$dates[$start] === 0 || (int)$dates[$end] === 0) {
                continue;
            }
            if ($strict ? (int)$dates[$end] <= (int)$dates[$start] : (int)$dates[$end] < (int)$dates[$start]) {
                $errors[$end] = get_string($errorstring, 'local_reschedule');
            }
        }
        return $errors;
    }

    /**
     * Persist dates directly and refresh standard Moodle side effects.
     *
     * Module adapters with a dedicated update API override this method.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Final date values.
     */
    public function save_date_fields(\cm_info $cm, array $dates): void {
        global $DB, $CFG;
        $module = $this->get_module_name();
        $record = $DB->get_record($module, ['id' => $cm->instance], '*', MUST_EXIST);
        foreach ($dates as $field => $value) {
            if (property_exists($record, $field)) {
                $record->{$field} = (int)$value;
            }
        }
        $record->timemodified = time();
        $DB->update_record($module, $record);

        $libfile = $CFG->dirroot . '/mod/' . $module . '/lib.php';
        if (file_exists($libfile)) {
            require_once($libfile);
        }
        $eventfunction = $module . '_update_events';
        $gradefunction = $module . '_grade_item_update';
        if (function_exists($eventfunction)) {
            $eventfunction($record);
        }
        if (function_exists($gradefunction)) {
            $gradefunction($record);
        }
        $this->trigger_cm_updated($cm);
    }

    /**
     * Get fields that are point milestones.
     *
     * @param \cm_info $cm Course module.
     * @return string[] Field names.
     */
    public function get_point_date_fields(\cm_info $cm): array {
        return [];
    }

    /**
     * Get fields that end intervals without a stored start date.
     *
     * @param \cm_info $cm Course module.
     * @return string[] Field names.
     */
    public function get_open_start_end_fields(\cm_info $cm): array {
        return [];
    }

    #[\Override]
    public function validate(array $item, int $newstart, int $newend): array {
        $ismilestone = !empty($item['ismilestone']);
        $errors = $ismilestone ? [] : parent::validate($item, $newstart, $newend);
        if ($ismilestone && ($newstart !== $newend || $newstart < 0 ||
                ($newstart === 0 && empty($item['optional'])))) {
            return [get_string('errorinvaliddate', 'calendar')];
        }
        $cmid = (int)($item['cmid'] ?? 0);
        $cm = $cmid > 0 ? get_fast_modinfo($this->course->id)->get_cm($cmid) :
            $this->get_cm((string)$item['table'], (int)$item['recordid']);
        if (!$cm || !$this->supports($cm->modname, $item)) {
            return $errors;
        }

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
        if (!$ismilestone && !empty($item['endcol']) && empty($item['isopenended']) &&
                array_key_exists($item['endcol'], $dates)) {
            $dates[$item['endcol']] = !empty($item['endenabled']) ? $newend : 0;
        }
        foreach ($this->validate_date_fields($cm, $dates) as $message) {
            $title = (string)($item['title'] ?? $cm->name);
            if ($ismilestone && !empty($item['parenttitle'])) {
                $title = $item['parenttitle'] . ' > ' . $title;
            }
            $errors[] = $title . ': ' . $message;
        }
        return array_values(array_unique($errors));
    }

    #[\Override]
    public function save(array $item, int $newstart, int $newend): void {
        $cm = $this->get_cm((string)$item['table'], (int)$item['recordid']);
        if (!$cm) {
            throw new \moodle_exception('invalidrecord', 'error');
        }
        $dates = [];
        if (!empty($item['startcol'])) {
            $dates[$item['startcol']] = $newstart;
        }
        if (!empty($item['endcol'])) {
            $dates[$item['endcol']] = $newend;
        }
        $this->save_date_fields($cm, $dates);
    }
}
