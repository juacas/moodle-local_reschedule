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

/**
 * Hook listeners for local_reschedule.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_reschedule;

use core\hook\output\before_standard_head_html_generation;

/**
 * Output hook callbacks for Reschedule.
 */
class hook_listener {
    /**
     * Add Reschedule shortcuts beside supported module edit form date fields.
     *
     * The modedit.php update parameter contains a course module ID. It is
     * absent when creating an activity, so creation forms are ignored.
     *
     * @param before_standard_head_html_generation $hook Hook instance.
     */
    public static function before_standard_head_html_generation_listener(
        before_standard_head_html_generation $hook
    ): void {
        $page = $hook->renderer->get_page();
        if (!str_ends_with($page->url->get_path(), '/course/modedit.php')) {
            return;
        }

        $cmid = optional_param('update', 0, PARAM_INT);
        if ($cmid <= 0) {
            return;
        }

        $cm = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $coursecontext = \context_course::instance((int)$cm->course);
        if (!has_capability('moodle/course:manageactivities', $coursecontext) ||
                !manager::cm_is_supported((int)$cm->course, (int)$cm->id)) {
            return;
        }

        $url = new \moodle_url('/local/reschedule/index.php', [
            'id' => (int)$cm->course,
            'cmid' => (int)$cm->id,
            'returnurl' => $page->url->out(false),
        ]);
        $page->requires->js_call_amd('local_reschedule/modform_link', 'init', [
            $url->out(false),
            $hook->renderer->image_url('icon', 'local_reschedule')->out(false),
            get_string('reschedulefromdatefield', 'local_reschedule'),
        ]);
        $page->requires->js_call_amd('local_reschedule/return_navigation', 'init', [get_string('configuration')]);
    }
}
