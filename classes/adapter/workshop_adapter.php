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
 * Safe adapter for mod_workshop and its phases.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workshop_adapter extends base_adapter {
    #[\Override]
    public function supports(string $modname, array $item): bool {
        return ($modname === 'workshop');
    }

    #[\Override]
    public function validate(array $item, int $newstart, int $newend): array {
        $errors = parent::validate($item, $newstart, $newend);
        $title = $item['title'] ?? 'Workshop';

        if ($newend < $newstart) {
            $errors[] = "{$title}: " . get_string('submissionendbeforestart', 'mod_workshop');
        }

        return $errors;
    }

    #[\Override]
    public function save(array $item, int $newstart, int $newend): void {
        global $DB, $CFG;

        $recordid = (int)$item['recordid'];
        $workshop = $DB->get_record('workshop', ['id' => $recordid]);
        if (!$workshop) {
            return;
        }

        $startcol = $item['startcol'];
        $endcol = $item['endcol'];

        $workshop->{$startcol} = $newstart;
        $workshop->{$endcol} = $newend;
        $workshop->timemodified = time();

        $DB->update_record('workshop', $workshop);

        $cm = $this->get_cm('workshop', $recordid);
        if ($cm) {
            require_once($CFG->dirroot . '/mod/workshop/lib.php');
            if (function_exists('workshop_calendar_update')) {
                workshop_calendar_update($workshop, $cm->id);
            }
            $this->trigger_cm_updated($cm);
        }
    }
}
