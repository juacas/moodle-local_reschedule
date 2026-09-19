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
 * Fallback date setting matching the report_editdates API.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shim_date_setting {
    /** @var string Setting label. */
    public $label;

    /** @var mixed Current setting value. */
    public $currentvalue;

    /** @var string Setting type. */
    public $type;

    /** @var bool Whether the setting is optional. */
    public $isoptional;

    /** @var int Step used by the date selector. */
    public $getstep;

    /**
     * Create a date setting.
     *
     * @param string $label Setting label.
     * @param mixed $currentvalue Current value.
     * @param string $type Setting type.
     * @param bool $isoptional Whether the setting is optional.
     * @param int $getstep Selector step.
     */
    public function __construct($label, $currentvalue, $type, $isoptional, $getstep = 1) {
        $this->label = $label;
        $this->currentvalue = $currentvalue;
        $this->type = $type;
        $this->isoptional = $isoptional;
        $this->getstep = $getstep;
    }
}
