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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.

namespace local_reschedule;

/** Validated JSON definitions of Gantt rows. */
final class mapping {
    /** Modules whose rows belong to code adapters. */
    public const OWNED_MODULES = ['assign', 'quiz', 'workshop', 'quest', 'quest_submissions',
        'kuet', 'kuet_sessions'];

    /** Default rules that an administrator may edit. */
    public const DEFAULT_JSON = <<<'JSON'
[
  {"module":"lesson","title":"name","label":"Lesson","kind":"range","start":"available","end":"deadline"},
  {"module":"feedback","title":"name","label":"Feedback","kind":"range","start":"timeopen","end":"timeclose"},
  {"module":"choice","title":"name","label":"Choice","kind":"range","start":"timeopen","end":"timeclose"},
  {"module":"data","title":"name","label":"Database","kind":"range","start":"timeavailablefrom","end":"timeavailableto"},
  {"module":"scorm","title":"name","label":"SCORM","kind":"range","start":"timeopen","end":"timeclose"}
]
JSON;

    /** Adapter-owned display definitions are never edited through the setting. */
    private const BUILTIN_JSON = <<<'JSON'
[
  {"module":"assign","title":"name","label":"Assignment","kind":"range","start":"allowsubmissionsfromdate","end":"duedate"},
  {"module":"quiz","title":"name","label":"Quiz","kind":"range","start":"timeopen","end":"timeclose"},
  {"module":"workshop","title":"name","label":"Workshop","kind":"range","start":"submissionstart","end":"assessmentend"},
  {"module":"workshop","title":"name","label":"Workshop - Submission Phase","kind":"range","start":"submissionstart","end":"submissionend","parent":"workshop"},
  {"module":"workshop","title":"name","label":"Workshop - Assessment Phase","kind":"range","start":"assessmentstart","end":"assessmentend","parent":"workshop"},
  {"module":"quest","title":"name","label":"Questournament","kind":"range","start":"datestart","end":"dateend"},
  {"module":"quest_submissions","title":"title","label":"Quest Challenge","kind":"range","start":"datestart","end":"dateend","parent":"quest","fk":"questid"},
  {"module":"kuet","title":"name","label":"Kuet","kind":"range","start":"startdate","end":"enddate"},
  {"module":"kuet_sessions","title":"name","label":"Kuet Session","kind":"range","start":"startdate","end":"enddate","parent":"kuet","fk":"kuetid"}
]
JSON;

    /** Parse and validate JSON rules before using identifiers in SQL. */
    public static function parse(string $raw): array {
        $decoded = json_decode($raw, true);
        $entries = is_array($decoded) ? (array_is_list($decoded) ? $decoded : ($decoded['rules'] ?? null)) : null;
        if (!is_array($entries)) {
            return [];
        }
        $rules = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $table = (string)($entry['module'] ?? '');
            $title = (string)($entry['title'] ?? 'name');
            $label = (string)($entry['label'] ?? $table);
            $start = (string)($entry['start'] ?? '');
            $end = (string)($entry['end'] ?? '');
            $kind = (string)($entry['kind'] ?? 'range');
            $parent = (string)($entry['parent'] ?? '');
            $fk = (string)($entry['fk'] ?? ($parent !== '' ? $parent . 'id' : ''));
            $identifier = static fn(string $value): bool =>
                (bool)preg_match('/^[a-z][a-z0-9_]*$/iD', $value);
            if (!in_array($kind, ['range', 'milestone', 'open'], true) ||
                    !$identifier($table) || !$identifier($title) || !$identifier($start) ||
                    ($kind === 'range' && !$identifier($end)) ||
                    ($kind === 'milestone' && $end !== '' && $end !== $start) ||
                    ($kind === 'open' && $end !== '') ||
                    ($parent !== '' && (!$identifier($parent) || !$identifier($fk))) || $label === '') {
                continue;
            }
            if (!in_array($entry['availability'] ?? 'auto', ['auto', 'off'], true)) {
                continue;
            }
            foreach (['optional', 'dateonly', 'boundstart', 'boundend', 'editable'] as $key) {
                if (array_key_exists($key, $entry) && !is_bool($entry[$key])) {
                    continue 2;
                }
            }
            $estimator = self::normalise_effort($entry['effort'] ?? null);
            if ($estimator === false || ($estimator !== null && $estimator['model'] === 'perday' &&
                    $kind !== 'range')) {
                continue;
            }
            $rules[] = [
                'issubtype' => $parent !== '',
                'table' => $table,
                'titlecol' => $title,
                'label' => $label,
                'startcol' => $start,
                'endcol' => $kind === 'range' ? $end : ($kind === 'milestone' ? $start : ''),
                'foreignkey' => $parent !== '' ? $fk : null,
                'parenttable' => $parent !== '' ? $parent : null,
                'kind' => $kind,
                'optional' => !empty($entry['optional']),
                'dateonly' => !empty($entry['dateonly']),
                'boundtoparentstart' => !array_key_exists('boundstart', $entry) || (bool)$entry['boundstart'],
                'boundtoparentend' => !array_key_exists('boundend', $entry) || (bool)$entry['boundend'],
                'editable' => !array_key_exists('editable', $entry) || (bool)$entry['editable'],
                'availability' => ($entry['availability'] ?? 'auto') === 'off' ? 'off' : 'auto',
                'effortestimator' => $estimator,
            ];
        }
        return $rules;
    }

    /** Normalise a fixed estimate or proportional hours-per-day estimate. */
    private static function normalise_effort($value) {
        if ($value === null) {
            return null;
        }
        if (is_numeric($value)) {
            $value = ['model' => 'fixed', 'hours' => $value];
        }
        if (!is_array($value)) {
            return false;
        }
        $model = $value['model'] ?? '';
        $field = $model === 'fixed' ? 'hours' : 'hoursperday';
        $hours = $value[$field] ?? null;
        if (!in_array($model, ['fixed', 'perday'], true) || !is_numeric($hours) ||
                !is_finite((float)$hours) || (float)$hours < 0) {
            return false;
        }
        return ['model' => $model, 'value' => (float)$hours];
    }

    /** Estimate effort in hours from actual endpoints, never from drawing fallbacks. */
    public static function estimate(?array $estimator, int $start, int $end): ?float {
        if ($estimator === null) {
            return null;
        }
        if ($estimator['model'] === 'fixed') {
            return $estimator['value'];
        }
        return $start > 0 && $end > $start ?
            round(($end - $start) / DAYSECS * $estimator['value'], 4) : 0.0;
    }

    /** Combine adapter-owned rows with custom rules. */
    public static function effective_rules(?string $configured): array {
        $rules = self::parse(self::BUILTIN_JSON);
        foreach (self::parse($configured === null || $configured === '' ? self::DEFAULT_JSON : $configured) as $rule) {
            if (in_array($rule['table'], self::OWNED_MODULES, true) ||
                    in_array($rule['parenttable'], self::OWNED_MODULES, true)) {
                continue;
            }
            $rules[] = $rule;
        }
        return $rules;
    }
}
