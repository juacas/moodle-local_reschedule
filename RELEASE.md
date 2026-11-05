Reschedule (local_reschedule) 1.2.5
===================================

Summary
-------
Version 1.2.5 provides a comprehensive report for monitoring and analyzing the workload of students across multiple courses. This report is available for users with the `local/reschedule:viewcrosscourse` capability, which is granted by default to managers and site administrators. The report aggregates the effort timelines of all students in the selected courses, allowing for direct comparison of workload across courses.
This release also caches the activity effort data for improved performance.

Highlights
----------

### Cross-course workload report

- Restricted the cross-course workload report with a new course capability,
  granted by default to managers and site administrators. The Moodle plugin
  upgrade registers the capability defaults.
- Automatically scaled the aggregate effort timeline vertically and applied
  its maximum to every student timeline for direct comparison.

Upgrade notes
-------------

This release adds no database tables. Run Moodle's normal plugin upgrade to
register the new capability and cache definitions, then purge Moodle caches so it loads the updated
AMD and style assets:

```bash
php admin/cli/purge_caches.php
```

See `CHANGELOG.md` and `README.md` for the full history and technical details.
