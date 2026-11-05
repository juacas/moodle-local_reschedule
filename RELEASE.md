Reschedule (local_reschedule) 1.2.4
===================================

Summary
-------
Version 1.2.4 completes the move of Moodle activity date handling into the
Reschedule adapter contract. It organizes native and community integrations,
clarifies adapter discovery and fallback order, and improves the injected
Reschedule shortcut on activity date forms.

Highlights
----------

### Adapter integrations

- Moved bundled activity date integrations from the legacy built-in extractor
  layer to the common adapter contract.
- Organized Moodle activity integrations under `adapter/core/` and third-party
  integrations under `adapter/community/`.
- Documented adapter discovery and resolution precedence, including the
  `report_editdates` compatibility fallback and generic JSON mapping fallback.
- Kept module-owned adapters discoverable through Moodle's class loader, with
  bundled adapters available for modules that do not provide one.

### Activity date forms and URLs

- The injected Reschedule shortcut stays beside Moodle's calendar button
  without forcing the date controls onto another line.
- Normalized URL handling for Reschedule links and navigation breadcrumbs.
- Corrected activity date-field labels and compatibility handling for Moodle
  versions that do not define optional Assignment date strings.

Upgrade notes
-------------

This release does not add database tables or require a schema migration. Update
the plugin files and purge Moodle caches so Moodle loads the updated AMD and
style assets:

```bash
php admin/cli/purge_caches.php
```

See `CHANGELOG.md` and `README.md` for the full history and technical details.
