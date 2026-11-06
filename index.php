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

// Resolve config.php from the web server's logical script path. __DIR__ points
// to the physical target when this plugin directory is symlinked from another
// Moodle checkout and would otherwise bootstrap the target checkout instead.
// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Config path must be resolved before bootstrap.
$scriptfilename = $_SERVER['SCRIPT_FILENAME'] ?? __FILE__;
$configpath = dirname($scriptfilename, 3) . '/config.php';
if (!is_readable($configpath)) {
    $configpath = __DIR__ . '/../../config.php';
}
require($configpath);
require_once($CFG->dirroot . '/local/reschedule/lib.php');

$courseid = required_param('id', PARAM_INT);
$cmidsparam = optional_param('cmids', '', PARAM_SEQUENCE);
$singlecmid = optional_param('cmid', 0, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$navigationparam = optional_param('navigation', '', PARAM_RAW);
$callernavigation = [];
if ($returnurl !== '' && $navigationparam !== '') {
    $decodednavigation = json_decode($navigationparam, true);
    if (is_array($decodednavigation)) {
        foreach (array_slice($decodednavigation, 0, 12) as $node) {
            if (!is_array($node) || empty($node['label'])) {
                continue;
            }
            $label = clean_param((string)$node['label'], PARAM_TEXT);
            if ($label === '') {
                continue;
            }
            $nodeurl = clean_param((string)($node['url'] ?? ''), PARAM_LOCALURL);
            $nodeurl = $nodeurl !== '' ? new \moodle_url($nodeurl) : null;
            if ($nodeurl && !$nodeurl->is_local_url()) {
                continue;
            }
            $callernavigation[] = [
                'label' => $label,
                'url' => $nodeurl ? $nodeurl->out(false) : '',
            ];
        }
    }
}
$instancesparam = optional_param('instances', '', PARAM_RAW);
$requestedstart = optional_param('datestart', 0, PARAM_INT);
$requestedend = optional_param('dateend', 0, PARAM_INT);
$editmode = optional_param('editmode', 0, PARAM_INT);
$forceeditmode = in_array($editmode, [1, 2], true);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);
$context = \context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);
$scopededitmode = $editmode === 2;
$scopecmid = 0;
$scopeactivityname = '';
if ($scopededitmode) {
    if ($singlecmid <= 0 || !\local_reschedule\manager::cm_is_supported($courseid, $singlecmid)) {
        throw new \invalid_parameter_exception('Scoped edit mode requires a supported course module.');
    }
    $scopecmid = $singlecmid;
    $scopeactivitycm = get_fast_modinfo($courseid)->get_cm($scopecmid);
    $scopeactivityname = '"' . format_string($scopeactivitycm->name, true, [
        'context' => \context_module::instance($scopecmid),
    ]) . '" (' . get_string('modulename', 'mod_' . $scopeactivitycm->modname) . ')';
}

$urlparams = ['id' => $courseid];
if ($returnurl !== '') {
    $urlparams['returnurl'] = $returnurl;
    if (!empty($callernavigation)) {
        $urlparams['navigation'] = json_encode($callernavigation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
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
if ($editmode > 0) {
    $urlparams['editmode'] = $editmode;
}
$PAGE->set_url(new \moodle_url('/local/reschedule/index.php', $urlparams));
$PAGE->set_context($context);
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('rescheduletitle', 'local_reschedule'));
$PAGE->set_heading($course->fullname);
if ($returnurl !== '' && !empty($callernavigation)) {
    // Replace this page's course-context path with the path supplied by the
    // caller, then let Moodle render all breadcrumb nodes through its navbar API.
    $PAGE->navbar->ignore_active();
    foreach ($callernavigation as $navigationnode) {
        $nodeurl = $navigationnode['url'] !== '' ? new \moodle_url($navigationnode['url']) : null;
        $PAGE->navbar->add($navigationnode['label'], $nodeurl, \navigation_node::TYPE_CUSTOM);
    }
}
$PAGE->navbar->add(get_string('rescheduletitle', 'local_reschedule'));
$editing = local_reschedule_user_is_editing($forceeditmode);

$items = \local_reschedule\manager::get_course_items($courseid);
$timeframe = \local_reschedule\manager::get_course_timeframe($course, $items);
$coursestart = $timeframe['start'];
$courseend = $timeframe['end'];

// Cmid controls initial focus (and editmode=2 scope); only cmids is an explicit
// list filter for selecting which activities to display.
$cmids = array_filter(array_map('intval', preg_split('/,/', $cmidsparam, -1, PREG_SPLIT_NO_EMPTY)));
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
if (!$scopededitmode && !empty($cmids)) {
    $cmidlookup = array_fill_keys($cmids, true);
    $items = array_values(array_filter($items, function (array $item) use ($cmidlookup): bool {
        return isset($cmidlookup[(int)($item['cmid'] ?? 0)]);
    }));
}
if (!$scopededitmode && !empty($instances)) {
    $instancelookup = array_fill_keys($instances, true);
    $parentlookup = [];
    foreach ($instances as $instance) {
        [$type, $recordid] = explode(':', $instance, 2);
        $parentlookup['main_' . $type . '_' . $recordid] = true;
    }
    $items = array_values(array_filter($items, function (array $item) use ($instancelookup, $parentlookup): bool {
        $itemkey = ($item['table'] ?? '') . ':' . (int)($item['recordid'] ?? 0);
        return isset($instancelookup[$itemkey]) ||
            (!empty($item['parentkey']) && isset($parentlookup[$item['parentkey']]));
    }));
}

$viewevent = \local_reschedule\event\reschedule_viewed::create([
    'context' => $context,
    'other' => [
        'editing' => (int)$editing,
        'itemcount' => count($items),
        'selectioncount' => count($cmids) + count($instances),
    ],
]);
$viewevent->trigger();

$timelinestart = $requestedstart > 0 ? $requestedstart : $coursestart;
$timelineend = $requestedend > 0 ? $requestedend : $courseend;
if ($timelineend <= $timelinestart) {
    $timelinestart = $coursestart;
    $timelineend = $courseend;
}

// Open intervals use the effective Gantt bounds for display and effort density.
foreach ($items as &$item) {
    if (!empty($item['ismilestone'])) {
        continue;
    }
    if (empty($item['startenabled'])) {
        $item['datestart'] = $timelinestart;
    }
    if (empty($item['endenabled'])) {
        $item['dateend'] = $timelineend;
    }
    if (
        ($item['effortsource'] ?? '') === 'mapping' &&
            ($item['effortestimator']['model'] ?? '') === 'perday'
    ) {
        $item['effort'] = \local_reschedule\mapping::estimate(
            $item['effortestimator'],
            (int)$item['datestart'],
            (int)$item['dateend']
        );
    }
}
unset($item);

// Recalculate derived parents after bounded child estimates have changed.
$parentsums = [];
foreach ($items as &$item) {
    if (!empty($item['issubtype']) && empty($item['ismilestone']) && !empty($item['parentkey'])) {
        $parentsums[(string)$item['parentkey']] = ($parentsums[(string)$item['parentkey']] ?? 0) +
            (float)($item['effort'] ?? 0);
    }
}
foreach ($items as &$item) {
    if (isset($parentsums[(string)$item['id']])) {
        $item['effort'] = round($parentsums[(string)$item['id']], 4);
        $item['effortsource'] = 'children';
    }
}
unset($item);

$dateformat = get_string('strftimedatetimeshort', 'langconfig');
$dateonlyformat = get_string('strftimedate', 'langconfig');
$localecldr = get_string('localecldr', 'langconfig');
if ($localecldr === 'localecldr') {
    $localecldr = str_replace('_', '-', current_language());
}
$itemsforview = [];
$modulepluginnames = [];
foreach (get_fast_modinfo($courseid)->get_cms() as $coursemodule) {
    $modulepluginnames[(int)$coursemodule->id] = get_string('pluginname', 'mod_' . $coursemodule->modname);
}
$effortsourcelabels = [
    'adapter' => get_string('efforttableadapter', 'local_reschedule'),
    'children' => get_string('efforttablechildren', 'local_reschedule'),
    'manual' => get_string('efforttablemanual', 'local_reschedule'),
    'mapping' => get_string('efforttablemapping', 'local_reschedule'),
    'default' => get_string('efforttabledefault', 'local_reschedule'),
];

foreach ($items as &$item) {
    $inscope = !$scopededitmode || (int)($item['cmid'] ?? 0) === $scopecmid;
    $cmid = (int)($item['cmid'] ?? 0);
    $canmanageactivity = $cmid > 0 && has_capability(
        'moodle/course:manageactivities',
        \context_module::instance($cmid)
    );
    $item['efforteditable'] = $editing && $inscope && $canmanageactivity && empty($item['ismilestone']) &&
        !in_array($item['effortsource'] ?? '', ['adapter', 'children'], true);
    if ($scopededitmode) {
        $item['scopedreadwrite'] = $inscope;
    }
    if (!$inscope) {
        $item['interactive'] = false;
        $item['editable'] = false;
        $item['availabilityeditable'] = false;
        $item['efforteditable'] = false;
    }
    if (!$editing) {
        $item['interactive'] = false;
        $item['editable'] = false;
        $item['derived'] = false;
        $item['editreason'] = get_string('editmoderequired', 'local_reschedule');
    }
    if (!empty($item['ismilestone'])) {
        $itemsforview[] = array_merge($item, [
            'startformatted' => !empty($item['startenabled']) ? userdate(
                $item['datestart'],
                !empty($item['dateonly']) ? $dateonlyformat : $dateformat
            ) : '',
            'endformatted' => '',
            'durationformatted' => get_string('milestone', 'local_reschedule'),
        ]);
        continue;
    }
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

    if (empty($item['startenabled']) || empty($item['endenabled'])) {
        $durstr = '—';
    }

    $effortsource = (string)($item['effortsource'] ?? 'default');
    $effortvalue = (float)($item['effort'] ?? 0);
    $item['effortpluginname'] = $modulepluginnames[(int)($item['cmid'] ?? 0)] ?? '';
    $effortsourcelabel = $effortsourcelabels[$effortsource] ?? $effortsourcelabels['default'];
    if ($effortsource === 'adapter' && $item['effortpluginname'] !== '') {
        $effortsourcelabel .= ' ' . $item['effortpluginname'];
    }
    $effortdescription = $effortsourcelabel . ': ' .
        format_float($effortvalue, 10, true, true) . ' ' . get_string('efforthoursabbr', 'local_reschedule');
    $itemsforview[] = array_merge($item, [
        'startformatted' => !empty($item['startenabled']) ? userdate($item['datestart'], $dateformat) : '',
        'endformatted' => !empty($item['endenabled']) ? userdate($item['dateend'], $dateformat) : '',
        'durationformatted' => $durstr,
        'efforteditable' => $item['efforteditable'],
        'effortformatted' => number_format($effortvalue, 1, '.', ''),
        'effortdescription' => $effortdescription,
    ]);
}
unset($item);

$courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);
$backurl = $returnurl !== '' ? $returnurl : $courseurl->out(false);

$templatecontext = [
    'courseid' => $courseid,
    'coursename' => $course->fullname,
    'courseurl' => $courseurl->out(),
    'backurl' => $backurl,
    'hasreturnurl' => $returnurl !== '',
    'coursestart' => $coursestart,
    'courseend' => $courseend,
    'coursestartstr' => userdate($coursestart, $dateformat),
    'courseendstr' => userdate($courseend, $dateformat),
    'timelineStart' => $timelinestart,
    'timelineEnd' => $timelineend,
    'hasitems' => !empty($itemsforview),
    'itemscount' => count($itemsforview),
    'items' => $itemsforview,
    'itemsjson' => json_encode($items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
    'showweekify' => $course->format === 'weeks' && !empty($itemsforview),
    'editing' => $editing,
    'readonly' => !$editing,
    'forceeditmode' => $forceeditmode,
    'scopededitmode' => $scopededitmode,
    'scopecmid' => $scopecmid,
    'focuscmid' => $singlecmid,
    'scopeactivityname' => $scopeactivityname,
    'returnurl' => $returnurl,
    'effortmodel' => \local_reschedule\effort_store::get_model($courseid),
    'language' => $localecldr,
    'datetimeformat' => $dateformat,
    'dateonlyformat' => $dateonlyformat,
    'usertimezone' => \core_date::get_user_timezone(),
];

$PAGE->requires->js_call_amd('local_reschedule/reschedule_calendar', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_reschedule/reschedule', $templatecontext);
echo $OUTPUT->footer();
