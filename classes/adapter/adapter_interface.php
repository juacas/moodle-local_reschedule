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
 * Interface for activity date adapters.
 *
 * Activity modules can provide an optional native integration as
 * `\\mod_PLUGIN\\local\\reschedule\\adapter`, stored in
 * `classes/local/reschedule/adapter.php`. The class must implement this
 * interface, either directly or by extending {@see base_adapter}, which
 * supplies shared defaults for the complete contract.
 *
 * Keep the integration in that dedicated, lazily loaded class. Moodle will
 * not load it during normal activity operation, so the module continues to
 * work when local_reschedule is not installed and does not need to declare a
 * hard plugin dependency.
 *
 * All dates exchanged by this interface are Unix timestamps in seconds. A
 * stored value of `0` means that the corresponding endpoint is disabled. The
 * manager may use a course or parent boundary for drawing such an endpoint,
 * but it keeps `startenabled` or `endenabled` false and passes `0` to save().
 *
 * Adapter instances are created as `new adapter($course)`, where `$course` is
 * the complete course record. Direct implementations must therefore provide a
 * constructor accepting one `\stdClass`; {@see base_adapter} already does so.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface adapter_interface {
    /**
     * Check if this adapter supports the given activity/item.
     *
     * This method is called both during discovery, when only `table` and
     * `mappedrule` may be available, and later with the complete Gantt item.
     * Implementations must treat every other key as optional.
     *
     * A minimal discovery item looks like:
     *
     *     [
     *         'table' => 'example_steps',
     *         'mappedrule' => true,
     *         'issubtype' => true,
     *     ]
     *
     * A complete item additionally contains keys such as `id`, `recordid`,
     * `cmid`, `parentrecordid`, `datestart`, `dateend`, `startenabled`,
     * `endenabled`, `startcol`, `endcol`, `ismilestone`, `editable`, and any
     * JSON-safe metadata returned by get_activity_attributes() or
     * get_subitem_attributes().
     *
     * @param string $modname Activity module name (e.g. 'assign', 'quiz').
     * @param array<string, mixed> $item Partial or complete Reschedule item.
     * @return bool True only when this adapter owns the supplied item.
     */
    public function supports(string $modname, array $item): bool;

    /**
     * Load parent activity records for one mapping rule.
     *
     * `$rule` is the validated internal form produced by `mapping::parse()`:
     *
     *     [
     *         'issubtype' => false,
     *         'table' => 'example',
     *         'titlecol' => 'name',
     *         'label' => 'Example activity',
     *         'startcol' => 'timeopen',
     *         'endcol' => 'timeclose',
     *         'foreignkey' => null,
     *         'parenttable' => null,
     *         'kind' => 'range', // range, milestone or open.
     *         'optional' => false,
     *         'dateonly' => false,
     *         'boundtoparentstart' => true,
     *         'boundtoparentend' => true,
     *         'editable' => true,
     *         'availability' => 'auto', // auto or off.
     *         'effortestimator' => ['model' => 'fixed', 'value' => 2.5],
     *     ]
     *
     * `effortestimator` is either `null`, the fixed form shown above, or
     * `['model' => 'perday', 'value' => 1.5]`, where `value` is work hours per
     * day. Adapter-owned effort returned in attributes takes precedence.
     *
     * Return an associative array keyed by the integer activity record id.
     * Every value must be an object with the following canonical properties:
     *
     *     42 => (object) [
     *         'id' => 42,
     *         'title' => 'Research report',
     *         'datestart' => 1793523600,
     *         'dateend' => 1794128400,
     *     ]
     *
     * `datestart` or `dateend` may be `0` for a disabled endpoint. For a
     * milestone, both may contain the same timestamp. Extra properties are
     * allowed and are passed unchanged to get_activity_attributes().
     *
     * @param array<string, mixed> $rule Normalised parent mapping rule.
     * @return array<int, \stdClass> Records keyed by their own `id` property.
     */
    public function get_activity_records(array $rule): array;

    /**
     * Return adapter-specific display and editing attributes for a parent item.
     *
     * The record is one value previously returned by
     * get_activity_records(). The manager recognises these parent attributes:
     *
     * - `derived` (`bool`): the displayed interval is calculated from child
     *   rows and must not be saved directly.
     * - `editable` (`bool`): whether the parent dates can be changed.
     * - `editreason` (`string`): explanation shown when saving is blocked.
     * - `interactreason` (`string`): drag/resize tooltip for a locked row.
     *
     * Example for a parent whose range is derived from sessions:
     *
     *     [
     *         'derived' => true,
     *         'editable' => false,
     *         'editreason' => 'This range is calculated from its sessions.',
     *         'interactreason' => 'Move the individual sessions instead.',
     *     ]
     *
     * Omitted keys use the mapping defaults. Identity and date fields belong
     * in the record object and must not be returned here.
     *
     * @param \stdClass $record Loaded parent record.
     * @param array<string, mixed> $rule Normalised parent mapping rule.
     * @return array<string, mixed> Recognised parent display attributes.
     */
    public function get_activity_attributes(\stdClass $record, array $rule): array;

    /**
     * Load mapped child records for one mapping rule.
     *
     * The subtype rule uses the same format as get_activity_records(), with
     * `issubtype` true and non-null `parenttable` and `foreignkey` values:
     *
     *     [
     *         'issubtype' => true,
     *         'table' => 'example_steps',
     *         'parenttable' => 'example',
     *         'foreignkey' => 'exampleid',
     *         'titlecol' => 'name',
     *         'startcol' => 'timeopen',
     *         'endcol' => 'timeclose',
     *         'kind' => 'range',
     *         // The remaining keys have the same meanings as the parent rule.
     *     ]
     *
     * Return records keyed by child id. Each object must contain `id`,
     * `parentid`, `title`, `datestart`, and `dateend`:
     *
     *     301 => (object) [
     *         'id' => 301,
     *         'parentid' => 42,
     *         'title' => 'Peer review',
     *         'datestart' => 1793782800,
     *         'dateend' => 1793955600,
     *     ]
     *
     * `parentid` must refer to one of `$parentids`. Extra properties may carry
     * module data to get_subitem_attributes(), such as an effort estimate or
     * a flag indicating that submissions already exist.
     *
     * @param array<string, mixed> $rule Normalised subtype mapping rule.
     * @param int[] $parentids Parent record IDs.
     * @return array<int, \stdClass> Child records keyed by their own `id`.
     */
    public function get_subitems(array $rule, array $parentids): array;

    /**
     * Return adapter-specific display and editing attributes for a mapped child.
     *
     * Unlike parent attributes, this array is merged into the complete child
     * item. Common supported keys are:
     *
     * - `editable`, `interactive`, `derived` (`bool`).
     * - `editreason`, `interactreason` (`string`).
     * - `absolutelimits` (the format documented by get_absolute_limits()).
     * - `effort` (`float`): estimated work hours.
     * - `effortsource` (`string`): normally `adapter` or `default`.
     *
     * Additional JSON-safe scalar or array metadata is preserved and returned
     * to validate() and save(). Do not replace structural keys such as `id`,
     * `recordid`, `cmid`, `table`, `parentkey`, or `parentrecordid`.
     *
     * Example:
     *
     *     [
     *         'editable' => true,
     *         'effort' => 1.5,
     *         'effortsource' => 'adapter',
     *         'absolutelimits' => [[
     *             'type' => 'start_max',
     *             'date' => 1793869200,
     *             'reason' => 'A submission already exists at this time.',
     *         ]],
     *     ]
     *
     * @param \stdClass $record Loaded child record.
     * @param array<string, mixed> $rule Normalised subtype mapping rule.
     * @return array<string, mixed> Attributes merged into the child item.
     */
    public function get_subitem_attributes(\stdClass $record, array $rule): array;

    /**
     * Check whether the item's dates are derived from its child items.
     *
     * The item may be only `['table' => 'example']` during initial loading, so
     * implementations must not require a complete row. Return true when the
     * parent interval is an aggregate such as the earliest child start and
     * latest child end. Derived parents are rendered specially and are not
     * persisted through save().
     *
     * @param array<string, mixed> $item Partial or complete Reschedule item.
     * @return bool True when the item is a calculated parent range.
     */
    public function is_derived_item(array $item): bool;

    /**
     * Get absolute date limits supplied by this adapter.
     *
     * Parent adapters may also reduce limits from their child items.
     *
     * Return a list containing at most one start limit and one end limit. Each
     * limit has this format:
     *
     *     [
     *         'type' => 'start_max',
     *         'date' => 1793869200,
     *         'reason' => 'The challenge already has an answer at this time.',
     *     ]
     *
     * Supported `type` values and their meaning are:
     *
     * - `start_min`: start must be greater than or equal to `date`.
     * - `start_max`: start must be less than or equal to `date`.
     * - `end_min`: end must be greater than or equal to `date`.
     * - `end_max`: end must be less than or equal to `date`.
     *
     * `date` is a positive Unix timestamp. `reason` is a formatted, user-facing
     * explanation used in hover and touch tooltips. `$children` contains the
     * complete child item arrays, including any `absolutelimits` returned by
     * get_subitem_attributes(). Parent implementations can reduce those child
     * limits into the tightest applicable parent boundaries.
     *
     * Example result with both endpoints constrained:
     *
     *     [
     *         ['type' => 'start_max', 'date' => 1793869200,
     *             'reason' => 'An answer has already been submitted.'],
     *         ['type' => 'end_min', 'date' => 1793876400,
     *             'reason' => 'The interval must contain the submitted answer.'],
     *     ]
     *
     * @param array<string, mixed> $item Complete parent or child item.
     * @param array<int, array<string, mixed>> $children Complete child items.
     * @return array<int, array{type:string, date:int, reason:string}> Absolute limits.
     */
    public function get_absolute_limits(array $item, array $children = []): array;

    /**
     * Validate the proposed dates for this activity item.
     *
     * `$item` is the complete row originally built by the manager. During
     * saving it also contains the proposed `startenabled` and `endenabled`
     * flags. When several fields of one course module change together,
     * `proposeddates` maps every affected database field to its final timestamp:
     *
     *     [
     *         'id' => 'sub_example_steps_301',
     *         'recordid' => 301,
     *         'cmid' => 88,
     *         'table' => 'example_steps',
     *         'parentrecordid' => 42,
     *         'title' => 'Peer review',
     *         'startenabled' => true,
     *         'endenabled' => true,
     *         'proposeddates' => [
     *             'timeopen' => 1793782800,
     *             'timeclose' => 1793955600,
     *         ],
     *     ]
     *
     * Return an empty list when valid. Otherwise return one translated,
     * user-facing string per violation, for example:
     *
     *     ['Peer review: the end date must be after the start date.']
     *
     * Validation should re-read mutable module constraints where necessary so
     * a concurrent submission cannot bypass the earlier Gantt calculation.
     * Expected user validation failures belong in this result; infrastructure
     * failures may throw an exception. For a disabled endpoint, `$newstart` or
     * `$newend` can be the positive boundary used to draw the row; inspect the
     * corresponding enabled flag to distinguish it from a stored date. save()
     * later receives `0` for that disabled endpoint.
     *
     * @param array<string, mixed> $item Complete Reschedule item.
     * @param int $newstart Proposed start timestamp.
     * @param int $newend Proposed end timestamp.
     * @return string[] User-facing validation errors; empty when valid.
     */
    public function validate(array $item, int $newstart, int $newend): array;

    /**
     * Save the dates and update satellite subsystems (calendar, gradebook, events).
     *
     * `$item` has the complete format described by validate(), including
     * `table`, `recordid`, `cmid`, and, for a child, `parentrecordid`. Adapter
     * metadata returned while loading is also available. `$newstart` and
     * `$newend` are storage values: a disabled endpoint is `0`, and a milestone
     * receives the same timestamp for both arguments.
     *
     * Use the activity plugin's public scheduling API whenever one exists so
     * calendar events, gradebook dates, caches, and module events remain in
     * sync. The manager already wraps all adapters in one delegated database
     * transaction. Throw on failure so that transaction is rolled back; do not
     * silently return after a partial update.
     *
     * Example child identity data available in `$item`:
     *
     *     [
     *         'table' => 'example_steps',
     *         'recordid' => 301,
     *         'parentrecordid' => 42,
     *         'cmid' => 88,
     *         'startcol' => 'timeopen',
     *         'endcol' => 'timeclose',
     *     ]
     *
     * @param array<string, mixed> $item Complete Reschedule item.
     * @param int $newstart New stored start timestamp, or `0` when disabled.
     * @param int $newend New stored end timestamp, or `0` when disabled.
     */
    public function save(array $item, int $newstart, int $newend): void;

    /**
     * Save one or more schedule tasks using this adapter.
     *
     * Tasks are consecutive validated changes owned by the same adapter. Each
     * task contains at least:
     *
     *     [
     *         'item' => [
     *             'id' => 'sub_example_steps_301',
     *             'table' => 'example_steps',
     *             'recordid' => 301,
     *             'parentrecordid' => 42,
     *             'cmid' => 88,
     *             // Remaining complete item metadata.
     *         ],
     *         'newstart' => 1793782800,
     *         'newend' => 1793955600,
     *     ]
     *
     * Manager-created tasks can also contain `adapter`, `saveactivity`,
     * `saveavailability`, and `availabilityupdates`; implementations should
     * ignore keys they do not need. The default base implementation calls
     * save() for every task in order. Override this method when the module has
     * an atomic bulk API, grouping further by `parentrecordid` if that API is
     * scoped to one parent. Throw on any failed batch so the outer transaction
     * rolls back.
     *
     * @param array<int, array{
     *     item: array<string, mixed>,
     *     newstart: int,
     *     newend: int
     * }> $tasks Validated changes for this adapter.
     */
    public function save_batch(array $tasks): void;
}
