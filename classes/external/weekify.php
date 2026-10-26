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
use local_reschedule\weekifier;

/**
 * Preview or apply Weekify moves through Moodle's course format API.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class weekify extends external_api {
    /**
     * Describe Weekify request parameters.
     *
     * @return external_function_parameters Parameter structure.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID.'),
            'forceeditmode' => new external_value(PARAM_BOOL, 'Use Reschedule edit mode.', VALUE_DEFAULT, false),
            'action' => new external_value(PARAM_ALPHA, 'preview or apply.'),
            'cmids' => new external_multiple_structure(new external_value(PARAM_INT, 'Course module ID.'),
                'Selected course modules.'),
        ]);
    }

    /**
     * Validate access and preview or apply Weekify.
     *
     * @param int $courseid Course ID.
     * @param bool $forceeditmode Use Reschedule edit mode.
     * @param string $action preview or apply.
     * @param array $cmids Selected course module IDs.
     * @return array Weekify result.
     */
    public static function execute(int $courseid, bool $forceeditmode, string $action, array $cmids): array {
        global $DB, $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'forceeditmode' => $forceeditmode,
            'action' => $action,
            'cmids' => $cmids,
        ]);
        if (!in_array($params['action'], ['preview', 'apply'], true)) {
            throw new \invalid_parameter_exception('Invalid Weekify action.');
        }
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

        if ($params['action'] === 'preview') {
            $plan = weekifier::plan($course, $params['cmids']);
            return [
                'success' => true,
                'count' => count($plan['moves']),
                'moves' => array_slice($plan['moves'], 0, 20),
                'moved' => 0,
                'failed' => 0,
                'skipped' => $plan['skipped'],
                'sections' => [],
            ];
        }
        $result = weekifier::apply($course, $params['cmids']);
        $sections = [];
        if ($result['moved'] > 0) {
            // Return the authoritative course order and section membership so
            // the open Gantt can redraw its headings and watermark intervals.
            foreach (manager::get_course_items((int)$course->id) as $item) {
                $sections[] = [
                    'id' => (string)$item['id'],
                    'sectionid' => (int)$item['sectionid'],
                    'sectionnum' => (int)$item['sectionnum'],
                    'sectiontitle' => (string)$item['sectiontitle'],
                ];
            }
        }
        return [
            'success' => $result['failed'] === 0,
            'count' => 0,
            'moves' => [],
            'moved' => $result['moved'],
            'failed' => $result['failed'],
            'skipped' => $result['skipped'],
            'sections' => $sections,
        ];
    }

    /**
     * Describe the Weekify result sent to the browser.
     *
     * @return external_single_structure Return structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded.'),
            'count' => new external_value(PARAM_INT, 'Number of proposed moves.'),
            'moves' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Course module ID.'),
                'title' => new external_value(PARAM_TEXT, 'Activity title.'),
                'from' => new external_value(PARAM_TEXT, 'Original section.'),
                'to' => new external_value(PARAM_TEXT, 'Destination section.'),
                'targetsectionid' => new external_value(PARAM_INT, 'Destination section ID.'),
                'hidden' => new external_value(PARAM_BOOL, 'Whether the section is hidden.'),
            ]), 'Proposed moves.'),
            'moved' => new external_value(PARAM_INT, 'Number of applied moves.'),
            'failed' => new external_value(PARAM_INT, 'Number of failed moves.'),
            'sections' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_TEXT, 'Gantt item ID.'),
                'sectionid' => new external_value(PARAM_INT, 'Current section ID.'),
                'sectionnum' => new external_value(PARAM_INT, 'Current section number.'),
                'sectiontitle' => new external_value(PARAM_TEXT, 'Current section title.'),
            ]), 'Updated Gantt section membership in course order.'),
            'skipped' => new external_single_structure([
                'undated' => new external_value(PARAM_INT, 'Undated activities.'),
                'outofrange' => new external_value(PARAM_INT, 'Activities outside the course weeks.'),
                'already' => new external_value(PARAM_INT, 'Activities already in place.'),
                'unsupported' => new external_value(PARAM_INT, 'Unsupported activities.'),
            ]),
        ]);
    }
}
