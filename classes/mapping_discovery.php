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

/** Read installed module forms to suggest date mapping rules for administrators. */
final class mapping_discovery {
    /** Return available modules, schema-backed date fields and likely intervals. */
    public static function modules(): array {
        global $CFG, $DB;
        $result = [];
        $dbman = $DB->get_manager();
        foreach ($DB->get_records('modules', null, 'name', 'name') as $module) {
            $name = (string)$module->name;
            if (!preg_match('/^[a-z][a-z0-9_]*$/D', $name) || !$dbman->table_exists($name)) {
                continue;
            }
            $formfile = $CFG->dirroot . '/mod/' . $name . '/mod_form.php';
            $fields = [];
            if (is_readable($formfile)) {
                $source = file_get_contents($formfile);
                // A form can declare controls over several lines and may use either quote style.
                preg_match_all('/addElement\s*\(\s*[\'\"](date_time_selector|date_selector)[\'\"]\s*,\s*[\'\"]([a-z][a-z0-9_]*)[\'\"]/i',
                    $source, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    if ($dbman->field_exists($name, $match[2])) {
                        $fields[$match[2]] = [
                            'name' => $match[2],
                            'dateonly' => $match[1] === 'date_selector',
                            'origin' => 'mod_form',
                        ];
                    }
                }
            }
            // Modules often add form controls through helpers; schema names remain useful hints.
            foreach ($DB->get_columns($name) as $column) {
                $field = $column->name;
                if (isset($fields[$field]) ||
                        !preg_match('/^(date|time|start|end|open|close|due|deadline|cutoff|available|submission|assessment|assess)/i', $field) ||
                        !preg_match('/(date|time|start|end|open|close|due|finish|from|to|deadline)$/i', $field) ||
                        preg_match('/(created|modified|duration|limit|zone|stamp|update|validate|notification)/i', $field) ||
                        !in_array($column->meta_type ?? '', ['I', 'N'], true)) {
                    continue;
                }
                $fields[$field] = ['name' => $field, 'dateonly' => false, 'origin' => 'schema'];
            }
            $pairs = [];
            foreach ($fields as $start => $data) {
                if (!preg_match('/(start|from|open|available)/i', $start)) {
                    continue;
                }
                $candidates = [
                    preg_replace('/start/i', 'end', $start),
                    preg_replace('/start$/i', 'finish', $start),
                    preg_replace('/from/i', 'to', $start),
                    preg_replace('/open/i', 'close', $start),
                    preg_replace('/available/i', 'deadline', $start),
                ];
                if ($data['origin'] === 'mod_form') {
                    $candidates = array_merge($candidates, ['duedate', 'dateend', 'enddate', 'timeclose']);
                }
                foreach ($candidates as $end) {
                    if ($end !== $start && isset($fields[$end]) &&
                            (!in_array($end, ['duedate', 'dateend', 'enddate', 'timeclose'], true) ||
                                $fields[$end]['origin'] === 'mod_form')) {
                        $pairs[] = ['start' => $start, 'end' => $end];
                        break;
                    }
                }
            }
            $result[$name] = [
                'module' => $name,
                'owned' => in_array($name, mapping::OWNED_MODULES, true),
                'fields' => array_values($fields),
                'pairs' => $pairs,
                'title' => $dbman->field_exists($name, 'name') ? 'name' :
                    ($dbman->field_exists($name, 'title') ? 'title' : ''),
            ];
        }
        return $result;
    }
}
