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

namespace local_reschedule\event;

/**
 * The Reschedule page was viewed.
 *
 * @property-read array $other {
 *     Extra information about the event.
 *
 *     - int editing: Whether editing was enabled.
 *     - int itemcount: Number of rows shown.
 *     - int selectioncount: Number of course modules explicitly requested.
 * }
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reschedule_viewed extends \core\event\base {
    /**
     * Set event properties.
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventrescheduleviewed', 'local_reschedule');
    }

    /**
     * Describe the event.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '$this->userid' viewed Reschedule for the course with id '$this->courseid', " .
            "showing '{$this->other['itemcount']}' rows.";
    }

    /**
     * Return the Reschedule URL.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/reschedule/index.php', ['id' => $this->courseid]);
    }

    /**
     * Validate event data.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if ($this->contextlevel !== CONTEXT_COURSE) {
            throw new \coding_exception('Context level must be CONTEXT_COURSE.');
        }
        foreach (['editing', 'itemcount', 'selectioncount'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '$key' value must be set in other.");
            }
        }
    }

    /**
     * Event data does not reference restorable records.
     *
     * @return false
     */
    public static function get_other_mapping() {
        return false;
    }
}
