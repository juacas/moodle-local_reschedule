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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_reschedule\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_reschedule\effort_store;
use local_reschedule\manager;

/**
 * Save editable effort estimates for a course.
 *
 * @package local_reschedule
 * @copyright 2026 Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_efforts extends external_api {
    /**
     * Describe accepted parameters.
     *
     * @return external_function_parameters Parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID.'),
            'forceeditmode' => new external_value(PARAM_BOOL, 'Use Reschedule edit mode.', VALUE_DEFAULT, false),
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_TEXT, 'Gantt row ID.'),
                'hours' => new external_value(PARAM_FLOAT, 'Estimated work hours.'),
            ]), 'Effort updates.', VALUE_DEFAULT, []),
            'model' => new external_value(PARAM_ALPHA, 'Effort distribution model.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Save estimates.
     *
     * @param int $courseid Course id.
     * @param bool $forceeditmode Forced edit mode.
     * @param array $items Values.
     * @param string $model Effort distribution model.
     * @return array
     */
    public static function execute(int $courseid, bool $forceeditmode, array $items = [], string $model = ''): array {
        global $DB, $PAGE;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'forceeditmode', 'items', 'model'));
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
        foreach ($params['items'] as $item) {
            if (!is_finite((float)$item['hours']) || (float)$item['hours'] < 0) {
                throw new \invalid_parameter_exception('Effort hours must be non-negative.');
            }
        }
        if ($params['model'] !== '' && !in_array($params['model'], ['real', 'ideal'], true)) {
            throw new \invalid_parameter_exception('Unknown effort distribution model.');
        }
        $saved = count(effort_store::read((int)$course->id)['entries']);
        if ($params['items']) {
            $current = manager::get_course_items((int)$course->id);
            $saved = effort_store::save_updates((int)$course->id, $current, $params['items']);
        }
        if ($params['model'] !== '') {
            effort_store::save_model((int)$course->id, $params['model']);
        }
        return ['success' => true, 'saved' => $saved, 'model' => effort_store::get_model((int)$course->id)];
    }

    /**
     * Describe the result.
     *
     * @return external_single_structure Result structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the values were saved.'),
            'saved' => new external_value(PARAM_INT, 'Number of stored overrides.'),
            'model' => new external_value(PARAM_ALPHA, 'Stored effort distribution model.'),
        ]);
    }
}
