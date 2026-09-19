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
 * AJAX save endpoint for local_reschedule.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

header('Content-Type: application/json; charset=utf-8');

try {
    // 1. Read input payload (JSON body or form POST).
    $rawinput = file_get_contents('php://input');
    $payload = !empty($rawinput) ? json_decode($rawinput, true) : null;

    if (!is_array($payload) || empty($payload['items'])) {
        // Fallback to POST param 'data'.
        $dataparam = optional_param('data', '', PARAM_RAW);
        if ($dataparam) {
            $payload = json_decode($dataparam, true);
        }
    }

    // 2. Resolve courseid from GET, POST, or JSON payload.
    $courseid = optional_param('courseid', 0, PARAM_INT);
    if (!$courseid && is_array($payload) && !empty($payload['courseid'])) {
        $courseid = clean_param($payload['courseid'], PARAM_INT);
    }

    if (!$courseid) {
        $missingmsg = get_string('missingparam', 'error', 'courseid');
        echo json_encode([
            'success' => false,
            'message' => $missingmsg,
            'errors' => [$missingmsg],
        ]);
        exit;
    }

    $course = $DB->get_record('course', ['id' => $courseid]);
    if (!$course) {
        $notfoundmsg = get_string('invalidcourseid', 'error');
        echo json_encode([
            'success' => false,
            'message' => $notfoundmsg,
            'errors' => [$notfoundmsg],
        ]);
        exit;
    }

    // 3. User authentication and course access.
    require_login($course);

    // 4. Permission verification.
    $context = \context_course::instance($courseid);
    require_capability('moodle/course:manageactivities', $context);

    // 5. Validate sesskey from GET, POST, or JSON payload.
    $sesskey = optional_param('sesskey', '', PARAM_RAW);
    if (empty($sesskey) && is_array($payload) && !empty($payload['sesskey'])) {
        $sesskey = clean_param($payload['sesskey'], PARAM_RAW);
    }

    if (!confirm_sesskey($sesskey)) {
        $sessionmsg = get_string('error_saving_session', 'local_reschedule');
        echo json_encode([
            'success' => false,
            'message' => $sessionmsg,
            'errors' => [$sessionmsg],
            'errorcode' => 'invalidsesskey',
        ]);
        exit;
    }

    // 6. Check items array.
    if (!is_array($payload) || empty($payload['items']) || !is_array($payload['items'])) {
        $invalidmsg = get_string('invalidrecord', 'error');
        echo json_encode([
            'success' => false,
            'message' => $invalidmsg,
            'errors' => [$invalidmsg],
        ]);
        exit;
    }

    // 7. Execute safe schedule update via manager.
    $result = \local_reschedule\manager::save_schedule($courseid, $payload['items']);
    echo json_encode($result);

} catch (\require_login_exception $e) {
    $msg = get_string('error_saving_session', 'local_reschedule');
    echo json_encode([
        'success' => false,
        'message' => $msg,
        'errors' => [$msg],
        'errorcode' => 'requireloginerror',
    ]);
} catch (\required_capability_exception $e) {
    $msg = get_string('error_saving_permission', 'local_reschedule');
    echo json_encode([
        'success' => false,
        'message' => $msg,
        'errors' => [$msg],
        'errorcode' => 'nopermissions',
    ]);
} catch (\moodle_exception $e) {
    $msg = $e->getMessage();
    if ($e->errorcode === 'invalidsesskey') {
        $msg = get_string('error_saving_session', 'local_reschedule');
    }
    echo json_encode([
        'success' => false,
        'message' => $msg,
        'errors' => [$msg],
        'errorcode' => $e->errorcode,
        'debuginfo' => !empty($e->debuginfo) ? $e->debuginfo : null,
    ]);
} catch (\Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'errors' => [$e->getMessage()],
        'errorcode' => 'generalexception',
    ]);
}

