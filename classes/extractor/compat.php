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

/**
 * Compatibility shim providing report_editdates base classes if report_editdates is not installed.
 *
 * This allows mod_{$modname}_report_editdates_integration classes across Moodle plugins
 * to load and execute cleanly without requiring the report_editdates plugin.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class compat {

    /** @var bool Flag indicating if compatibility classes have been declared. */
    private static bool $initialized = false;

    /**
     * Ensure report_editdates_date_setting and report_editdates_mod_date_extractor exist.
     */
    public static function init(): void {
        global $CFG;

        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // If report_editdates is installed, load its library if not already loaded.
        $libfile = $CFG->dirroot . '/report/editdates/lib.php';
        if (file_exists($libfile)) {
            require_once($libfile);
        }

        // Define report_editdates_date_setting if not defined.
        if (!class_exists('report_editdates_date_setting', false)) {
            class_alias(
                '\local_reschedule\extractor\shim_date_setting',
                'report_editdates_date_setting'
            );
        }

        // Define report_editdates_mod_date_extractor if not defined.
        if (!class_exists('report_editdates_mod_date_extractor', false)) {
            class_alias(
                '\local_reschedule\extractor\shim_mod_date_extractor',
                'report_editdates_mod_date_extractor'
            );
        }
    }
}

/**
 * Fallback date setting class matching report_editdates_date_setting API.
 */
class shim_date_setting {
    public $label;
    public $currentvalue;
    public $type;
    public $isoptional;
    public $getstep;

    public function __construct($label, $currentvalue, $type, $isoptional, $getstep = 1) {
        $this->label = $label;
        $this->currentvalue = $currentvalue;
        $this->type = $type;
        $this->isoptional = $isoptional;
        $this->getstep = $getstep;
    }
}

/**
 * Fallback base extractor class matching report_editdates_mod_date_extractor API.
 */
abstract class shim_mod_date_extractor {
    const DATE = 'date_selector';
    const DATETIME = 'date_time_selector';

    protected $course;
    protected $type;
    protected $mods;

    public function __construct($course, $type) {
        $this->course = $course;
        $this->type = $type;
    }

    public static function make($modname, $course) {
        return \local_reschedule\extractor\extractor_factory::get_extractor($modname, $course);
    }

    public function load_data() {
        global $DB;
        try {
            $this->mods = $DB->get_records($this->type, ['course' => $this->course->id]);
        } catch (\dml_exception $e) {
            $this->mods = [];
        }
    }

    abstract public function get_settings(\cm_info $cm);
    abstract public function validate_dates(\cm_info $cm, array $dates);

    public function save_dates(\cm_info $cm, array $dates) {
        global $DB;
        $updateobj = new \stdClass();
        $updateobj->id = $cm->instance;
        foreach ($this->get_settings($cm) as $name => $setting) {
            if (array_key_exists($name, $dates)) {
                $updateobj->$name = $dates[$name];
            }
        }
        $updateobj->timemodified = time();
        $DB->update_record($this->type, $updateobj);
    }
}
