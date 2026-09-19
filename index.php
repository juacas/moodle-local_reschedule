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
 * Main index controller for local_reschedule.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);
$context = \context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url(new \moodle_url('/local/reschedule/index.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('rescheduletitle', 'local_reschedule'));
$PAGE->set_heading($course->fullname);

$timeframe = \local_reschedule\manager::get_course_timeframe($course);
$coursestart = $timeframe['start'];
$courseend = $timeframe['end'];
$items = \local_reschedule\manager::get_course_items($courseid);

$dateformat = get_string('strftimedatetimeshort', 'langconfig');
$itemsforview = [];

foreach ($items as $item) {
    $dursec = max(0, $item['dateend'] - $item['datestart']);
    $durdays = floor($dursec / 86400);
    $durhours = round(($dursec % 86400) / 3600);

    $durstr = '';
    if ($durdays > 0 && $durhours > 0) {
        $durstr = $durdays . 'd ' . $durhours . 'h';
    } else if ($durdays > 0) {
        $durstr = $durdays . 'd';
    } else {
        $durstr = max(1, $durhours) . 'h';
    }

    $itemsforview[] = array_merge($item, [
        'startformatted' => userdate($item['datestart'], $dateformat),
        'endformatted' => userdate($item['dateend'], $dateformat),
        'durationformatted' => $durstr,
    ]);
}

$saveurl = new \moodle_url('/local/reschedule/save.php', [
    'courseid' => $courseid,
    'sesskey' => sesskey(),
]);
$courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);

$templatecontext = [
    'courseid' => $courseid,
    'coursename' => $course->fullname,
    'courseurl' => $courseurl->out(),
    'coursestart' => $coursestart,
    'courseend' => $courseend,
    'coursestartstr' => userdate($coursestart, $dateformat),
    'courseendstr' => userdate($courseend, $dateformat),
    'hasitems' => !empty($itemsforview),
    'itemscount' => count($itemsforview),
    'items' => $itemsforview,
    'itemsjson' => json_encode($items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
    'saveurl' => $saveurl->out(false),
    'sesskey' => sesskey(),
];

$amdconfig = [
    'courseid' => $courseid,
    'courseStart' => $coursestart,
    'courseEnd' => $courseend,
    'saveUrl' => $saveurl->out(false),
    'sesskey' => sesskey(),
    'lang' => current_language(),
    'strings' => [
        'error_saving' => get_string('error_saving', 'local_reschedule'),
        'error_saving_header' => get_string('error_saving_header', 'local_reschedule'),
        'schedulesaved' => get_string('schedulesaved', 'local_reschedule'),
    ],
];

$PAGE->requires->js_call_amd('local_reschedule/reschedule_calendar', 'init', [$amdconfig]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_reschedule/reschedule', $templatecontext);
echo $OUTPUT->footer();
