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
 * Native date adapter for the glossary activity.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class glossary_adapter extends base_date_adapter {
    #[\Override]
    protected function get_module_name(): string {
        return 'glossary';
    }

    /**
     * Describe Glossary assessment dates.
     *
     * @return array<string, array{label:string, component?:string, type?:string, optional?:bool}>
     */
    protected function get_field_definitions(): array {
        return [
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
        return !empty($cmrecord->assessed) && ((int)$cmrecord->assesstimestart !== 0 ||
            (int)$cmrecord->assesstimefinish !== 0);
    }

    /**
     * Return this module's date ordering rules.
     *
     * @return array<int, array{0:string, 1:string, 2:string, 3?:bool}>
     */
    protected function get_date_relationships(): array {
        return [['assesstimestart', 'assesstimefinish', 'assesstimefinish']];
    }
}
