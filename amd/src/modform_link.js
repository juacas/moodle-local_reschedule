// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Add a Reschedule shortcut beside date selectors in supported module forms.
 *
 * @module     local_reschedule/modform_link
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    /**
     * Add an accessible Reschedule link to each date selector.
     *
     * @param {string} url Reschedule URL scoped to the current course module.
     * @param {string} iconUrl Plugin icon URL.
     * @param {string} label Accessible link label.
     */
    const init = (url, iconUrl, label) => {
        document.querySelectorAll('.fdate_time_selector, .fdate_selector').forEach((selector) => {
            if (selector.dataset.rescheduleLinkAdded === 'true') {
                return;
            }

            const link = document.createElement('a');
            link.className = 'local-reschedule-modform-link';
            link.href = url;
            link.title = label;
            link.setAttribute('aria-label', label);

            const icon = document.createElement('img');
            icon.src = iconUrl;
            icon.alt = '';
            icon.setAttribute('aria-hidden', 'true');
            link.appendChild(icon);

            // Keep the shortcut beside Moodle's calendar button. Appending it
            // to the flex-wrapped selector makes it wrap onto a new line.
            const calendarButton = selector.querySelector('button[id$="_calendar"]');
            if (calendarButton) {
                calendarButton.insertAdjacentElement('afterend', link);
            } else {
                selector.appendChild(link);
            }
            selector.dataset.rescheduleLinkAdded = 'true';
        });
    };

    return {init: init};
});
