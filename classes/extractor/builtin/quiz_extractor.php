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

namespace local_reschedule\extractor\builtin;

use cm_info;
use local_reschedule\extractor\base_builtin_extractor;
use report_editdates_date_setting;

/**
 * Replicated date extractor for the quiz activity module.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_extractor extends base_builtin_extractor {
    /**
     * Initialise the extractor and load records for the course.
     *
     * @param \stdClass $course Course record.
     */
    public function __construct($course) {
        parent::__construct($course, 'quiz');
        parent::load_data();
    }

    #[\Override]
    public function get_settings(cm_info $cm) {
        if (!isset($this->mods[$cm->instance])) {
            return null;
        }
        $quiz = $this->mods[$cm->instance];
        return [
            'timeopen' => new report_editdates_date_setting(
                get_string('quizopen', 'quiz'),
                $quiz->timeopen,
                self::DATETIME,
                true
            ),
            'timeclose' => new report_editdates_date_setting(
                get_string('quizclose', 'quiz'),
                $quiz->timeclose,
                self::DATETIME,
                true
            ),
        ];
    }

    #[\Override]
    public function validate_dates(cm_info $cm, array $dates) {
        $errors = [];
        if (!empty($dates['timeopen']) && !empty($dates['timeclose']) && $dates['timeclose'] < $dates['timeopen']) {
            $errors['timeclose'] = $this->get_error_string('timeclose');
        }
        return $errors;
    }

    #[\Override]
    public function save_dates(cm_info $cm, array $dates) {
        global $CFG;
        parent::save_dates($cm, $dates);

        if (!isset($this->mods[$cm->instance])) {
            return;
        }
        $quiz = $this->mods[$cm->instance];
        $quiz->instance = $cm->instance;
        $quiz->coursemodule = $cm->id;

        foreach ($dates as $datetype => $datevalue) {
            $quiz->$datetype = $datevalue;
        }

        $quizlib = $CFG->dirroot . '/mod/quiz/lib.php';
        if (file_exists($quizlib)) {
            require_once($quizlib);
        }

        if (function_exists('quiz_update_events')) {
            quiz_update_events($quiz);
        }
        if (function_exists('quiz_grade_item_update')) {
            quiz_grade_item_update($quiz);
        }
    }
}
