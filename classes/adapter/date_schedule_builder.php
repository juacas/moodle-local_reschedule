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
 * Converts additional activity date fields into Gantt intervals and milestones.
 *
 * This shared adapter helper owns the field interpretation previously mixed
 * into manager::get_course_items(). Providers supply their date settings and
 * dependencies; report_editdates heuristics are used only when the provider
 * does not declare dependencies.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class date_schedule_builder {
    /**
     * Check for date fields from a legacy provider without exposing settings to the manager.
     *
     * @param \cm_info $cm Course module.
     * @param date_fields_interface $provider Date settings adapter.
     * @return bool True when at least one date field is available.
     */
    public static function provider_has_date_fields(\cm_info $cm, date_fields_interface $provider): bool {
        return (bool)$provider->get_date_fields($cm);
    }

    /**
     * Build additional date rows and any parent interval inferred from them.
     *
     * @param \cm_info $cm Course module.
     * @param date_fields_interface $provider Date settings adapter.
     * @param array $parent Parent row from manager.
     * @param array<string, bool> $rangedatefields Fields already used by mapping rules.
     * @param int $coursestart Gantt course start.
     * @param int $courseend Gantt course end.
     * @return array{parent:array, children:array}
     */
    public static function build_from_adapter(
        \cm_info $cm,
        date_fields_interface $provider,
        array $parent,
        array $rangedatefields,
        int $coursestart,
        int $courseend
    ): array {
        $settings = $provider->get_date_fields($cm);
        if (!is_array($settings) || !$settings) {
            return ['parent' => $parent, 'children' => []];
        }

        // Adapter output is already authoritative: its declared date fields
        // are consumed as-is without repeating database schema checks.
        $datefields = array_keys($settings);
        $dependencies = self::get_dependencies($cm, $provider, $settings, $datefields);
        return self::build_rows(
            $cm,
            $provider,
            $parent,
            $settings,
            $rangedatefields,
            $coursestart,
            $courseend,
            $datefields,
            $dependencies
        );
    }

    /**
     * Build rows for extractor or unknown-module metadata after schema checks.
     *
     * @param \cm_info $cm Course module.
     * @param date_fields_interface $provider Date settings adapter.
     * @param array $parent Existing Gantt parent row.
     * @param array<string, bool> $rangedatefields Fields already described by mapping rules.
     * @param int $coursestart Course Gantt start timestamp.
     * @param int $courseend Course Gantt end timestamp.
     * @return array{parent:array, children:array}
     */
    public static function build_from_fallback(
        \cm_info $cm,
        date_fields_interface $provider,
        array $parent,
        array $rangedatefields,
        int $coursestart,
        int $courseend
    ): array {
        global $DB;

        $settings = $provider->get_date_fields($cm);
        if (!is_array($settings) || !$settings) {
            return ['parent' => $parent, 'children' => []];
        }
        $dbman = $DB->get_manager();
        $datefields = [];
        foreach ($settings as $field => $setting) {
            if (
                preg_match('/^[a-z][a-z0-9_]*$/i', (string)$field) &&
                    $dbman->field_exists($cm->modname, $field) &&
                    isset($setting['value'], $setting['type']) &&
                    in_array($setting['type'], ['date', 'datetime'], true)
            ) {
                $datefields[] = (string)$field;
            }
        }
        if (!$datefields) {
            return ['parent' => $parent, 'children' => []];
        }

        $dependencies = self::get_dependencies($cm, $provider, $settings, $datefields);
        return self::build_rows(
            $cm,
            $provider,
            $parent,
            $settings,
            $rangedatefields,
            $coursestart,
            $courseend,
            $datefields,
            $dependencies
        );
    }

    /**
     * Compose Gantt rows from already selected date fields and relationships.
     *
     * @param \cm_info $cm Course module.
     * @param date_fields_interface $provider Date settings adapter.
     * @param array $parent Existing Gantt parent row.
     * @param array $settings Date metadata keyed by module database field.
     * @param array<string, bool> $rangedatefields Fields already described by mapping rules.
     * @param int $coursestart Course Gantt start timestamp.
     * @param int $courseend Course Gantt end timestamp.
     * @param string[] $datefields Valid date fields.
     * @param array $dependencies Resolved date relationships.
     * @return array{parent:array, children:array}
     */
    private static function build_rows(
        \cm_info $cm,
        date_fields_interface $provider,
        array $parent,
        array $settings,
        array $rangedatefields,
        int $coursestart,
        int $courseend,
        array $datefields,
        array $dependencies
    ): array {
        global $DB;

        usort($dependencies, static function (array $left, array $right): int {
            return $left['declarationstart'] <=> $right['declarationstart'] ?:
                $left['declarationend'] <=> $right['declarationend'];
        });
        $datefieldmap = array_fill_keys($datefields, true);
        $record = $DB->get_record($cm->modname, ['id' => $cm->instance]);
        if (!$record) {
            return ['parent' => $parent, 'children' => []];
        }
        $getdate = static function (string $field) use ($record, $settings): int {
            return isset($record->{$field}) ? (int)$record->{$field} : (int)($settings[$field]['value'] ?? 0);
        };

        if (empty($parent['mappedrule']) && empty($parent['startcol']) && empty($parent['endcol'])) {
            foreach ($provider->get_open_start_end_fields($cm) as $endfield) {
                if (!isset($datefieldmap[$endfield])) {
                    continue;
                }
                $endvalue = $getdate($endfield);
                $parent['endcol'] = $endfield;
                $parent['startenabled'] = false;
                $parent['endenabled'] = $endvalue > 0;
                $parent['datestart'] = $coursestart;
                $parent['dateend'] = $endvalue > 0 ? $endvalue : $courseend;
                $parent['isopenstart'] = true;
                $parent['isdateinterval'] = true;
                $parent['editable'] = true;
                $parent['interactive'] = true;
                break;
            }
        }

        $mappedrange = !empty($parent['mappedrule']) ||
            (!empty($parent['startcol']) && !empty($parent['endcol'])) || !empty($parent['isopenstart']);
        $usedfields = [];
        if (!empty($parent['isopenstart'])) {
            $usedfields[$parent['endcol']] = true;
        }

        if (!$mappedrange && $dependencies) {
            $firstfield = $datefields[0];
            $firstdependencyindex = min(array_column($dependencies, 'declarationstart'));
            $firstfieldispaired = false;
            foreach ($dependencies as $dependency) {
                if ($dependency['startfield'] === $firstfield || $dependency['endfield'] === $firstfield) {
                    $firstfieldispaired = true;
                    break;
                }
            }

            if (!$firstfieldispaired && array_search($firstfield, $datefields, true) < $firstdependencyindex) {
                $startvalue = $getdate($firstfield);
                $parent['startcol'] = $firstfield;
                $parent['endcol'] = '';
                $parent['startenabled'] = $startvalue > 0;
                $parent['endenabled'] = false;
                $parent['datestart'] = $startvalue > 0 ? $startvalue : $coursestart;
                $parent['dateend'] = $courseend;
                $parent['isopenended'] = true;
                $parent['editable'] = true;
                $parent['interactive'] = true;
                $parent['derived'] = false;
                $usedfields[$firstfield] = true;
            } else {
                $primary = $dependencies[0];
                $startfield = $primary['startfield'];
                $endfield = $primary['endfield'];
                $startvalue = $getdate($startfield);
                $endvalue = $getdate($endfield);
                $parent['startcol'] = $startfield;
                $parent['endcol'] = $endfield;
                $parent['startenabled'] = $startvalue > 0;
                $parent['endenabled'] = $endvalue > 0;
                $parent['datestart'] = $startvalue > 0 ? $startvalue : $coursestart;
                $parent['dateend'] = $endvalue > 0 ? $endvalue : $courseend;
                if ($parent['dateend'] <= $parent['datestart']) {
                    $parent['dateend'] = min($courseend, $parent['datestart'] + 3600);
                }
                $parent['editable'] = true;
                $parent['interactive'] = true;
                $parent['derived'] = false;
                $parent['title'] = (string)$cm->name;
                $usedfields[$startfield] = true;
                $usedfields[$endfield] = true;
            }
        }

        $children = [];
        foreach ($dependencies as $dependency) {
            $startfield = $dependency['startfield'];
            $endfield = $dependency['endfield'];
            if (
                isset($usedfields[$startfield]) || isset($usedfields[$endfield]) ||
                    isset($rangedatefields[$startfield]) || isset($rangedatefields[$endfield])
            ) {
                continue;
            }
            $parentbounds = editdates_bridge::discover_parent_interval_bounds(
                $cm,
                $provider,
                $settings,
                $parent,
                ['startcol' => $startfield, 'endcol' => $endfield]
            );
            $startvalue = $getdate($startfield);
            $endvalue = $getdate($endfield);
            $children[] = [
                'id' => 'interval_' . $cm->modname . '_' . $cm->instance . '_' . $startfield . '_' . $endfield,
                'recordid' => (int)$cm->instance,
                'cmid' => (int)$cm->id,
                'table' => (string)$cm->modname,
                'parentkey' => (string)$parent['id'],
                'parenttitle' => (string)$parent['title'],
                'issubtype' => true,
                'isdateinterval' => true,
                'boundtoparentstart' => $parentbounds['start'],
                'boundtoparentend' => $parentbounds['end'],
                'haschildren' => false,
                'childrencount' => 0,
                'iconurl' => (string)$parent['iconurl'],
                'viewurl' => (string)$parent['viewurl'],
                'editable' => true,
                'derived' => false,
                'interactive' => true,
                'title' => (string)($settings[$startfield]['label'] ?? $startfield) . ' - ' .
                    (string)($settings[$endfield]['label'] ?? $endfield),
                'typelabel' => get_string('dateinterval', 'local_reschedule'),
                'startcol' => $startfield,
                'endcol' => $endfield,
                'startenabled' => $startvalue > 0,
                'endenabled' => $endvalue > 0,
                'datestart' => $startvalue > 0 ? $startvalue : (int)$parent['datestart'],
                'dateend' => $endvalue > 0 ? $endvalue : (int)$parent['dateend'],
            ];
            $usedfields[$startfield] = true;
            $usedfields[$endfield] = true;
        }

        foreach ($settings as $field => $setting) {
            if (
                isset($usedfields[$field]) || isset($rangedatefields[$field]) ||
                    !isset($datefieldmap[$field])
            ) {
                continue;
            }
            $timestamp = (int)$setting['value'];
            $enabled = $timestamp > 0;
            $drawtime = $enabled ? $timestamp : (int)$parent['datestart'];
            $children[] = [
                'id' => 'milestone_' . $cm->modname . '_' . $cm->instance . '_' . $field,
                'recordid' => (int)$cm->instance,
                'cmid' => (int)$cm->id,
                'table' => (string)$cm->modname,
                'parentkey' => (string)$parent['id'],
                'parenttitle' => (string)$parent['title'],
                'issubtype' => true,
                'ismilestone' => true,
                'haschildren' => false,
                'childrencount' => 0,
                'iconurl' => (string)$parent['iconurl'],
                'viewurl' => (string)$parent['viewurl'],
                'editable' => true,
                'derived' => false,
                'interactive' => true,
                'title' => (string)$setting['label'],
                'typelabel' => get_string('milestone', 'local_reschedule'),
                'startcol' => (string)$field,
                'endcol' => (string)$field,
                'startenabled' => $enabled,
                'endenabled' => $enabled,
                'optional' => (bool)$setting['optional'],
                'dateonly' => $setting['type'] === 'date',
                'datestart' => $drawtime,
                'dateend' => $drawtime,
            ];
            $usedfields[$field] = true;
        }

        if ($children) {
            $parent['haschildren'] = true;
            $parent['childrencount'] = (int)($parent['childrencount'] ?? 0) + count($children);
        }
        return ['parent' => $parent, 'children' => $children];
    }

    /**
     * Prefer the adapter's explicit relationships; otherwise probe its validator.
     *
     * @param \cm_info $cm Course module.
     * @param date_fields_interface $provider Date settings adapter.
     * @param array $settings Date settings keyed by database field.
     * @param string[] $datefields Valid database date fields in declaration order.
     * @return array<int, array{startfield:string, endfield:string, declarationstart:int, declarationend:int}>
     */
    private static function get_dependencies(
        \cm_info $cm,
        date_fields_interface $provider,
        array $settings,
        array $datefields
    ): array {
        $dependencies = null;
        if ($provider instanceof date_dependencies_interface) {
            $dependencies = $provider->get_date_dependencies($cm, $settings);
        }
        if ($dependencies === null) {
            $dependencies = editdates_bridge::discover_date_dependencies($cm, $provider, $settings);
        }

        $fieldindexes = array_flip($datefields);
        $pointfields = array_fill_keys($provider->get_point_date_fields($cm), true);
        $normalised = [];
        foreach ($dependencies as $dependency) {
            $start = (string)($dependency['startfield'] ?? '');
            $end = (string)($dependency['endfield'] ?? '');
            if (
                !isset($fieldindexes[$start], $fieldindexes[$end]) ||
                    isset($pointfields[$start]) || isset($pointfields[$end])
            ) {
                continue;
            }
            $normalised[] = [
                'startfield' => $start,
                'endfield' => $end,
                'declarationstart' => min($fieldindexes[$start], $fieldindexes[$end]),
                'declarationend' => max($fieldindexes[$start], $fieldindexes[$end]),
            ];
        }
        return $normalised;
    }
}
