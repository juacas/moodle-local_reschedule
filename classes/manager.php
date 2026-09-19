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

defined('MOODLE_INTERNAL') || die();

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
                "-quest_submissions,title,Quest Challenge,datestart,dateend,questid";
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
        $parentitems = []; // Cache of parent records by parent table.
        $childrenbyparent = []; // Group subitems by parent key.

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
            $fieldsexist = $dbman->field_exists($table, 'id') &&
                $dbman->field_exists($table, $titlecol) &&
                $dbman->field_exists($table, $startcol) &&
                $dbman->field_exists($table, $endcol);

            if (!$fieldsexist) {
                continue;
            }

            $records = [];
            if ($dbman->field_exists($table, 'course')) {
                $sql = "SELECT t.id, t.{$titlecol} AS title, t.{$startcol} AS datestart, t.{$endcol} AS dateend
                          FROM {{$table}} t
                         WHERE t.course = :courseid
                      ORDER BY t.{$startcol} ASC, t.id ASC";
                $records = $DB->get_records_sql($sql, ['courseid' => $courseid]);
            }

            $parentitems[$table] = $records;

            foreach ($records as $rec) {
                $itemid = 'main_' . $table . '_' . $rec->id;
                $ds = (int)$rec->datestart;
                $de = (int)$rec->dateend;

                // If dates are unset, assign a friendly default within the course.
                if ($ds <= 0 && $de <= 0) {
                    $ds = $coursestart;
                    $de = min($courseend, $coursestart + (7 * 86400));
                } else if ($ds <= 0) {
                    $ds = max($coursestart, $de - (7 * 86400));
                } else if ($de <= 0 || $de <= $ds) {
                    $de = min($courseend, $ds + (7 * 86400));
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

                $items[$itemid] = [
                    'id' => $itemid,
                    'recordid' => (int)$rec->id,
                    'table' => $table,
                    'parentkey' => null,
                    'parenttitle' => null,
                    'issubtype' => false,
                    'haschildren' => false,
                    'childrencount' => 0,
                    'iconurl' => $iconurl,
                    'title' => (string)$rec->title,
                    'typelabel' => $rule['label'],
                    'startcol' => $startcol,
                    'endcol' => $endcol,
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
                    $ds = (int)$prec->datestart;
                    $de = (int)$prec->dateend;

                    if ($ds <= 0 && $de <= 0) {
                        $parentstart = $items[$parentkey]['datestart'] ?? $coursestart;
                        $ds = $parentstart;
                        $de = $ds + (3 * 86400);
                    } else if ($ds <= 0) {
                        $ds = max($coursestart, $de - (3 * 86400));
                    } else if ($de <= 0 || $de <= $ds) {
                        $de = $ds + (3 * 86400);
                    }

                    if (isset($items[$parentkey])) {
                        $items[$parentkey]['haschildren'] = true;
                        $items[$parentkey]['childrencount']++;
                    }

                    $parenticon = $items[$parentkey]['iconurl'] ?? '';

                    $subitem = [
                        'id' => $itemid,
                        'recordid' => (int)$prec->id,
                        'table' => $subtable,
                        'parentkey' => $parentkey,
                        'parenttitle' => (string)$prec->title,
                        'issubtype' => true,
                        'haschildren' => false,
                        'childrencount' => 0,
                        'iconurl' => $parenticon,
                        'title' => (string)$prec->title . ' - ' . $rule['label'],
                        'typelabel' => $rule['label'],
                        'startcol' => $startcol,
                        'endcol' => $endcol,
                        'datestart' => $ds,
                        'dateend' => $de,
                    ];

                    $childrenbyparent[$parentkey][] = $subitem;
                }
            } else {
                // Subtype is in a related child table (e.g. quest_submissions with questid).
                if (!$dbman->field_exists($subtable, $fkey) ||
                    !$dbman->field_exists($subtable, $titlecol) ||
                    !$dbman->field_exists($subtable, $startcol) ||
                    !$dbman->field_exists($subtable, $endcol)) {
                    continue;
                }

                $parentids = array_keys($parentitems[$parenttable]);
                [$insql, $inparams] = $DB->get_in_or_equal($parentids, SQL_PARAMS_NAMED);
                $sql = "SELECT id, {$fkey} AS parentid, {$titlecol} AS title, {$startcol} AS datestart, {$endcol} AS dateend
                          FROM {{$subtable}}
                         WHERE {$fkey} $insql
                      ORDER BY {$startcol} ASC, id ASC";
                $children = $DB->get_records_sql($sql, $inparams);

                foreach ($children as $ch) {
                    $parentkey = 'main_' . $parenttable . '_' . $ch->parentid;
                    $itemid = 'sub_' . $subtable . '_' . $ch->id;
                    $ds = (int)$ch->datestart;
                    $de = (int)$ch->dateend;

                    if ($ds <= 0 && $de <= 0) {
                        $parentstart = $items[$parentkey]['datestart'] ?? $coursestart;
                        $ds = $parentstart;
                        $de = $ds + (3 * 86400);
                    } else if ($ds <= 0) {
                        $ds = max($coursestart, $de - (3 * 86400));
                    } else if ($de <= 0 || $de <= $ds) {
                        $de = $ds + (3 * 86400);
                    }

                    $parenttitle = $items[$parentkey]['title'] ?? '';
                    $parenticon = $items[$parentkey]['iconurl'] ?? '';

                    if (isset($items[$parentkey])) {
                        $items[$parentkey]['haschildren'] = true;
                        $items[$parentkey]['childrencount']++;
                    }

                    $subitem = [
                        'id' => $itemid,
                        'recordid' => (int)$ch->id,
                        'table' => $subtable,
                        'parentkey' => $parentkey,
                        'parenttitle' => $parenttitle,
                        'issubtype' => true,
                        'haschildren' => false,
                        'childrencount' => 0,
                        'iconurl' => $parenticon,
                        'title' => (string)$ch->title,
                        'typelabel' => $rule['label'],
                        'startcol' => $startcol,
                        'endcol' => $endcol,
                        'datestart' => $ds,
                        'dateend' => $de,
                    ];

                    $childrenbyparent[$parentkey][] = $subitem;
                }
            }
        }

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

        foreach ($updates as $up) {
            $key = $up['id'] ?? '';
            if (!isset($itemmap[$key])) {
                continue;
            }

            $item = $itemmap[$key];
            $newstart = (int)($up['datestart'] ?? 0);
            $newend = (int)($up['dateend'] ?? 0);

            // Skip if no change in dates.
            if ($newstart === (int)$item['datestart'] && $newend === (int)$item['dateend']) {
                continue;
            }

            $adapter = adapter_manager::get_adapter($item['table'], $course, $item);
            $errs = $adapter->validate($item, $newstart, $newend);
            if (!empty($errs)) {
                $validationerrors = array_merge($validationerrors, $errs);
            }

            $plan[] = [
                'item' => $item,
                'adapter' => $adapter,
                'newstart' => $newstart,
                'newend' => $newend,
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
            $task['adapter']->save($task['item'], $task['newstart'], $task['newend']);
            $updatedcount++;
        }

        $transaction->allow_commit();

        // Phase 3: Course cache rebuild.
        rebuild_course_cache($courseid, true);

        return [
            'success' => true,
            'updated' => $updatedcount,
            'message' => get_string('schedulesaved', 'local_reschedule'),
        ];
    }
}
