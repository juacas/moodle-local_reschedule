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

namespace local_reschedule\adapter\core;

/**
 * Native date adapter for the data activity.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_adapter extends base_date_adapter {
    #[\Override]
    protected function get_module_name(): string {
        return 'data';
    }

    /**
     * Describe Database availability, viewing and assessment dates.
     *
     * @return array<string, array{label:string, component?:string, type?:string, optional?:bool}>
     */
    protected function get_field_definitions(): array {
        return [
            'timeavailablefrom' => ['label' => 'availablefromdate', 'optional' => true],
            'timeavailableto' => ['label' => 'availabletodate', 'optional' => true],
            'timeviewfrom' => ['label' => 'viewfromdate', 'optional' => true],
            'timeviewto' => ['label' => 'viewtodate', 'optional' => true],
            'assesstimestart' => ['label' => 'from', 'component' => 'moodle', 'optional' => false],
            'assesstimefinish' => ['label' => 'to', 'component' => 'moodle', 'optional' => false],
        ];
    }

    /**
     * Decide whether a configured field is available on this activity.
     *
     * @param \stdClass $cmrecord Module record.
     * @param string $field Field name.
     * @return bool Whether to expose the date field.
     */
    protected function should_show_field(\stdClass $cmrecord, string $field): bool {
        if (in_array($field, ['assesstimestart', 'assesstimefinish'], true)) {
            return !empty($cmrecord->assessed) && ((int)$cmrecord->assesstimestart !== 0 ||
                (int)$cmrecord->assesstimefinish !== 0);
        }
        return true;
    }

    /**
     * Return this module's date ordering rules.
     *
     * @return array<int, array{0:string, 1:string, 2:string, 3?:bool}>
     */
    protected function get_date_relationships(): array {
        return [['timeavailablefrom', 'timeavailableto', 'assesstimefinish'],
            ['timeviewfrom', 'timeviewto', 'assesstimefinish'],
            ['assesstimestart', 'assesstimefinish', 'assesstimefinish']];
    }
}
