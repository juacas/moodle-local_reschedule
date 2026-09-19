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
 * Replicated date extractor for the zoom activity module.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zoom_extractor extends base_builtin_extractor {
    /**
     * Initialise the extractor and load records for the course.
     *
     * @param \stdClass $course Course record.
     */
    public function __construct($course) {
        parent::__construct($course, 'zoom');
        parent::load_data();
    }

    #[\Override]
    public function get_settings(cm_info $cm) {
        if (!isset($this->mods[$cm->instance])) {
            return null;
        }
        $zoom = $this->mods[$cm->instance];
        if (!empty($zoom->recurring)) {
            return [];
        }

        $label = get_string_manager()->string_exists('meeting_time', 'zoom') ?
            get_string('meeting_time', 'zoom') : 'Meeting time';

        return [
            'starttime' => new report_editdates_date_setting(
                $label,
                $zoom->start_time,
                self::DATETIME,
                false
            ),
        ];
    }

    #[\Override]
    public function validate_dates(cm_info $cm, array $dates) {
        $errors = [];
        if (!isset($this->mods[$cm->instance])) {
            return $errors;
        }
        $zoom = $this->mods[$cm->instance];
        if (empty($zoom->recurring) && isset($dates['starttime'])) {
            if ($dates['starttime'] != $zoom->start_time && $dates['starttime'] < strtotime('today')) {
                $msg = get_string_manager()->string_exists('err_start_time_past', 'zoom') ?
                    get_string('err_start_time_past', 'zoom') : 'Meeting cannot start in the past';
                $errors['starttime'] = $msg;
            }
        }
        return $errors;
    }

    #[\Override]
    public function save_dates(cm_info $cm, array $dates) {
        global $CFG;

        if (!isset($this->mods[$cm->instance])) {
            return;
        }
        $zoom = $this->mods[$cm->instance];
        $zoom->instance = $cm->instance;
        $zoom->cmidnumber = $cm->id;

        if (isset($dates['starttime'])) {
            $zoom->start_time = $dates['starttime'];
        }

        $libfile = $CFG->dirroot . '/mod/zoom/locallib.php';
        if (file_exists($libfile)) {
            require_once($libfile);
        }

        if (function_exists('zoom_update_instance')) {
            zoom_update_instance($zoom);
        } else {
            parent::save_dates($cm, ['start_time' => $dates['starttime'] ?? $zoom->start_time]);
        }
    }
}
