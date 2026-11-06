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

/**
 * Bridge adapter to report_editdates integration if available.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editdates_bridge extends base_adapter implements date_fields_interface {
    /** @var array Cache of inferred date dependencies keyed by CM and field list. */
    private static array $dependencycache = [];

    /** @var array Cache of inferred parent-boundary constraints. */
    private static array $parentboundscache = [];

    /**
     * Discard date relationships inferred from an earlier schedule read.
     */
    public static function reset_cache(): void {
        self::$dependencycache = [];
        self::$parentboundscache = [];
    }

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

    /**
     * Expose extractor settings through the optional adapter date-field API.
     *
     * @param \cm_info $cm Course module.
     * @return array<string, array{label:string, value:int, type:string, optional:bool}>
     */
    public function get_date_fields(\cm_info $cm): array {
        $extractor = self::get_extractor($cm->modname, $this->course);
        if (!$extractor) {
            return [];
        }
        $settings = $extractor->get_settings($cm);
        if (!is_array($settings)) {
            return [];
        }
        $fields = [];
        foreach ($settings as $name => $setting) {
            if (!isset($setting->currentvalue, $setting->type)) {
                continue;
            }
            if (!in_array($setting->type, ['date_selector', 'date_time_selector'], true)) {
                continue;
            }
            $fields[(string)$name] = [
                'label' => (string)($setting->label ?? $name),
                'value' => (int)$setting->currentvalue,
                'type' => $setting->type === 'date_selector' ? 'date' : 'datetime',
                'optional' => (bool)($setting->isoptional ?? false),
            ];
        }
        return $fields;
    }

    /**
     * Build date rows using schema checks and legacy extraction heuristics.
     *
     * @param \cm_info $cm Course module.
     * @param array $parent Existing Gantt parent row.
     * @param array<string, bool> $rangedatefields Fields already described by mapping rules.
     * @param int $coursestart Course Gantt start timestamp.
     * @param int $courseend Course Gantt end timestamp.
     * @return array{parent:array, children:array}
     */
    public function build_date_schedule(
        \cm_info $cm,
        array $parent,
        array $rangedatefields,
        int $coursestart,
        int $courseend
    ): array {
        return date_schedule_builder::build_from_fallback(
            $cm,
            $this,
            $parent,
            $rangedatefields,
            $coursestart,
            $courseend
        );
    }

    /**
     * Validate date values using the legacy extractor contract.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Proposed date values.
     * @return array<string, string> Errors keyed by date field.
     */
    public function validate_date_fields(\cm_info $cm, array $dates): array {
        $extractor = self::get_extractor($cm->modname, $this->course);
        if (!$extractor || !method_exists($extractor, 'validate_dates')) {
            return [];
        }
        return $extractor->validate_dates($cm, $dates);
    }

    /**
     * Persist the proposed dates through the legacy extractor contract.
     *
     * @param \cm_info $cm Course module.
     * @param array<string, int> $dates Final date values.
     */
    public function save_date_fields(\cm_info $cm, array $dates): void {
        $extractor = self::get_extractor($cm->modname, $this->course);
        if (!$extractor) {
            throw new \moodle_exception('invalidrecord', 'error');
        }
        $extractor->save_dates($cm, $dates);
    }

    /**
     * Get extractor fields explicitly identified as milestones.
     *
     * @return string[]
     */
    public function get_point_date_fields(\cm_info $cm): array {
        $extractor = self::get_extractor($cm->modname, $this->course);
        return $extractor && method_exists($extractor, 'get_reschedule_point_fields') ?
            $extractor->get_reschedule_point_fields() : [];
    }

    /**
     * Get extractor fields that end an open-start interval.
     *
     * @return string[]
     */
    public function get_open_start_end_fields(\cm_info $cm): array {
        $extractor = self::get_extractor($cm->modname, $this->course);
        return $extractor && method_exists($extractor, 'get_reschedule_end_fields') ?
            $extractor->get_reschedule_end_fields() : [];
    }

    /**
     * Discover directed date dependencies by probing opposite timestamp extremes.
     *
     * All other date fields are set to zero so optional, unrelated dates do not
     * affect the probe. A dependency is reported only when one ordering passes
     * validate_dates() and the inverse ordering fails. Returned fields are
     * always in chronological start/end order, regardless of declaration order.
     *
     * @param \cm_info $cm Course module.
     * @param date_fields_interface $extractor Date-field adapter.
     * @param array $settings Date fields in declaration order.
     * @return array Directed field pairs with startfield and endfield.
     */
    public static function discover_date_dependencies(
        \cm_info $cm,
        date_fields_interface $extractor,
        array $settings
    ): array {
        $fieldnames = [];
        foreach ($settings as $field => $setting) {
            if (
                is_string($field) && preg_match('/^[a-z][a-z0-9_]*$/i', $field) &&
                    isset($setting['value'], $setting['type']) &&
                    in_array($setting['type'], ['date', 'datetime'], true)
            ) {
                $fieldnames[] = $field;
            }
        }
        $cachekey = $cm->modname . ':' . (int)$cm->id . ':' . implode(',', $fieldnames);
        if (isset(self::$dependencycache[$cachekey])) {
            return self::$dependencycache[$cachekey];
        }
        if (count($fieldnames) < 2) {
            self::$dependencycache[$cachekey] = [];
            return [];
        }

        // Keep the probe dates positive because many module validators treat 0
        // as an intentionally disabled date. These timestamps span a wide,
        // ordinary date range without approaching integer or DB limits.
        $early = 946684800; // 2000-01-01.
        $late = 4102444800; // 2100-01-01.
        $emptydates = array_fill_keys($fieldnames, 0);
        try {
            if (!empty($extractor->validate_date_fields($cm, $emptydates))) {
                self::$dependencycache[$cachekey] = [];
                return [];
            }
        } catch (\Throwable $e) {
            debugging('Could not establish a date-validation baseline: ' . $e->getMessage(), DEBUG_DEVELOPER);
            self::$dependencycache[$cachekey] = [];
            return [];
        }

        $dependencies = [];
        $count = count($fieldnames);
        for ($leftindex = 0; $leftindex < $count - 1; $leftindex++) {
            for ($rightindex = $leftindex + 1; $rightindex < $count; $rightindex++) {
                $left = $fieldnames[$leftindex];
                $right = $fieldnames[$rightindex];
                $dates = $emptydates;
                $dates[$left] = $early;
                $dates[$right] = $late;
                try {
                    $forwarderrors = $extractor->validate_date_fields($cm, $dates);
                    $dates[$left] = $late;
                    $dates[$right] = $early;
                    $reverseerrors = $extractor->validate_date_fields($cm, $dates);
                } catch (\Throwable $e) {
                    debugging('Could not probe date-field dependency: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    continue;
                }

                $forwardvalid = empty($forwarderrors);
                $reversevalid = empty($reverseerrors);
                if ($forwardvalid === $reversevalid) {
                    continue;
                }
                $dependencies[] = [
                    'startfield' => $forwardvalid ? $left : $right,
                    'endfield' => $forwardvalid ? $right : $left,
                    'declarationstart' => min($leftindex, $rightindex),
                    'declarationend' => max($leftindex, $rightindex),
                ];
            }
        }

        self::$dependencycache[$cachekey] = $dependencies;
        return $dependencies;
    }

    /**
     * Find which parent interval boundaries a child interval must respect.
     *
     * Each edge is tested independently while keeping both intervals valid.
     * A boundary is considered enforced only if an otherwise-valid baseline
     * becomes invalid when the child is moved across that edge.
     *
     * @param \cm_info $cm Course module.
     * @param date_fields_interface $extractor Date-field adapter.
     * @param array $settings Date fields.
     * @param array $parent Parent interval fields (startcol, endcol, isopenended).
     * @param array $child Child interval fields (startcol, endcol).
     * @return array{start: bool, end: bool} Whether the child is bounded by each parent edge.
     */
    public static function discover_parent_interval_bounds(
        \cm_info $cm,
        date_fields_interface $extractor,
        array $settings,
        array $parent,
        array $child
    ): array {
        $bounds = ['start' => false, 'end' => false];
        $parentstart = (string)($parent['startcol'] ?? '');
        $parentend = (string)($parent['endcol'] ?? '');
        $childstart = (string)($child['startcol'] ?? '');
        $childend = (string)($child['endcol'] ?? '');
        if ($parentstart === '' || $childstart === '' || $childend === '') {
            return $bounds;
        }
        $cachekey = implode(':', [$cm->modname, (int)$cm->id, $parentstart, $parentend,
            $childstart, $childend]);
        if (isset(self::$parentboundscache[$cachekey])) {
            return self::$parentboundscache[$cachekey];
        }

        $fieldnames = [];
        foreach ($settings as $field => $setting) {
            if (
                is_string($field) && preg_match('/^[a-z][a-z0-9_]*$/i', $field) &&
                    isset($setting['value'], $setting['type']) &&
                    in_array($setting['type'], ['date', 'datetime'], true)
            ) {
                $fieldnames[] = $field;
            }
        }
        if (
            !in_array($parentstart, $fieldnames, true) || !in_array($childstart, $fieldnames, true) ||
                !in_array($childend, $fieldnames, true) ||
                ($parentend !== '' && !in_array($parentend, $fieldnames, true))
        ) {
            return self::$parentboundscache[$cachekey] = $bounds;
        }

        // The dates keep each interval internally ordered in the baseline and
        // make each edge violation independent of the opposite edge.
        $early = 946684800; // 2000-01-01.
        $middle = 1577836800; // 2020-01-01.
        $middlelate = 2524608000; // 2050-01-01.
        $late = 4102444800; // 2100-01-01.
        $dates = array_fill_keys($fieldnames, 0);
        $dates[$parentstart] = $early;
        if ($parentend !== '') {
            $dates[$parentend] = $late;
        }
        $dates[$childstart] = $middle;
        $dates[$childend] = $middlelate;
        try {
            if (!empty($extractor->validate_date_fields($cm, $dates))) {
                $bounds = ['start' => true, 'end' => $parentend !== '' && empty($parent['isopenended'])];
                return self::$parentboundscache[$cachekey] = $bounds;
            }

            $startviolation = $dates;
            $startviolation[$parentstart] = 3786912000; // 2090-01-01.
            $startviolation[$childstart] = $early;
            $bounds['start'] = !empty($extractor->validate_date_fields($cm, $startviolation));

            if ($parentend !== '' && empty($parent['isopenended'])) {
                $endviolation = $dates;
                $endviolation[$parentend] = $middle;
                $endviolation[$childstart] = $early + 86400;
                $endviolation[$childend] = $late;
                $bounds['end'] = !empty($extractor->validate_date_fields($cm, $endviolation));
            }
        } catch (\Throwable $e) {
            debugging('Could not probe parent interval boundaries: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // Unknown validator behaviour is treated conservatively by the UI.
            $bounds = ['start' => true, 'end' => $parentend !== '' && empty($parent['isopenended'])];
        }

        return self::$parentboundscache[$cachekey] = $bounds;
    }

    #[\Override]
    public function supports(string $modname, array $item): bool {
        // Editdates extractors also own heuristic interval subactivities;
        // point milestones are handled by milestone_adapter.
        if (!empty($item['issubtype']) && empty($item['isdateinterval'])) {
            return false;
        }

        $extractor = self::get_extractor($modname, $this->course);
        return ($extractor !== null);
    }

    #[\Override]
    public function validate(array $item, int $newstart, int $newend): array {
        global $DB;
        $errors = !empty($item['isopenstart']) ? [] : parent::validate($item, $newstart, $newend);
        if (!empty($item['isopenstart']) && !empty($item['endenabled']) && $newend <= 0) {
            $errors[] = get_string('errorinvaliddate', 'calendar');
        }
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

            foreach ($item['proposeddates'] ?? [] as $fieldname => $value) {
                if (array_key_exists($fieldname, $dates)) {
                    $dates[$fieldname] = (int)$value;
                }
            }

            // Map timeline start and end cols.
            $startcol = $item['startcol'];
            $endcol = $item['endcol'] ?? '';
            if ($startcol !== '') {
                $dates[$startcol] = !empty($item['startenabled']) ? $newstart : 0;
            }
            if ($endcol !== '' && empty($item['isopenended'])) {
                $dates[$endcol] = !empty($item['endenabled']) ? $newend : 0;
            }

            $moderrors = $extractor->validate_dates($cm, $dates);
            if (!empty($moderrors)) {
                $title = $item['title'] ?? $cm->name;
                foreach ($moderrors as $field => $msg) {
                    $errors[] = "{$title} ({$field}): {$msg}";
                }
            }
        } catch (\Throwable $e) {
            debugging(
                'Date extractor validation failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
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
            if (!empty($item['isopenended'])) {
                throw new \moodle_exception('invalidrecord', 'error');
            }
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
        $endcol = $item['endcol'] ?? '';
        if ($startcol !== '') {
            $dates[$startcol] = $newstart;
        }
        if ($endcol !== '' && empty($item['isopenended'])) {
            $dates[$endcol] = $newend;
        }

        $extractor->save_dates($cm, $dates);
        $this->trigger_cm_updated($cm);
    }
}
