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
 * Cross-course student workload report.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Use the logical path so a shared symlink bootstraps its calling Moodle site.
// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Config path must be resolved before bootstrap.
$scriptfilename = $_SERVER['SCRIPT_FILENAME'] ?? __FILE__;
$configpath = dirname($scriptfilename, 3) . '/config.php';
if (!is_readable($configpath)) {
    $configpath = __DIR__ . '/../../config.php';
}
require($configpath);

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);

$context = \context_course::instance($courseid);
require_capability('local/reschedule:viewglobalreport', $context);
require_capability('moodle/course:manageactivities', $context);
require_capability('moodle/course:viewparticipants', $context);

$PAGE->set_url(new \moodle_url('/local/reschedule/globalreport.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('globalreporttitle', 'local_reschedule'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('globalreporttitle', 'local_reschedule'));

$report = \local_reschedule\global_report::get_data($course);
$dateformat = get_string('strftimedate', 'langconfig');
$datetimeformat = get_string('strftimedatetimeshort', 'langconfig');
$localecldr = get_string('localecldr', 'langconfig');
if ($localecldr === 'localecldr') {
    $localecldr = str_replace('_', '-', current_language());
}
$studentrows = [];
foreach ($report['students'] as $student) {
    $studentrows[] = [
        'id' => $student['id'],
        'fullname' => $student['fullname'],
        'profileurl' => $student['profileurl'],
        'coursecount' => count($student['courseids']),
    ];
}
$courserows = [];
foreach ($report['courses'] as $reportcourse) {
    $courserows[] = [
        'id' => $reportcourse['id'],
        'fullname' => $reportcourse['fullname'],
        'studentcount' => $reportcourse['studentcount'],
        'itemcount' => $reportcourse['activitycount'],
        'current' => $reportcourse['current'],
    ];
}

$templatecontext = [
    'courseid' => $courseid,
    'coursename' => format_string($course->fullname, true, ['context' => $context]),
    'studentcount' => $report['studentcount'],
    'coursecount' => $report['coursecount'],
    'hasstudents' => !empty($studentrows),
    'students' => $studentrows,
    'courses' => $courserows,
    'rangestartstr' => userdate($report['rangestart'], $dateformat),
    'rangeendstr' => userdate($report['rangeend'], $dateformat),
    'reportjson' => json_encode($report, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
    'language' => $localecldr,
    'datetimeformat' => $datetimeformat,
    'dateonlyformat' => $dateformat,
    'usertimezone' => \core_date::get_user_timezone(),
    'effortscalemax' => 0,
    'rescheduleurl' => (new \moodle_url('/local/reschedule/index.php', ['id' => $courseid]))->out(false),
];

$PAGE->requires->js_call_amd('local_reschedule/reschedule_calendar', 'initReport');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_reschedule/globalreport', $templatecontext);
echo $OUTPUT->footer();
