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
 * Replicated date extractor for the workshop activity module.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workshop_extractor extends base_builtin_extractor {
    /**
     * Initialise the extractor and load records for the course.
     *
     * @param \stdClass $course Course record.
     */
    public function __construct($course) {
        parent::__construct($course, 'workshop');
        parent::load_data();
    }

    #[\Override]
    public function get_settings(cm_info $cm) {
        if (!isset($this->mods[$cm->instance])) {
            return null;
        }
        $workshop = $this->mods[$cm->instance];

        return [
            'submissionstart' => new report_editdates_date_setting(
                get_string('submissionstart', 'workshop'),
                $workshop->submissionstart,
                self::DATETIME,
                true
            ),
            'submissionend' => new report_editdates_date_setting(
                get_string('submissionend', 'workshop'),
                $workshop->submissionend,
                self::DATETIME,
                true
            ),
            'assessmentstart' => new report_editdates_date_setting(
                get_string('assessmentstart', 'workshop'),
                $workshop->assessmentstart,
                self::DATETIME,
                true
            ),
            'assessmentend' => new report_editdates_date_setting(
                get_string('assessmentend', 'workshop'),
                $workshop->assessmentend,
                self::DATETIME,
                true
            ),
        ];
    }

    #[\Override]
    public function validate_dates(cm_info $cm, array $dates) {
        $errors = [];
        if (
            !empty($dates['submissionstart']) && !empty($dates['submissionend'])
                && $dates['submissionend'] < $dates['submissionstart']
        ) {
            $errors['submissionend'] = $this->get_error_string('timeclose');
        }
        if (
            !empty($dates['assessmentstart']) && !empty($dates['assessmentend'])
                && $dates['assessmentend'] < $dates['assessmentstart']
        ) {
            $errors['assessmentend'] = $this->get_error_string('timeclose');
        }
        if (
            !empty($dates['submissionend']) && !empty($dates['assessmentstart'])
                && $dates['assessmentstart'] < $dates['submissionend']
        ) {
            $errors['assessmentstart'] = $this->get_error_string('timeclose');
        }
        return $errors;
    }

    #[\Override]
    public function save_dates(cm_info $cm, array $dates) {
        global $DB, $CFG;

        if (!isset($this->mods[$cm->instance])) {
            return;
        }
        $workshop = $this->mods[$cm->instance];

        foreach ($dates as $datetype => $datevalue) {
            $workshop->$datetype = $datevalue;
        }
        $workshop->timemodified = time();
        $DB->update_record('workshop', $workshop);

        $workshoplib = $CFG->dirroot . '/mod/workshop/lib.php';
        if (file_exists($workshoplib)) {
            require_once($workshoplib);
        }

        if (function_exists('workshop_calendar_update')) {
            workshop_calendar_update($workshop, $cm->id);
        }
    }
}
