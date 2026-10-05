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
 * Library callbacks for local_reschedule.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extend course navigation with a link to Reschedule.
 *
 * @param navigation_node $parentnode Course navigation node.
 * @param stdClass $course Course object.
 * @param context_course $context Course context.
 */
function local_reschedule_extend_navigation_course(\navigation_node $parentnode, \stdClass $course, \context_course $context) {
    if (has_capability('moodle/course:manageactivities', $context)) {
        $url = new \moodle_url('/local/reschedule/index.php', ['id' => $course->id]);
        $node = \navigation_node::create(
            get_string('reschedule', 'local_reschedule'),
            $url,
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_reschedule',
            new \pix_icon('i/calendar', '')
        );
        $parentnode->add_node($node);
    }
}

/**
 * Add a scoped Reschedule link to a module's administration menu when it has child rows.
 *
 * @param settings_navigation $settings Module settings navigation.
 * @param context $context Current page context.
 */
function local_reschedule_extend_settings_navigation(\settings_navigation $settings, \context $context): void {
    global $PAGE;

    if ($context->contextlevel !== CONTEXT_MODULE) {
        return;
    }

    $cm = $PAGE->cm;
    if (!$cm || (int)$cm->id !== (int)$context->instanceid) {
        return;
    }
    $coursecontext = \context_course::instance((int)$cm->course);
    if (!has_capability('moodle/course:manageactivities', $coursecontext) ||
            !has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    $modulesettings = $settings->get('modulesettings');
    if (!$modulesettings || !\local_reschedule\manager::cm_has_subactivities((int)$cm->course, (int)$cm->id)) {
        return;
    }

    $url = new \moodle_url('/local/reschedule/index.php', [
        'id' => (int)$cm->course,
        'cmid' => (int)$cm->id,
        'editmode' => 1,
    ]);
    $modulesettings->add(
        get_string('reschedulemodule', 'local_reschedule'),
        $url,
        \navigation_node::TYPE_SETTING,
        null,
        'local_reschedule_cmid',
        new \pix_icon('i/calendar', '')
    );
}

/**
 * Whether Reschedule may edit dates in this request.
 *
 * The URL override is limited to Reschedule and does not change Moodle's
 * course-wide editing preference. Callers must set the page context and
 * editing capability before using this check.
 *
 * @param bool $forceeditmode Whether editmode=1 was requested.
 * @return bool
 */
function local_reschedule_user_is_editing(bool $forceeditmode): bool {
    global $PAGE;

    return $PAGE->user_is_editing() || ($forceeditmode && $PAGE->user_allowed_editing());
}
