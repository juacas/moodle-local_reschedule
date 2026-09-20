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
$cmidsparam = optional_param('cmids', '', PARAM_SEQUENCE);
$singlecmid = optional_param('cmid', 0, PARAM_INT);
$instancesparam = optional_param('instances', '', PARAM_RAW);
$requestedstart = optional_param('datestart', 0, PARAM_INT);
$requestedend = optional_param('dateend', 0, PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);
$context = \context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$urlparams = ['id' => $courseid];
if ($cmidsparam !== '') {
    $urlparams['cmids'] = $cmidsparam;
}
if ($singlecmid > 0) {
    $urlparams['cmid'] = $singlecmid;
}
if ($instancesparam !== '') {
    $urlparams['instances'] = $instancesparam;
}
if ($requestedstart > 0) {
    $urlparams['datestart'] = $requestedstart;
}
if ($requestedend > 0) {
    $urlparams['dateend'] = $requestedend;
}
$PAGE->set_url(new \moodle_url('/local/reschedule/index.php', $urlparams));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('rescheduletitle', 'local_reschedule'));
$PAGE->set_heading($course->fullname);

$timeframe = \local_reschedule\manager::get_course_timeframe($course);
$coursestart = $timeframe['start'];
$courseend = $timeframe['end'];
$items = \local_reschedule\manager::get_course_items($courseid);

$cmids = array_filter(array_map('intval', preg_split('/,/', $cmidsparam, -1, PREG_SPLIT_NO_EMPTY)));
if ($singlecmid > 0) {
    $cmids[] = $singlecmid;
}
$cmids = array_values(array_unique($cmids));
$instances = [];
foreach (preg_split('/,/', $instancesparam, -1, PREG_SPLIT_NO_EMPTY) as $instancevalue) {
    $parts = explode(':', trim($instancevalue), 2);
    if (count($parts) !== 2 || !preg_match('/^[a-z][a-z0-9_]*$/i', $parts[0])) {
        continue;
    }
    $recordid = (int)$parts[1];
    if ($recordid > 0) {
        $instances[] = strtolower($parts[0]) . ':' . $recordid;
    }
}
$instances = array_values(array_unique($instances));
if (!empty($cmids)) {
    $cmidlookup = array_fill_keys($cmids, true);
    $items = array_values(array_filter($items, function(array $item) use ($cmidlookup): bool {
        return isset($cmidlookup[(int)($item['cmid'] ?? 0)]);
    }));
}
if (!empty($instances)) {
    $instancelookup = array_fill_keys($instances, true);
    $parentlookup = [];
    foreach ($instances as $instance) {
        [$type, $recordid] = explode(':', $instance, 2);
        $parentlookup['main_' . $type . '_' . $recordid] = true;
    }
    $items = array_values(array_filter($items, function(array $item) use ($instancelookup, $parentlookup): bool {
        $itemkey = ($item['table'] ?? '') . ':' . (int)($item['recordid'] ?? 0);
        return isset($instancelookup[$itemkey]) ||
            (!empty($item['parentkey']) && isset($parentlookup[$item['parentkey']]));
    }));
}

$timelinestart = $requestedstart > 0 ? $requestedstart : $coursestart;
$timelineend = $requestedend > 0 ? $requestedend : $courseend;
if ($timelineend <= $timelinestart) {
    $timelinestart = $coursestart;
    $timelineend = $courseend;
}

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
        'startformatted' => !empty($item['startenabled']) ? userdate($item['datestart'], $dateformat) : '',
        'endformatted' => !empty($item['endenabled']) ? userdate($item['dateend'], $dateformat) : '',
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
    'timelineStart' => $timelinestart,
    'timelineEnd' => $timelineend,
    'saveUrl' => $saveurl->out(false),
    'sesskey' => sesskey(),
    'lang' => current_language(),
    'strings' => [
        'error_saving' => get_string('error_saving', 'local_reschedule'),
        'error_saving_header' => get_string('error_saving_header', 'local_reschedule'),
        'schedulesaved' => get_string('schedulesaved', 'local_reschedule'),
        'activitybeforetimeline' => get_string('activitybeforetimeline', 'local_reschedule'),
        'activityaftertimeline' => get_string('activityaftertimeline', 'local_reschedule'),
        'activitystartdisabled' => get_string('activitystartdisabled', 'local_reschedule'),
        'activityenddisabled' => get_string('activityenddisabled', 'local_reschedule'),
        'noschedulablechanges' => get_string('noschedulablechanges', 'local_reschedule'),
        'subactivityparentbounds' => get_string('subactivityparentbounds', 'local_reschedule'),
        'kuetactivityderivedhint' => get_string('kuetactivityderivedhint', 'local_reschedule'),
    ],
];

$PAGE->requires->js_call_amd('local_reschedule/reschedule_calendar', 'init', [$amdconfig]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_reschedule/reschedule', $templatecontext);
echo $OUTPUT->footer();
