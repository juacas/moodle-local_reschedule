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
 * Safe adapter for mod_kuet and kuet_sessions.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class kuet_adapter extends base_adapter {
    /**
     * Load the date range of KUET activities through the module API.
     *
     * @param array $rule Normalised mapping rule.
     * @return \stdClass[] Activity records keyed by instance ID.
     */
    #[\Override]
    public function get_activity_records(array $rule): array {
        return self::get_course_activity_ranges((int)$this->course->id);
    }

    /**
     * Explain that a KUET range comes from its sessions and cannot be edited.
     *
     * @param \stdClass $record Loaded activity record.
     * @param array $rule Normalised mapping rule.
     * @return array Adapter-specific item attributes.
     */
    #[\Override]
    public function get_activity_attributes(\stdClass $record, array $rule): array {
        return [
            'derived' => true,
            'editable' => false,
            'editreason' => get_string('kuetactivitynoteditable', 'local_reschedule'),
            'interactreason' => get_string('kuetactivityderivedhint', 'local_reschedule'),
        ];
    }

    /**
     * Load programmed sessions through each KUET activity API.
     *
     * @param array $rule Normalised subtype mapping rule.
     * @param int[] $parentids Parent KUET IDs.
     * @return \stdClass[] Session records keyed by session ID.
     */
    #[\Override]
    public function get_subitems(array $rule, array $parentids): array {
        if (!class_exists(\mod_kuet\kuet::class)) {
            return [];
        }

        $modinfo = get_fast_modinfo($this->course->id);
        $records = [];
        foreach ($parentids as $parentid) {
            $cm = $modinfo->instances['kuet'][(int)$parentid] ?? null;
            if (!$cm) {
                continue;
            }
            $kuetapi = new \mod_kuet\kuet((int)$cm->id);
            foreach ($kuetapi->get_sessions() as $session) {
                if (!$session->is_programmed_mode()) {
                    continue;
                }
                $record = (object)[
                    'id' => (int)$session->get('id'),
                    'parentid' => (int)$session->get('kuetid'),
                    'title' => (string)$session->get('name'),
                    'datestart' => (int)$session->get('startdate'),
                    'dateend' => (int)$session->get('enddate'),
                    'sessionmode' => (string)$session->get('sessionmode'),
                ];
                $records[$record->id] = $record;
            }
        }

        uasort($records, static fn(\stdClass $left, \stdClass $right): int =>
            [$left->datestart, $left->id] <=> [$right->datestart, $right->id]);
        return $records;
    }

    /**
     * Determine whether a loaded KUET session may be edited.
     *
     * @param \stdClass $record Session record.
     * @param array $rule Normalised subtype mapping rule.
     * @return array Adapter-specific item attributes.
     */
    #[\Override]
    public function get_subitem_attributes(\stdClass $record, array $rule): array {
        $editable = self::is_programmed_session_mode((string)($record->sessionmode ?? ''));
        return [
            'editable' => $editable,
            'absolutelimits' => [],
        ];
    }

    /**
     * Find the parent KUET API for an item.
     *
     * Prefer the parent module supplied by the Gantt item. If it is missing,
     * inspect course KUET instances and find the one containing the session.
     *
     * @param array $item Reschedule item metadata.
     * @return object|null KUET API, or null when the parent no longer exists.
     */
    private function get_kuet_api(array $item): ?object {
        if (!class_exists(\mod_kuet\kuet::class)) {
            return null;
        }

        $kuetid = (int)($item['parentrecordid'] ?? 0);
        if ($kuetid <= 0 && preg_match('/^main_kuet_(\d+)$/', (string)($item['parentkey'] ?? ''), $matches)) {
            $kuetid = (int)$matches[1];
        }

        $modinfo = get_fast_modinfo($this->course->id);
        $instances = $modinfo->instances['kuet'] ?? [];
        if ($kuetid > 0) {
            $cm = $instances[$kuetid] ?? null;
            $instances = $cm ? [$kuetid => $cm] : [];
        }

        foreach ($instances as $cm) {
            $kuetapi = new \mod_kuet\kuet((int)$cm->id);
            if ($kuetid > 0 || $this->get_session($kuetapi, (int)($item['recordid'] ?? 0))) {
                return $kuetapi;
            }
        }

        return null;
    }

    /**
     * Find a session through the mod_kuet API.
     *
     * @param object $kuetapi KUET module API.
     * @param int $sessionid Session ID.
     * @return object|null KUET session, or null when it no longer exists.
     */
    private function get_session(object $kuetapi, int $sessionid): ?object {
        foreach ($kuetapi->get_sessions() as $session) {
            if ((int)$session->get('id') === $sessionid) {
                return $session;
            }
        }

        return null;
    }

    /**
     * Build the derived date range of each KUET activity in a course.
     *
     * KUET stores dates on its sessions rather than on the activity record.
     * Use the module's public service facade so KUET remains responsible for
     * loading sessions and deciding which modes are programmed.
     *
     * @param int $courseid Course ID.
     * @return \stdClass[] Activity records keyed by KUET instance ID.
     */
    public static function get_course_activity_ranges(int $courseid): array {
        if (!class_exists(\mod_kuet\kuet::class)) {
            return [];
        }

        $modinfo = get_fast_modinfo($courseid);
        $ranges = [];
        foreach ($modinfo->instances['kuet'] ?? [] as $cm) {
            $kuetapi = new \mod_kuet\kuet((int)$cm->id);
            $activity = $kuetapi->get_kuet();
            $range = null;
            foreach ($kuetapi->get_sessions() as $session) {
                if (!$session->is_programmed_mode()) {
                    continue;
                }
                if ($range === null) {
                    $range = (object)[
                        'id' => (int)$activity->id,
                        'title' => (string)$activity->name,
                        'datestart' => 0,
                        'dateend' => 0,
                    ];
                }

                $startdate = (int)$session->get('startdate');
                $enddate = (int)$session->get('enddate');
                if ($startdate <= 0 || $enddate <= 0) {
                    continue;
                }
                if ($range->datestart === 0 || $startdate < $range->datestart) {
                    $range->datestart = $startdate;
                }
                $range->dateend = max($range->dateend, $enddate);
            }
            if ($range !== null) {
                $ranges[$range->id] = $range;
            }
        }

        uasort($ranges, static fn(\stdClass $left, \stdClass $right): int =>
            [$left->datestart, $left->id] <=> [$right->datestart, $right->id]);
        return $ranges;
    }

    /**
     * Check whether a KUET session uses a scheduled/programmed mode.
     *
     * @param string $sessionmode Session mode value.
     * @return bool
     */
    public static function is_programmed_session_mode(string $sessionmode): bool {
        return in_array($sessionmode, [
            'podium_programmed',
            'race_programmed',
            'inactive_programmed',
        ], true);
    }

    #[\Override]
    public function supports(string $modname, array $item): bool {
        return ($modname === 'kuet' || ($item['table'] ?? '') === 'kuet' || ($item['table'] ?? '') === 'kuet_sessions');
    }

    #[\Override]
    public function is_derived_item(array $item): bool {
        return ($item['table'] ?? '') === 'kuet';
    }

    #[\Override]
    public function validate(array $item, int $newstart, int $newend): array {
        $errors = parent::validate($item, $newstart, $newend);
        $title = $item['title'] ?? 'Kuet';

        // Minimum duration safeguard (at least 60 seconds).
        if ($newend > $newstart && ($newend - $newstart) < 60) {
            $errors[] = "{$title}: " . get_string('errorinvaliddate', 'calendar');
        }

        $table = $item['table'] ?? '';
        $recordid = (int)($item['recordid'] ?? 0);

        if ($table === 'kuet_sessions') {
            $kuetapi = $this->get_kuet_api($item);
            $session = $kuetapi ? $this->get_session($kuetapi, $recordid) : null;
            if (!$session) {
                $errors[] = "{$title}: " . get_string('invalidrecord', 'error');
            } else if (!$session->is_programmed_mode()) {
                $errors[] = "{$title}: " . get_string('kuetsessionnoteditable', 'local_reschedule');
            }
        }

        return $errors;
    }

    #[\Override]
    public function save(array $item, int $newstart, int $newend): void {
        $table = $item['table'] ?? '';
        $recordid = (int)($item['recordid'] ?? 0);

        if ($table === 'kuet_sessions') {
            $kuetapi = $this->get_kuet_api($item);
            $session = $kuetapi ? $this->get_session($kuetapi, $recordid) : null;
            if (!$kuetapi || !$session || !$session->is_programmed_mode()) {
                return;
            }

            $kuetapi->update_session_schedule((int)$session->get('id'), $newstart, $newend);

            // Trigger core cm update so caches and completion reflect the change.
            $cm = $this->get_cm('kuet', (int)$session->get('kuetid'));
            if ($cm) {
                $this->trigger_cm_updated($cm);
            }
            return;
        }

        // The parent date range is derived from sessions and is not stored on the activity.
        $cm = $this->get_cm('kuet', $recordid);
        if ($cm) {
            $this->trigger_cm_updated($cm);
        }
    }
}
