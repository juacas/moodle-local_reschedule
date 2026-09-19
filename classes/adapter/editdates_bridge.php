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

namespace local_reschedule\adapter;

defined('MOODLE_INTERNAL') || die();

/**
 * Bridge adapter to report_editdates integration if available.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editdates_bridge extends base_adapter {

    /**
     * Check whether date extractor infrastructure is available for this module.
     * Uses local_reschedule's autonomous extractor_factory (compatible with report_editdates if installed,
     * but fully functional without it).
     *
     * @param string $modname Module name.
     * @param \stdClass $course Course object.
     * @return object|null Extractor instance if available.
     */
    public static function get_extractor(string $modname, \stdClass $course): ?object {
        return \local_reschedule\extractor\extractor_factory::get_extractor($modname, $course);
    }

    #[\Override]
    public function supports(string $modname, array $item): bool {
        // Editdates extractors handle main course modules, not child subtypes directly.
        if (!empty($item['issubtype'])) {
            return false;
        }

        $extractor = self::get_extractor($modname, $this->course);
        return ($extractor !== null);
    }

    #[\Override]
    public function validate(array $item, int $newstart, int $newend): array {
        global $DB;
        $errors = parent::validate($item, $newstart, $newend);
        if (!empty($errors)) {
            return $errors;
        }

        $modname = $item['table'];
        $extractor = self::get_extractor($modname, $this->course);
        if (!$extractor) {
            return [];
        }

        $cm = $this->get_cm($modname, (int)$item['recordid']);
        if (!$cm) {
            return [];
        }

        try {
            $settings = $extractor->get_settings($cm);
            if (empty($settings)) {
                return [];
            }

            $record = $DB->get_record($modname, ['id' => $cm->instance]);
            if (!$record) {
                return [];
            }

            // Build full dates payload expected by the extractor.
            $dates = [];
            foreach ($settings as $fieldname => $setting) {
                $dates[$fieldname] = isset($record->{$fieldname}) ? (int)$record->{$fieldname} : 0;
            }

            // Map timeline start and end cols.
            $startcol = $item['startcol'];
            $endcol = $item['endcol'];
            $dates[$startcol] = $newstart;
            $dates[$endcol] = $newend;

            // Handle dependent fields for assign (e.g. cutoffdate / gradingduedate).
            if ($modname === 'assign') {
                if (!empty($dates['cutoffdate']) && $dates['cutoffdate'] < $newend) {
                    $dates['cutoffdate'] = $newend;
                }
                if (!empty($dates['gradingduedate']) && $dates['gradingduedate'] < $newend) {
                    $dates['gradingduedate'] = $newend + (7 * 86400);
                }
            }

            $moderrors = $extractor->validate_dates($cm, $dates);
            if (!empty($moderrors)) {
                $title = $item['title'] ?? $cm->name;
                foreach ($moderrors as $field => $msg) {
                    $errors[] = "{$title} ({$field}): {$msg}";
                }
            }
        } catch (\Throwable $e) {
            // If validation threw unexpectedly, do not block if parent validation passed.
        }

        return $errors;
    }

    #[\Override]
    public function save(array $item, int $newstart, int $newend): void {
        global $DB;

        $modname = $item['table'];
        $extractor = self::get_extractor($modname, $this->course);
        $cm = $this->get_cm($modname, (int)$item['recordid']);

        if (!$extractor || !$cm) {
            $this->raw_update_record($modname, (int)$item['recordid'], $item['startcol'], $item['endcol'], $newstart, $newend);
            return;
        }

        $settings = $extractor->get_settings($cm);
        $record = $DB->get_record($modname, ['id' => $cm->instance]);
        if (!$record) {
            return;
        }

        $dates = [];
        foreach ($settings as $fieldname => $setting) {
            $dates[$fieldname] = isset($record->{$fieldname}) ? (int)$record->{$fieldname} : 0;
        }

        $startcol = $item['startcol'];
        $endcol = $item['endcol'];
        $dates[$startcol] = $newstart;
        $dates[$endcol] = $newend;

        // Auto-adjust dependent dates for assignment to prevent database validation conflicts.
        if ($modname === 'assign') {
            if (!empty($dates['cutoffdate']) && $dates['cutoffdate'] < $newend) {
                $dates['cutoffdate'] = $newend;
            }
            if (!empty($dates['gradingduedate']) && $dates['gradingduedate'] < $newend) {
                $dates['gradingduedate'] = $newend + (7 * 86400);
            }
        }

        $extractor->save_dates($cm, $dates);
        $this->trigger_cm_updated($cm);
    }
}
