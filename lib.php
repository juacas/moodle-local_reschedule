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

defined('MOODLE_INTERNAL') || die();

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
