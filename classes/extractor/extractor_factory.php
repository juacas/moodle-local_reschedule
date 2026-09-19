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

namespace local_reschedule\extractor;

/**
 * Universal date extractor factory for local_reschedule.
 *
 * Implements full autonomous discovery of date extractors:
 * 1. Checks module's own mod_{modname}_report_editdates_integration (guaranteed to load via our compat shim).
 * 2. Checks local_reschedule's built-in replicated extractors.
 * 3. Falls back to report/editdates/mod/{modname}dates.php if report_editdates happens to be installed.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor_factory {
    /** @var array Cache of instantiated extractors keyed by courseid_modname. */
    private static array $extractors = [];

    /**
     * Get or create a date extractor for the given module and course.
     *
     * @param string $modname Activity module name (e.g. 'choice', 'data', 'forum', 'offlinequiz').
     * @param \stdClass $course Course database record.
     * @return \report_editdates_mod_date_extractor|null Date extractor instance or null if unsupported.
     */
    public static function get_extractor(string $modname, \stdClass $course): ?\report_editdates_mod_date_extractor {
        global $CFG;

        // Ensure compatibility classes (report_editdates_mod_date_extractor, report_editdates_date_setting) are declared.
        compat::init();

        $cachekey = $course->id . '_' . $modname;
        if (array_key_exists($cachekey, self::$extractors)) {
            return self::$extractors[$cachekey];
        }

        // 1. Check if the module provides its own auto-loaded integration class.
        $modclass = 'mod_' . $modname . '_report_editdates_integration';
        if (class_exists($modclass)) {
            try {
                $instance = new $modclass($course);
                self::$extractors[$cachekey] = $instance;
                return $instance;
            } catch (\Throwable $e) {
                debugging(
                    'Module extractor could not be instantiated: ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        // 2. Check local_reschedule replicated built-in extractors.
        $builtinclass = '\\local_reschedule\\extractor\\builtin\\' . $modname . '_extractor';
        if (class_exists($builtinclass)) {
            try {
                $instance = new $builtinclass($course);
                self::$extractors[$cachekey] = $instance;
                return $instance;
            } catch (\Throwable $e) {
                debugging(
                    'Built-in extractor could not be instantiated: ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        // Keep the fallback deliberately boring: only discover an external extractor here.
        // 3. Check the report_editdates folder if it is installed on the system.
        $editdatesfile = $CFG->dirroot . '/report/editdates/mod/' . $modname . 'dates.php';
        if (file_exists($editdatesfile)) {
            include_once($editdatesfile);
            $extclass = 'report_editdates_mod_' . $modname . '_date_extractor';
            if (class_exists($extclass)) {
                try {
                    $instance = new $extclass($course);
                    self::$extractors[$cachekey] = $instance;
                    return $instance;
                } catch (\Throwable $e) {
                    debugging(
                        'External report_editdates extractor could not be instantiated: ' . $e->getMessage(),
                        DEBUG_DEVELOPER
                    );
                }
            }
        }

        self::$extractors[$cachekey] = null;
        return null;
    }

    /**
     * Clear the extractor cache (useful for testing or course switching).
     */
    public static function reset_cache(): void {
        self::$extractors = [];
    }
}
