Reschedule (local_reschedule) 1.2.3
===================================

Summary
-------
Version 1.2.3 corrects how Assignment dates are represented and changed in the
Gantt. It also includes small improvements to snap feedback and pending-change
visibility.

Highlights
----------

### Assignment scheduling

- The Assignment activity range now runs from **Allow submissions from** to
  **Cut-off date**.
- **Due date** and **Remind me to grade by** are displayed as independent
  milestones.
- Moving the activity range no longer moves either milestone implicitly.
- Assignment changes are checked together against Moodle's date rules before
  saving, with contextual validation messages for invalid dates.

### Gantt feedback

- The snap guide now runs from the dragged activity toward the target activity
  and points at the target row.
- A collapsed activity row indicates when one of its subactivities has
  unsaved changes. Expanding the parent returns the highlight to the changed
  subactivity rows.

Upgrade notes
-------------

This release does not add database tables or require a schema migration. Update
the plugin files and purge Moodle caches:

```bash
php admin/cli/purge_caches.php
```

See `CHANGELOG.md` and `README.md` for the full history and technical details.
