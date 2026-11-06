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
 * Application cache for the extracted schedule rows of each course.
 *
 * Cached variants are separated by user, language, theme and mapping settings
 * because the schedule includes capability-dependent and rendered metadata.
 * The course key remains the invalidation boundary.
 *
 * @package local_reschedule
 * @copyright 2026 Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_items_cache {
    /**
     * Build a cache variant for the current rendering and permission context.
     *
     * @param string|false $mappingraw Raw mapping setting.
     * @return string Cache variant key.
     */
    public static function get_variant_key($mappingraw): string {
        global $USER, $PAGE;

        $context = [
            'userid' => (int)($USER->id ?? 0),
            'lang' => current_language(),
            'theme' => $PAGE->theme->name ?? '',
            'mapping' => $mappingraw === false ? '' : (string)$mappingraw,
        ];
        return md5(serialize($context));
    }

    /**
     * Retrieve one course's schedule rows for a context variant.
     *
     * @param int $courseid Course ID.
     * @param string $variant Cache variant key.
     * @return array|null Cached rows, or null on a cache miss.
     */
    public static function get(int $courseid, string $variant): ?array {
        $cache = \cache::make('local_reschedule', 'course_items');
        $variants = $cache->get((string)$courseid);
        if (
            !is_array($variants) || !array_key_exists($variant, $variants) ||
                !is_array($variants[$variant])
        ) {
            return null;
        }
        return $variants[$variant];
    }

    /**
     * Store extracted schedule rows for a course and context variant.
     *
     * Keep a bounded number of variants for each course to prevent the shared
     * application cache growing with every user or language combination.
     *
     * @param int $courseid Course ID.
     * @param string $variant Cache variant key.
     * @param array $items Full, unscoped course schedule rows.
     */
    public static function set(int $courseid, string $variant, array $items): void {
        $cache = \cache::make('local_reschedule', 'course_items');
        $variants = $cache->get((string)$courseid);
        if (!is_array($variants)) {
            $variants = [];
        }
        $variants[$variant] = $items;
        while (count($variants) > 16) {
            array_shift($variants);
        }
        $cache->set((string)$courseid, $variants);
    }

    /**
     * Invalidate cached schedule rows for a course.
     *
     * @param int $courseid Course ID.
     */
    public static function invalidate(int $courseid): void {
        if ($courseid <= 0) {
            return;
        }
        \cache::make('local_reschedule', 'course_items')->delete((string)$courseid);
    }

    /**
     * Invalidate all schedules after a global permission or enrolment change.
     */
    public static function invalidate_all(): void {
        \cache::make('local_reschedule', 'course_items')->purge();
    }
}
