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
 * AJAX external function declarations for Activity Rescheduler.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_reschedule_save_schedule' => [
        'classname' => \local_reschedule\external\save_schedule::class,
        'methodname' => 'execute',
        'description' => 'Validate and save changes to course activity dates.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_reschedule_weekify' => [
        'classname' => \local_reschedule\external\weekify::class,
        'methodname' => 'execute',
        'description' => 'Preview or apply placement of activities into weekly sections.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_reschedule_save_efforts' => [
        'classname' => \local_reschedule\external\save_efforts::class,
        'methodname' => 'execute',
        'description' => 'Save course activity effort estimates.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:manageactivities',
    ],
];
