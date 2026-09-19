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
}
