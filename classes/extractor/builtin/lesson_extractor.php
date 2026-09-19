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
 * Replicated date extractor for the lesson activity module.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lesson_extractor extends base_builtin_extractor {
    /**
     * Initialise the extractor and load records for the course.
     *
     * @param \stdClass $course Course record.
     */
    public function __construct($course) {
        parent::__construct($course, 'lesson');
        parent::load_data();
    }

    #[\Override]
    public function get_settings(cm_info $cm) {
        if (!isset($this->mods[$cm->instance])) {
            return null;
        }
        $mod = $this->mods[$cm->instance];
        return [
            'available' => new report_editdates_date_setting(
                get_string('available', 'lesson'),
                $mod->available,
                self::DATETIME,
                true
            ),
            'deadline' => new report_editdates_date_setting(
                get_string('deadline', 'lesson'),
                $mod->deadline,
                self::DATETIME,
                true
            ),
        ];
    }

    #[\Override]
    public function validate_dates(cm_info $cm, array $dates) {
        $errors = [];
        if (!empty($dates['available']) && !empty($dates['deadline']) && $dates['deadline'] < $dates['available']) {
            $errors['deadline'] = $this->get_error_string('deadline');
        }
        return $errors;
    }

    #[\Override]
    public function save_dates(cm_info $cm, array $dates) {
        global $DB, $CFG;

        if (!isset($this->mods[$cm->instance])) {
            return;
        }
        $lesson = $this->mods[$cm->instance];

        foreach ($dates as $datetype => $datevalue) {
            $lesson->$datetype = $datevalue;
        }
        $lesson->timemodified = time();
        $DB->update_record('lesson', $lesson);

        $lessonlib = $CFG->dirroot . '/mod/lesson/locallib.php';
        if (file_exists($lessonlib)) {
            require_once($lessonlib);
        }

        if (function_exists('lesson_process_post_save')) {
            lesson_process_post_save($lesson);
        } else if (function_exists('lesson_update_events')) {
            lesson_update_events($lesson);
        }
    }
}
