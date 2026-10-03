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

/** Admin setting with a guided JSON rule editor. */
class admin_setting_mapping extends \admin_setting_configtextarea {
    /** Reject malformed JSON rather than silently losing all configured rows. */
    public function validate($data) {
        global $DB;
        $raw = trim((string)$data);
        if ($raw === '') {
            return true;
        }
        $decoded = json_decode($raw, true);
        $entries = is_array($decoded) ? (array_is_list($decoded) ? $decoded : ($decoded['rules'] ?? null)) : null;
        if (!is_array($entries)) {
            return get_string('mappinginvalidjson', 'local_reschedule');
        }
        if (count(mapping::parse($raw)) !== count($entries)) {
            return get_string('mappinginvalidrules', 'local_reschedule');
        }
        $mainmodules = [];
        foreach ($entries as $entry) {
            if (empty($entry['parent'])) {
                $name = (string)($entry['module'] ?? '');
                if (isset($mainmodules[$name])) {
                    return get_string('mappinginvalidrules', 'local_reschedule');
                }
                $mainmodules[$name] = true;
            }
        }
        $dbman = $DB->get_manager();
        foreach ($entries as $entry) {
            if (in_array($entry['module'] ?? '', mapping::OWNED_MODULES, true) ||
                    in_array($entry['parent'] ?? '', mapping::OWNED_MODULES, true)) {
                return get_string('mappingownedrule', 'local_reschedule');
            }
            $table = (string)$entry['module'];
            $parent = (string)($entry['parent'] ?? '');
            if (!$dbman->table_exists($table) ||
                    ($parent === '' && !$dbman->field_exists($table, 'course')) ||
                    ($parent !== '' && !isset($mainmodules[$parent]))) {
                return get_string('mappinginvalidrules', 'local_reschedule');
            }
            $fields = [(string)$entry['title'], (string)$entry['start']];
            if (($entry['kind'] ?? 'range') === 'range') {
                $fields[] = (string)$entry['end'];
            }
            if ($parent !== '' && $parent !== $table) {
                $fields[] = (string)($entry['fk'] ?? $parent . 'id');
            }
            foreach ($fields as $field) {
                if (!$dbman->field_exists($table, $field)) {
                    return get_string('mappinginvalidrules', 'local_reschedule');
                }
            }
        }
        return true;
    }

    /** Append the editor to the standard Moodle settings control. */
    public function output_html($data, $query = '') {
        global $OUTPUT, $PAGE;
        $helpidentifiers = [
            'module' => 'mappingmodule',
            'kind' => 'mappingkind',
            'title' => 'mappingtitlefield',
            'label' => 'mappinglabel',
            'start' => 'mappingstart',
            'end' => 'mappingend',
            'parent' => 'mappingparent',
            'fk' => 'mappingforeignkey',
            'optional' => 'mappingoptional',
            'dateonly' => 'mappingdateonly',
            'boundstart' => 'mappingboundstart',
            'boundend' => 'mappingboundend',
            'availability' => 'mappingavailability',
            'editable' => 'mappingeditable',
            'effortmodel' => 'mappingeffortmodel',
            'efforthours' => 'mappingefforthours',
            'efforthoursperday' => 'mappingefforthoursperday',
            'raw' => 'mappingraw',
        ];
        $help = [];
        foreach ($helpidentifiers as $field => $identifier) {
            $help[$field] = $OUTPUT->help_icon($identifier, 'local_reschedule');
        }
        $config = [
            'id' => $this->get_id(),
            'modules' => mapping_discovery::modules(),
            'owned' => mapping::OWNED_MODULES,
            'help' => $help,
            'strings' => [
                'heading' => get_string('mappingeditor', 'local_reschedule'),
                'module' => get_string('mappingmodule', 'local_reschedule'),
                'type' => get_string('mappingkind', 'local_reschedule'),
                'range' => get_string('mappingrange', 'local_reschedule'),
                'milestone' => get_string('mappingmilestone', 'local_reschedule'),
                'open' => get_string('mappingopen', 'local_reschedule'),
                'title' => get_string('mappingtitlefield', 'local_reschedule'),
                'label' => get_string('mappinglabel', 'local_reschedule'),
                'start' => get_string('mappingstart', 'local_reschedule'),
                'end' => get_string('mappingend', 'local_reschedule'),
                'parent' => get_string('mappingparent', 'local_reschedule'),
                'fk' => get_string('mappingforeignkey', 'local_reschedule'),
                'optional' => get_string('mappingoptional', 'local_reschedule'),
                'dateonly' => get_string('mappingdateonly', 'local_reschedule'),
                'boundstart' => get_string('mappingboundstart', 'local_reschedule'),
                'boundend' => get_string('mappingboundend', 'local_reschedule'),
                'availability' => get_string('mappingavailability', 'local_reschedule'),
                'effortmodel' => get_string('mappingeffortmodel', 'local_reschedule'),
                'effortdefault' => get_string('mappingeffortdefault', 'local_reschedule'),
                'effortfixed' => get_string('mappingeffortfixed', 'local_reschedule'),
                'effortperday' => get_string('mappingeffortperday', 'local_reschedule'),
                'efforthours' => get_string('mappingefforthours', 'local_reschedule'),
                'efforthoursperday' => get_string('mappingefforthoursperday', 'local_reschedule'),
                'editable' => get_string('mappingeditable', 'local_reschedule'),
                'add' => get_string('mappingadd', 'local_reschedule'),
                'remove' => get_string('mappingremove', 'local_reschedule'),
                'suggestions' => get_string('mappingsuggestions', 'local_reschedule'),
                'raw' => get_string('mappingraw', 'local_reschedule'),
                'originform' => get_string('mappingoriginform', 'local_reschedule'),
                'originschema' => get_string('mappingoriginschema', 'local_reschedule'),
                'ownedrule' => get_string('mappingownedrule', 'local_reschedule'),
            ],
        ];
        $PAGE->requires->js_call_amd('local_reschedule/mapping_editor', 'init', [$this->get_id()]);
        $json = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT |
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return parent::output_html($data, $query) .
            '<script type="application/json" id="' . s($this->get_id()) . '-editor-config">' . $json . '</script>';
    }
}
