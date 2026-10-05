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

/**
 * Preview or apply weekly section moves.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \moodle_exception('invalidrequest', 'error');
    }
    $courseid = required_param('courseid', PARAM_INT);
    $action = required_param('action', PARAM_ALPHA);
    $cmidsparam = optional_param('cmids', '', PARAM_SEQUENCE);
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    require_login($course);
    $context = \context_course::instance($courseid);
    require_capability('moodle/course:manageactivities', $context);
    $PAGE->set_context($context);
    $PAGE->set_other_editing_capability('moodle/course:manageactivities');
    require_sesskey();
    if (!local_reschedule_user_is_editing((bool)optional_param('editmode', 0, PARAM_BOOL))) {
        throw new \moodle_exception('editmoderequired', 'local_reschedule');
    }
    if (!in_array($action, ['preview', 'apply'], true)) {
        throw new \moodle_exception('invalidrequest', 'error');
    }

    $cmids = array_values(array_unique(array_filter(array_map('intval', explode(',', $cmidsparam)))));
    if ($action === 'preview') {
        $plan = \local_reschedule\weekifier::plan($course, $cmids);
        echo json_encode([
            'success' => true,
            'count' => count($plan['moves']),
            'moves' => array_slice($plan['moves'], 0, 20),
            'skipped' => $plan['skipped'],
        ]);
    } else {
        $result = \local_reschedule\weekifier::apply($course, $cmids);
        echo json_encode([
            'success' => $result['failed'] === 0,
            'moved' => $result['moved'],
            'failed' => $result['failed'],
            'skipped' => $result['skipped'],
        ]);
    }
} catch (\Throwable $e) {
    debugging('Weekify failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    $message = $e instanceof \moodle_exception &&
        in_array($e->errorcode, ['weekifynotweeks', 'editmoderequired'], true) ?
        get_string($e->errorcode, 'local_reschedule') : get_string('weekifyerror', 'local_reschedule');
    echo json_encode([
        'success' => false,
        'message' => $message,
    ]);
}
