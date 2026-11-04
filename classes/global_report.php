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

namespace local_reschedule;

/**
 * Builds the cross-course workload report for the students of one course.
 *
 * Activity association is based on active course enrolment. Consequently, an
 * activity's weight is the number of current-course students who are also
 * actively enrolled in the course that owns that activity.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class global_report {
    /** Fields required by fullname(). */
    private const USER_NAME_FIELDS = 'u.id, u.firstname, u.lastname, u.firstnamephonetic, ' .
        'u.lastnamephonetic, u.middlename, u.alternatename';

    /**
     * Build report data for a course cohort.
     *
     * @param \stdClass $currentcourse Current course record.
     * @return array JSON-safe report data.
     */
    public static function get_data(\stdClass $currentcourse): array {
        global $DB;

        $currentcourseid = (int)$currentcourse->id;
        $context = \context_course::instance($currentcourseid);
        $students = get_enrolled_users(
            $context,
            'moodle/course:isincompletionreports',
            0,
            self::USER_NAME_FIELDS,
            'u.lastname ASC, u.firstname ASC, u.id ASC',
            0,
            0,
            true
        );

        $studentcourses = [];
        $courseparticipants = [];
        $courseids = [$currentcourseid => true];
        foreach ($students as $student) {
            $enrolledcourses = enrol_get_all_users_courses(
                (int)$student->id,
                true,
                'id, fullname, shortname, visible',
                'sortorder ASC'
            );
            $ids = [];
            foreach ($enrolledcourses as $enrolledcourse) {
                $courseid = (int)$enrolledcourse->id;
                $courseids[$courseid] = true;
                $courseparticipants[$courseid][(int)$student->id] = true;
                $ids[] = $courseid;
            }
            $studentcourses[(int)$student->id] = array_values(array_unique($ids));
        }

        $courses = $DB->get_records_list('course', 'id', array_keys($courseids));
        $reportcourses = [];
        $currenttimeframe = manager::get_course_timeframe($currentcourse);
        $rangestart = (int)$currenttimeframe['start'];
        $rangeend = (int)$currenttimeframe['end'];
        foreach ($courses as $course) {
            $courseid = (int)$course->id;
            $participantcount = count($courseparticipants[$courseid] ?? []);
            if ($courseid !== $currentcourseid && $participantcount === 0) {
                continue;
            }
            try {
                $items = manager::get_course_items($courseid);
            } catch (\Throwable $exception) {
                debugging('Could not build global workload data for course ' . $courseid . ': ' .
                    $exception->getMessage(), DEBUG_DEVELOPER);
                continue;
            }
            $timeframe = manager::get_course_timeframe($course, $items);
            if ($courseid === $currentcourseid) {
                // The report uses exactly the visible range of the main Gantt.
                $rangestart = (int)$timeframe['start'];
                $rangeend = (int)$timeframe['end'];
            }
            $coursefullname = format_string($course->fullname, true, [
                'context' => \context_course::instance($courseid),
            ]);
            $reportcourses[] = [
                'id' => $courseid,
                'fullname' => $coursefullname,
                'studentcount' => $participantcount,
                'current' => $courseid === $currentcourseid,
                'activitycount' => count(array_filter($items, static function(array $item): bool {
                    return empty($item['issubtype']);
                })),
                'items' => self::namespace_items($items, $courseid, $coursefullname, $courseid === $currentcourseid),
            ];
        }
        usort($reportcourses, static function(array $left, array $right): int {
            if ($left['current'] !== $right['current']) {
                return $left['current'] ? -1 : 1;
            }
            return strnatcasecmp($left['fullname'], $right['fullname']);
        });

        $reportstudents = [];
        foreach ($students as $student) {
            $reportstudents[] = [
                'id' => (int)$student->id,
                'fullname' => fullname($student),
                'courseids' => $studentcourses[(int)$student->id] ?? [],
                'profileurl' => (new \moodle_url('/user/view.php', [
                    'id' => (int)$student->id,
                    'course' => $currentcourseid,
                ]))->out(false),
            ];
        }

        return [
            'currentcourseid' => $currentcourseid,
            'studentcount' => count($reportstudents),
            'coursecount' => count($reportcourses),
            'rangestart' => $rangestart,
            'rangeend' => $rangeend,
            'effortmodel' => effort_store::get_model($currentcourseid),
            'courses' => array_values($reportcourses),
            'students' => $reportstudents,
        ];
    }

    /**
     * Make row identifiers unique while retaining the complete Gantt item contract.
     *
     * @param array $items Schedule rows.
     * @param int $courseid Owning course id.
     * @param string $coursefullname Formatted owning course name.
     * @param bool $currentcourse Whether this is the report's source course.
     * @return array Namespaced Gantt rows.
     */
    private static function namespace_items(
        array $items,
        int $courseid,
        string $coursefullname,
        bool $currentcourse
    ): array {
        $prefix = 'course_' . $courseid . '_';
        foreach ($items as &$item) {
            $item['reportsourceid'] = (string)$item['id'];
            $item['id'] = $prefix . (string)$item['id'];
            if (!empty($item['parentkey'])) {
                $item['parentkey'] = $prefix . (string)$item['parentkey'];
            }
            $item['reportcourseid'] = $courseid;
            $item['reportcoursefullname'] = $coursefullname;
            $item['reportcurrentcourse'] = $currentcourse;
            $cmid = (int)($item['cmid'] ?? 0);
            $item['reportefforteditable'] = $currentcourse && $cmid > 0 &&
                has_capability('moodle/course:manageactivities', \context_module::instance($cmid)) &&
                empty($item['ismilestone']) &&
                !in_array($item['effortsource'] ?? '', ['adapter', 'children'], true);
        }
        unset($item);
        return array_values($items);
    }
}
