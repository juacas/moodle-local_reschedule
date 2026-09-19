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
 * Replicated date extractor for the forum activity module.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forum_extractor extends base_builtin_extractor {
    /**
     * Initialise the extractor and load records for the course.
     *
     * @param \stdClass $course Course record.
     */
    public function __construct($course) {
        parent::__construct($course, 'forum');
        parent::load_data();
    }

    #[\Override]
    public function get_settings(cm_info $cm) {
        if (!isset($this->mods[$cm->instance])) {
            return null;
        }
        $forum = $this->mods[$cm->instance];

        $fields = [];
        $fields['duedate'] = new report_editdates_date_setting(
            get_string('duedate', 'forum'),
            $forum->duedate,
            self::DATETIME,
            true
        );
        $fields['cutoffdate'] = new report_editdates_date_setting(
            get_string('cutoffdate', 'forum'),
            $forum->cutoffdate,
            self::DATETIME,
            true
        );

        if (!empty($forum->assessed)) {
            $fields['assesstimestart'] = new report_editdates_date_setting(
                $this->get_error_string('assesstimefrom'),
                $forum->assesstimestart,
                self::DATETIME,
                true
            );
            $fields['assesstimefinish'] = new report_editdates_date_setting(
                $this->get_error_string('assesstimeto'),
                $forum->assesstimefinish,
                self::DATETIME,
                true
            );
        }

        return $fields;
    }

    #[\Override]
    public function validate_dates(cm_info $cm, array $dates) {
        $errors = [];
        if (!isset($this->mods[$cm->instance])) {
            return $errors;
        }
        $forum = $this->mods[$cm->instance];

        if (!empty($forum->assessed)) {
            if (
                !empty($dates['assesstimestart']) && !empty($dates['assesstimefinish']) &&
                    $dates['assesstimefinish'] < $dates['assesstimestart']
            ) {
                $errors['assesstimefinish'] = $this->get_error_string('assesstimefinish');
            }

            if (empty($dates['assesstimestart']) && !empty($dates['assesstimefinish'])) {
                $errors['assesstimestart'] = $this->get_error_string('dependentdate');
            }

            if (empty($dates['assesstimefinish']) && !empty($dates['assesstimestart'])) {
                $errors['assesstimefinish'] = $this->get_error_string('dependentdate');
            }
        }

        if (!empty($dates['cutoffdate']) && !empty($dates['duedate']) && $dates['cutoffdate'] < $dates['duedate']) {
            $errors['cutoffdate'] = $this->get_error_string('cutoffdate', 'forum');
        }

        return $errors;
    }

    #[\Override]
    public function save_dates(cm_info $cm, array $dates) {
        global $DB, $CFG;
        parent::save_dates($cm, $dates);

        $forumlib = $CFG->dirroot . '/mod/forum/lib.php';
        if (file_exists($forumlib)) {
            require_once($forumlib);
        }

        if (function_exists('forum_update_grades')) {
            $forum = $DB->get_record('forum', ['id' => $cm->instance]);
            if ($forum && !empty($forum->assessed)) {
                forum_update_grades($forum);
            }
        }
    }
}
