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
 * Safe adapter for mod_quest and quest_submissions.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quest_adapter extends base_adapter {
    #[\Override]
    public function supports(string $modname, array $item): bool {
        return ($modname === 'quest' || $item['table'] === 'quest' || $item['table'] === 'quest_submissions');
    }

    #[\Override]
    public function save(array $item, int $newstart, int $newend): void {
        global $DB, $CFG;

        $table = $item['table'];
        $recordid = (int)$item['recordid'];

        if ($table === 'quest_submissions') {
            // Updating a challenge within a quest.
            $submission = $DB->get_record('quest_submissions', ['id' => $recordid]);
            if (!$submission) {
                return;
            }

            $submission->datestart = $newstart;
            $submission->dateend = $newend;
            $DB->update_record('quest_submissions', $submission);

            $quest = $DB->get_record('quest', ['id' => $submission->questid]);
            if ($quest) {
                $cm = $this->get_cm('quest', $quest->id);
                if ($cm && file_exists($CFG->dirroot . '/mod/quest/locallib.php')) {
                    require_once($CFG->dirroot . '/mod/quest/locallib.php');
                    if (function_exists('quest_update_challenge_calendar')) {
                        quest_update_challenge_calendar($cm, $quest, $submission);
                    }
                }
            }
            return;
        }

        // Updating main quest tournament.
        $quest = $DB->get_record('quest', ['id' => $recordid]);
        if (!$quest) {
            return;
        }

        $quest->datestart = $newstart;
        $quest->dateend = $newend;
        $quest->timemodified = time();
        $DB->update_record('quest', $quest);

        $cm = $this->get_cm('quest', $recordid);
        if ($cm && file_exists($CFG->dirroot . '/mod/quest/locallib.php')) {
            require_once($CFG->dirroot . '/mod/quest/locallib.php');
            if (function_exists('quest_update_quest_calendar')) {
                quest_update_quest_calendar($quest, $cm);
            }
            if (function_exists('quest_update_grades')) {
                quest_update_grades($quest);
            }
            $this->trigger_cm_updated($cm);
        }
    }
}
