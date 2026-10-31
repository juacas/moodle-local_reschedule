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

namespace local_reschedule\customfield;

/**
 * Private custom-field area used to store one effort document per course.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class effort_handler extends \core_customfield\handler {
    /**
     * Load data for the requested fields, keeping compatibility with Moodle 4.5.
     *
     * Newer core versions provide this wrapper on the handler, while Moodle
     * 4.5 exposes the equivalent operation on core_customfield\api.
     *
     * @param \core_customfield\field_controller[] $fields Fields keyed by ID.
     * @param int $instanceid Course ID.
     * @param bool $adddefaults Include controllers for fields without saved data.
     * @return \core_customfield\data_controller[] Data controllers keyed by field ID.
     */
    public function get_instance_fields_data(array $fields, int $instanceid, bool $adddefaults = true): array {
        if (method_exists(\core_customfield\handler::class, __FUNCTION__)) {
            return parent::get_instance_fields_data($fields, $instanceid, $adddefaults);
        }

        return \core_customfield\api::get_instance_fields_data($fields, $instanceid, $adddefaults);
    }

    /**
     * Return the field configuration context.
     * @return \context Configuration context.
     */
    public function get_configuration_context(): \context {
        return \context_system::instance();
    }

    /**
     * Return the field configuration URL.
     * @return \moodle_url Configuration URL.
     */
    public function get_configuration_url(): \moodle_url {
        return new \moodle_url('/admin/settings.php', ['section' => 'local_reschedule']);
    }

    /**
     * Return an effort document context.
     * @param int $instanceid Course id.
     * @return \context Course context.
     */
    public function get_instance_context(int $instanceid = 0): \context {
        return $instanceid ? \context_course::instance($instanceid) : \context_system::instance();
    }

    /**
     * Check configuration access.
     * @return bool Whether fields can be configured.
     */
    public function can_configure(): bool {
        return has_capability('moodle/site:config', \context_system::instance());
    }

    /**
     * Check edit access.
     * @param \core_customfield\field_controller $field Field.
     * @param int $instanceid Course id.
     * @return bool
     */
    public function can_edit(\core_customfield\field_controller $field, int $instanceid = 0): bool {
        return $instanceid > 0 && has_capability('moodle/course:manageactivities', $this->get_instance_context($instanceid));
    }

    /**
     * Check view access.
     * @param \core_customfield\field_controller $field Field.
     * @param int $instanceid Course id.
     * @return bool
     */
    public function can_view(\core_customfield\field_controller $field, int $instanceid): bool {
        return has_capability('moodle/course:view', $this->get_instance_context($instanceid));
    }
}
