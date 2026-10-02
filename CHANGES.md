# Changelog

## 1.1.0 - 2026-10-02

- Added sourced educational context to the effort model dialog and README, explaining deadline pressure and intermediate submissions.
- Increased dialog text contrast and made the academic note expandable from its title.
- Increased each activity's total effort to 10 points and added an effort badge before the Gantt duration badge.
- Added Weekify for weekly courses: preview and confirm moving displayed activities into the section matching their saved start week.
- Uses Moodle's course format section dates and module move API; skips activities without a start date or an existing target week.
- Added a gear selector for realistic deadline pressure and ideal S-curve daily effort models, with a short exponential residual after each activity ends.
- Normalized each activity to the same total effort in either model and saved the chosen model in browser local storage.
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
