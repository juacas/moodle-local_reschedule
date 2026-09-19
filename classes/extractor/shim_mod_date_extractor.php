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
 * Fallback base extractor matching the report_editdates API.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class shim_mod_date_extractor {
    /** @var string Date selector type. */
    public const DATE = 'date_selector';

    /** @var string Date and time selector type. */
    public const DATETIME = 'date_time_selector';

    /** @var \stdClass Course record. */
    protected $course;

    /** @var string Module table name. */
    protected $type;

    /** @var array Loaded module records. */
    protected $mods;

    /**
     * Create an extractor.
     *
     * @param \stdClass $course Course record.
     * @param string $type Module table name.
     */
    public function __construct($course, $type) {
        $this->course = $course;
        $this->type = $type;
    }

    /**
     * Resolve an extractor through local_reschedule.
     *
     * @param string $modname Module name.
     * @param \stdClass $course Course record.
     * @return \report_editdates_mod_date_extractor|null Extractor instance.
     */
    public static function make($modname, $course) {
        return \local_reschedule\extractor\extractor_factory::get_extractor($modname, $course);
    }

    /**
     * Load module records for the configured course.
     */
    public function load_data() {
        global $DB;
        try {
            $this->mods = $DB->get_records($this->type, ['course' => $this->course->id]);
        } catch (\dml_exception $e) {
            $this->mods = [];
        }
    }

    /**
     * Return date settings for an activity.
     *
     * @param \cm_info $cm Course module.
     * @return array Date settings.
     */
    abstract public function get_settings(\cm_info $cm);

    /**
     * Validate proposed dates.
     *
     * @param \cm_info $cm Course module.
     * @param array $dates Proposed dates.
     * @return array Validation errors.
     */
    abstract public function validate_dates(\cm_info $cm, array $dates);

    /**
     * Persist proposed dates for an activity.
     *
     * @param \cm_info $cm Course module.
     * @param array $dates Proposed dates.
     */
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
