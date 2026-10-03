# Changelog

## 1.1.0 - 2026-10-02

- Extended magnetic snap and its visual guide to activity, milestone and availability edges across visible Gantt rows, with selection and parent bounds respected.
- Replaced the activity mapping CSV with a guided JSON editor, suggested date fields from installed module forms, explicit hierarchy/milestone/availability rules, and fixed or duration-proportional effort estimates. Dedicated adapter rows are defined in code.
- Parent activities now display the sum of their duration subactivities' effort, including Quest challenges; manual child edits recalculate the read-only parent total without adding a duplicate curve.
- Quest activities and challenges without an estimated answer time now use 1 effort point, including null and unset (`-1`) durations; estimated challenges retain their time-and-difficulty calculation.
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
