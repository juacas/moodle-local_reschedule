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
 * Generic safe fallback adapter.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generic_adapter extends base_adapter {

    #[\Override]
    public function supports(string $modname, array $item): bool {
        return true; // Fallback supports any activity.
    }

    #[\Override]
    public function save(array $item, int $newstart, int $newend): void {
        global $DB, $CFG;

        $table = $item['table'];
        $recordid = (int)$item['recordid'];
        $startcol = $item['startcol'];
        $endcol = $item['endcol'];

        $this->raw_update_record($table, $recordid, $startcol, $endcol, $newstart, $newend);

        $cm = $this->get_cm($table, $recordid);
        if ($cm) {
            $libfile = $CFG->dirroot . '/mod/' . $table . '/lib.php';
            if (file_exists($libfile)) {
                require_once($libfile);
            }

            $modrecord = $DB->get_record($table, ['id' => $recordid]);
            if ($modrecord) {
                $modrecord->coursemodule = $cm->id;

                // Check for standard event update hook.
                $eventfunc = $table . '_update_events';
                if (function_exists($eventfunc)) {
                    try {
                        $eventfunc($modrecord);
                    } catch (\Throwable $e) {
                        // Suppress hook failure.
                    }
                }

                // Check for standard gradebook item update hook.
                $gradefunc = $table . '_grade_item_update';
                if (function_exists($gradefunc)) {
                    try {
                        $gradefunc($modrecord);
                    } catch (\Throwable $e) {
                        // Suppress hook failure.
                    }
                }
            }

            $this->trigger_cm_updated($cm);
        }
    }
}
