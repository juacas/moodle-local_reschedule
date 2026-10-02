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

namespace local_reschedule;

defined('MOODLE_INTERNAL') || die();

/**
 * Move dated course modules into their matching weekly section.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class weekifier {
    /**
     * Preview moves using the course's saved dates and its actual week boundaries.
     *
     * @param \stdClass $course Course record.
     * @param int[] $selectedcmids Course module IDs visible in the rescheduler.
     * @return array Move plan and skipped counts.
     */
    public static function plan(\stdClass $course, array $selectedcmids): array {
        if ($course->format !== 'weeks') {
            throw new \moodle_exception('weekifynotweeks', 'local_reschedule');
        }

        $selected = array_fill_keys(array_filter(array_map('intval', $selectedcmids)), true);
        $result = [
            'moves' => [],
            'skipped' => ['undated' => 0, 'outofrange' => 0, 'already' => 0, 'unsupported' => 0],
        ];
        if (!$selected) {
            return $result;
        }

        $format = \course_get_format($course);
        $modinfo = \get_fast_modinfo($course);
        $weeks = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section || (int)$section->section < 1 || !empty($section->component)) {
                continue;
            }
            $dates = $format->get_section_dates($section);
            $weeks[] = [
                'section' => $section,
                'start' => (int)$dates->start,
                'end' => (int)$dates->end,
            ];
        }

        $parents = [];
        $childstarts = [];
        foreach (manager::get_course_items((int)$course->id) as $item) {
            $cmid = (int)($item['cmid'] ?? 0);
            if (!isset($selected[$cmid])) {
                continue;
            }
            if (!empty($item['issubtype'])) {
                if (!empty($item['startenabled']) && (int)$item['datestart'] > 0) {
                    $childstarts[$cmid][] = (int)$item['datestart'];
                }
            } else {
                $parents[$cmid] = $item;
            }
        }

        foreach ($parents as $cmid => $item) {
            $start = !empty($item['startenabled']) ? (int)$item['datestart'] : 0;
            if (!$start && !empty($childstarts[$cmid])) {
                $start = min($childstarts[$cmid]);
            }
            if (!$start) {
                $result['skipped']['undated']++;
                continue;
            }
            $target = null;
            foreach ($weeks as $week) {
                if ($start >= $week['start'] && $start < $week['end']) {
                    $target = $week['section'];
                    break;
                }
            }
            if (!$target) {
                $result['skipped']['outofrange']++;
                continue;
            }

            $cm = $modinfo->get_cm($cmid);
            if (!\course_modinfo::is_mod_type_visible_on_course($cm->modname)) {
                $result['skipped']['unsupported']++;
                continue;
            }
            $current = $cm->get_section_info();
            if ((int)$current->id === (int)$target->id) {
                $result['skipped']['already']++;
                continue;
            }
            $result['moves'][] = [
                'cmid' => $cmid,
                'title' => (string)$item['title'],
                'from' => (string)$format->get_section_name($current),
                'to' => (string)$format->get_section_name($target),
                'targetsectionid' => (int)$target->id,
                'hidden' => !$target->visible,
            ];
        }

        return $result;
    }

    /**
     * Apply a freshly calculated plan through Moodle's course format API.
     *
     * @param \stdClass $course Course record.
     * @param int[] $selectedcmids Visible course module IDs.
     * @return array Outcome counts.
     */
    public static function apply(\stdClass $course, array $selectedcmids): array {
        $plan = self::plan($course, $selectedcmids);
        $actions = \core_courseformat\formatactions::cm($course);
        $moved = 0;
        $failed = 0;
        foreach ($plan['moves'] as $move) {
            try {
                if ($actions->move_end_section($move['cmid'], $move['targetsectionid'])) {
                    $moved++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                debugging('Could not weekify course module ' . $move['cmid'] . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                $failed++;
            }
        }
        return ['moved' => $moved, 'failed' => $failed, 'skipped' => $plan['skipped']];
    }
}
