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

namespace local_reschedule\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_reschedule\manager;

/**
 * Save course activity dates through the adapter manager.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_schedule extends external_api {
    /**
     * Describe the accepted schedule changes.
     *
     * @return external_function_parameters Parameter structure.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID.'),
            'forceeditmode' => new external_value(PARAM_BOOL, 'Use Reschedule edit mode.', VALUE_DEFAULT, false),
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_TEXT, 'Gantt row ID.'),
                'datestart' => new external_value(PARAM_INT, 'Start timestamp.'),
                'dateend' => new external_value(PARAM_INT, 'End timestamp.'),
                'startenabled' => new external_value(PARAM_BOOL, 'Whether the start is enabled.'),
                'endenabled' => new external_value(PARAM_BOOL, 'Whether the end is enabled.'),
                'availabilityconditions' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_TEXT, 'Availability condition ID.'),
                    'direction' => new external_value(PARAM_TEXT, 'Availability direction.'),
                    'time' => new external_value(PARAM_INT, 'Availability timestamp.'),
                ]), 'Changed availability conditions.', VALUE_OPTIONAL),
            ]), 'Changed Gantt rows.'),
        ]);
    }

    /**
     * Validate access and save changed rows.
     *
     * @param int $courseid Course ID.
     * @param bool $forceeditmode Use Reschedule edit mode.
     * @param array $items Changed Gantt rows.
     * @return array Save result.
     */
    public static function execute(int $courseid, bool $forceeditmode, array $items): array {
        global $DB, $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'forceeditmode' => $forceeditmode,
            'items' => $items,
        ]);
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        require_login($course);
        $context = \context_course::instance((int)$course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);
        $PAGE->set_other_editing_capability('moodle/course:manageactivities');
        require_once(__DIR__ . '/../../lib.php');
        if (!\local_reschedule_user_is_editing($params['forceeditmode'])) {
            throw new \moodle_exception('editmoderequired', 'local_reschedule');
        }

        $result = manager::save_schedule((int)$course->id, $params['items']);
        return array_merge([
            'success' => false,
            'updated' => 0,
            'message' => '',
            'errors' => [],
            'itemerrors' => [],
            'warnings' => [],
            'itemwarnings' => [],
            'skipped' => [],
        ], $result);
    }

    /**
     * Describe the save result sent to the browser.
     *
     * @return external_single_structure Return structure.
     */
    public static function execute_returns(): external_single_structure {
        $itemissue = new external_single_structure([
            'id' => new external_value(PARAM_TEXT, 'Gantt row ID.'),
            'message' => new external_value(PARAM_RAW, 'Validation message.'),
        ]);
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the save succeeded.'),
            'updated' => new external_value(PARAM_INT, 'Number of updated rows.'),
            'message' => new external_value(PARAM_RAW, 'Summary message.'),
            'errors' => new external_multiple_structure(new external_value(PARAM_RAW, 'Error message.')),
            'itemerrors' => new external_multiple_structure($itemissue),
            'warnings' => new external_multiple_structure(new external_value(PARAM_RAW, 'Warning message.')),
            'itemwarnings' => new external_multiple_structure($itemissue),
            'skipped' => new external_multiple_structure(new external_value(PARAM_RAW, 'Skipped item.')),
        ]);
    }
}
