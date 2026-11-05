// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Preserve the caller's rendered breadcrumb path in Reschedule links.
 *
 * @module     local_reschedule/return_navigation
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    /**
     * Add the current page's navigation path and return URL before following a link.
     *
     * @param {string} configurationLabel Localized label for the configuration breadcrumb.
     */
    const init = (configurationLabel) => {
        document.addEventListener('click', (event) => {
            const link = event.target.closest('a[href*="/local/reschedule/index.php"][href*="cmid="]');
            if (!link) {
                return;
            }

            const destination = new URL(link.href, window.location.href);
            const returnUrl = window.location.href;
            destination.searchParams.set('returnurl', returnUrl);

            const breadcrumb = document.querySelector('nav[aria-label="breadcrumb"] ol.breadcrumb') ||
                document.querySelector('ol.breadcrumb');
            const nodes = [];
            if (breadcrumb) {
                breadcrumb.querySelectorAll('li').forEach((item, index, list) => {
                    const anchor = item.querySelector('a');
                    const label = (anchor ? anchor.textContent : item.textContent).trim();
                    if (!label) {
                        return;
                    }

                    let url = '';
                    if (index === list.length - 1) {
                        url = window.location.href;
                    } else if (anchor) {
                        const nodeUrl = new URL(anchor.href, window.location.href);
                        if (nodeUrl.origin === window.location.origin) {
                            url = nodeUrl.href;
                        }
                    }
                    nodes.push({label: label, url: url});
                });
            }

            const lastNode = nodes[nodes.length - 1];
            if (!lastNode || lastNode.label.toLocaleLowerCase() !== configurationLabel.toLocaleLowerCase()) {
                nodes.push({label: configurationLabel, url: returnUrl});
            } else {
                lastNode.url = returnUrl;
            }
            destination.searchParams.set('navigation', JSON.stringify(nodes));
            link.href = destination.toString();
        }, true);
    };

    return {init: init};
});
