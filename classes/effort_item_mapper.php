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
 * Maps Gantt rows to durable and backup-portable effort locators.
 *
 * @package local_reschedule
 * @copyright 2026 Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class effort_item_mapper {
    /**
     * Build locators indexed by Gantt row id.
     *
     * Child occurrence is calculated after sorting by database record id, so date
     * changes do not change it. The title is retained for safe partial restores.
     *
     * @param array $items Current Gantt rows.
     * @return array
     */
    public static function map(array $items): array {
        $result = [];
        $children = [];
        foreach ($items as $item) {
            if (!empty($item['ismilestone']) || empty($item['cmid']) || empty($item['id'])) {
                continue;
            }
            if (empty($item['issubtype'])) {
                $result[(string)$item['id']] = [
                    'scope' => 'activity',
                    'cmid' => (int)$item['cmid'],
                ];
                continue;
            }
            $base = [
                'scope' => 'subactivity',
                'cmid' => (int)$item['cmid'],
                'table' => (string)($item['table'] ?? ''),
                'startfield' => (string)($item['startcol'] ?? ''),
                'endfield' => (string)($item['endcol'] ?? ''),
                'title' => (string)($item['title'] ?? ''),
                'recordid' => (int)($item['recordid'] ?? 0),
            ];
            $group = implode("\0", [$base['cmid'], $base['table'], $base['startfield'], $base['endfield'], $base['title']]);
            $children[$group][] = ['id' => (string)$item['id'], 'locator' => $base];
        }
        foreach ($children as $rows) {
            usort($rows, static fn(array $a, array $b): int =>
                $a['locator']['recordid'] <=> $b['locator']['recordid']);
            foreach ($rows as $occurrence => $row) {
                $row['locator']['occurrence'] = $occurrence;
                $result[$row['id']] = $row['locator'];
            }
        }
        return $result;
    }

    /**
     * Return a canonical comparison key.
     *
     * @param array $locator Locator.
     * @param bool $portable Exclude local ids.
     * @return string
     */
    public static function key(array $locator, bool $portable = false): string {
        $copy = $locator;
        if ($portable) {
            unset($copy['recordid']);
        } else {
            unset($copy['occurrence'], $copy['title']);
        }
        ksort($copy);
        return json_encode($copy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Remove local database ids for backup.
     *
     * @param array $locator Locator.
     * @return array
     */
    public static function portable(array $locator): array {
        unset($locator['recordid']);
        return $locator;
    }
}
