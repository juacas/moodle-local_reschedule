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

defined('MOODLE_INTERNAL') || die();

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

        $origstart = (int)$assignrecord->allowsubmissionsfromdate;
        $origdue = (int)$assignrecord->duedate;
        $origcutoff = (int)$assignrecord->cutoffdate;
        $origgrading = (int)$assignrecord->gradingduedate;

        $up = new \stdClass();
        $up->id = $recordid;
        $up->allowsubmissionsfromdate = $newstart;
        $up->duedate = $newend;
        $up->timemodified = time();

        // Harmoniously shift cutoff date if originally configured.
        if ($origcutoff > 0) {
            $cutoffmargin = max(0, $origcutoff - $origdue);
            $up->cutoffdate = $newend + $cutoffmargin;
        }

        // Harmoniously shift grading due date if originally configured.
        if ($origgrading > 0) {
            $gradingmargin = max(86400 * 7, $origgrading - $origdue);
            $up->gradingduedate = $newend + $gradingmargin;
        }

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
