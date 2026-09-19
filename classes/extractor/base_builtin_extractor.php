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

defined('MOODLE_INTERNAL') || die();

// Ensure compatibility classes are declared before extending.
compat::init();

/**
 * Base class for all replicated built-in extractors in local_reschedule.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_builtin_extractor extends \report_editdates_mod_date_extractor {

    #[\Override]
    public function load_data() {
        global $DB;
        try {
            $this->mods = $DB->get_records($this->type, ['course' => $this->course->id]);
        } catch (\dml_exception $e) {
            $this->mods = [];
        }
    }

    /**
     * Helper to retrieve a validation error message, falling back to local_reschedule if report_editdates is not installed.
     *
     * @param string $identifier String identifier.
     * @param string $component Fallback component (default 'local_reschedule').
     * @param mixed $a Optional argument for get_string.
     * @return string Localized error string.
     */
    protected function get_error_string(string $identifier, string $component = 'local_reschedule', $a = null): string {
        $sm = get_string_manager();
        if ($sm->string_exists($identifier, 'report_editdates')) {
            return get_string($identifier, 'report_editdates', $a);
        }
        if ($sm->string_exists($identifier, $component)) {
            return get_string($identifier, $component, $a);
        }
        if ($sm->string_exists($identifier, 'moodle')) {
            return get_string($identifier, 'moodle', $a);
        }
        return $identifier;
    }
}
