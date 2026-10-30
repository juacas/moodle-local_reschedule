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

use local_reschedule\customfield\effort_handler;

/**
 * Persists manual effort estimates as a versioned JSON course custom field.
 *
 * @package local_reschedule
 * @copyright 2026 Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class effort_store {
    /** @var int Current JSON document schema. */
    public const SCHEMA_VERSION = 1;
    /** @var string Custom field short name. */
    public const FIELD_SHORTNAME = 'effort_overrides';
    /** @var string Default effort distribution model. */
    public const DEFAULT_MODEL = 'real';

    /**
     * Ensure the private custom-field definition exists.
     *
     * @return \core_customfield\field_controller
     */
    public static function ensure_field(): \core_customfield\field_controller {
        $handler = effort_handler::create();
        foreach ($handler->get_fields() as $field) {
            if ($field->get('shortname') === self::FIELD_SHORTNAME) {
                return $field;
            }
        }
        $categoryid = $handler->create_category('Activity Rescheduler');
        $categories = $handler->get_categories_with_fields();
        $category = $handler->get_category_from_array($categories, $categoryid, 'local_reschedule', 'effort', 0);
        $field = \core_customfield\field_controller::create(0, (object)['type' => 'textarea'], $category);
        $handler->save_field_configuration($field, (object)[
            'name' => 'Effort overrides',
            'shortname' => self::FIELD_SHORTNAME,
            'description' => 'Versioned JSON maintained by local_reschedule.',
            'descriptionformat' => FORMAT_PLAIN,
            'configdata' => ['required' => 0, 'uniquevalues' => 0],
        ]);
        return $field;
    }

    /**
     * Read and validate the stored document.
     *
     * @param int $courseid Course id.
     * @return array
     */
    public static function read(int $courseid): array {
        $handler = effort_handler::create();
        $field = self::ensure_field();
        $data = $handler->get_instance_fields_data([(int)$field->get('id') => $field], $courseid, false);
        if (!$data) {
            return ['version' => self::SCHEMA_VERSION, 'model' => self::DEFAULT_MODEL, 'entries' => []];
        }
        $controller = reset($data);
        $decoded = json_decode((string)$controller->get('value'), true);
        if (!is_array($decoded) || ($decoded['version'] ?? null) !== self::SCHEMA_VERSION ||
                !is_array($decoded['entries'] ?? null)) {
            return ['version' => self::SCHEMA_VERSION, 'model' => self::DEFAULT_MODEL, 'entries' => []];
        }
        $decoded['model'] = self::normalise_model($decoded['model'] ?? self::DEFAULT_MODEL);
        return $decoded;
    }

    /**
     * Write a complete document, replacing stale references atomically.
     *
     * @param int $courseid Course id.
     * @param array $entries Current entries.
     * @param string|null $model Effort model, or null to preserve the stored model.
     */
    public static function write(int $courseid, array $entries, ?string $model = null): void {
        if ($model === null) {
            $model = self::read($courseid)['model'];
        }
        $handler = effort_handler::create();
        $field = self::ensure_field();
        $data = $handler->get_instance_fields_data([(int)$field->get('id') => $field], $courseid);
        $controller = reset($data);
        $controller->set('instanceid', $courseid);
        $controller->set('contextid', \context_course::instance($courseid)->id);
        $controller->set('value', json_encode([
            'version' => self::SCHEMA_VERSION,
            'model' => self::normalise_model($model),
            'entries' => array_values($entries),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $controller->set('valueformat', FORMAT_PLAIN);
        $controller->set('valuetrust', 0);
        $controller->save();
    }

    /**
     * Return the selected effort distribution model.
     *
     * @param int $courseid Course id.
     * @return string
     */
    public static function get_model(int $courseid): string {
        return self::read($courseid)['model'];
    }

    /**
     * Save the selected model while preserving all effort overrides.
     *
     * @param int $courseid Course id.
     * @param string $model Effort model.
     */
    public static function save_model(int $courseid, string $model): void {
        $document = self::read($courseid);
        self::write($courseid, $document['entries'], $model);
    }

    /**
     * Restrict a stored model to the supported values.
     *
     * @param mixed $model Candidate model.
     * @return string
     */
    private static function normalise_model($model): string {
        return $model === 'ideal' ? 'ideal' : self::DEFAULT_MODEL;
    }

    /**
     * Apply stored values to current rows; orphan entries are ignored.
     *
     * @param int $courseid Course id.
     * @param array $items Current rows.
     */
    public static function apply(int $courseid, array &$items): void {
        $stored = self::read($courseid)['entries'];
        if (!$stored) {
            return;
        }
        $bykey = [];
        foreach ($stored as $entry) {
            if (is_array($entry['locator'] ?? null) && is_numeric($entry['hours'] ?? null)) {
                $bykey[effort_item_mapper::key($entry['locator'])] = (float)$entry['hours'];
            }
        }
        $locators = effort_item_mapper::map($items);
        foreach ($items as &$item) {
            $id = (string)($item['id'] ?? '');
            if (!isset($locators[$id]) || !empty($item['ismilestone']) ||
                    ($item['effortsource'] ?? '') === 'adapter') {
                continue;
            }
            $key = effort_item_mapper::key($locators[$id]);
            if (array_key_exists($key, $bykey)) {
                $item['effort'] = max(0.0, $bykey[$key]);
                $item['effortsource'] = 'manual';
            }
        }
        unset($item);
    }

    /**
     * Rebuild storage from current rows and submitted editable values.
     *
     * @param int $courseid Course id.
     * @param array $items Current rows.
     * @param array $updates Submitted values.
     * @return int Number saved.
     */
    public static function save_updates(int $courseid, array $items, array $updates): int {
        $values = [];
        foreach ($updates as $update) {
            $values[(string)$update['id']] = (float)$update['hours'];
        }
        $locators = effort_item_mapper::map($items);
        $entries = [];
        foreach ($items as $item) {
            $id = (string)($item['id'] ?? '');
            $ismanuallyset = ($item['effortsource'] ?? '') === 'manual';
            if ((!array_key_exists($id, $values) && !$ismanuallyset) || !isset($locators[$id]) ||
                    !empty($item['ismilestone']) ||
                    in_array($item['effortsource'] ?? '', ['adapter', 'children'], true)) {
                continue;
            }
            $hours = array_key_exists($id, $values) ? $values[$id] : (float)($item['effort'] ?? 0);
            $entries[] = ['locator' => $locators[$id], 'hours' => max(0.0, $hours)];
        }
        self::write($courseid, $entries);
        return count($entries);
    }

    /**
     * Delete all effort metadata for a deleted course.
     *
     * @param int $courseid Course id.
     */
    public static function delete_course(int $courseid): void {
        effort_handler::create()->delete_instance($courseid);
    }

    /**
     * Remove references to a deleted course module.
     *
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     */
    public static function delete_course_module(int $courseid, int $cmid): void {
        $document = self::read($courseid);
        $entries = array_filter($document['entries'], static fn(array $entry): bool =>
            (int)($entry['locator']['cmid'] ?? 0) !== $cmid);
        if (count($entries) !== count($document['entries'])) {
            self::write($courseid, $entries);
        }
    }
}
