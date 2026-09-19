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

namespace local_reschedule\extractor\builtin;

use cm_info;
use local_reschedule\extractor\base_builtin_extractor;
use report_editdates_date_setting;

defined('MOODLE_INTERNAL') || die();

/**
 * Replicated date extractor for the glossary activity module.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class glossary_extractor extends base_builtin_extractor {

    public function __construct($course) {
        parent::__construct($course, 'glossary');
        parent::load_data();
    }

    #[\Override]
    public function get_settings(cm_info $cm) {
        if (!isset($this->mods[$cm->instance])) {
            return null;
        }
        $mod = $this->mods[$cm->instance];

        if (!empty($mod->assessed) && ($mod->assesstimestart != 0 || $mod->assesstimefinish != 0)) {
            return [
                'assesstimestart' => new report_editdates_date_setting(
                    get_string('from'),
                    $mod->assesstimestart,
                    self::DATETIME, false
                ),
                'assesstimefinish' => new report_editdates_date_setting(
                    get_string('to'),
                    $mod->assesstimefinish,
                    self::DATETIME, false
                ),
            ];
        }
        return null;
    }

    #[\Override]
    public function validate_dates(cm_info $cm, array $dates) {
        $errors = [];
        if (!empty($dates['assesstimestart']) && !empty($dates['assesstimefinish'])
                && $dates['assesstimefinish'] < $dates['assesstimestart']) {
            $errors['assesstimefinish'] = $this->get_error_string('assesstimefinish');
        }
        return $errors;
    }
}
