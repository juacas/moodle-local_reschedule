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
 * Event observers for Activity Rescheduler.
 *
 * @package local_reschedule
 * @copyright 2026 Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\local_reschedule\observer::course_structure_changed',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\local_reschedule\observer::course_structure_changed',
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\local_reschedule\observer::course_module_deleted',
    ],
    [
        'eventname' => '\core\event\course_updated',
        'callback' => '\local_reschedule\observer::course_updated',
    ],
    [
        'eventname' => '\core\event\course_section_created',
        'callback' => '\local_reschedule\observer::course_structure_changed',
    ],
    [
        'eventname' => '\core\event\course_section_updated',
        'callback' => '\local_reschedule\observer::course_structure_changed',
    ],
    [
        'eventname' => '\core\event\course_section_deleted',
        'callback' => '\local_reschedule\observer::course_structure_changed',
    ],
    [
        'eventname' => '\core\event\role_assigned',
        'callback' => '\local_reschedule\observer::access_changed',
    ],
    [
        'eventname' => '\core\event\role_unassigned',
        'callback' => '\local_reschedule\observer::access_changed',
    ],
    [
        'eventname' => '\core\event\role_capabilities_updated',
        'callback' => '\local_reschedule\observer::access_changed',
    ],
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback' => '\local_reschedule\observer::access_changed',
    ],
    [
        'eventname' => '\core\event\user_enrolment_updated',
        'callback' => '\local_reschedule\observer::access_changed',
    ],
    [
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback' => '\local_reschedule\observer::access_changed',
    ],
    [
        'eventname' => '\core\event\course_deleted',
        'callback' => '\local_reschedule\observer::course_deleted',
    ],
];
