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
 * Attendance integration backed by mod_attendance's library API.
 *
 * Reschedule does not query or update Attendance tables. Session discovery,
 * attendance-log lookup and schedule updates are delegated to Attendance.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance_adapter extends base_adapter {
    /** @var array<int, \stdClass>|null Attendance instance records keyed by id. */
    private ?array $attendanceinstances = null;

    /**
     * Return Attendance activity records with ranges derived from their sessions.
     *
     * @param array $rule Normalised mapping rule.
     * @return \stdClass[] Attendance activities keyed by instance id.
     */
    #[\Override]
    public function get_activity_records(array $rule): array {
        if (!$this->load_api()) {
            return [];
        }
        $records = [];
        foreach ($this->get_attendance_instances() as $attendanceid => $instance) {
            $sessions = $this->get_sessions((int)$attendanceid);
            $starts = [];
            $ends = [];
            foreach ($sessions as $session) {
                if ((int)$session->sessdate <= 0) {
                    continue;
                }
                $starts[] = (int)$session->sessdate;
                $ends[] = (int)$session->sessdate + max(0, (int)$session->duration);
            }
            $records[(int)$attendanceid] = (object)[
                'id' => (int)$attendanceid,
                'title' => (string)$instance->name,
                'datestart' => $starts ? min($starts) : 0,
                'dateend' => $ends ? max($ends) : 0,
            ];
        }
        return $records;
    }

    /**
     * Attendance activity ranges are derived from the child session schedule.
     *
     * @param \stdClass $record Attendance activity record.
     * @param array $rule Normalised mapping rule.
     * @return array Adapter attributes.
     */
    #[\Override]
    public function get_activity_attributes(\stdClass $record, array $rule): array {
        return [
            'derived' => true,
            'editable' => false,
            'editreason' => get_string('attendanceactivityderived', 'local_reschedule'),
            'interactreason' => get_string('attendanceactivityderived', 'local_reschedule'),
        ];
    }

    /**
     * Load every session and its attendance log through Attendance's API.
     *
     * @param array $rule Normalised subtype mapping rule.
     * @param int[] $parentids Attendance instance ids.
     * @return \stdClass[] Session records keyed by session id.
     */
    #[\Override]
    public function get_subitems(array $rule, array $parentids): array {
        if (!$this->load_api()) {
            return [];
        }
        $instances = $this->get_attendance_instances();
        $records = [];
        foreach ($parentids as $attendanceid) {
            if (!isset($instances[(int)$attendanceid])) {
                continue;
            }
            foreach ($this->get_sessions((int)$attendanceid) as $session) {
                $start = (int)$session->sessdate;
                $end = $start + max(0, (int)$session->duration);
                $session->parentid = (int)$attendanceid;
                $session->title = $this->session_title($session);
                $session->datestart = $start;
                $session->dateend = $end;
                $session->hasattendance = !empty($session->attendance_log);
                $session->absolutelimits = $this->attendance_limits($session);
                $records[(int)$session->id] = $session;
            }
        }
        uasort($records, static fn(\stdClass $left, \stdClass $right): int =>
            [$left->datestart, $left->id] <=> [$right->datestart, $right->id]);
        return $records;
    }

    /**
     * Return editability and session attendance restrictions.
     *
     * @param \stdClass $record Session record.
     * @param array $rule Normalised subtype mapping rule.
     * @return array Adapter attributes.
     */
    #[\Override]
    public function get_subitem_attributes(\stdClass $record, array $rule): array {
        return [
            'editable' => true,
            'absolutelimits' => $record->absolutelimits ?? [],
        ];
    }

    /**
     * Validate against attendance records again on the server before saving.
     *
     * @param array $item Gantt row.
     * @param int $newstart Proposed start timestamp.
     * @param int $newend Proposed end timestamp.
     * @return array Validation errors.
     */
    #[\Override]
    public function validate(array $item, int $newstart, int $newend): array {
        $errors = parent::validate($item, $newstart, $newend);
        if (($item['table'] ?? '') !== 'attendance_sessions') {
            return $errors;
        }
        $attendanceid = (int)($item['parentrecordid'] ?? 0);
        $sessionid = (int)($item['recordid'] ?? 0);
        $session = $this->get_sessions($attendanceid)[$sessionid] ?? null;
        if (!$session || empty($session->attendance_log)) {
            return $errors;
        }
        $start = (int)$session->sessdate;
        $end = $start + max(0, (int)$session->duration);
        if ($newstart > $start || $newend < $end) {
            $errors[] = $this->limit_reason($session, $start, $end);
        }
        return $errors;
    }

    /**
     * Save session schedule through mod_attendance_structure.
     *
     * @param array $item Gantt row.
     * @param int $newstart New start timestamp.
     * @param int $newend New end timestamp.
     */
    #[\Override]
    public function save(array $item, int $newstart, int $newend): void {
        if (($item['table'] ?? '') !== 'attendance_sessions') {
            return;
        }
        $attendanceid = (int)($item['parentrecordid'] ?? 0);
        $sessionid = (int)($item['recordid'] ?? 0);
        if (!$this->load_api()) {
            throw new \moodle_exception('Attendance module is not available');
        }
        $instance = $this->get_attendance_instances()[$attendanceid] ?? null;
        $cm = get_coursemodule_from_instance(
            'attendance',
            $attendanceid,
            (int)$this->course->id,
            false,
            IGNORE_MISSING
        );
        if (!$instance || !$cm) {
            throw new \moodle_exception('Attendance activity no longer exists');
        }
        $api = new \mod_attendance_structure(
            $instance,
            $cm,
            $this->course,
            \context_module::instance((int)$cm->id)
        );
        $session = $this->get_sessions($attendanceid)[$sessionid] ?? null;
        if (!$session) {
            throw new \moodle_exception('Attendance session no longer exists');
        }
        $api->update_session_from_form_data(
            $this->session_form_data($session, $newstart, $newend, (int)$cm->id),
            $sessionid
        );
    }

    /**
     * Check whether an item belongs to the Attendance adapter.
     *
     * @param string $modname Module name or mapped table.
     * @param array $item Gantt row.
     * @return bool True for Attendance activities or sessions.
     */
    #[\Override]
    public function supports(string $modname, array $item): bool {
        return $modname === 'attendance' || in_array($item['table'] ?? '', ['attendance', 'attendance_sessions'], true);
    }

    /**
     * Load Attendance APIs when the activity plugin is installed.
     *
     * @return bool Whether Attendance is installed and available.
     */
    private function load_api(): bool {
        global $CFG;

        if (!file_exists($CFG->dirroot . '/mod/attendance/classes/structure.php') ||
                !file_exists($CFG->dirroot . '/mod/attendance/classes/attendance_webservices_handler.php')) {
            return false;
        }
        require_once($CFG->dirroot . '/mod/attendance/classes/structure.php');
        require_once($CFG->dirroot . '/mod/attendance/classes/attendance_webservices_handler.php');
        return class_exists('mod_attendance_structure') && class_exists('attendance_handler');
    }

    /**
     * Fetch session objects including attendance-log data from the module API.
     *
     * @param int $attendanceid Attendance instance id.
     * @return \stdClass[] Session records.
     */
    private function get_sessions(int $attendanceid): array {
        return $attendanceid > 0 ? (array)\attendance_handler::get_sessions($attendanceid) : [];
    }

    /**
     * Load Attendance instances through Moodle's module discovery API.
     *
     * `cm_info::get_instance_record()` and `get_course_module_record()` are not
     * available in Moodle 4.5. The long-standing module discovery helper
     * returns the Attendance record without requiring this adapter to query
     * module tables. It also appends course-module fields, which must be
     * removed before passing the record to mod_attendance_structure.
     *
     * @return array<int, \stdClass> Instance records accepted by Attendance.
     */
    private function get_attendance_instances(): array {
        if ($this->attendanceinstances !== null) {
            return $this->attendanceinstances;
        }

        $this->attendanceinstances = [];
        foreach (get_all_instances_in_course('attendance', $this->course, null, true) as $record) {
            $instance = new \stdClass();
            foreach (get_object_vars($record) as $field => $value) {
                if (property_exists(\mod_attendance_structure::class, $field)) {
                    $instance->{$field} = $value;
                }
            }
            $instanceid = (int)($instance->id ?? 0);
            if ($instanceid > 0) {
                $this->attendanceinstances[$instanceid] = $instance;
            }
        }
        return $this->attendanceinstances;
    }

    /**
     * Build the fixed bounds for a session that already has attendance entries.
     *
     * Keep the recorded session interval inside any proposed interval. This
     * prevents a reschedule from excluding dates to which attendance records
     * already refer. The shared Gantt renderer and validation use these bounds.
     *
     * @param \stdClass $session Attendance session with API-provided log data.
     * @return array Absolute limits.
     */
    private function attendance_limits(\stdClass $session): array {
        $start = (int)($session->sessdate ?? 0);
        $end = $start + max(0, (int)($session->duration ?? 0));
        if (empty($session->attendance_log) || $start <= 0 || $end <= $start) {
            return [];
        }
        return [
            ['type' => 'start_max', 'date' => $start,
                'reason' => $this->limit_reason($session, $start, $end)],
            ['type' => 'end_min', 'date' => $end,
                'reason' => $this->limit_reason($session, $start, $end)],
        ];
    }

    /**
     * Describe why a session date cannot cross its attendance boundary.
     *
     * @param \stdClass $session Attendance session.
     * @param int $start Existing session start timestamp.
     * @param int $end Existing session end timestamp.
     * @return string Human-readable reason.
     */
    private function limit_reason(\stdClass $session, int $start, int $end): string {
        return get_string('attendanceabsolutelimit', 'local_reschedule', (object)[
            'session' => format_string($this->session_title($session)),
            'start' => userdate($start, get_string('strftimedatetimeshort', 'langconfig')),
            'end' => userdate($end, get_string('strftimedatetimeshort', 'langconfig')),
        ]);
    }

    /**
     * Build complete form data for Attendance's public session update API.
     *
     * Attendance's update function expects its full edit form, so preserve
     * existing session options and description while changing only the range.
     *
     * @param \stdClass $session Existing session returned by Attendance's API.
     * @param int $start New start timestamp.
     * @param int $end New end timestamp.
     * @param int $cmid Attendance course-module id.
     * @return \stdClass Form data for update_session_from_form_data().
     */
    private function session_form_data(\stdClass $session, int $start, int $end, int $cmid): \stdClass {
        $dateparts = usergetdate($start);
        $sessiondate = make_timestamp($dateparts['year'], $dateparts['mon'], $dateparts['mday'], 0, 0, 0);
        $startoffset = max(0, $start - $sessiondate);
        $endoffset = max(0, $end - $sessiondate);
        $draftitemid = file_get_unused_draft_itemid();
        file_prepare_draft_area(
            $draftitemid,
            \context_module::instance($cmid)->id,
            'mod_attendance',
            'session',
            (int)$session->id,
            ['subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0],
            (string)($session->description ?? '')
        );

        $formdata = new \stdClass();
        $formdata->sessiondate = $sessiondate;
        $formdata->sestime = [
            'starthour' => (int)floor($startoffset / HOURSECS),
            'startminute' => (int)floor(($startoffset % HOURSECS) / MINSECS),
            'endhour' => (int)floor($endoffset / HOURSECS),
            'endminute' => (int)floor(($endoffset % HOURSECS) / MINSECS),
        ];
        $formdata->sdescription = [
            'itemid' => $draftitemid,
            'text' => (string)($session->description ?? ''),
            'format' => (int)($session->descriptionformat ?? FORMAT_HTML),
        ];
        $formdata->calendarevent = $session->calendarevent ?? 0;
        $formdata->absenteereport = $session->absenteereport ?? 0;
        $formdata->autoassignstatus = $session->autoassignstatus ?? 0;
        $formdata->studentscanmark = $session->studentscanmark ?? 0;
        $formdata->allowupdatestatus = $session->allowupdatestatus ?? 0;
        $formdata->studentpassword = $session->studentpassword ?? '';
        $formdata->includeqrcode = $session->includeqrcode ?? 0;
        $formdata->rotateqrcode = 0;
        $formdata->usedefaultsubnet = 0;
        $formdata->subnet = $session->subnet ?? '';
        $formdata->automark = $session->automark ?? 0;
        $formdata->automarkcompleted = $session->automarkcompleted ?? 0;
        $formdata->studentsearlyopentime = $session->studentsearlyopentime ?? 0;
        $formdata->preventsharedip = $session->preventsharedip ?? 0;
        $formdata->preventsharediptime = $session->preventsharediptime ?? '';
        $formdata->automarkcmid = $session->automarkcmid ?? 0;
        return $formdata;
    }

    /**
     * Return a readable session name.
     *
     * @param \stdClass $session Attendance session.
     * @return string Session title.
     */
    private function session_title(\stdClass $session): string {
        $description = trim(strip_tags((string)($session->description ?? '')));
        return $description !== '' ? $description : get_string('modulename', 'attendance');
    }
}
