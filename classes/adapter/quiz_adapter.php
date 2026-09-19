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
 * Safe adapter for mod_quiz.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_adapter extends base_adapter {

    #[\Override]
    public function supports(string $modname, array $item): bool {
        return ($modname === 'quiz' && empty($item['issubtype']));
    }

    #[\Override]
    public function validate(array $item, int $newstart, int $newend): array {
        $errors = parent::validate($item, $newstart, $newend);
        if ($newend < $newstart) {
            $title = $item['title'] ?? 'Quiz';
            $errors[] = "{$title}: " . get_string('closebeforeopen', 'quiz');
        }
        return $errors;
    }

    #[\Override]
    public function save(array $item, int $newstart, int $newend): void {
        global $DB, $CFG;

        $recordid = (int)$item['recordid'];
        $quiz = $DB->get_record('quiz', ['id' => $recordid]);
        if (!$quiz) {
            return;
        }

        $quiz->timeopen = $newstart;
        $quiz->timeclose = $newend;
        $quiz->timemodified = time();

        $DB->update_record('quiz', $quiz);

        $cm = $this->get_cm('quiz', $recordid);
        if ($cm) {
            $quiz->coursemodule = $cm->id;
            $quiz->instance = $recordid;

            require_once($CFG->dirroot . '/mod/quiz/lib.php');
            if (function_exists('quiz_update_events')) {
                quiz_update_events($quiz);
            }
            if (function_exists('quiz_grade_item_update')) {
                quiz_grade_item_update($quiz);
            }
            $this->trigger_cm_updated($cm);
        }
    }
}
