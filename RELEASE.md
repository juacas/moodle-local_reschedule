Reschedule (local_reschedule) 1.2.2
===================================

Summary
-------
Version 1.2.2 is an integration, persistence and compatibility release. It
turns Reschedule into both a course-wide planning screen and a screen that
supported activity modules can open in a safe, scoped editing mode.

Highlights
----------

### Persistent effort planning

- Manual estimates and the selected effort model are now stored in Moodle's
  `core_customfield` subsystem as work hours.
- Activity-table fields, Gantt badges, the effort dialog and the effort graph
  stay synchronized. Badges can be adjusted with the mouse wheel or a gentle
  horizontal drag.
- Parent rows display the combined effort of their contributing subactivities.
- Backup and restore remap stored module and child references. Missing or
  deleted activities are ignored safely and cleaned from later saves.

### Activity-module integration

- Supported modules can open Reschedule from their settings navigation and
  from shortcuts beside date fields in an existing activity form.
- The caller's breadcrumb and return URL are retained. Scoped mode shows the
  full course for context while allowing changes only to the requested module
  and its children.
- Third-party modules can own their integration by providing
  `\mod_<plugin>\local\reschedule\adapter`. The class is discovered through
  Moodle's autoloader and must implement the documented
  `\local_reschedule\adapter\adapter_interface` contract.
- Bundled integrations remain fallbacks, so older supported plugin versions
  continue to work.

### Safer module APIs and constraints

- Attendance sessions are loaded and updated through the Attendance library.
  Sessions with recorded attendance expose absolute start and end limits.
- Quest challenge loading and saving use the Quest API; submitted answers
  constrain both the latest allowed start and earliest allowed end.
- KUET sessions use the module session model rather than direct database or
  persistent access.
- The manager now delegates module-specific loading, validation, limits and
  saving through the common adapter interface.
- Group dragging checks each row independently. Reaching one row's absolute
  limit no longer prevents other valid rows from moving.

### Planning and interface improvements

- Added a parent/child movement lock and distinctive derived-range rendering.
- Double-clicking a row activates group selection for its parent and children.
- Weekify updates section decoration immediately, handles undated activities
  consistently and skips the special announcements forum.
- Courses without an enabled end date use their latest dated activity as the
  visible Gantt boundary.
- Dates follow Moodle's user locale and timezone in tables and editors.
- Pending rows use a translucent background and a tooltip without adding text
  that can disturb the row layout.
- Reschedule records view and save events, including affected modules and
  subactivities.

Compatibility and fixes
-----------------------

- Added compatibility paths for Moodle 4.5 custom-field APIs and `cm_info`.
- Navigation support checks now inspect only the current course module instead
  of collecting every adapter's course data.
- Fixed scoped effort validation, Attendance save payloads, effort input event
  loops, badge drag clicks, stale leave-page warnings and legacy event metadata.
- Improved the visual JSON mapping editor and added default optional rules for
  Treasure Hunt, Listgrades and ChoiceGroup.

Upgrade notes
-------------

Run Moodle's normal plugin upgrade so Reschedule can provision its private
effort metadata category and field in `core_customfield` and register the
current services and events:

```bash
php admin/cli/upgrade.php
php admin/cli/purge_caches.php
```

Reschedule does not add plugin-owned database tables. Existing course dates are
unchanged during upgrade. The full feature history and implementation details
are available in `CHANGELOG.md` and `README.md`.
