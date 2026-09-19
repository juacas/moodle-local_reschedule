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
    public function validate(array $item, int $newstart, int $newend): array {
        global $DB;

        $errors = parent::validate($item, $newstart, $newend);
        $title = $item['title'] ?? 'Kuet';

        // Minimum duration safeguard (at least 60 seconds).
        if ($newend > $newstart && ($newend - $newstart) < 60) {
            $errors[] = "{$title}: " . get_string('errorinvaliddate', 'calendar');
        }

        $table = $item['table'] ?? '';
        $recordid = (int)($item['recordid'] ?? 0);

        if ($table === 'kuet_sessions') {
            $session = $DB->get_record('kuet_sessions', ['id' => $recordid]);
            if (!$session) {
                $errors[] = "{$title}: " . get_string('invalidrecord', 'error');
            } else if (!self::is_programmed_session_mode((string)$session->sessionmode)) {
                $errors[] = "{$title}: " . get_string('kuetsessionnoteditable', 'local_reschedule');
            }
        }

        return $errors;
    }

    #[\Override]
    public function save(array $item, int $newstart, int $newend): void {
        global $DB;

        $table = $item['table'] ?? '';
        $recordid = (int)($item['recordid'] ?? 0);

        if ($table === 'kuet_sessions') {
            $session = $DB->get_record('kuet_sessions', ['id' => $recordid]);
            if (!$session || !self::is_programmed_session_mode((string)$session->sessionmode)) {
                return;
            }

            $session->startdate = $newstart;
            $session->enddate = $newend;
            $session->timemodified = time();

            // When scheduling dates, activate automatic start.
            $session->automaticstart = 1;

            // If session was marked finished (0) but is being rescheduled for future, reactivate it (1 = SESSION_ACTIVE).
            if ((int)$session->status === 0 && $newstart > time()) {
                $session->status = 1;
            }

            $DB->update_record('kuet_sessions', $session);

            // Trigger core cm update so caches and completion reflect the change.
            $cm = $this->get_cm('kuet', (int)$session->kuetid);
            if ($cm) {
                $this->trigger_cm_updated($cm);
            }
            return;
        }

        // Updating parent kuet activity container.
        $kuet = $DB->get_record('kuet', ['id' => $recordid]);
        if (!$kuet) {
            return;
        }

        $kuet->timemodified = time();
        $DB->update_record('kuet', $kuet);

        $cm = $this->get_cm('kuet', $recordid);
        if ($cm) {
            $this->trigger_cm_updated($cm);
        }
    }
}
