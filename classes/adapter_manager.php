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

namespace local_reschedule;

use local_reschedule\adapter\adapter_interface;
use local_reschedule\adapter\assign_adapter;
use local_reschedule\adapter\quiz_adapter;
use local_reschedule\adapter\workshop_adapter;
use local_reschedule\adapter\quest_adapter;
use local_reschedule\adapter\kuet_adapter;
use local_reschedule\adapter\editdates_bridge;
use local_reschedule\adapter\generic_adapter;

defined('MOODLE_INTERNAL') || die();

/**
 * Adapter Manager for resolving the appropriate date adapter.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adapter_manager {

    /** @var array Cache of instantiated adapters by course id and adapter class. */
    private static array $adapters = [];

    /**
     * Resolve the most appropriate adapter for the given item.
     *
     * Resolution order:
     * 1. Specific local_reschedule adapters (handling complex workflows, subactivities, or dependent dates).
     * 2. report_editdates discovery bridge (if report_editdates or module integration exists).
     * 3. Generic safe fallback adapter.
     *
     * @param string $modname Activity module name or table.
     * @param \stdClass $course Course object.
     * @param array $item Reschedule item metadata.
     * @return adapter_interface
     */
    public static function get_adapter(string $modname, \stdClass $course, array $item): adapter_interface {
        $courseid = (int)$course->id;

        // 1. Specific local_reschedule adapters.
        $specificclasses = [
            kuet_adapter::class,
            quest_adapter::class,
            workshop_adapter::class,
            assign_adapter::class,
            quiz_adapter::class,
        ];

        foreach ($specificclasses as $cls) {
            $adapter = self::get_instance($cls, $course);
            if ($adapter->supports($modname, $item)) {
                return $adapter;
            }
        }

        // 2. report_editdates discovery bridge.
        $bridge = self::get_instance(editdates_bridge::class, $course);
        if ($bridge->supports($modname, $item)) {
            return $bridge;
        }

        // 3. Generic safe fallback.
        return self::get_instance(generic_adapter::class, $course);
    }

    /**
     * Get or create adapter instance cached per course.
     *
     * @param string $class Class name.
     * @param \stdClass $course Course object.
     * @return adapter_interface
     */
    private static function get_instance(string $class, \stdClass $course): adapter_interface {
        $key = $course->id . '_' . $class;
        if (!isset(self::$adapters[$key])) {
            self::$adapters[$key] = new $class($course);
        }
        return self::$adapters[$key];
    }
}
