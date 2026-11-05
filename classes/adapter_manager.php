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
use local_reschedule\adapter\editdates_bridge;
use local_reschedule\adapter\generic_adapter;
use local_reschedule\adapter\availability_date_adapter;
use local_reschedule\adapter\milestone_adapter;
use local_reschedule\adapter\date_fields_interface;
use local_reschedule\adapter\core\assign_adapter;
use local_reschedule\adapter\core\chat_adapter;
use local_reschedule\adapter\core\choice_adapter;
use local_reschedule\adapter\core\data_adapter;
use local_reschedule\adapter\core\feedback_adapter;
use local_reschedule\adapter\core\forum_adapter;
use local_reschedule\adapter\core\glossary_adapter;
use local_reschedule\adapter\core\lesson_adapter;
use local_reschedule\adapter\core\questionnaire_adapter;
use local_reschedule\adapter\core\quiz_adapter;
use local_reschedule\adapter\core\scorm_adapter;
use local_reschedule\adapter\core\workshop_adapter;
use local_reschedule\adapter\core\zoom_adapter;
use local_reschedule\adapter\community\attendance_adapter;
use local_reschedule\adapter\community\kuet_adapter;
use local_reschedule\adapter\community\quest_adapter;

/**
 * Adapter Manager for resolving the appropriate date adapter.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adapter_manager {
    /** @var int Estimated effort for activity types without a specific weight. */
    private const DEFAULT_EFFORT = 5;

    /** @var array<string, int> Estimated total work hours by Moodle activity module. */
    private const EFFORT_WEIGHTS = [
        'offlinequiz' => 0,
        'choice' => 1,
        'feedback' => 1,
        'questionnaire' => 1,
        'forum' => 2,
        'chat' => 2,
        'zoom' => 2,
        'quiz' => 3,
        'lesson' => 3,
        'scorm' => 3,
        'glossary' => 3,
        'data' => 3,
        'kuet' => 3,
        'assign' => 10,
        'workshop' => 10,
        'quest' => 1,
        'attendance' => 0,
    ];

    /** @var array Cache of instantiated adapters by course id and adapter class. */
    private static array $adapters = [];

    /** @var array Cache of availability adapters by course id. */
    private static array $availabilityadapters = [];

    /**
     * Discard adapters that hold a course record from an earlier schedule read.
     */
    public static function reset_cache(): void {
        self::$adapters = [];
        self::$availabilityadapters = [];
    }

    /**
     * Estimate total effort for one dated activity interval from its module type.
     *
     * These are planning weights, not measured student work. The chart spreads
     * each weight over the item's dated interval according to the chosen model.
     *
     * @param string $modname Moodle activity module name.
     * @return int Estimated work hours, from 0 to 10.
     */
    public static function get_effort(string $modname): int {
        return self::EFFORT_WEIGHTS[$modname] ?? self::DEFAULT_EFFORT;
    }

    /**
     * Resolve the most appropriate adapter for the given item.
     *
     * Resolution order:
     * 1. Core adapters bundled with Reschedule.
     * 2. Adapter supplied by the owning activity plugin.
     * 3. Local community adapters for modules without a plugin adapter.
     * 4. report_editdates extractor bridge.
     * 5. Generic adapter based on the mapping configuration.
     *
     * @param string $modname Activity module name or table.
     * @param \stdClass $course Course object.
     * @param array $item Reschedule item metadata.
     * @return adapter_interface
     */
    public static function get_adapter(string $modname, \stdClass $course, array $item): adapter_interface {
        // 1. Core adapters own native Moodle activity behavior.
        $coreclasses = [
            workshop_adapter::class,
            assign_adapter::class,
            forum_adapter::class,
            chat_adapter::class,
            choice_adapter::class,
            data_adapter::class,
            feedback_adapter::class,
            glossary_adapter::class,
            lesson_adapter::class,
            questionnaire_adapter::class,
            quiz_adapter::class,
            scorm_adapter::class,
            zoom_adapter::class,
        ];

        foreach ($coreclasses as $cls) {
            $adapter = self::get_instance($cls, $course);
            if ($adapter->supports($modname, $item)) {
                return $adapter;
            }
        }

        // 2. Let the activity plugin provide the integration when it has one.
        $pluginadapterclass = self::get_plugin_adapter_class($modname, $course, $item);
        if ($pluginadapterclass !== null) {
            $adapter = self::get_instance($pluginadapterclass, $course);
            if ($adapter->supports($modname, $item)) {
                return $adapter;
            }
        }

        // 3. Community adapters are local integrations for modules that do
        // not yet provide their own adapter contract.
        foreach ([attendance_adapter::class, kuet_adapter::class, quest_adapter::class] as $cls) {
            $adapter = self::get_instance($cls, $course);
            if ($adapter->supports($modname, $item)) {
                return $adapter;
            }
        }

        // 4. Use report_editdates extractors only if no adapter owns the item.
        $bridge = self::get_instance(editdates_bridge::class, $course);
        if ($bridge->supports($modname, $item)) {
            return $bridge;
        }

        // Point fields with no module-specific owner use the standard
        // milestone saver after plugin and external date integrations decline.
        if (!empty($item['ismilestone'])) {
            return self::get_instance(milestone_adapter::class, $course);
        }

        // 5. The mapping-backed adapter is the final fallback.
        return self::get_instance(generic_adapter::class, $course);
    }

    /**
     * Resolve the adapter that can describe additional date fields.
     *
     * Adapters with a native date-fields implementation take precedence.
     * The editdates bridge is used only when the selected schedule adapter does
     * not expose that optional API.
     *
     * @param string $modname Activity module name.
     * @param \stdClass $course Course record.
     * @param array $item Partial or complete item metadata.
     * @return date_fields_interface|null Provider of additional date fields.
     */
    public static function get_date_fields_adapter(
        string $modname,
        \stdClass $course,
        array $item
    ): ?date_fields_interface {
        $adapter = self::get_adapter($modname, $course, $item);
        if ($adapter instanceof date_fields_interface) {
            return $adapter;
        }

        $bridge = self::get_instance(editdates_bridge::class, $course);
        return $bridge->supports($modname, $item) ? $bridge : null;
    }

    /**
     * Find the optional adapter class supplied by an activity plugin.
     *
     * Plugins expose their integration as `\\mod_PLUGIN\\local\\reschedule\\adapter`
     * in `classes/local/reschedule/adapter.php`. Moodle's autoloader means no
     * plugin files are scanned or included manually. The class is loaded only
     * while local_reschedule is resolving an installed module, so the activity
     * plugin remains installable when local_reschedule is absent.
     *
     * @param string $modname Candidate activity module name.
     * @param \stdClass $course Course record.
     * @param array $item Reschedule item metadata.
     * @return class-string<adapter_interface>|null Compatible plugin adapter class.
     */
    private static function get_plugin_adapter_class(
        string $modname,
        \stdClass $course,
        array $item
    ): ?string {
        $pluginname = self::get_owning_module_name($modname, $course, $item);
        if ($pluginname === null) {
            return null;
        }

        $class = '\\mod_' . $pluginname . '\\local\\reschedule\\adapter';
        if (!class_exists($class)) {
            return null;
        }
        if (!is_subclass_of($class, adapter_interface::class)) {
            debugging(
                "Activity adapter {$class} must implement " . adapter_interface::class . '.',
                DEBUG_DEVELOPER
            );
            return null;
        }
        return $class;
    }

    /**
     * Resolve a child table or item back to its owning activity module.
     *
     * The course-module id is authoritative. The supplied module name is used
     * only when it identifies an installed activity plugin directly.
     *
     * @param string $modname Candidate activity module name.
     * @param \stdClass $course Course record.
     * @param array $item Reschedule item metadata.
     * @return string|null Installed activity module name.
     */
    private static function get_owning_module_name(
        string $modname,
        \stdClass $course,
        array $item
    ): ?string {
        $cmid = (int)($item['cmid'] ?? 0);
        if ($cmid > 0) {
            $cms = get_fast_modinfo((int)$course->id)->get_cms();
            if (isset($cms[$cmid])) {
                $cm = $cms[$cmid];
                return $cm->modname;
            }
        }

        return \core_component::get_plugin_directory('mod', $modname) !== null ? $modname : null;
    }

    /**
     * Get the adapter responsible for core date-availability conditions.
     *
     * Availability restrictions are a separate date source from the module's
     * own start/end fields, so they must not replace the normal activity
     * adapter selected by get_adapter().
     *
     * @param \stdClass $course Course record.
     * @return availability_date_adapter
     */
    public static function get_availability_adapter(\stdClass $course): availability_date_adapter {
        $key = (int)$course->id;
        if (!isset(self::$availabilityadapters[$key])) {
            self::$availabilityadapters[$key] = new availability_date_adapter($course);
        }
        return self::$availabilityadapters[$key];
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
