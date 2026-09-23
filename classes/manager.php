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

namespace local_reschedule;

/**
 * Manager class for local_reschedule.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /**
     * Parse mapping rules configured in settings.
     *
     * @return array Array of parsed rules.
     */
    public static function get_mapping_rules(): array {
        $raw = get_config('local_reschedule', 'mapping');
        if (empty($raw)) {
            $raw = "assign,name,Assignment,allowsubmissionsfromdate,duedate\n" .
                "quiz,name,Quiz,timeopen,timeclose\n" .
                "workshop,name,Workshop,submissionstart,assessmentend\n" .
                "-workshop,name,Workshop - Submission Phase,submissionstart,submissionend\n" .
                "-workshop,name,Workshop - Assessment Phase,assessmentstart,assessmentend\n" .
                "lesson,name,Lesson,available,deadline\n" .
                "feedback,name,Feedback,timeopen,timeclose\n" .
                "choice,name,Choice,timeopen,timeclose\n" .
                "data,name,Database,timeavailablefrom,timeavailableto\n" .
                "scorm,name,SCORM,timeopen,timeclose\n" .
                "quest,name,Questournament,datestart,dateend\n" .
                "-quest_submissions,title,Quest Challenge,datestart,dateend,questid\n" .
                "kuet,name,Kuet,startdate,enddate\n" .
                "-kuet_sessions,name,Kuet Session,startdate,enddate,kuetid";
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $rules = [];
        $currentparent = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '//')) {
                continue;
            }

            $issubtype = str_starts_with($line, '-');
            if ($issubtype) {
                $line = ltrim($line, '-');
            }

            $parts = array_map('trim', explode(',', $line));
            if (count($parts) < 5) {
                continue;
            }

            $table = $parts[0];
            $titlecol = $parts[1];
            $label = $parts[2];
            $startcol = $parts[3];
            $endcol = $parts[4];
            $foreignkey = $parts[5] ?? null;

            if (!$issubtype) {
                $currentparent = $table;
                $rules[] = [
                    'issubtype' => false,
                    'table' => $table,
                    'titlecol' => $titlecol,
                    'label' => $label,
                    'startcol' => $startcol,
                    'endcol' => $endcol,
                    'foreignkey' => null,
                    'parenttable' => null,
                ];
            } else {
                $rules[] = [
                    'issubtype' => true,
                    'table' => $table,
                    'titlecol' => $titlecol,
                    'label' => $label,
                    'startcol' => $startcol,
                    'endcol' => $endcol,
                    'foreignkey' => $foreignkey ?: ($currentparent ? $currentparent . 'id' : 'parentid'),
                    'parenttable' => $currentparent,
                ];
            }
        }

        return $rules;
    }

    /**
     * Compute effective course start and end timestamps.
     *
     * @param \stdClass $course Course object.
     * @return array Associative array with 'start' and 'end' keys.
     */
    public static function get_course_timeframe(\stdClass $course): array {
        $start = (int)$course->startdate;
        if ($start <= 0) {
            $start = (int)($course->timecreated ?? time());
        }

        $end = (int)$course->enddate;
        // If end date is missing or not greater than start date, default to 4 months (120 days) after start.
        if ($end <= 0 || $end <= $start) {
            $end = $start + (120 * 86400);
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Retrieve all activities and subtypes to schedule for a course.
     *
     * @param int $courseid Course ID.
     * @return array Array of item objects ready for timeline and JSON output.
     */
    public static function get_course_items(int $courseid): array {
        global $DB, $OUTPUT;

        $rules = self::get_mapping_rules();
        $dbman = $DB->get_manager();
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $timeframe = self::get_course_timeframe($course);
        $coursestart = $timeframe['start'];
        $courseend = $timeframe['end'];

        $modinfo = get_fast_modinfo($courseid);

        $items = [];
        $itemorders = [];
        $parentitems = []; // Cache of parent records by parent table.
        $childrenbyparent = []; // Group subitems by parent key.

        // Use the same section and activity sequence as the course page.
        $courseorder = 0;
        $cmorder = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            foreach ($section->get_sequence_cm_infos() as $cm) {
                $cmorder[(int)$cm->id] = $courseorder++;
            }
        }

        // Pass 1: Process main activities.
        foreach ($rules as $rule) {
            if ($rule['issubtype']) {
                continue;
            }

            $table = $rule['table'];
            if (!$dbman->table_exists($table)) {
                continue;
            }

            $titlecol = $rule['titlecol'];
            $startcol = $rule['startcol'];
            $endcol = $rule['endcol'];

            // Query module instance records.
            // Check if course column exists in table.
            $hasdates = $dbman->field_exists($table, $startcol) && $dbman->field_exists($table, $endcol);
            $fieldsexist = $dbman->field_exists($table, 'id') &&
                $dbman->field_exists($table, $titlecol) &&
                ($hasdates || $table === 'kuet');

            if (!$fieldsexist) {
                continue;
            }

            $records = [];
            if ($hasdates && $dbman->field_exists($table, 'course')) {
                $sql = "SELECT t.id, t.{$titlecol} AS title, t.{$startcol} AS datestart, t.{$endcol} AS dateend
                          FROM {{$table}} t
                         WHERE t.course = :courseid
                      ORDER BY t.{$startcol} ASC, t.id ASC";
                $records = $DB->get_records_sql($sql, ['courseid' => $courseid]);
            } else if ($table === 'kuet' && $dbman->field_exists('kuet', 'course')) {
                // KUET activity timeframe is dynamically bounded by programmed sessions.
                // Manual sessions have no scheduling meaning and must not enter this range.
                $sql = "SELECT k.id, k.{$titlecol} AS title,
                               COALESCE(MIN(CASE WHEN s.startdate > 0 AND s.enddate > 0
                                   THEN s.startdate ELSE NULL END), 0) AS datestart,
                               COALESCE(MAX(CASE WHEN s.startdate > 0 AND s.enddate > 0
                                   THEN s.enddate ELSE NULL END), 0) AS dateend
                          FROM {kuet} k
                          JOIN {kuet_sessions} s ON s.kuetid = k.id
                           AND s.sessionmode IN
                               ('podium_programmed', 'race_programmed', 'inactive_programmed')
                         WHERE k.course = :courseid
                      GROUP BY k.id, k.{$titlecol}
                      ORDER BY datestart ASC, k.id ASC";
                $records = $DB->get_records_sql($sql, ['courseid' => $courseid]);
            }

            $parentitems[$table] = $records;

            foreach ($records as $rec) {
                $itemid = 'main_' . $table . '_' . $rec->id;
                $rawstart = (int)$rec->datestart;
                $rawend = (int)$rec->dateend;
                $startenabled = $rawstart > 0;
                $endenabled = $rawend > 0;
                $itemmetadata = ['table' => $table];
                $isderived = adapter_manager::get_adapter($table, $course, $itemmetadata)
                    ->is_derived_item($itemmetadata);

                // Disabled endpoints use the course limits only for drawing.
                $ds = $startenabled ? $rawstart : $coursestart;
                $de = $endenabled ? $rawend : $courseend;
                if ($de <= $ds) {
                    $de = max($de, $ds + 3600);
                }

                // Resolve activity icon.
                $cm = $modinfo->instances[$table][$rec->id] ?? null;
                if ($cm) {
                    $iconurl = $cm->get_icon_url()->out(false);
                } else {
                    try {
                        $iconurl = $OUTPUT->image_url('monologo', $table)->out(false);
                    } catch (\Exception $e) {
                        try {
                            $iconurl = $OUTPUT->image_url('icon', $table)->out(false);
                        } catch (\Exception $ex) {
                            $iconurl = '';
                        }
                    }
                }

                // Build view URL from course module.
                $viewurl = '';
                if ($cm) {
                    $viewurl = $cm->get_url() ? $cm->get_url()->out(false) : '';
                }

                $itemorders[$itemid] = $cm ? ($cmorder[(int)$cm->id] ?? PHP_INT_MAX) : PHP_INT_MAX;

                $items[$itemid] = [
                    'id' => $itemid,
                    'recordid' => (int)$rec->id,
                    'cmid' => $cm ? (int)$cm->id : 0,
                    'table' => $table,
                    'parentkey' => null,
                    'parenttitle' => null,
                    'issubtype' => false,
                    'haschildren' => false,
                    'childrencount' => 0,
                    'iconurl' => $iconurl,
                    'viewurl' => $viewurl,
                    'editable' => !$isderived,
                    'derived' => $isderived,
                    'editreason' => $isderived ?
                        get_string('kuetactivitynoteditable', 'local_reschedule') : '',
                    'interactreason' => $isderived ?
                        get_string('kuetactivityderivedhint', 'local_reschedule') : '',
                    'interactive' => true,
                    'title' => (string)$rec->title,
                    'typelabel' => $rule['label'],
                    'startcol' => $startcol,
                    'endcol' => $endcol,
                    'startenabled' => $startenabled,
                    'endenabled' => $endenabled,
                    'datestart' => $ds,
                    'dateend' => $de,
                ];
            }
        }

        // Pass 2: Process subtypes / phases.
        foreach ($rules as $rule) {
            if (!$rule['issubtype']) {
                continue;
            }

            $parenttable = $rule['parenttable'];
            if (!$parenttable || empty($parentitems[$parenttable])) {
                continue;
            }

            $subtable = $rule['table'];
            if (!$dbman->table_exists($subtable)) {
                continue;
            }

            $titlecol = $rule['titlecol'];
            $startcol = $rule['startcol'];
            $endcol = $rule['endcol'];
            $fkey = $rule['foreignkey'];

            $issametbl = ($subtable === $parenttable);

            if ($issametbl) {
                // Subtype is a phase stored in the same parent record (e.g. Workshop submission/assessment phases).
                if (!$dbman->field_exists($subtable, $startcol) || !$dbman->field_exists($subtable, $endcol)) {
                    continue;
                }

                // Query fresh parent records with phase columns.
                $parentids = array_keys($parentitems[$parenttable]);
                [$insql, $inparams] = $DB->get_in_or_equal($parentids, SQL_PARAMS_NAMED);
                $sql = "SELECT id, {$titlecol} AS title, {$startcol} AS datestart, {$endcol} AS dateend
                          FROM {{$subtable}}
                         WHERE id $insql";
                $phases = $DB->get_records_sql($sql, $inparams);

                foreach ($phases as $prec) {
                    $parentkey = 'main_' . $parenttable . '_' . $prec->id;
                    $itemid = 'sub_' . $subtable . '_' . $prec->id . '_' . $startcol;
                    $rawstart = (int)$prec->datestart;
                    $rawend = (int)$prec->dateend;
                    $startenabled = $rawstart > 0;
                    $endenabled = $rawend > 0;
                    $parentstart = $items[$parentkey]['datestart'] ?? $coursestart;
                    $ds = $startenabled ? $rawstart : $parentstart;
                    $de = $endenabled ? $rawend : ($items[$parentkey]['dateend'] ?? $courseend);
                    if ($de <= $ds) {
                        $de = max($de, $ds + 3600);
                    }

                    if (isset($items[$parentkey])) {
                        $items[$parentkey]['haschildren'] = true;
                        $items[$parentkey]['childrencount']++;
                    }

                    $parenticon = $items[$parentkey]['iconurl'] ?? '';

                    $parentviewurl = $items[$parentkey]['viewurl'] ?? '';

                    $subitem = [
                        'id' => $itemid,
                        'recordid' => (int)$prec->id,
                        'cmid' => $items[$parentkey]['cmid'] ?? 0,
                        'table' => $subtable,
                        'parentkey' => $parentkey,
                        'parenttitle' => (string)$prec->title,
                        'issubtype' => true,
                        'haschildren' => false,
                        'childrencount' => 0,
                        'iconurl' => $parenticon,
                        'viewurl' => $parentviewurl,
                        'editable' => true,
                        'derived' => false,
                        'interactive' => true,
                        'title' => (string)$prec->title . ' - ' . $rule['label'],
                        'typelabel' => $rule['label'],
                        'startcol' => $startcol,
                        'endcol' => $endcol,
                        'startenabled' => $startenabled,
                        'endenabled' => $endenabled,
                        'datestart' => $ds,
                        'dateend' => $de,
                    ];

                    $childrenbyparent[$parentkey][] = $subitem;
                }
            } else {
                // Subtype is in a related child table (e.g. quest_submissions with questid).
                if (
                    !$dbman->field_exists($subtable, $fkey) ||
                    !$dbman->field_exists($subtable, $titlecol) ||
                    !$dbman->field_exists($subtable, $startcol) ||
                    !$dbman->field_exists($subtable, $endcol)
                ) {
                    continue;
                }

                $parentids = array_keys($parentitems[$parenttable]);
                [$insql, $inparams] = $DB->get_in_or_equal($parentids, SQL_PARAMS_NAMED);

                // For kuet_sessions, only programmed sessions have meaningful schedule dates.
                $extracols = '';
                if ($subtable === 'kuet_sessions' && $dbman->field_exists($subtable, 'sessionmode')) {
                    $extracols = ', sessionmode';
                }

                $sessionfilter = '';
                if ($subtable === 'kuet_sessions') {
                    $sessionfilter = " AND sessionmode IN
                        ('podium_programmed', 'race_programmed', 'inactive_programmed')";
                }

                $sql = "SELECT id, {$fkey} AS parentid, {$titlecol} AS title, "
                    . "{$startcol} AS datestart, {$endcol} AS dateend{$extracols}
                          FROM {{$subtable}}
                         WHERE {$fkey} $insql{$sessionfilter}
                      ORDER BY {$startcol} ASC, id ASC";
                $children = $DB->get_records_sql($sql, $inparams);

                foreach ($children as $ch) {
                    $parentkey = 'main_' . $parenttable . '_' . $ch->parentid;
                    $itemid = 'sub_' . $subtable . '_' . $ch->id;
                    $rawstart = (int)$ch->datestart;
                    $rawend = (int)$ch->dateend;
                    $startenabled = $rawstart > 0;
                    $endenabled = $rawend > 0;
                    $parentstart = $items[$parentkey]['datestart'] ?? $coursestart;
                    $ds = $startenabled ? $rawstart : $parentstart;
                    $de = $endenabled ? $rawend : ($items[$parentkey]['dateend'] ?? $courseend);
                    if ($de <= $ds) {
                        $de = max($de, $ds + 3600);
                    }

                    $parenttitle = $items[$parentkey]['title'] ?? '';
                    $parenticon = $items[$parentkey]['iconurl'] ?? '';

                    if (isset($items[$parentkey])) {
                        $items[$parentkey]['haschildren'] = true;
                        $items[$parentkey]['childrencount']++;
                    }

                    // Determine if this subitem is editable.
                    // For kuet_sessions, only programmed modes are editable.
                    $editable = true;
                    if ($subtable === 'kuet_sessions') {
                        $editable = isset($ch->sessionmode) &&
                            \local_reschedule\adapter\kuet_adapter::is_programmed_session_mode((string)$ch->sessionmode);
                    }

                    $parentviewurl = $items[$parentkey]['viewurl'] ?? '';

                    $subitem = [
                        'id' => $itemid,
                        'recordid' => (int)$ch->id,
                        'cmid' => $items[$parentkey]['cmid'] ?? 0,
                        'table' => $subtable,
                        'parentkey' => $parentkey,
                        'parenttitle' => $parenttitle,
                        'issubtype' => true,
                        'haschildren' => false,
                        'childrencount' => 0,
                        'iconurl' => $parenticon,
                        'viewurl' => $parentviewurl,
                        'editable' => $editable,
                        'derived' => false,
                        'interactive' => $editable,
                        'title' => (string)$ch->title,
                        'typelabel' => $rule['label'],
                        'startcol' => $startcol,
                        'endcol' => $endcol,
                        'startenabled' => $startenabled,
                        'endenabled' => $endenabled,
                        'datestart' => $ds,
                        'dateend' => $de,
                    ];

                    $childrenbyparent[$parentkey][] = $subitem;
                }
            }
        }

        // Availability is a Moodle-level capability, so it can expose an
        // activity even when no local date adapter knows how to read that
        // module's native scheduling fields. Such rows deliberately use the
        // course timeframe as a drawing window and keep both native endpoints
        // disabled; the Gantt then renders them as an unbounded activity
        // (<< activity >>) while the availability layer remains visible.
        $availabilityadapter = adapter_manager::get_availability_adapter($course);
        $mappedcmids = [];
        foreach ($items as $item) {
            $cmid = (int)($item['cmid'] ?? 0);
            if ($cmid > 0) {
                $mappedcmids[$cmid] = true;
            }
        }
        foreach ($modinfo->get_cms() as $cm) {
            $cmid = (int)$cm->id;
            if ($cmid <= 0 || isset($mappedcmids[$cmid])) {
                continue;
            }

            $availability = $availabilityadapter->describe($cm);
            if (empty($availability['hasavailabilitydates'])) {
                continue;
            }

            $itemid = 'main_' . $cm->modname . '_' . $cm->instance;
            $itemorders[$itemid] = $cmorder[$cmid] ?? PHP_INT_MAX;
            $items[$itemid] = array_merge([
                'id' => $itemid,
                'recordid' => (int)$cm->instance,
                'cmid' => $cmid,
                'table' => (string)$cm->modname,
                'parentkey' => null,
                'parenttitle' => null,
                'issubtype' => false,
                'haschildren' => false,
                'childrencount' => 0,
                'iconurl' => $cm->get_icon_url()->out(false),
                'viewurl' => $cm->get_url() ? $cm->get_url()->out(false) : '',
                'editable' => false,
                'derived' => false,
                'editreason' => get_string('unmappedactivitynoteditable', 'local_reschedule'),
                'interactreason' => get_string('unmappedactivitynoteditable', 'local_reschedule'),
                'interactive' => false,
                'title' => (string)$cm->name,
                'typelabel' => (string)$cm->modname,
                'startcol' => '',
                'endcol' => '',
                'startenabled' => false,
                'endenabled' => false,
                'datestart' => $coursestart,
                'dateend' => $courseend,
            ], $availability);
        }

        uasort($items, static function(array $left, array $right) use ($itemorders): int {
            $leftorder = $itemorders[$left['id']] ?? PHP_INT_MAX;
            $rightorder = $itemorders[$right['id']] ?? PHP_INT_MAX;
            return $leftorder <=> $rightorder ?: strcmp($left['id'], $right['id']);
        });

        // Interleave main items followed immediately by their respective subitems.
        $ordereditems = [];
        foreach ($items as $parentkey => $mainitem) {
            $ordereditems[] = $mainitem;
            if (!empty($childrenbyparent[$parentkey])) {
                foreach ($childrenbyparent[$parentkey] as $subitem) {
                    $ordereditems[] = $subitem;
                }
            }
        }

        // Conditional availability is a second, independent date layer. Only
        // the main activity row receives it; child/phase rows share the same
        // CM and would otherwise render and submit the same conditions more
        // than once.
        foreach ($ordereditems as &$ordereditem) {
            if (!empty($ordereditem['issubtype']) || (int)($ordereditem['cmid'] ?? 0) <= 0) {
                continue;
            }
            $cm = $modinfo->get_cm((int)$ordereditem['cmid']);
            if ($cm) {
                $ordereditem = array_merge($ordereditem, $availabilityadapter->describe($cm));
            }
        }
        unset($ordereditem);

        return $ordereditems;
    }

    /**
     * Save updated item schedules to the database using safe adapters.
     *
     * @param int $courseid Course ID.
     * @param array $updates Array of items with 'id', 'datestart', 'dateend'.
     * @return array Result summary.
     */
    public static function save_schedule(int $courseid, array $updates): array {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $existingitems = self::get_course_items($courseid);
        $itemmap = [];
        foreach ($existingitems as $item) {
            $itemmap[$item['id']] = $item;
        }

        // Phase 1: Pre-validation of all proposed updates using adapters.
        $plan = [];
        $validationerrors = [];
        $skipped = [];
        $availabilityadapter = adapter_manager::get_availability_adapter($course);

        foreach ($updates as $up) {
            $key = $up['id'] ?? '';
            if (!isset($itemmap[$key])) {
                continue;
            }

            $item = $itemmap[$key];
            $currentstart = ($item['startenabled'] ?? true) ? (int)$item['datestart'] : 0;
            $currentend = ($item['endenabled'] ?? true) ? (int)$item['dateend'] : 0;
            $newstart = array_key_exists('datestart', $up) ? (int)$up['datestart'] : $currentstart;
            $newend = array_key_exists('dateend', $up) ? (int)$up['dateend'] : $currentend;
            $startenabled = array_key_exists('startenabled', $up) ?
                !empty($up['startenabled']) : ($item['startenabled'] ?? true);
            $endenabled = array_key_exists('endenabled', $up) ?
                !empty($up['endenabled']) : ($item['endenabled'] ?? true);

            // A disabled endpoint is represented by zero in the module table.
            $storedstart = $startenabled ? $newstart : 0;
            $storedend = $endenabled ? $newend : 0;

            $activitychanged = $storedstart !== $currentstart || $storedend !== $currentend ||
                    $startenabled !== ($item['startenabled'] ?? true) ||
                    $endenabled !== ($item['endenabled'] ?? true);

            $availabilitychanged = false;
            $availabilityupdates = null;
            if (array_key_exists('availabilityconditions', $up)) {
                $availabilityupdates = $up['availabilityconditions'];
                $normalise = static function($conditions): string {
                    if (!is_array($conditions)) {
                        return 'invalid';
                    }
                    $values = [];
                    foreach ($conditions as $condition) {
                        if (!is_array($condition) || empty($condition['id'])) {
                            return 'invalid';
                        }
                        $values[(string)$condition['id']] = [
                            'id' => (string)$condition['id'],
                            'direction' => (string)($condition['direction'] ?? ''),
                            'time' => (int)($condition['time'] ?? 0),
                        ];
                    }
                    ksort($values);
                    return json_encode($values);
                };
                $availabilitychanged = $normalise($availabilityupdates) !==
                    $normalise($item['availabilityconditions'] ?? []);
            }

            if (!$activitychanged && !$availabilitychanged) {
                continue;
            }

            if ($activitychanged && ($item['editable'] ?? true) === false) {
                $skipped[] = ($item['title'] ?? $key) . ': ' .
                    ($item['editreason'] ?? get_string('noteditable', 'local_reschedule'));
                $activitychanged = false;
            }

            $adapter = null;
            if ($activitychanged) {
                $adapter = adapter_manager::get_adapter($item['table'], $course, $item);
                // Validate against the visible range, while saving zero for disabled ends.
                $validationstart = $startenabled ? $newstart : (int)$item['datestart'];
                $validationend = $endenabled ? $newend : (int)$item['dateend'];
                $errs = $adapter->validate($item, $validationstart, $validationend);
                if (!empty($errs)) {
                    $validationerrors = array_merge($validationerrors, $errs);
                }
            }

            if ($availabilitychanged) {
                $errs = $availabilityadapter->validate($item, $availabilityupdates);
                if (!empty($errs)) {
                    $validationerrors = array_merge($validationerrors, $errs);
                }
            }

            if (!$activitychanged && !$availabilitychanged) {
                continue;
            }
            $plan[] = [
                'item' => $item,
                'adapter' => $adapter,
                'newstart' => $storedstart,
                'newend' => $storedend,
                'saveactivity' => $activitychanged,
                'saveavailability' => $availabilitychanged,
                'availabilityupdates' => $availabilityupdates,
            ];
        }

        // If any validation error occurred, abort and return detailed error list.
        if (!empty($validationerrors)) {
            return [
                'success' => false,
                'message' => implode("\n", array_unique($validationerrors)),
                'errors' => array_values(array_unique($validationerrors)),
            ];
        }

        if (empty($plan)) {
            if (!empty($skipped)) {
                return [
                    'success' => false,
                    'updated' => 0,
                    'message' => implode("\n", array_unique($skipped)),
                    'errors' => array_values(array_unique($skipped)),
                    'skipped' => array_values(array_unique($skipped)),
                ];
            }

            return [
                'success' => true,
                'updated' => 0,
                'message' => get_string('schedulesaved', 'local_reschedule'),
            ];
        }

        // Phase 2: Atomic transactional execution.
        $transaction = $DB->start_delegated_transaction();
        $updatedcount = 0;

        foreach ($plan as $task) {
            if ($task['saveactivity']) {
                $task['adapter']->save($task['item'], $task['newstart'], $task['newend']);
            }
            if ($task['saveavailability']) {
                $availabilityadapter->save($task['item'], $task['availabilityupdates']);
            }
            $updatedcount++;
        }

        $transaction->allow_commit();

        // Phase 3: Course cache rebuild.
        rebuild_course_cache($courseid, true);

        $message = get_string('schedulesaved', 'local_reschedule');
        if (!empty($skipped)) {
            $message .= "\n" . implode("\n", array_unique($skipped));
        }

        return [
            'success' => true,
            'updated' => $updatedcount,
            'message' => $message,
            'warnings' => array_values(array_unique($skipped)),
        ];
    }
}
