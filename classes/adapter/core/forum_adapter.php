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
 * Native Reschedule integration for Forum dates and rating periods.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forum_adapter extends base_adapter implements date_fields_interface, date_dependencies_interface {
    #[\Override]
    public function supports(string $modname, array $item): bool {
        return $modname === 'forum' || ($item['table'] ?? '') === 'forum';
    }

    /**
     * Expose Forum due, cutoff and optional assessment date fields.
     *
     * @param \cm_info $cm Course module.
     * @return array<string, array{label:string, value:int, type:string, optional:bool}>
     */
    public function get_date_fields(\cm_info $cm): array {
        global $DB;
        $forum = $DB->get_record('forum', ['id' => $cm->instance]);
        if (!$forum) {
            return [];
        }
        $fields = [
            'duedate' => [
                'label' => get_string('duedate', 'forum'),
                'value' => (int)$forum->duedate,
                'type' => 'datetime',
                'optional' => true,
            ],
            'cutoffdate' => [
                'label' => get_string('cutoffdate', 'forum'),
                'value' => (int)$forum->cutoffdate,
                'type' => 'datetime',
                'optional' => true,
            ],
        ];
        if (!empty($forum->assessed)) {
            $fields['assesstimestart'] = [
                'label' => get_string('assesstimefrom', 'local_reschedule'),
                'value' => (int)$forum->assesstimestart,
                'type' => 'datetime',
                'optional' => true,
            ];
            $fields['assesstimefinish'] = [
                'label' => get_string('assesstimeto', 'local_reschedule'),
                'value' => (int)$forum->assesstimefinish,
                'type' => 'datetime',
                'optional' => true,
            ];
        }
        return $fields;
    }

    /**
     * The forum due date is a point milestone.
     *
     * @param \cm_info $cm Course module.
     * @return string[]
     */
    public function get_point_date_fields(\cm_info $cm): array {
        return ['duedate'];
    }

    /**
     * Forum assessment dates form an interval when ratings are enabled.
     *
     * @param \cm_info $cm Course module.
     * @param array $settings Exposed date fields.
     * @return array<int, array{startfield:string, endfield:string}>
     */
    public function get_date_dependencies(\cm_info $cm, array $settings): ?array {
        if (!isset($settings['assesstimestart'], $settings['assesstimefinish'])) {
            return [];
        }
        return [[
            'startfield' => 'assesstimestart',
            'endfield' => 'assesstimefinish',
        ]];
    }

    /**
     * The cutoff closes an interval with no stored start.
     *
     * @param \cm_info $cm Course module.
     * @return string[]
     */
    public function get_open_start_end_fields(\cm_info $cm): array {
        return ['cutoffdate'];
    }

    /**
     * Validate Forum cutoff and assessment date relationships.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Proposed date values.
     * @return array<string, string> Errors keyed by date field.
     */
    public function validate_date_fields(\cm_info $cm, array $dates): array {
        global $DB;
        $forum = $DB->get_record('forum', ['id' => $cm->instance]);
        if (!$forum) {
            return [];
        }
        $errors = [];
        if (!empty($forum->assessed)) {
            if (!empty($dates['assesstimestart']) && !empty($dates['assesstimefinish']) &&
                    $dates['assesstimefinish'] < $dates['assesstimestart']) {
                $errors['assesstimefinish'] = get_string('assesstimefinish', 'local_reschedule');
            }
            if (empty($dates['assesstimestart']) && !empty($dates['assesstimefinish'])) {
                $errors['assesstimestart'] = get_string('dependentdate', 'local_reschedule');
            }
            if (empty($dates['assesstimefinish']) && !empty($dates['assesstimestart'])) {
                $errors['assesstimefinish'] = get_string('dependentdate', 'local_reschedule');
            }
        }
        if (!empty($dates['cutoffdate']) && !empty($dates['duedate']) &&
                $dates['cutoffdate'] < $dates['duedate']) {
            $errors['cutoffdate'] = get_string('cutoffdatevalidation', 'forum');
        }
        return $errors;
    }

    /**
     * Save Forum dates and refresh gradebook data for assessed forums.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Final date values.
     */
    public function save_date_fields(\cm_info $cm, array $dates): void {
        global $DB, $CFG;
        $forum = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);
        foreach ($dates as $field => $value) {
            if (property_exists($forum, $field)) {
                $forum->{$field} = (int)$value;
            }
        }
        $forum->timemodified = time();
        $DB->update_record('forum', $forum);

        require_once($CFG->dirroot . '/mod/forum/lib.php');
        if (!empty($forum->assessed) && function_exists('forum_update_grades')) {
            forum_update_grades($forum);
        }
    }

    /**
     * Validate an individual Gantt row against all Forum date rules.
     *
     * @param array $item Schedule item.
     * @param int $newstart Proposed start.
     * @param int $newend Proposed end.
     * @return array Validation errors.
     */
    public function validate(array $item, int $newstart, int $newend): array {
        global $DB;
        $ismilestone = !empty($item['ismilestone']);
        $errors = $ismilestone ? [] : parent::validate($item, $newstart, $newend);
        if ($ismilestone && ($newstart !== $newend || $newstart < 0 ||
                ($newstart === 0 && empty($item['optional'])))) {
            return [get_string('errorinvaliddate', 'calendar')];
        }
        $cm = $this->get_cm('forum', (int)$item['recordid']);
        if (!$cm) {
            return $errors;
        }
        $record = $DB->get_record('forum', ['id' => $cm->instance]);
        if (!$record) {
            return $errors;
        }
        $fields = $this->get_date_fields($cm);
        $dates = [];
        foreach ($fields as $field => $definition) {
            $dates[$field] = (int)$record->{$field};
        }
        foreach ($item['proposeddates'] ?? [] as $field => $value) {
            if (array_key_exists($field, $dates)) {
                $dates[$field] = (int)$value;
            }
        }
        if (!empty($item['startcol'])) {
            $dates[$item['startcol']] = !empty($item['startenabled']) ? $newstart : 0;
        }
        if (!$ismilestone && !empty($item['endcol']) && empty($item['isopenended'])) {
            $dates[$item['endcol']] = !empty($item['endenabled']) ? $newend : 0;
        }
        $dateerrors = $this->validate_date_fields($cm, $dates);
        if ($ismilestone) {
            $relevant = $item['startcol'] === 'duedate' ? ['duedate', 'cutoffdate'] : [$item['startcol']];
            $dateerrors = array_intersect_key($dateerrors, array_fill_keys($relevant, true));
        }
        foreach ($dateerrors as $message) {
            $title = trim((string)($item['title'] ?? 'Forum date'));
            if ($ismilestone && !empty($item['parenttitle'])) {
                $title = $item['parenttitle'] . ' > ' . $title;
            }
            $errors[] = $title . ': ' . $message;
        }
        return $errors;
    }

    /**
     * Persist a Gantt date row through this Forum adapter.
     *
     * @param array $item Schedule item.
     * @param int $newstart New start value.
     * @param int $newend New end value.
     */
    public function save(array $item, int $newstart, int $newend): void {
        $cm = $this->get_cm('forum', (int)$item['recordid']);
        if (!$cm) {
            throw new \moodle_exception('invalidrecord', 'error');
        }
        $dates = [];
        if (!empty($item['startcol'])) {
            $dates[$item['startcol']] = $newstart;
        }
        if (!empty($item['endcol']) && empty($item['isopenended'])) {
            $dates[$item['endcol']] = $newend;
        }
        $this->save_date_fields($cm, $dates);
        $this->trigger_cm_updated($cm);
    }
}
