// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * In-memory editor for Gantt effort estimates.
 *
 * @module     local_reschedule/effort_table
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    /** Build one independent editor bound to the current calendar instance. */
    function EffortTable(options) {
        this.options = options;
        this.modal = document.getElementById('reschedule-effort-table-modal');
        this.body = document.getElementById('reschedule-effort-table-body');
        this.error = document.getElementById('reschedule-effort-table-error');
        if (!this.modal || !this.body) {
            return;
        }
        var self = this;
        var apply = document.getElementById('btn-effort-table-apply');
        if (apply) {
            apply.addEventListener('click', function() {
                self.apply();
            });
        }
        this.modal.querySelectorAll('[data-effort-table-dismiss]').forEach(function(button) {
            button.addEventListener('click', function() {
                self.close();
            });
        });
    }

    /** Render every Gantt row, with derived estimates and milestones locked. */
    EffortTable.prototype.render = function() {
        var self = this;
        var strings = this.options.strings;
        this.body.textContent = '';
        this.options.getItems().forEach(function(item) {
            var row = document.createElement('tr');
            var titleCell = document.createElement('th');
            titleCell.scope = 'row';
            titleCell.className = 'align-middle';
            titleCell.textContent = (item.issubtype ? '\u21b3 ' : '') + (item.title || '');
            row.appendChild(titleCell);

            var valueCell = document.createElement('td');
            valueCell.className = 'align-middle';
            var sourceCell = document.createElement('td');
            sourceCell.className = 'align-middle text-nowrap';
            if (item.ismilestone) {
                valueCell.textContent = '\u2014';
                sourceCell.textContent = strings.efforttablemilestone || 'Milestone';
            } else {
                var input = document.createElement('input');
                input.type = 'number';
                input.min = '0';
                input.step = 'any';
                input.inputMode = 'decimal';
                input.className = 'form-control form-control-sm quest-effort-hours-input';
                var originalEffort = Number(item.effort === null ||
                    typeof item.effort === 'undefined' ? 0 : item.effort);
                input.value = originalEffort.toFixed(1);
                input.setAttribute('data-original-effort', String(originalEffort));
                input.addEventListener('input', function() {
                    input.setAttribute('data-effort-edited', 'true');
                });
                input.setAttribute('data-itemid', String(item.id));
                input.setAttribute('aria-label', (strings.efforttablehours || 'Estimated hours') + ': ' +
                    (item.title || ''));
                input.disabled = item.effortsource === 'adapter' || item.effortsource === 'children' ||
                    !self.options.canEdit();
                valueCell.appendChild(input);
                sourceCell.textContent = item.effortsource === 'children' ?
                    (strings.efforttablechildren || 'Sum of subactivities') :
                    (item.effortsource === 'adapter' ?
                        (strings.efforttableadapter || 'Activity adapter') :
                        (item.effortsource === 'manual' ?
                            (strings.efforttablemanual || 'Edited in this visit') :
                            (strings.efforttabledefault || 'Editable estimate')));
            }
            row.appendChild(valueCell);
            row.appendChild(sourceCell);
            self.body.appendChild(row);
        });
    };

    /** Open the table with fresh values from the current Gantt state. */
    EffortTable.prototype.open = function() {
        if (!this.modal || !this.options.canEdit()) {
            return;
        }
        this.render();
        this.error.textContent = '';
        this.error.classList.add('d-none');
        if (window.bootstrap && window.bootstrap.Modal) {
            var modal = typeof window.bootstrap.Modal.getOrCreateInstance === 'function' ?
                window.bootstrap.Modal.getOrCreateInstance(this.modal) :
                (window.bootstrap.Modal.getInstance(this.modal) || new window.bootstrap.Modal(this.modal));
            modal.show();
        } else if (window.jQuery && typeof window.jQuery(this.modal).modal === 'function') {
            window.jQuery(this.modal).modal('show');
        } else {
            this.modal.classList.add('show');
            this.modal.style.display = 'block';
            this.modal.removeAttribute('aria-hidden');
            this.modal.setAttribute('aria-modal', 'true');
            var backdrop = document.createElement('div');
            backdrop.id = 'reschedule-effort-table-backdrop';
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        }
    };

    /** Validate the complete table before changing any estimate. */
    EffortTable.prototype.apply = function() {
        if (!this.modal || !this.options.canEdit()) {
            return false;
        }
        var self = this;
        var items = this.options.getItems();
        var updates = [];
        var invalid = null;
        this.body.querySelectorAll('input[data-itemid]').forEach(function(input) {
            var item = items.find(function(candidate) {
                return String(candidate.id) === input.getAttribute('data-itemid');
            });
            if (!item || item.ismilestone || item.effortsource === 'adapter' ||
                    item.effortsource === 'children') {
                return;
            }
            var raw = input.value.trim();
            // A displayed decimal is rounded; retain the precise value when untouched.
            var hours = input.getAttribute('data-effort-edited') === 'true' ?
                Number(raw) : Number(input.getAttribute('data-original-effort'));
            if (raw === '' || !Number.isFinite(hours) || hours < 0 || !input.validity.valid) {
                invalid = invalid || input;
                return;
            }
            updates.push({id: String(item.id), hours: hours});
        });
        if (invalid) {
            this.error.textContent = this.options.strings.efforttableinvalid ||
                'Enter a non-negative number of hours for every editable activity.';
            this.error.classList.remove('d-none');
            invalid.focus();
            return false;
        }
        self.options.onApply(updates);
        this.close();
        return true;
    };

    /** Close the table with the same Bootstrap and fallback paths as the model dialog. */
    EffortTable.prototype.close = function() {
        if (!this.modal) {
            return;
        }
        if (window.bootstrap && window.bootstrap.Modal) {
            var modal = window.bootstrap.Modal.getInstance(this.modal);
            if (modal) {
                modal.hide();
            }
        } else if (window.jQuery && typeof window.jQuery(this.modal).modal === 'function') {
            window.jQuery(this.modal).modal('hide');
        } else {
            this.modal.classList.remove('show');
            this.modal.style.display = 'none';
            this.modal.setAttribute('aria-hidden', 'true');
            this.modal.removeAttribute('aria-modal');
            var backdrop = document.getElementById('reschedule-effort-table-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        }
    };

    return {
        create: function(options) {
            return new EffortTable(options);
        }
    };
});
