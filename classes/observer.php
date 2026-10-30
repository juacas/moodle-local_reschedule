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

namespace local_reschedule;

/**
 * Keeps effort metadata consistent with course and module deletion.
 *
 * @package local_reschedule
 * @copyright 2026 Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Remove metadata for a deleted module.
     *
     * @param \core\event\course_module_deleted $event Deletion event.
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        effort_store::delete_course_module((int)$event->courseid, (int)$event->objectid);
    }

    /**
     * Remove metadata for a deleted course.
     *
     * @param \core\event\course_deleted $event Deletion event.
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        effort_store::delete_course((int)$event->objectid);
    }
}
