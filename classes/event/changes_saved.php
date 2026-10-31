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

namespace local_reschedule\event;

/**
 * Reschedule changes were saved for one or more course modules.
 *
 * @property-read array $other {
 *     Extra information about the event.
 *
 *     - string operation: Operation that saved the changes.
 *     - int affectedcount: Number of affected course modules.
 *     - string affectedmoduleids: Comma-separated course module ids.
 *     - string affectedmodulesummary: Human-readable module ids and names.
 *     - string affectedmodules: JSON list containing module ids and names.
 *     - int affectedsubactivitycount: Number of affected subactivities.
 *     - string affectedsubactivitysummary: Human-readable subactivity ids and names.
 *     - string affectedsubactivities: JSON list containing subactivity details.
 * }
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class changes_saved extends \core\event\base {
    /**
     * Create an event from a list of affected course modules.
     *
     * @param \context_course $context Course context.
     * @param string $operation Operation name.
     * @param array $modules Lists containing cmid and name.
     * @param array $subactivities Lists describing affected child rows.
     * @return self
     */
    public static function create_for_modules(
        \context_course $context,
        string $operation,
        array $modules,
        array $subactivities = []
    ): self {
        $affected = [];
        foreach ($modules as $module) {
            $cmid = (int)($module['cmid'] ?? 0);
            if ($cmid > 0) {
                $affected[$cmid] = [
                    'cmid' => $cmid,
                    'name' => (string)($module['name'] ?? ''),
                ];
            }
        }
        ksort($affected);
        $summary = array_map(static fn(array $module): string =>
            $module['cmid'] . ' (' . $module['name'] . ')', $affected);
        $affectedsubactivities = [];
        foreach ($subactivities as $subactivity) {
            $id = (string)($subactivity['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $affectedsubactivities[$id] = [
                'id' => $id,
                'name' => (string)($subactivity['name'] ?? ''),
                'cmid' => (int)($subactivity['cmid'] ?? 0),
                'table' => (string)($subactivity['table'] ?? ''),
                'recordid' => (int)($subactivity['recordid'] ?? 0),
                'parentid' => (string)($subactivity['parentid'] ?? ''),
                'milestone' => (int)!empty($subactivity['milestone']),
            ];
        }
        ksort($affectedsubactivities);
        $subactivitysummary = array_map(static fn(array $subactivity): string =>
            $subactivity['id'] . ' (' . $subactivity['name'] . ')', $affectedsubactivities);
        return static::create([
            'context' => $context,
            'other' => [
                'operation' => $operation,
                'affectedcount' => count($affected),
                'affectedmoduleids' => implode(',', array_keys($affected)),
                'affectedmodulesummary' => implode(', ', $summary),
                'affectedmodules' => json_encode(array_values($affected),
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'affectedsubactivitycount' => count($affectedsubactivities),
                'affectedsubactivitysummary' => implode(', ', $subactivitysummary),
                'affectedsubactivities' => json_encode(array_values($affectedsubactivities),
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
        ]);
    }

    /**
     * Set event properties.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventchangessaved', 'local_reschedule');
    }

    /**
     * Describe the event and expose the affected modules in the log report.
     *
     * @return string
     */
    public function get_description(): string {
        // Older log entries may predate the subactivity details in event->other.
        $operation = (string)($this->other['operation'] ?? 'schedule');
        $affectedcount = (int)($this->other['affectedcount'] ?? 0);
        $affectedmodulesummary = (string)($this->other['affectedmodulesummary'] ?? '');
        $affectedsubactivitycount = (int)($this->other['affectedsubactivitycount'] ?? 0);
        $affectedsubactivitysummary = (string)($this->other['affectedsubactivitysummary'] ?? '');

        $description = "The user with id '$this->userid' started saving Reschedule changes using " .
            "'{$operation}' for '{$affectedcount}' course modules: " .
            "'{$affectedmodulesummary}' in the course with id '$this->courseid'.";
        if ($affectedsubactivitycount > 0) {
            $description .= " The changes affect '{$affectedsubactivitycount}' subactivities: " .
                "'{$affectedsubactivitysummary}'.";
        }
        return $description;
    }

    /**
     * Return the Reschedule URL.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/reschedule/index.php', ['id' => $this->courseid]);
    }

    /**
     * Validate event data.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if ($this->contextlevel !== CONTEXT_COURSE) {
            throw new \coding_exception('Context level must be CONTEXT_COURSE.');
        }
        foreach (['operation', 'affectedcount', 'affectedmoduleids', 'affectedmodulesummary',
                'affectedmodules', 'affectedsubactivitycount', 'affectedsubactivitysummary',
                'affectedsubactivities'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '$key' value must be set in other.");
            }
        }
    }

    /**
     * The module list is audit information and is not restored.
     *
     * @return false
     */
    public static function get_other_mapping() {
        return false;
    }
}
