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
 * Base abstract class for date adapters.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_adapter implements adapter_interface {
    /** @var \stdClass Course record. */
    protected \stdClass $course;

    /**
     * Constructor.
     *
     * @param \stdClass $course Course object.
     */
    public function __construct(\stdClass $course) {
        $this->course = $course;
    }

    /**
     * Load ordinary activity records from the configured table.
     *
     * @param array $rule Normalised mapping rule.
     * @return \stdClass[] Records keyed by record ID.
     */
    public function get_activity_records(array $rule): array {
        global $DB;

        $table = (string)$rule['table'];
        $titlecol = (string)$rule['titlecol'];
        $startcol = (string)$rule['startcol'];
        $endcol = (string)$rule['endcol'];
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists($table) || !$dbman->field_exists($table, 'course') ||
                !$dbman->field_exists($table, 'id') || !$dbman->field_exists($table, $titlecol) ||
                !$dbman->field_exists($table, $startcol) ||
                ($endcol !== '' && !$dbman->field_exists($table, $endcol))) {
            return [];
        }

        $endselect = $endcol === '' ? '0' : "t.{$endcol}";
        $sql = "SELECT t.id, t.{$titlecol} AS title, t.{$startcol} AS datestart, {$endselect} AS dateend
                  FROM {{$table}} t
                 WHERE t.course = :courseid
              ORDER BY t.{$startcol} ASC, t.id ASC";
        return $DB->get_records_sql($sql, ['courseid' => (int)$this->course->id]);
    }

    /**
     * Return standard parent editing attributes.
     *
     * @param \stdClass $record Loaded parent record.
     * @param array $rule Normalised mapping rule.
     * @return array Attributes to merge into the reschedule item.
     */
    public function get_activity_attributes(\stdClass $record, array $rule): array {
        $metadata = ['table' => $rule['table']];
        $derived = $this->is_derived_item($metadata);
        return [
            'derived' => $derived,
            'editable' => !$derived && (bool)$rule['editable'],
            'editreason' => '',
            'interactreason' => '',
        ];
    }

    /**
     * Load ordinary mapped child records from a database table.
     *
     * @param array $rule Normalised subtype mapping rule.
     * @param int[] $parentids Parent record IDs.
     * @return \stdClass[] Records keyed by record ID.
     */
    public function get_subitems(array $rule, array $parentids): array {
        global $DB;

        if (!$parentids) {
            return [];
        }
        $table = (string)$rule['table'];
        $parenttable = (string)$rule['parenttable'];
        $titlecol = (string)$rule['titlecol'];
        $startcol = (string)$rule['startcol'];
        $endcol = (string)$rule['endcol'];
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists($table) || !$dbman->field_exists($table, 'id') ||
                !$dbman->field_exists($table, $titlecol) || !$dbman->field_exists($table, $startcol) ||
                ($endcol !== '' && !$dbman->field_exists($table, $endcol))) {
            return [];
        }

        $same = $table === $parenttable;
        $foreignkey = $same ? 'id' : (string)$rule['foreignkey'];
        if (!$dbman->field_exists($table, $foreignkey)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($parentids, SQL_PARAMS_NAMED);
        $endselect = $endcol === '' ? '0' : $endcol;
        $sql = "SELECT id, {$foreignkey} AS parentid, {$titlecol} AS title, " .
            "{$startcol} AS datestart, {$endselect} AS dateend
                  FROM {{$table}}
                 WHERE {$foreignkey} $insql
              ORDER BY {$startcol} ASC, id ASC";
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Return standard child attributes from the mapping rule.
     *
     * @param \stdClass $record Loaded child record.
     * @param array $rule Normalised subtype mapping rule.
     * @return array Attributes to merge into the reschedule item.
     */
    public function get_subitem_attributes(\stdClass $record, array $rule): array {
        return [
            'editable' => (bool)$rule['editable'],
            'absolutelimits' => $record->absolutelimits ?? [],
        ];
    }

    /**
     * Dates are normally stored directly on the activity item.
     *
     * @param array $item Reschedule item metadata.
     * @return bool False for ordinary stored date ranges.
     */
    public function is_derived_item(array $item): bool {
        return false;
    }

    /**
     * Return the tightest absolute limits on this row and its child items.
     *
     * A row can expose at most one upper/lower bound for each date endpoint.
     * This shared reduction lets child constraints propagate to a parent without
     * module-specific aggregation in the schedule manager.
     *
     * @param array $item Reschedule item metadata.
     * @param array $children Child item metadata, if any.
     * @return array Absolute limits in the Gantt data format.
     */
    public function get_absolute_limits(array $item, array $children = []): array {
        $limits = $item['absolutelimits'] ?? [];
        foreach ($children as $child) {
            foreach ($child['absolutelimits'] ?? [] as $limit) {
                $limits[] = $limit;
            }
        }

        $byendpoint = [];
        foreach ($limits as $limit) {
            $endpoint = str_starts_with((string)($limit['type'] ?? ''), 'start_') ? 'start' : 'end';
            $current = $byendpoint[$endpoint] ?? null;
            $date = (int)($limit['date'] ?? 0);
            $currentdate = (int)($current['date'] ?? 0);
            $tighter = !$current || ($endpoint === 'start' && $date < $currentdate) ||
                ($endpoint === 'end' && $date > $currentdate);
            if ($tighter) {
                $byendpoint[$endpoint] = $limit;
            }
        }
        return array_values($byendpoint);
    }

    /**
     * Get course module info instance for an activity module instance.
     *
     * @param string $modname Module name (e.g. 'assign', 'quiz').
     * @param int $instanceid Instance ID in module table.
     * @return \cm_info|null
     */
    protected function get_cm(string $modname, int $instanceid): ?\cm_info {
        try {
            $modinfo = get_fast_modinfo($this->course->id);
            return $modinfo->instances[$modname][$instanceid] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Trigger standard course_module_updated event.
     *
     * @param \cm_info $cm Course module info.
     */
    protected function trigger_cm_updated(\cm_info $cm): void {
        try {
            $event = \core\event\course_module_updated::create_from_cm($cm);
            $event->trigger();
        } catch (\Throwable $e) {
            debugging(
                'Could not dispatch the course module update event: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Perform basic table column update.
     *
     * @param string $table Table name without prefix.
     * @param int $recordid Record ID.
     * @param string $startcol Start timestamp column name.
     * @param string $endcol End timestamp column name.
     * @param int $newstart Start timestamp.
     * @param int $newend End timestamp.
     */
    protected function raw_update_record(
        string $table,
        int $recordid,
        string $startcol,
        string $endcol,
        int $newstart,
        int $newend
    ): void {
        global $DB;
        $up = new \stdClass();
        $up->id = $recordid;
        $up->{$startcol} = $newstart;
        $up->{$endcol} = $newend;
        $dbman = $DB->get_manager();
        if ($dbman->field_exists($table, 'timemodified')) {
            $up->timemodified = time();
        }
        $DB->update_record($table, $up);
    }

    /**
     * Standard date range validation.
     *
     * @param array $item Reschedule item.
     * @param int $newstart Start timestamp.
     * @param int $newend End timestamp.
     * @return array Error messages if any.
     */
    public function validate(array $item, int $newstart, int $newend): array {
        $errors = [];
        $title = $item['title'] ?? ('#' . ($item['recordid'] ?? ''));

        if ($newstart <= 0) {
            $errors[] = get_string('error') . ": {$title} - " . get_string('errorinvaliddate', 'calendar');
        }
        if ($newend <= 0) {
            $errors[] = get_string('error') . ": {$title} - " . get_string('errorinvaliddate', 'calendar');
        }
        if ($newend <= $newstart) {
            $errors[] = "{$title}: " . get_string('closebeforeopen', 'quiz');
        }

        return $errors;
    }

    /**
     * Save tasks sequentially by default. Adapters can override this to use a
     * module API that supports bulk schedule updates.
     *
     * @param array $tasks Planned changes with item, newstart and newend keys.
     */
    public function save_batch(array $tasks): void {
        foreach ($tasks as $task) {
            $this->save($task['item'], (int)$task['newstart'], (int)$task['newend']);
        }
    }
}
