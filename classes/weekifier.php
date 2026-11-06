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
        global $DB;

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
            // Moodle marks the course's special Announcements forum with
            // type=news. Its position is managed by the course, not Weekify.
            if (
                $item['table'] === 'forum' && $DB->record_exists('forum', [
                    'id' => (int)$item['recordid'],
                    'course' => (int)$course->id,
                    'type' => 'news',
                ])
            ) {
                $result['skipped']['unsupported']++;
                continue;
            }
            $start = !empty($item['startenabled']) ? (int)$item['datestart'] : 0;
            if (!$start && !empty($childstarts[$cmid])) {
                $start = min($childstarts[$cmid]);
            }
            // With neither a parent nor a child start, the interval is open
            // toward the past and belongs in the first weekly section.
            $target = $start ? null : ($weeks[0]['section'] ?? null);
            if ($start) {
                foreach ($weeks as $week) {
                    if ($start >= $week['start'] && $start < $week['end']) {
                        $target = $week['section'];
                        break;
                    }
                }
            }
            if (!$target) {
                $result['skipped']['outofrange']++;
                continue;
            }

            $cm = $modinfo->get_cm($cmid);
            if (
                method_exists(\course_modinfo::class, 'is_mod_type_visible_on_course') &&
                    !\course_modinfo::is_mod_type_visible_on_course($cm->modname)
            ) {
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
        global $CFG;

        $plan = self::plan($course, $selectedcmids);
        $actions = \core_courseformat\formatactions::cm($course);
        $moved = 0;
        $failed = 0;
        $affectedmodules = array_map(static fn(array $move): array => [
            'cmid' => (int)$move['cmid'],
            'name' => (string)$move['title'],
        ], $plan['moves']);
        if ($affectedmodules) {
            // Emit before Moodle's course format API records each move.
            $event = \local_reschedule\event\changes_saved::create_for_modules(
                \context_course::instance((int)$course->id),
                'weekify',
                $affectedmodules
            );
            $event->trigger();
        }
        foreach ($plan['moves'] as $move) {
            try {
                // This course format API may not be available in Moodle versions older than 5.0.
                if (method_exists($actions, 'move_end_section')) {
                    $success = $actions->move_end_section($move['cmid'], $move['targetsectionid']);
                } else {
                    require_once($CFG->dirroot . '/course/lib.php');
                    $modinfo = get_fast_modinfo($course);
                    $cm = $modinfo->get_cm((int)$move['cmid']);
                    $section = $modinfo->get_section_info_by_id((int)$move['targetsectionid'], MUST_EXIST);
                    moveto_module($cm, $section);
                    $success = true;
                }
                if ($success) {
                    $moved++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                debugging('Could not weekify course module ' . $move['cmid'] . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                $failed++;
            }
        }
        if ($moved > 0) {
            course_items_cache::invalidate((int)$course->id);
        }
        return ['moved' => $moved, 'failed' => $failed, 'skipped' => $plan['skipped']];
    }
}
