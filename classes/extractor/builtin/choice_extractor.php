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
 * Replicated date extractor for the choice activity module.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class choice_extractor extends base_builtin_extractor {
    /**
     * Initialise the extractor and load records for the course.
     *
     * @param \stdClass $course Course record.
     */
    public function __construct($course) {
        parent::__construct($course, 'choice');
        parent::load_data();
    }

    #[\Override]
    public function get_settings(cm_info $cm) {
        if (!isset($this->mods[$cm->instance])) {
            return null;
        }
        $choice = $this->mods[$cm->instance];
        return [
            'timeopen' => new report_editdates_date_setting(
                get_string('choiceopen', 'choice'),
                $choice->timeopen,
                self::DATETIME,
                false
            ),
            'timeclose' => new report_editdates_date_setting(
                get_string('choiceclose', 'choice'),
                $choice->timeclose,
                self::DATETIME,
                false
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
}
