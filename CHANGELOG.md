# Changelog

## 1.2.4 - 2026-11-05

Adapter architecture and activity-form integration release.

### Added

- Added core adapters for the activity date integrations previously handled by
  built-in `report_editdates`-style extractors. Core adapters are organized in
  `classes/adapter/core/`; third-party compatibility adapters are in
  `classes/adapter/community/`.
- Documented adapter discovery and the resolution order from core and
  module-provided adapters through community adapters, the optional
  `report_editdates` bridge, and generic JSON mapping.
- Added the shared date-field interface for adapters that expose date controls
  in activity forms.

### Fixed

- Kept the injected Reschedule shortcut beside Moodle's date-picker button
  without increasing the date control's flex width or wrapping the button onto
  another line.
- Corrected URL construction for activity links and caller navigation, avoiding
  duplicated Moodle base paths.
- Avoided requesting an optional Assignment language string that may not exist
  on supported Moodle installations.

There are no database schema changes in this release.

## 1.2.3 - 2026-11-04

Maintenance release focused on correcting Assignment scheduling semantics.

### Fixed

- Assignment activities now span from **Allow submissions from** to **Cut-off
  date**. **Due date** and **Remind me to grade by** are separate milestones.
- Moving the Assignment interval no longer treats the due date as its end or
  shifts cutoff and grading milestones implicitly. The complete proposed date
  set is checked against Moodle's Assignment validation rules before saving.
- Assignment date errors now identify the affected milestone and its parent
  activity, and use Moodle's specific cutoff validation messages.
- Corrected the Gantt snap guide so it points from the dragged row to the target
  row. A collapsed parent row also shows pending changes from its subactivities.

There are no database schema changes in this release.

## 1.2.2 - 2026-11-03

Integration, persistence and compatibility release. This version keeps the
course-wide planner while also allowing supported activities to open a scoped
Reschedule screen from their own module workflow.

### Added

- Persisted manual effort estimates and the selected effort model in Moodle's
  `core_customfield` subsystem. Stored row locators tolerate deleted or
  partially restored activities, and course backup/restore remaps activity and
  subactivity references without adding plugin-owned database tables.
- Added editable work-hour fields to the activity table and synchronized edits
  with Gantt badges, the effort dialog and the effort plot. Effort badges also
  support gradual mouse-wheel and horizontal-drag adjustment.
- Added complete Attendance session support through the module library,
  including session updates and absolute start/end limits when attendance has
  already been recorded.
- Added adapter autodiscovery for the conventional
  `\mod_<plugin>\local\reschedule\adapter` class. Plugin-owned adapters must
  implement `adapter_interface`; bundled adapters remain available as
  fallbacks for older plugin versions.
- Documented every `adapter_interface` method and normalized data structure in
  PHPDoc and README examples for third-party module authors.
- Added links from supported module settings and activity date fields to a
  scoped planner. Caller breadcrumbs and `returnurl` are preserved, with
  **Save and return** and **Back** actions inside Reschedule.
- Added scoped edit mode (`editmode=2`): the selected activity and its children
  remain editable while all other course rows are visible as read-only context.
- Added start and end absolute-limit markers, reasons and hover/touch feedback
  supplied by adapters. Quest challenge answers constrain both interval edges.
- Added a parent/child movement lock, derived-range styling, double-click group
  selection and automatic expansion of the activity selected by `cmid`.
- Logged Reschedule views and successful schedule saves, including the affected
  course modules and subactivities.

### Changed

- Expressed all effort values as work hours and displayed them with one decimal
  place while retaining full precision in tooltips and dialogs.
- Refactored schedule loading, validation and saving so the manager uses only
  the common adapter contract. Quest, KUET and Attendance integrations now use
  their module APIs instead of manager-level module queries or persistent
  access.
- Quest parent effort is the sum of its contributing challenges. Challenges
  without estimated time or difficulty default to one work hour.
- Group movement now evaluates absolute limits per row. A constrained child or
  parent stops at its own limit without blocking otherwise valid members, and
  parents with independent stored ranges are no longer always recalculated.
- Weekify now refreshes section grouping after moves, places undated activities
  in the first weekly section, preserves the existing parent/child rule and
  excludes the course announcements forum.
- Courses without an enabled end date now extend the Gantt to the latest dated
  activity instead of hiding later activities behind a fallback boundary.
- Standardized table and date-editor formatting on Moodle's user locale and
  timezone preferences.
- Extended default JSON mappings for Treasure Hunt, Listgrades and ChoiceGroup;
  incompatible optional rules are reported by the visual editor but formally
  valid JSON can still be saved when a referenced module is not installed.
- Made configured mapping cards collapsible and prevented mapping-field edits
  from scrolling the settings page.
- Improved Gantt badges, scoped row colours, derived range markers, section
  decoration, control wrapping and pending-change highlighting.

### Fixed

- Added Moodle 4.5 compatibility fallbacks for custom-field category and value
  APIs and removed reliance on unavailable `cm_info` record helper methods.
- Prevented support checks used by module navigation and edit forms from loading
  adapters and schedule data for unrelated activity types.
- Fixed scoped effort updates being rejected when the payload contained visible
  read-only rows outside the selected module.
- Fixed effort-input feedback loops, mouse-wheel updates and accidental badge
  clicks after dragging.
- Fixed Attendance save payload validation and parent movement being blocked by
  one constrained session.
- Fixed stale unsaved-change warnings after **Save and return**.
- Fixed legacy event records whose saved metadata predates subactivity counts.
- Replaced layout-affecting hidden pending-change text with row tooltips and
  corrected the scoped-mode notice to show `"Activity name" (Activity type)`.

## 1.2.0 - 2026-10-26

Maintenance release. It contains no database changes and no changes to the scheduling behaviour introduced in 1.1.0.

### Changed

- Reformatted the default activity mapping JSON and the date-field detection patterns to meet Moodle coding style, without changing which fields are detected.
- Removed the `MOODLE_INTERNAL` guard from class and test files that only declare classes.

### Tests

- Extended the availability date adapter tests and reformatted the mapping, Quest effort and Weekify tests for coding style.

## 1.1.0 - 2026-10-02

- Extended magnetic snap and its visual guide to activity, milestone and availability edges across visible Gantt rows, with selection and parent bounds respected.
- Replaced the activity mapping CSV with a guided JSON editor, suggested date fields from installed module forms, explicit hierarchy/milestone/availability rules, and fixed or duration-proportional effort estimates. Dedicated adapter rows are defined in code.
- Parent activities now display the sum of their duration subactivities' effort, including Quest challenges; manual child edits recalculate the read-only parent total without adding a duplicate curve.
- Quest activities and challenges without an estimated answer time now use 1 work hour, including null and unset (`-1`) durations; estimated challenges retain their time-and-difficulty calculation.
- Added an in-memory effort table editor beside the graph settings. Adapter-owned estimates and milestones are read-only; editable hours update Gantt badges and the effort plot without a database save.
- Quest challenges now derive effort from estimated answer minutes at one point per hour, adjusted by perceived difficulty (0.7, 1 or 1.3).
- Hovering a parent activity now overlays each contributing subactivity's individual effort curve with its own label and colour.
- Auto-sequence now explains when a strategy cannot fit and keeps dates unchanged on failure. Sequential chaining can shorten trailing activities or leave trailing activities at their original dates.
- Grouped Gantt rows by Moodle course section and added subtle dated section intervals with watermark titles; their bounds follow activity edits.
- The day row can group seven days at a time, aligned to natural locale weeks, without adding a separate week row.
- Removed the separate week row and grouped month/year periods from the timeline scale; it shows only the relevant year, month, day and hour rows.
- Restricted the hourly grid to 6, 3 or 1-hour cells, labelled without minutes and restarted at each local midnight.
- Overlaid the individual effort curve when hovering an activity in the Gantt or date table, using the same contribution as the summed plot.
- Added Gantt row selection mode and scoped drag, resize, Auto-sequence and Weekify to selected activities; editable availability windows participate in date operations.
- Added an optional Auto-sequence blackout-days setting, initially excluding locale weekends from calculated start and end dates.
- Adapted Gantt date-grid resolution to zoom and viewport width, with calendar-aligned hours, days, months and years.
- Numbered day cells without month abbreviations and extended zoom to a full-day viewport.
- Added magnetic snapping and drag feedback when editable availability ranges reach their activity's start or end edge.
- Bound all scheduling controls and save/Weekify requests to Moodle course Edit mode, with a read-only view when it is off.
- Added independent enable/disable checkboxes for start and end dates in the date editor, with open boundaries and zero values on save.
- Estimated effort by activity type in the adapter manager and documented the summed effort graph on the bilingual website.
- Added sourced educational context to the effort model dialog and README, explaining deadline pressure and intermediate submissions.
- Increased dialog text contrast and made the academic note expandable from its title.
- Added an effort badge before the Gantt duration badge, including zero-point activities.
- Added Weekify for weekly courses: preview and confirm moving displayed activities into the section matching their saved start week.
- Uses Moodle's course format section dates and module move API; skips activities without a start date or an existing target week.
- Added a gear selector for realistic deadline pressure and ideal S-curve daily effort models, with a short exponential residual after each activity ends.
- Normalized both models to each activity's assigned total effort and saved the chosen model in browser local storage.
- Excluded activities and subactivities with missing schedule endpoints from every Auto-sequence strategy, so Gantt drawing limits do not become scheduled dates.
- Moved the realistic Parkinson effort peak to the exact end of each activity; its exponential residual now begins immediately afterward.
- Linked affected activity names in save error notices to their editable table rows.
- Excluded point milestones from effort totals while including dated parents whose only children are milestones, and fixed the effort chart scale at 0–4.
- Coloured the effort fill with green through 1, yellow at 2, and red from 3 on the fixed vertical scale.
- Restored movement for narrow assignment bars and prevented undated milestone placeholders from affecting parent drag and resize interactions.

## 1.0.9 - 2026-10-01

- Changed effort drops to an asymmetric double exponential curve, peaking one day before the activity ends.
- Assigned each activity an effort value of `1`; undated activities and parents with subactivities no longer contribute to the total.

## 1.0.8 - 2026-10-01

- Added an effort drops row above the Gantt date bands. Activity and subactivity efforts rise over seven days before their start, overlap additively, and fall over one day after their end.
- Updated the chart live when dates change, using Moodle's bundled Chart.js.

## 1.0.7 - 2026-09-20

- Added the project landing page in English and Spanish.
- Added GitHub Pages deployment from `main`.
- Added automated Moodle Marketplace release submission for `v*` tags.
- Excluded the website and repository automation from Marketplace plugin packages.
