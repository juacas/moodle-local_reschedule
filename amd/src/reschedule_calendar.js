// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Reschedule interactive calendar and timeline AMD module.
 *
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/notification'], function(Notification) {
    'use strict';

    /**
     * Format a timestamp in seconds using active locale.
     *
     * @param {number} sec Unix timestamp in seconds.
     * @param {string} [locale] Active locale.
     * @return {string} Formatted date and time.
     */
    function formatDateTime(sec, locale) {
        var d = new Date(sec * 1000);
        var loc = locale || undefined;
        try {
            var formatter = new Intl.DateTimeFormat(loc, {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
            return formatter.format(d);
        } catch (e) {
            var yr = d.getFullYear();
            var mo = String(d.getMonth() + 1).padStart(2, '0');
            var dy = String(d.getDate()).padStart(2, '0');
            var hr = String(d.getHours()).padStart(2, '0');
            var mn = String(d.getMinutes()).padStart(2, '0');
            return dy + '/' + mo + '/' + yr + ' ' + hr + ':' + mn;
        }
    }

    /**
     * Format duration in seconds into a readable string.
     *
     * @param {number} sec Duration in seconds.
     * @return {string} Formatted duration.
     */
    function formatDuration(sec) {
        var days = Math.floor(sec / 86400);
        var hours = Math.round((sec % 86400) / 3600);
        if (days > 0 && hours > 0) {
            return days + 'd ' + hours + 'h';
        } else if (days > 0) {
            return days + 'd';
        }
        return Math.max(1, hours) + 'h';
    }

    /**
     * Convert Unix timestamp in seconds to datetime-local input string format (YYYY-MM-DDTHH:mm).
     *
     * @param {number} sec Unix timestamp in seconds.
     * @return {string} Datetime local string.
     */
    function timestampToDateTimeLocal(sec) {
        var d = new Date(sec * 1000);
        var yr = d.getFullYear();
        var mo = String(d.getMonth() + 1).padStart(2, '0');
        var dy = String(d.getDate()).padStart(2, '0');
        var hr = String(d.getHours()).padStart(2, '0');
        var mn = String(d.getMinutes()).padStart(2, '0');
        return yr + '-' + mo + '-' + dy + 'T' + hr + ':' + mn;
    }

    /**
     * Convert datetime-local input string format (YYYY-MM-DDTHH:mm) to Unix timestamp in seconds.
     *
     * @param {string} str Datetime local string.
     * @return {number} Unix timestamp in seconds.
     */
    function dateTimeLocalToTimestamp(str) {
        if (!str) {
            return 0;
        }
        var parts = str.split('T');
        if (parts.length < 2) {
            return 0;
        }
        var dateParts = parts[0].split('-');
        var timeParts = parts[1].split(':');
        var d = new Date(
            parseInt(dateParts[0], 10),
            parseInt(dateParts[1], 10) - 1,
            parseInt(dateParts[2], 10),
            parseInt(timeParts[0], 10),
            parseInt(timeParts[1], 10),
            0
        );
        return Math.floor(d.getTime() / 1000);
    }

    /**
     * Get weekend days for the given locale.
     * Returns an array of ISO day numbers: 1 (Monday) to 7 (Sunday).
     *
     * @param {string} [locale] BCP47 locale or Moodle language code.
     * @return {Array<number>}
     */
    function getLocaleWeekendDays(locale) {
        if (typeof Intl !== 'undefined' && Intl.Locale) {
            try {
                var tag = String(locale || 'default').replace(/_/g, '-');
                var loc = new Intl.Locale(tag);
                var info = loc.weekInfo || (loc.getWeekInfo ? loc.getWeekInfo() : null);
                if (info && Array.isArray(info.weekend) && info.weekend.length > 0) {
                    return info.weekend;
                }
            } catch (e) {
                // Fallback below.
            }
        }
        return [6, 7];
    }

    /**
     * Check whether a given Date is on a weekend according to weekend days.
     *
     * @param {Date} date Date instance.
     * @param {Array<number>} weekendDays Array of ISO day numbers (1 = Mon ... 7 = Sun).
     * @return {boolean}
     */
    function isDateWeekend(date, weekendDays) {
        var day = date.getDay();
        var isoDay = (day === 0) ? 7 : day;
        return weekendDays.indexOf(isoDay) !== -1;
    }

    /**
     * RescheduleCalendar controller object.
     */
    var RescheduleCalendar = {
        config: null,
        items: [],
        initialItems: [],
        isDirty: false,
        activeDrag: null,
        trackWidthPx: 1200,
        baseTrackWidthPx: 1200,
        zoomLevel: 1,
        minZoom: 0.5,
        maxZoom: 5,
        pointerPositions: {},
        backgroundPan: null,
        pinchState: null,
        locale: undefined,

        /**
         * Initialize calendar timeline.
         *
         * @param {Object} cfg Configuration object from PHP.
         */
        init: function(cfg) {
            cfg = cfg || {};
            var root = document.getElementById('reschedule-page-root');
            if (root) {
                var getRootData = function(name, fallback) {
                    var value = root.getAttribute(name);
                    return value === null ? fallback : value;
                };

                cfg.courseid = Number(getRootData('data-courseid', cfg.courseid || 0));
                cfg.courseStart = Number(getRootData('data-course-start', cfg.courseStart || 0));
                cfg.courseEnd = Number(getRootData('data-course-end', cfg.courseEnd || 0));
                cfg.timelineStart = Number(getRootData('data-timeline-start', cfg.timelineStart || cfg.courseStart));
                cfg.timelineEnd = Number(getRootData('data-timeline-end', cfg.timelineEnd || cfg.courseEnd));
                cfg.saveUrl = getRootData('data-save-url', cfg.saveUrl || '');
                cfg.sesskey = getRootData('data-sesskey', cfg.sesskey || '');
                cfg.lang = getRootData('data-lang', cfg.lang || '');
                cfg.strings = cfg.strings || {};

                [
                    ['data-error-saving', 'error_saving'],
                    ['data-error-saving-header', 'error_saving_header'],
                    ['data-schedulesaved', 'schedulesaved'],
                    ['data-activity-before-timeline', 'activitybeforetimeline'],
                    ['data-activity-after-timeline', 'activityaftertimeline'],
                    ['data-activity-start-disabled', 'activitystartdisabled'],
                    ['data-activity-end-disabled', 'activityenddisabled'],
                    ['data-no-schedulable-changes', 'noschedulablechanges'],
                    ['data-subactivity-parent-bounds', 'subactivityparentbounds'],
                    ['data-kuet-activity-derived-hint', 'kuetactivityderivedhint'],
                    ['data-availability-restriction', 'availabilityrestriction'],
                    ['data-availability-restriction-hint', 'availabilityrestrictionhint']
                ].forEach(function(mapping) {
                    cfg.strings[mapping[1]] = getRootData(mapping[0], cfg.strings[mapping[1]] || '');
                });
            }

            this.config = cfg;
            this.strings = cfg.strings || {};
            this.locale = cfg.lang || (typeof M !== 'undefined' && M.cfg && M.cfg.lang) ||
                document.documentElement.lang || undefined;

            var dataEl = document.getElementById('reschedule-activities-data');
            var rawItems = cfg.items;
            if (!rawItems && dataEl) {
                try {
                    rawItems = JSON.parse(dataEl.textContent);
                } catch (e) {
                    rawItems = [];
                }
            }

            var timelineStart = Number(cfg.timelineStart || cfg.courseStart);
            var timelineEnd = Number(cfg.timelineEnd || cfg.courseEnd);
            this.items = (rawItems || []).map(function(item) {
                var copy = Object.assign({}, item);
                // Disabled endpoints occupy the complete visible timeline without
                // turning the display boundary into a stored date.
                if (copy.startenabled === false) {
                    copy.datestart = timelineStart;
                }
                if (copy.endenabled === false) {
                    copy.dateend = timelineEnd;
                }
                return copy;
            });
            this.initialItems = this.items.map(function(item) {
                return JSON.parse(JSON.stringify(item));
            });

            this.board = document.getElementById('reschedule-timeline-app');
            this.wrapper = this.board;
            this.hud = document.getElementById('reschedule-drag-hud');
            this.hudTitle = document.getElementById('hud-title');
            this.hudDates = document.getElementById('hud-dates');
            this.saveBtn = document.getElementById('btn-save');
            this.resetBtn = document.getElementById('btn-reset');
            this.autoSeqBtn = document.getElementById('btn-autosequence');
            this.unsavedAlert = document.getElementById('reschedule-unsaved-alert');
            this.expandedParents = {};

            if (!this.board || !this.items.length) {
                return;
            }

            this.renderTimeline();
            this.bindGlobalEvents();
            this.bindActions();
        },

        /**
         * Generate 3-level timeline bands and weekend blocks.
         *
         * @param {number} tStartSec Timeline start timestamp.
         * @param {number} tEndSec Timeline end timestamp.
         * @return {Object} Bands structure.
         */
        calculateBands: function(tStartSec, tEndSec) {
            var self = this;
            var totalSec = Math.max(3600, tEndSec - tStartSec);
            var totalDays = totalSec / 86400;
            var isShort = totalDays <= 7;
            var weekendDays = getLocaleWeekendDays(self.locale);
            var b1 = [], b2 = [], b3 = [];

            if (!isShort) {
                // Level 1: Year
                var y0 = new Date(tStartSec * 1000).getFullYear();
                var y1 = new Date(tEndSec * 1000).getFullYear();
                for (var y = y0; y <= y1; y++) {
                    var yStart = Math.max(tStartSec, Math.floor(new Date(y, 0, 1, 0, 0, 0).getTime() / 1000));
                    var yEnd = Math.min(tEndSec, Math.floor(new Date(y + 1, 0, 1, 0, 0, 0).getTime() / 1000));
                    if (yEnd > yStart) {
                        b1.push({
                            label: String(y),
                            left: ((yStart - tStartSec) / totalSec) * 100,
                            width: ((yEnd - yStart) / totalSec) * 100
                        });
                    }
                }

                // Level 2: Month
                var dCur = new Date(tStartSec * 1000);
                var curY = dCur.getFullYear();
                var curM = dCur.getMonth();
                while (new Date(curY, curM, 1, 0, 0, 0).getTime() / 1000 < tEndSec) {
                    var mStart = Math.max(tStartSec, Math.floor(new Date(curY, curM, 1, 0, 0, 0).getTime() / 1000));
                    var mEnd = Math.min(tEndSec, Math.floor(new Date(curY, curM + 1, 1, 0, 0, 0).getTime() / 1000));
                    if (mEnd > mStart) {
                        var mDate = new Date(curY, curM, 1);
                        var mName = mDate.toLocaleDateString(self.locale, {month: 'short'});
                        b2.push({
                            label: mName,
                            left: ((mStart - tStartSec) / totalSec) * 100,
                            width: ((mEnd - mStart) / totalSec) * 100
                        });
                    }
                    curM++;
                    if (curM > 11) {
                        curM = 0;
                        curY++;
                    }
                }

                // Level 3: Day scale
                var stepDays = 1;
                if (totalDays > 365 * 3) {
                    stepDays = 30;
                } else if (totalDays > 365) {
                    stepDays = 14;
                } else if (totalDays > 90) {
                    stepDays = 7;
                } else if (totalDays > 31) {
                    stepDays = 2;
                } else {
                    stepDays = 1;
                }

                var dayPointer = new Date(tStartSec * 1000);
                dayPointer.setHours(0, 0, 0, 0);

                while (dayPointer.getTime() / 1000 < tEndSec) {
                    var nextDay = new Date(dayPointer.getTime() + stepDays * 86400 * 1000);
                    var dStart = Math.max(tStartSec, Math.floor(dayPointer.getTime() / 1000));
                    var dEnd = Math.min(tEndSec, Math.floor(nextDay.getTime() / 1000));

                    if (dEnd > dStart) {
                        var isWkDay = (stepDays === 1) ? isDateWeekend(dayPointer, weekendDays) : false;
                        var dLabel = '';
                        if (stepDays === 1) {
                            dLabel = dayPointer.getDate();
                        } else if (stepDays <= 7) {
                            dLabel = dayPointer.getDate();
                        } else {
                            dLabel = dayPointer.toLocaleDateString(self.locale, {day: 'numeric', month: 'numeric'});
                        }
                        b3.push({
                            label: String(dLabel),
                            left: ((dStart - tStartSec) / totalSec) * 100,
                            width: ((dEnd - dStart) / totalSec) * 100,
                            isWeekend: isWkDay
                        });
                    }
                    dayPointer = nextDay;
                }
            } else {
                // Short period <= 7 days: Level 1 Month/Year, Level 2 Day, Level 3 Hours.
                var dtS = new Date(tStartSec * 1000);
                var dtE = new Date(tEndSec * 1000);
                var strM1 = dtS.toLocaleDateString(self.locale, {month: 'long', year: 'numeric'});
                var strM2 = dtE.toLocaleDateString(self.locale, {month: 'long', year: 'numeric'});
                var topLabel = (strM1 === strM2) ? strM1 : (strM1 + ' - ' + strM2);
                b1.push({
                    label: topLabel,
                    left: 0,
                    width: 100
                });

                var dPtr = new Date(tStartSec * 1000);
                dPtr.setHours(0, 0, 0, 0);
                while (dPtr.getTime() / 1000 < tEndSec) {
                    var dNextP = new Date(dPtr.getTime() + 86400 * 1000);
                    var dayS = Math.max(tStartSec, Math.floor(dPtr.getTime() / 1000));
                    var dayE = Math.min(tEndSec, Math.floor(dNextP.getTime() / 1000));
                    if (dayE > dayS) {
                        var dayName = dPtr.toLocaleDateString(self.locale, {weekday: 'short', day: 'numeric'});
                        var isWk = isDateWeekend(dPtr, weekendDays);
                        b2.push({
                            label: dayName,
                            left: ((dayS - tStartSec) / totalSec) * 100,
                            width: ((dayE - dayS) / totalSec) * 100,
                            isWeekend: isWk
                        });
                    }
                    dPtr = dNextP;
                }

                var stepHours = (totalDays > 3) ? 6 : ((totalDays > 1) ? 4 : 2);
                var hPtr = new Date(tStartSec * 1000);
                var remH = hPtr.getHours() % stepHours;
                hPtr.setHours(hPtr.getHours() - remH, 0, 0, 0);

                while (hPtr.getTime() / 1000 < tEndSec) {
                    var hNextP = new Date(hPtr.getTime() + stepHours * 3600 * 1000);
                    var hS = Math.max(tStartSec, Math.floor(hPtr.getTime() / 1000));
                    var hE = Math.min(tEndSec, Math.floor(hNextP.getTime() / 1000));
                    if (hE > hS) {
                        var hLabel = hPtr.toLocaleTimeString(self.locale, {hour: '2-digit', minute: '2-digit', hour12: false});
                        var isHWk = isDateWeekend(hPtr, weekendDays);
                        b3.push({
                            label: hLabel,
                            left: ((hS - tStartSec) / totalSec) * 100,
                            width: ((hE - hS) / totalSec) * 100,
                            isWeekend: isHWk
                        });
                    }
                    hPtr = hNextP;
                }
            }

            // Compute contiguous weekend blocks for track backdrop shading.
            var weekends = [];
            var curD = new Date(tStartSec * 1000);
            curD.setHours(0, 0, 0, 0);

            var inWeekend = false;
            var wBlockStart = 0;
            var wBlockEnd = 0;

            while (curD.getTime() / 1000 < tEndSec) {
                var nxtD = new Date(curD.getTime() + 86400 * 1000);
                var curDs = Math.max(tStartSec, Math.floor(curD.getTime() / 1000));
                var curDe = Math.min(tEndSec, Math.floor(nxtD.getTime() / 1000));
                var isCurW = isDateWeekend(curD, weekendDays);

                if (isCurW) {
                    if (!inWeekend) {
                        inWeekend = true;
                        wBlockStart = curDs;
                        wBlockEnd = curDe;
                    } else {
                        wBlockEnd = curDe;
                    }
                } else {
                    if (inWeekend) {
                        weekends.push({
                            left: ((wBlockStart - tStartSec) / totalSec) * 100,
                            width: ((wBlockEnd - wBlockStart) / totalSec) * 100
                        });
                        inWeekend = false;
                    }
                }
                curD = nxtD;
            }
            if (inWeekend) {
                weekends.push({
                    left: ((wBlockStart - tStartSec) / totalSec) * 100,
                    width: ((wBlockEnd - wBlockStart) / totalSec) * 100
                });
            }

            return {
                b1: b1,
                b2: b2,
                b3: b3,
                isShort: isShort,
                weekends: weekends
            };
        },

        /**
         * Render timeline board.
         */
        renderTimeline: function() {
            var self = this;
            var cStart = self.getTimelineStart();
            var cEnd = self.getTimelineEnd();
            var totalSec = Math.max(3600, cEnd - cStart);

            var bands = self.calculateBands(cStart, cEnd);

            var wrapperWidth = self.wrapper ? Math.max(1, self.wrapper.clientWidth - 280) : 1000;
            // Keep the initial timeline inside the available viewport. The
            // horizontal scrollbar is intentionally introduced only by zoom.
            self.baseTrackWidthPx = wrapperWidth;
            self.trackWidthPx = Math.max(wrapperWidth, self.baseTrackWidthPx * self.zoomLevel);

            var board = document.createElement('div');
            board.className = 'quest-timeline-table d-flex';
            board.style.width = (280 + self.trackWidthPx) + 'px';

            // Left column: Sticky activity titles
            var leftCol = document.createElement('div');
            leftCol.className = 'quest-timeline-left-column flex-shrink-0';
            leftCol.style.width = '280px';

            var leftHeader = document.createElement('div');
            leftHeader.className = 'quest-left-header p-2 border-bottom border-end d-flex flex-column justify-content-center';
            leftHeader.innerHTML = '<div class="fw-bold small text-dark"><i class="fa fa-tasks me-1 text-primary"></i> ' +
                self.items.length + ' Items</div><div class="smaller text-muted">Drag bars to adjust schedule</div>';

            self.items.forEach(function(item) {
                var rowLabel = document.createElement('div');
                var isSub = !!item.issubtype;
                rowLabel.className = 'quest-left-row border-bottom border-end' +
                    (isSub ? ' is-subtype ps-3' : '');
                rowLabel.setAttribute('data-itemid', item.id);
                if (isSub) {
                    rowLabel.setAttribute('data-parentkey', item.parentkey);
                    if (!self.expandedParents[item.parentkey]) {
                        rowLabel.classList.add('d-none');
                    }
                }
                if (!self.isItemEditable(item) && item.editreason) {
                    rowLabel.setAttribute('title', item.interactreason || item.editreason);
                }
                var dur = formatDuration(item.dateend - item.datestart);

                var expanderHtml = '';
                if (item.haschildren) {
                    var isExp = !!self.expandedParents[item.id];
                    expanderHtml = '<button type="button" ' +
                        'class="btn btn-xs btn-outline-secondary py-0 px-1 quest-toggle-subtasks"' +
                        ' data-parentkey="' + self.escapeHtml(item.id) + '"' +
                        ' aria-expanded="' + (isExp ? 'true' : 'false') + '"' +
                        ' title="Expand / Collapse"><i class="fa ' + (isExp ? 'fa-minus' : 'fa-plus') + '"></i></button>';
                } else if (isSub) {
                    expanderHtml = '<i class="fa fa-level-up fa-rotate-90 text-muted small"></i>';
                }

                var iconHtml = '';
                if (item.iconurl) {
                    iconHtml = '<img src="' + self.escapeHtml(item.iconurl) + '" class="quest-activity-icon" alt="">';
                } else if (isSub) {
                    iconHtml = '<i class="fa fa-circle text-muted" style="font-size: 6px;"></i>';
                } else {
                    iconHtml = '<i class="fa fa-cube text-primary"></i>';
                }

                rowLabel.innerHTML = '<div class="quest-row-expander">' +
                    expanderHtml +
                    '</div>' +
                    '<div class="quest-row-icon">' +
                    iconHtml +
                    '</div>' +
                    '<div class="quest-row-text">' +
                    '<div class="quest-row-title text-truncate fw-bold ' +
                    (isSub ? 'text-secondary' : 'text-dark') + '"' +
                    ' title="' + self.escapeHtml(item.title) + '">' +
                    self.escapeHtml(item.title) +
                    '</div>' +
                    '<div class="quest-row-subtitle">' +
                    '<span class="quest-row-typename text-truncate me-1" ' +
                    'title="' + self.escapeHtml(item.typelabel || '') + '">' +
                    self.escapeHtml(item.typelabel || '') +
                    '</span>' +
                    '<span class="badge bg-light text-secondary border quest-lbl-dur flex-shrink-0">' +
                    dur +
                    '</span>' +
                    '</div>' +
                    '</div>';
                leftCol.appendChild(rowLabel);
            });
            board.appendChild(leftCol);

            // Right area: Track area
            var rightArea = document.createElement('div');
            rightArea.className = 'quest-timeline-right-area flex-grow-1 position-relative';
            rightArea.style.width = self.trackWidthPx + 'px';
            rightArea.style.minWidth = self.trackWidthPx + 'px';

            var bandsHeader = document.createElement('div');
            bandsHeader.className = 'quest-bands-header border-bottom position-relative';

            // Level 1 Band
            var band1Row = document.createElement('div');
            band1Row.className = 'quest-band-row quest-band-l1 position-relative';
            bands.b1.forEach(function(item) {
                var cell = document.createElement('div');
                cell.className = 'quest-band-cell quest-cell-l1 fw-bold text-center position-absolute border-end';
                cell.style.left = item.left + '%';
                cell.style.width = item.width + '%';
                cell.textContent = item.label;
                band1Row.appendChild(cell);
            });
            bandsHeader.appendChild(band1Row);

            // Level 2 Band
            var band2Row = document.createElement('div');
            band2Row.className = 'quest-band-row quest-band-l2 position-relative';
            bands.b2.forEach(function(item) {
                var cell = document.createElement('div');
                cell.className = 'quest-band-cell quest-cell-l2 text-center position-absolute border-end';
                if (item.isWeekend) {
                    cell.className += ' quest-cell-weekend';
                }
                cell.style.left = item.left + '%';
                cell.style.width = item.width + '%';
                cell.textContent = item.label;
                band2Row.appendChild(cell);
            });
            bandsHeader.appendChild(band2Row);

            // Level 3 Band
            var band3Row = document.createElement('div');
            band3Row.className = 'quest-band-row quest-band-l3 position-relative';
            bands.b3.forEach(function(item) {
                var cell = document.createElement('div');
                cell.className = 'quest-band-cell quest-cell-l3 text-center position-absolute border-end';
                if (item.isWeekend) {
                    cell.className += ' quest-cell-weekend';
                }
                cell.style.left = item.left + '%';
                cell.style.width = item.width + '%';
                cell.textContent = item.label;
                band3Row.appendChild(cell);
            });
            bandsHeader.appendChild(band3Row);
            bandsHeader.style.width = self.trackWidthPx + 'px';
            bandsHeader.style.minWidth = self.trackWidthPx + 'px';

            // Lanes for each item
            self.items.forEach(function(item) {
                var lane = document.createElement('div');
                lane.className = 'quest-timeline-lane position-relative border-bottom' +
                    (item.issubtype ? ' is-subtype' : '');
                lane.setAttribute('data-itemid', item.id);
                if (item.issubtype) {
                    lane.setAttribute('data-parentkey', item.parentkey);
                    if (!self.expandedParents[item.parentkey]) {
                        lane.classList.add('d-none');
                    }
                }

                // Shaded weekend backdrop blocks
                bands.weekends.forEach(function(wk) {
                    var wkBlock = document.createElement('div');
                    wkBlock.className = 'quest-lane-weekend position-absolute';
                    wkBlock.style.left = wk.left + '%';
                    wkBlock.style.width = wk.width + '%';
                    lane.appendChild(wkBlock);
                });

                // Vertical background ticks
                bands.b3.forEach(function(tick) {
                    var gridLine = document.createElement('div');
                    gridLine.className = 'quest-lane-grid-tick position-absolute border-end';
                    if (tick.isWeekend) {
                        gridLine.className += ' quest-lane-grid-weekend';
                    }
                    gridLine.style.left = tick.left + '%';
                    gridLine.style.width = tick.width + '%';
                    lane.appendChild(gridLine);
                });

                var bar = self.createBarElement(item, totalSec, cStart);
                lane.appendChild(bar);
                self.createAvailabilityOverlays(lane, item, totalSec, cStart);
                rightArea.appendChild(lane);
            });

            board.appendChild(rightArea);

            // Keep the date header outside the horizontal scroll container so
            // the page-scroll controller can float it through the complete
            // Gantt height without introducing vertical overflow.
            var stickyHeader = document.createElement('div');
            stickyHeader.className = 'quest-timeline-sticky-header';
            stickyHeader.appendChild(leftHeader);

            var headerViewport = document.createElement('div');
            headerViewport.className = 'quest-timeline-header-viewport';
            headerViewport.appendChild(bandsHeader);
            stickyHeader.appendChild(headerViewport);

            var scrollContainer = document.createElement('div');
            scrollContainer.className = 'quest-timeline-scroll';
            scrollContainer.appendChild(board);

            var shell = document.createElement('div');
            shell.className = 'quest-timeline-shell';
            var headerPlaceholder = document.createElement('div');
            headerPlaceholder.className = 'quest-timeline-sticky-placeholder';
            headerPlaceholder.setAttribute('aria-hidden', 'true');
            shell.appendChild(headerPlaceholder);
            shell.appendChild(stickyHeader);
            shell.appendChild(scrollContainer);

            self.board.innerHTML = '';
            self.board.appendChild(shell);
            self.timelineShell = shell;
            self.stickyHeader = stickyHeader;
            self.headerPlaceholder = headerPlaceholder;
            self.scrollContainer = scrollContainer;
            self.headerViewport = headerViewport;
            self.headerTrack = bandsHeader;

            var syncHeaderScroll = function() {
                self.headerTrack.style.transform = 'translateX(-' + self.scrollContainer.scrollLeft + 'px)';
            };
            self.scrollContainer.addEventListener('scroll', syncHeaderScroll, {passive: true});
            syncHeaderScroll();
            self.updatePageStickyHeader();
        },

        /**
         * Keep the timeline date header attached to the page viewport while
         * the page is scrolling through the Gantt. The placeholder preserves
         * the original layout height while the header is fixed.
         */
        updatePageStickyHeader: function() {
            var header = this.stickyHeader;
            var shell = this.timelineShell;
            var scrollContainer = this.scrollContainer;
            var placeholder = this.headerPlaceholder;

            if (!header || !shell || !scrollContainer || !placeholder) {
                return;
            }

            var headerHeight = header.offsetHeight;
            var shellRect = shell.getBoundingClientRect();
            var scrollRect = scrollContainer.getBoundingClientRect();
            var topOffset = 0;
            var isInsideGantt = shellRect.top <= topOffset &&
                scrollRect.bottom > topOffset + headerHeight;

            if (isInsideGantt) {
                var viewportWidth = scrollContainer.clientWidth || scrollRect.width;

                placeholder.classList.add('is-active');
                placeholder.style.height = headerHeight + 'px';
                header.classList.add('is-page-sticky');
                header.style.left = scrollRect.left + 'px';
                header.style.top = topOffset + 'px';
                header.style.width = viewportWidth + 'px';
                return;
            }

            placeholder.classList.remove('is-active');
            placeholder.style.height = '';
            header.classList.remove('is-page-sticky');
            header.style.left = '';
            header.style.top = '';
            header.style.width = '';
        },

        /**
         * Create a draggable bar element.
         *
         * @param {Object} item Activity item.
         * @param {number} totalSec Total timeline seconds.
         * @param {number} cStart Course start timestamp.
         * @return {HTMLElement}
         */
        createBarElement: function(item, totalSec, cStart) {
            var bar = document.createElement('div');
            bar.className = 'quest-calendar-bar' + (item.issubtype ? ' is-subtype' : '') +
                (this.isItemInteractive(item) ? '' : ' is-disabled');
            if (!this.isItemInteractive(item)) {
                bar.setAttribute('aria-disabled', 'true');
            }
            bar.setAttribute('data-itemid', item.id);
            bar.setAttribute('data-base-title', (!this.isItemEditable(item) &&
                (item.interactreason || item.editreason)) ?
                (item.interactreason || item.editreason) : '');

            var sFrac = Math.max(0, Math.min(1, (item.datestart - cStart) / totalSec));
            var eFrac = Math.max(0, Math.min(1, (item.dateend - cStart) / totalSec));
            var wFrac = Math.max(0.005, eFrac - sFrac);

            bar.style.left = (sFrac * 100) + '%';
            bar.style.width = (wFrac * 100) + '%';
            this.updateBarOverflow(bar, item, totalSec, cStart);

            var hStart = document.createElement('div');
            hStart.className = 'quest-bar-handle quest-bar-handle-start';
            bar.appendChild(hStart);

            var content = document.createElement('div');
            content.className = 'quest-bar-content text-truncate';
            content.textContent = item.title;
            bar.appendChild(content);

            var hEnd = document.createElement('div');
            hEnd.className = 'quest-bar-handle quest-bar-handle-end';
            bar.appendChild(hEnd);

            return bar;
        },

        /**
         * Render core date-availability ranges in the upper lane margin.
         *
         * @param {HTMLElement} lane Gantt lane.
         * @param {Object} item Activity item.
         * @param {number} totalSec Timeline duration.
         * @param {number} cStart Timeline start.
         */
        createAvailabilityOverlays: function(lane, item, totalSec, cStart) {
            var self = this;
            var ranges = item.availabilityranges || [];
            if (!item.hasavailabilitydates || !ranges.length) {
                return;
            }
            var cEnd = cStart + totalSec;
            ranges.forEach(function(range, index) {
                var overlay = document.createElement('div');
                overlay.className = 'quest-availability-range' +
                    (item.availabilityeditable ? '' : ' is-readonly');
                overlay.setAttribute('data-availability-rangeid', range.id || ('range-' + index));
                overlay.setAttribute('data-itemid', item.id);
                overlay.setAttribute('aria-label', self.strings.availabilityrestriction || 'Date availability restriction');
                overlay.setAttribute('title', item.availabilityeditable ?
                    (self.strings.availabilityrestrictionhint || 'Drag to adjust the date restriction.') :
                    (item.availabilityreason || 'Date availability restriction'));
                self.positionAvailabilityOverlay(overlay, range, totalSec, cStart);

                if (range.startcondition) {
                    var startHandle = document.createElement('span');
                    startHandle.className = 'quest-availability-handle quest-availability-handle-start';
                    startHandle.setAttribute('data-condition-id', range.startcondition);
                    startHandle.setAttribute('aria-hidden', 'true');
                    overlay.appendChild(startHandle);
                } else {
                    var before = document.createElement('span');
                    before.className = 'quest-availability-open quest-availability-open-start';
                    before.textContent = '<<';
                    before.setAttribute('aria-hidden', 'true');
                    overlay.appendChild(before);
                }

                var lock = document.createElement('span');
                lock.className = 'quest-availability-lock fa fa-lock';
                lock.setAttribute('aria-hidden', 'true');
                overlay.appendChild(lock);

                if (range.endcondition) {
                    var endHandle = document.createElement('span');
                    endHandle.className = 'quest-availability-handle quest-availability-handle-end';
                    endHandle.setAttribute('data-condition-id', range.endcondition);
                    endHandle.setAttribute('aria-hidden', 'true');
                    overlay.appendChild(endHandle);
                } else {
                    var after = document.createElement('span');
                    after.className = 'quest-availability-open quest-availability-open-end';
                    after.textContent = '>>';
                    after.setAttribute('aria-hidden', 'true');
                    overlay.appendChild(after);
                }
                lane.appendChild(overlay);
            });
        },

        /**
         * Position one availability range against the visible timeline.
         *
         * @param {HTMLElement} overlay Range element.
         * @param {Object} range Range metadata.
         * @param {number} totalSec Timeline duration.
         * @param {number} cStart Timeline start.
         */
        positionAvailabilityOverlay: function(overlay, range, totalSec, cStart) {
            var cEnd = cStart + totalSec;
            var start = Number(range.start || cStart);
            var end = Number(range.end || cEnd);
            var startFraction = Math.max(0, Math.min(1, (start - cStart) / totalSec));
            var endFraction = Math.max(0, Math.min(1, (end - cStart) / totalSec));
            overlay.style.left = (startFraction * 100) + '%';
            overlay.style.width = (Math.max(0.005, endFraction - startFraction) * 100) + '%';
            overlay.classList.toggle('has-open-start', !range.start);
            overlay.classList.toggle('has-open-end', !range.end);
        },

        /**
         * Add or remove markers for dates outside the visible course timeframe.
         *
         * @param {HTMLElement} bar Gantt activity bar.
         * @param {Object} item Activity item.
         * @param {number} totalSec Visible timeline duration in seconds.
         * @param {number} cStart Visible timeline start timestamp.
         */
        updateBarOverflow: function(bar, item, totalSec, cStart) {
            var cEnd = cStart + totalSec;
            var startsBefore = item.startenabled === false || Number(item.datestart) < cStart;
            var endsAfter = item.endenabled === false || Number(item.dateend) > cEnd;
            var markers = bar.querySelectorAll('.quest-bar-overflow');

            for (var i = 0; i < markers.length; i++) {
                markers[i].remove();
            }

            bar.classList.toggle('has-overflow-start', startsBefore);
            bar.classList.toggle('has-overflow-end', endsAfter);

            if (startsBefore) {
                var startMarker = document.createElement('span');
                startMarker.className = 'quest-bar-overflow quest-bar-overflow-start';
                startMarker.setAttribute('aria-hidden', 'true');
                startMarker.textContent = '<<';
                bar.appendChild(startMarker);
            }

            if (endsAfter) {
                var endMarker = document.createElement('span');
                endMarker.className = 'quest-bar-overflow quest-bar-overflow-end';
                endMarker.setAttribute('aria-hidden', 'true');
                endMarker.textContent = '>>';
                bar.appendChild(endMarker);
            }

            var messages = [];
            if (startsBefore) {
                messages.push(item.startenabled === false ?
                    ((this.strings && this.strings.activitystartdisabled) || 'The start date is disabled.') :
                    ((this.strings && this.strings.activitybeforetimeline) ||
                        'The activity starts before the visible timeline.'));
            }
            if (endsAfter) {
                messages.push(item.endenabled === false ?
                    ((this.strings && this.strings.activityenddisabled) || 'The end date is disabled.') :
                    ((this.strings && this.strings.activityaftertimeline) ||
                        'The activity ends after the visible timeline.'));
            }
            var baseTitle = bar.getAttribute('data-base-title') || '';
            var titleParts = baseTitle ? [baseTitle] : [];
            if (messages.length) {
                titleParts = titleParts.concat(messages);
            }
            bar.setAttribute('aria-label', item.title + (titleParts.length ? '. ' + titleParts.join(' ') : ''));
            if (titleParts.length) {
                bar.setAttribute('title', titleParts.join(' '));
            } else {
                bar.removeAttribute('title');
            }
        },

        /**
         * Start a drag on a date-availability decoration.
         *
         * @param {PointerEvent} e Pointer event.
         * @param {HTMLElement} target Range or handle element.
         * @return {boolean} True when a restriction drag was started.
         */
        startAvailabilityDrag: function(e, target) {
            var self = this;
            var overlay = target.closest('.quest-availability-range');
            if (!overlay) {
                return false;
            }
            var itemId = overlay.getAttribute('data-itemid');
            var item = self.items.find(function(candidate) {
                return String(candidate.id) === String(itemId);
            });
            if (!item || !item.availabilityeditable) {
                return false;
            }
            var rangeId = overlay.getAttribute('data-availability-rangeid');
            var range = (item.availabilityranges || []).find(function(candidate) {
                return String(candidate.id) === String(rangeId);
            });
            if (!range) {
                return false;
            }

            var handle = target.closest('.quest-availability-handle');
            var mode = handle ? (handle.classList.contains('quest-availability-handle-start') ?
                'resize-start' : 'resize-end') : 'move';
            if (mode === 'move' && (!range.startcondition || !range.endcondition)) {
                return false;
            }
            var rightArea = self.board.querySelector('.quest-timeline-right-area');
            if (!rightArea) {
                return false;
            }
            var conditionIds = {
                start: range.startcondition || null,
                end: range.endcondition || null
            };
            self.activeAvailabilityDrag = {
                pointerId: e.pointerId,
                item: item,
                range: range,
                overlay: overlay,
                mode: mode,
                startX: e.clientX,
                trackRect: rightArea.getBoundingClientRect(),
                initialStart: Number(range.start || 0),
                initialEnd: Number(range.end || 0),
                conditionIds: conditionIds,
                totalSec: Math.max(3600, self.getTimelineEnd() - self.getTimelineStart()),
                pendingPointer: null,
                frameId: 0,
                changed: false
            };
            overlay.classList.add('is-dragging');
            if (overlay.setPointerCapture) {
                overlay.setPointerCapture(e.pointerId);
            }
            e.preventDefault();
            return true;
        },

        /**
         * Coalesce availability pointer moves to one visual update per frame.
         * Pointer events can arrive faster than the browser can present them;
         * retaining only the latest coordinates prevents an event backlog from
         * delaying the next pointer input and inflating INP.
         *
         * @param {PointerEvent} e Pointer event.
         */
        queueAvailabilityDrag: function(e) {
            var self = this;
            var drag = self.activeAvailabilityDrag;
            if (!drag || drag.pointerId !== e.pointerId) {
                return;
            }
            drag.pendingPointer = {
                pointerId: e.pointerId,
                clientX: e.clientX,
                clientY: e.clientY
            };
            if (drag.frameId) {
                return;
            }
            drag.frameId = window.requestAnimationFrame(function() {
                drag.frameId = 0;
                if (self.activeAvailabilityDrag !== drag || !drag.pendingPointer) {
                    return;
                }
                var pointer = drag.pendingPointer;
                drag.pendingPointer = null;
                self.updateAvailabilityDrag(pointer);
            });
        },

        /**
         * Apply the final queued availability position before pointerup.
         */
        flushAvailabilityDrag: function() {
            var drag = this.activeAvailabilityDrag;
            if (!drag) {
                return;
            }
            if (drag.frameId) {
                window.cancelAnimationFrame(drag.frameId);
                drag.frameId = 0;
            }
            if (drag.pendingPointer) {
                var pointer = drag.pendingPointer;
                drag.pendingPointer = null;
                this.updateAvailabilityDrag(pointer);
            }
        },

        /**
         * Cancel a queued availability frame without applying it.
         */
        cancelAvailabilityDragFrame: function() {
            var drag = this.activeAvailabilityDrag;
            if (!drag) {
                return;
            }
            if (drag.frameId) {
                window.cancelAnimationFrame(drag.frameId);
                drag.frameId = 0;
            }
            drag.pendingPointer = null;
        },

        /**
         * Update one availability range during a drag.
         *
         * @param {PointerEvent} e Pointer event.
         */
        updateAvailabilityDrag: function(e) {
            var drag = this.activeAvailabilityDrag;
            if (!drag || drag.pointerId !== e.pointerId) {
                return;
            }
            var dx = e.clientX - drag.startX;
            var dt = Math.round((dx / drag.trackRect.width) * drag.totalSec / 3600) * 3600;
            var newStart = drag.initialStart;
            var newEnd = drag.initialEnd;
            var minDuration = 3600;
            var timelineStart = this.getTimelineStart();
            var timelineEnd = this.getTimelineEnd();

            if (drag.mode === 'move') {
                var minShift = timelineStart - drag.initialStart;
                var maxShift = timelineEnd - drag.initialEnd;
                var shift = Math.max(minShift, Math.min(maxShift, dt));
                newStart = drag.initialStart + shift;
                newEnd = drag.initialEnd + shift;
            } else if (drag.mode === 'resize-start') {
                newStart = Math.max(timelineStart, drag.initialStart + dt);
                if (drag.initialEnd && newStart >= drag.initialEnd) {
                    newStart = drag.initialEnd - minDuration;
                }
            } else {
                newEnd = Math.min(timelineEnd, drag.initialEnd + dt);
                if (drag.initialStart && newEnd <= drag.initialStart) {
                    newEnd = drag.initialStart + minDuration;
                }
            }

            var changed = newStart !== drag.range.start || newEnd !== drag.range.end;
            this.updateAvailabilityHUD(e.clientX, e.clientY, drag.item, drag.range);
            if (!changed) {
                return;
            }

            drag.range.start = newStart;
            drag.range.end = newEnd;
            var conditions = drag.item.availabilityconditions || [];
            conditions.forEach(function(condition) {
                if (condition.id === drag.conditionIds.start) {
                    condition.time = newStart;
                }
                if (condition.id === drag.conditionIds.end) {
                    condition.time = newEnd;
                }
            });
            this.positionAvailabilityOverlay(drag.overlay, drag.range, drag.totalSec, timelineStart);
            this.updateAvailabilityHUD(e.clientX, e.clientY, drag.item, drag.range);
            if (!drag.changed) {
                drag.changed = true;
                this.markDirty();
            }
        },

        /**
         * Update the drag HUD for a restriction.
         *
         * @param {number} x Pointer X.
         * @param {number} y Pointer Y.
         * @param {Object} item Activity item.
         * @param {Object} range Restriction range.
         */
        updateAvailabilityHUD: function(x, y, item, range) {
            if (!this.hud) {
                return;
            }
            this.hud.classList.remove('d-none');
            this.hud.style.transform = 'translate3d(' + (x + 15) + 'px, ' + (y - 50) + 'px, 0)';
            var dateText = (range.start ? formatDateTime(range.start, this.locale) : '<<') +
                '  \u2192  ' + (range.end ? formatDateTime(range.end, this.locale) : '>>');
            if (this.availabilityHudDateText !== dateText) {
                this.hudTitle.textContent = (this.strings.availabilityrestriction || 'Date availability restriction') +
                    ': ' + item.title;
                this.hudDates.textContent = dateText;
                this.availabilityHudDateText = dateText;
            }
        },

        /**
         * Return the horizontal scroll container for the timeline body.
         *
         * @return {HTMLElement} Timeline scroll container.
         */
        getScrollContainer: function() {
            return this.scrollContainer || this.board;
        },

        /**
         * Return the width of the fixed activity column in pixels.
         *
         * @return {number} Left column width.
         */
        getLeftColumnWidth: function() {
            var leftColumn = this.board && this.board.querySelector('.quest-timeline-left-column');
            return leftColumn ? leftColumn.getBoundingClientRect().width : 280;
        },

        /**
         * Clamp a horizontal scroll position to the current board bounds.
         *
         * @param {number} value Desired scroll position.
         * @return {number} Safe scroll position.
         */
        clampScrollLeft: function(value) {
            var scrollContainer = this.getScrollContainer();
            if (!scrollContainer) {
                return 0;
            }
            var maxScroll = Math.max(0, scrollContainer.scrollWidth - scrollContainer.clientWidth);
            return Math.max(0, Math.min(maxScroll, value));
        },

        /**
         * Change the horizontal time zoom while keeping a focal point stable.
         *
         * @param {number} requestedZoom Requested zoom multiplier.
         * @param {number} clientX Focal point in viewport coordinates.
         * @param {number} [focalRatio] Optional time ratio to preserve.
         */
        setZoomAt: function(requestedZoom, clientX, focalRatio) {
            if (!this.board || !this.baseTrackWidthPx) {
                return;
            }

            var oldZoom = this.zoomLevel;
            var newZoom = Math.max(this.minZoom, Math.min(this.maxZoom, requestedZoom));
            if (Math.abs(newZoom - oldZoom) < 0.001) {
                return;
            }

            var boardRect = this.board.getBoundingClientRect();
            var viewportX = clientX - boardRect.left;
            var leftColumnWidth = this.getLeftColumnWidth();
            var scrollContainer = this.getScrollContainer();
            var oldContentX = scrollContainer.scrollLeft + viewportX - leftColumnWidth;
            var oldScrollTop = scrollContainer.scrollTop;
            var ratio = typeof focalRatio === 'number' ? focalRatio :
                Math.max(0, Math.min(1, oldContentX / Math.max(1, this.trackWidthPx)));

            this.zoomLevel = newZoom;
            this.renderTimeline();
            scrollContainer = this.getScrollContainer();
            scrollContainer.scrollTop = oldScrollTop;

            var newLeftColumnWidth = this.getLeftColumnWidth();
            var newContentX = ratio * this.trackWidthPx;
            scrollContainer.scrollLeft = this.clampScrollLeft(
                newContentX - viewportX + newLeftColumnWidth
            );
        },

        /**
         * Get the two active pointer positions used for pinch zooming.
         *
         * @param {Array<number>} pointerIds Pointer IDs.
         * @return {Array<Object>} Two pointer positions or an empty array.
         */
        getPinchPoints: function(pointerIds) {
            if (!pointerIds || pointerIds.length < 2) {
                return [];
            }
            var first = this.pointerPositions[pointerIds[0]];
            var second = this.pointerPositions[pointerIds[1]];
            return first && second ? [first, second] : [];
        },

        /**
         * Start a two-finger timeline gesture.
         */
        startPinch: function() {
            var pointerIds = Object.keys(this.pointerPositions);
            if (pointerIds.length < 2 || this.activeDrag) {
                return;
            }
            pointerIds = pointerIds.slice(0, 2);
            var points = this.getPinchPoints(pointerIds);
            var centerX = (points[0].x + points[1].x) / 2;
            var distance = Math.max(1, Math.hypot(
                points[1].x - points[0].x,
                points[1].y - points[0].y
            ));
            var boardRect = this.board.getBoundingClientRect();
            var scrollContainer = this.getScrollContainer();
            var contentX = scrollContainer.scrollLeft + centerX - boardRect.left - this.getLeftColumnWidth();

            this.pinchState = {
                pointerIds: pointerIds,
                startDistance: distance,
                startCenterX: centerX,
                startZoom: this.zoomLevel,
                focalRatio: Math.max(0, Math.min(1, contentX / Math.max(1, this.trackWidthPx)))
            };
            this.backgroundPan = null;
            this.clickCandidate = null;
            this.board.classList.add('is-panning');
        },

        /**
         * Update an active two-finger gesture.
         */
        updatePinch: function() {
            if (!this.pinchState) {
                return;
            }
            var points = this.getPinchPoints(this.pinchState.pointerIds);
            if (points.length < 2) {
                return;
            }

            var centerX = (points[0].x + points[1].x) / 2;
            var distance = Math.max(1, Math.hypot(
                points[1].x - points[0].x,
                points[1].y - points[0].y
            ));
            var scale = distance / this.pinchState.startDistance;
            this.setZoomAt(
                this.pinchState.startZoom * scale,
                centerX,
                this.pinchState.focalRatio
            );

            // Translation of both fingers pans the timeline as well as zooming it.
            var centerDelta = centerX - this.pinchState.startCenterX;
            if (Math.abs(centerDelta) > 0) {
                var scrollContainer = this.getScrollContainer();
                scrollContainer.scrollLeft = this.clampScrollLeft(
                    scrollContainer.scrollLeft - centerDelta
                );
            }
        },

        /**
         * Bind drag, pan, wheel-zoom and pinch-zoom events.
         */
        bindGlobalEvents: function() {
            var self = this;

            if (!self.pageStickyEventsBound) {
                self.pageStickyHandler = function() {
                    self.updatePageStickyHeader();
                };
                window.addEventListener('scroll', self.pageStickyHandler, {passive: true});
                window.addEventListener('resize', self.pageStickyHandler);
                self.pageStickyEventsBound = true;
            }

            self.board.addEventListener('pointerdown', function(e) {
                self.pointerPositions[e.pointerId] = {
                    x: e.clientX,
                    y: e.clientY,
                    pointerType: e.pointerType
                };

                if (e.pointerType === 'touch' && Object.keys(self.pointerPositions).length >= 2) {
                    self.startPinch();
                    e.preventDefault();
                    return;
                }

                var availabilityTarget = e.target.closest(
                    '.quest-availability-range, .quest-availability-handle'
                );
                if (availabilityTarget && self.startAvailabilityDrag(e, availabilityTarget)) {
                    return;
                }

                var bar = e.target.closest('.quest-calendar-bar');
                if (!bar) {
                    var horizontalSurface = e.target.closest(
                        '.quest-timeline-right-area, .quest-timeline-header-viewport'
                    );
                    if (!horizontalSurface || (e.pointerType === 'mouse' && e.button !== 0)) {
                        return;
                    }
                    self.backgroundPan = {
                        pointerId: e.pointerId,
                        startX: e.clientX,
                        startScrollLeft: self.getScrollContainer().scrollLeft,
                        hasMoved: false
                    };
                    self.board.classList.add('is-panning');
                    return;
                }
                var itemId = bar.getAttribute('data-itemid');
                var item = self.items.find(function(it) {
                    return it.id === itemId;
                });
                if (!item) {
                    return;
                }
                // Disabled items remain clickable for navigation, but cannot be dragged.
                if (!self.isItemInteractive(item)) {
                    self.clickCandidate = {
                        itemId: itemId,
                        startX: e.clientX,
                        startY: e.clientY,
                        hasMoved: false,
                        startTime: Date.now()
                    };
                    return;
                }


                var barRect = bar.getBoundingClientRect();
                var clickX = e.clientX - barRect.left;
                var barW = barRect.width;

                var isStartHandle = !!e.target.closest('.quest-bar-handle-start');
                var isEndHandle = !!e.target.closest('.quest-bar-handle-end');

                var mode = 'move';
                // If bar has very small width (< 24px) or if either handle was clicked:
                if (isStartHandle || isEndHandle || barW < 24) {
                    if (barW < 24) {
                        // When handles overlap or bar is very narrow, determine direction based on click position.
                        // Left half allows stretching start; right half allows stretching end.
                        mode = (clickX < barW / 2) ? 'resize-start' : 'resize-end';
                    } else if (isStartHandle) {
                        mode = 'resize-start';
                    } else if (isEndHandle) {
                        // Even if handle-end caught the event due to DOM stacking, check if click is on left half.
                        if (clickX < 12 && clickX < barW / 2) {
                            mode = 'resize-start';
                        } else {
                            mode = 'resize-end';
                        }
                    }
                }

                // An activity with a disabled endpoint has no movable interval yet.
                // Its handles are still active so dragging one enables that endpoint.
                if (mode === 'move' &&
                        (item.startenabled === false || item.endenabled === false)) {
                    self.clickCandidate = {
                        itemId: itemId,
                        startX: e.clientX,
                        startY: e.clientY,
                        hasMoved: false,
                        startTime: Date.now()
                    };
                    return;
                }

                // Capture subactivities if this item is a parent activity.
                var childItems = self.items.filter(function(it) {
                    return it.parentkey === item.id && self.isItemEditable(it);
                });
                var childSnapshots = childItems.map(function(child) {
                    var childBar = self.board.querySelector('.quest-calendar-bar[data-itemid="' + child.id + '"]');
                    return {
                        item: child,
                        barEl: childBar,
                        initialStart: child.datestart,
                        initialEnd: child.dateend,
                        initialDuration: Math.max(1, child.dateend - child.datestart)
                    };
                });

                var rightArea = self.board.querySelector('.quest-timeline-right-area');
                var trackRect = rightArea.getBoundingClientRect();
                var parentItem = self.getParentItem(item);

                self.activeDrag = {
                    item: item,
                    barEl: bar,
                    mode: mode,
                    startX: e.clientX,
                    trackRect: trackRect,
                    initialStart: item.datestart,
                    initialEnd: item.dateend,
                    duration: Math.max(1, item.dateend - item.datestart),
                    parent: parentItem,
                    children: childSnapshots,
                    totalSec: Math.max(3600, self.getTimelineEnd() - self.getTimelineStart())
                };

                // Track potential simple click on the bar
                self.clickCandidate = {
                    itemId: itemId,
                    startX: e.clientX,
                    startY: e.clientY,
                    hasMoved: false,
                    startTime: Date.now()
                };

                bar.classList.add('is-dragging');
                if (bar.setPointerCapture) {
                    bar.setPointerCapture(e.pointerId);
                }
                e.preventDefault();
            });

            document.addEventListener('pointermove', function(e) {
                if (self.pointerPositions[e.pointerId]) {
                    self.pointerPositions[e.pointerId].x = e.clientX;
                    self.pointerPositions[e.pointerId].y = e.clientY;
                }

                if (self.pinchState) {
                    self.updatePinch();
                    e.preventDefault();
                    return;
                }

                if (self.activeAvailabilityDrag) {
                    self.queueAvailabilityDrag(e);
                    e.preventDefault();
                    return;
                }

                if (self.backgroundPan && self.backgroundPan.pointerId === e.pointerId) {
                    var panDx = e.clientX - self.backgroundPan.startX;
                    if (Math.abs(panDx) > 4) {
                        self.backgroundPan.hasMoved = true;
                    }
                    if (self.backgroundPan.hasMoved) {
                        e.preventDefault();
                        self.getScrollContainer().scrollLeft = self.clampScrollLeft(
                            self.backgroundPan.startScrollLeft - panDx
                        );
                    }
                    return;
                }

                if (self.clickCandidate && !self.clickCandidate.hasMoved) {
                    var moveDist = Math.hypot(e.clientX - self.clickCandidate.startX, e.clientY - self.clickCandidate.startY);
                    if (moveDist > 4) {
                        self.clickCandidate.hasMoved = true;
                    }
                }

                if (!self.activeDrag) {
                    return;
                }
                var drag = self.activeDrag;
                var trackWidth = drag.trackRect.width;
                var dx = e.clientX - drag.startX;
                var dt = Math.round((dx / trackWidth) * drag.totalSec);

                // Snap to 1 hour (3600s)
                dt = Math.round(dt / 3600) * 3600;

                var cStart = self.config.courseStart;
                var cEnd = self.config.courseEnd;
                var timelineStart = self.getTimelineStart();
                var minDur = 3600;

                var newStart = drag.item.datestart;
                var newEnd = drag.item.dateend;

                if (drag.mode === 'resize-start') {
                    drag.item.startenabled = true;
                } else if (drag.mode === 'resize-end') {
                    drag.item.endenabled = true;
                }

                if (drag.mode === 'move') {
                    var minShift = cStart - drag.initialStart;
                    var maxShift = cEnd - drag.initialEnd;
                    if (drag.parent && !drag.parent.derived) {
                        minShift = Math.max(minShift, drag.parent.datestart - drag.initialStart);
                        maxShift = Math.min(maxShift, drag.parent.dateend - drag.initialEnd);
                    }
                    if (drag.children && drag.children.length > 0) {
                        drag.children.forEach(function(c) {
                            minShift = Math.max(minShift, cStart - c.initialStart);
                            maxShift = Math.min(maxShift, cEnd - c.initialEnd);
                        });
                    }
                    if (minShift > maxShift) {
                        if (drag.parent && !drag.parent.derived) {
                            minShift = drag.parent.datestart - drag.initialStart;
                            maxShift = drag.parent.dateend - drag.initialEnd;
                        } else {
                            minShift = cStart - drag.initialStart;
                            maxShift = cEnd - drag.initialEnd;
                        }
                    }
                    var shift = Math.max(minShift, Math.min(maxShift, dt));
                    newStart = drag.initialStart + shift;
                    newEnd = drag.initialEnd + shift;

                    // Synchronously shift subactivities by the exact same amount.
                    if (drag.children && drag.children.length > 0) {
                        drag.children.forEach(function(c) {
                            c.item.datestart = c.initialStart + shift;
                            c.item.dateend = c.initialEnd + shift;

                            if (c.barEl) {
                                var cSFrac = Math.max(0, Math.min(1, (c.item.datestart - timelineStart) / drag.totalSec));
                                var cEFrac = Math.max(0, Math.min(1, (c.item.dateend - timelineStart) / drag.totalSec));
                                var cWFrac = Math.max(0.005, cEFrac - cSFrac);
                                c.barEl.style.left = (cSFrac * 100) + '%';
                                c.barEl.style.width = (cWFrac * 100) + '%';
                                self.updateBarOverflow(c.barEl, c.item, drag.totalSec, timelineStart);
                            }
                            self.updateTableRow(c.item);
                        });
                    }
                } else if (drag.mode === 'resize-start') {
                    var minStart = cStart;
                    if (drag.parent && !drag.parent.derived) {
                        minStart = Math.max(minStart, drag.parent.datestart);
                    }
                    newStart = Math.min(drag.initialEnd - minDur, Math.max(minStart, drag.initialStart + dt));
                    newEnd = drag.initialEnd;
                } else if (drag.mode === 'resize-end') {
                    var maxEnd = cEnd;
                    if (drag.parent && !drag.parent.derived) {
                        maxEnd = Math.min(maxEnd, drag.parent.dateend);
                    }
                    newStart = drag.initialStart;
                    newEnd = Math.max(drag.initialStart + minDur, Math.min(maxEnd, drag.initialEnd + dt));
                }

                drag.item.datestart = newStart;
                drag.item.dateend = newEnd;

                // Proportionally resize subactivities when parent activity is resized.
                if ((drag.mode === 'resize-start' || drag.mode === 'resize-end') &&
                    drag.children && drag.children.length > 0) {
                    var pInitDur = Math.max(1, drag.initialEnd - drag.initialStart);
                    var pNewDur = Math.max(1, newEnd - newStart);

                    drag.children.forEach(function(c) {
                        var rStart = (c.initialStart - drag.initialStart) / pInitDur;
                        var rEnd = (c.initialEnd - drag.initialStart) / pInitDur;

                        var newCStart = newStart + rStart * pNewDur;
                        var newCEnd = newStart + rEnd * pNewDur;

                        // Snap to hour (3600s) if new parent duration is large enough, else minute.
                        var step = (pNewDur >= 7200) ? 3600 : 60;
                        newCStart = Math.round(newCStart / step) * step;
                        newCEnd = Math.round(newCEnd / step) * step;

                        // Preserve exact boundary alignment if originally aligned with parent.
                        if (c.initialStart === drag.initialStart) {
                            newCStart = newStart;
                        }
                        if (c.initialEnd === drag.initialEnd) {
                            newCEnd = newEnd;
                        }

                        // Maintain subactivity within parent boundaries and enforce valid duration.
                        if (c.initialEnd <= drag.initialEnd && newCEnd > newEnd) {
                            newCEnd = newEnd;
                        }
                        if (c.initialStart >= drag.initialStart && newCStart < newStart) {
                            newCStart = newStart;
                        }
                        if (newCEnd <= newCStart) {
                            if (newCEnd > newStart + step) {
                                newCStart = newCEnd - step;
                            } else {
                                newCEnd = newCStart + step;
                            }
                        }

                        c.item.datestart = newCStart;
                        c.item.dateend = newCEnd;

                        if (c.barEl) {
                            var cSFrac = Math.max(0, Math.min(1, (newCStart - timelineStart) / drag.totalSec));
                            var cEFrac = Math.max(0, Math.min(1, (newCEnd - timelineStart) / drag.totalSec));
                            var cWFrac = Math.max(0.005, cEFrac - cSFrac);
                            c.barEl.style.left = (cSFrac * 100) + '%';
                            c.barEl.style.width = (cWFrac * 100) + '%';
                            self.updateBarOverflow(c.barEl, c.item, drag.totalSec, timelineStart);
                        }
                        self.updateTableRow(c.item);
                    });
                }

                var sFrac = Math.max(0, Math.min(1, (newStart - timelineStart) / drag.totalSec));
                var eFrac = Math.max(0, Math.min(1, (newEnd - timelineStart) / drag.totalSec));
                var wFrac = Math.max(0.005, eFrac - sFrac);

                drag.barEl.style.left = (sFrac * 100) + '%';
                drag.barEl.style.width = (wFrac * 100) + '%';
                self.updateBarOverflow(drag.barEl, drag.item, drag.totalSec, timelineStart);

                self.updateHUD(e.clientX, e.clientY, drag.item);
                self.updateTableRow(drag.item);
                if (drag.item.derived) {
                    self.refreshDerivedItem(drag.item);
                } else {
                    self.refreshDerivedParent(drag.item);
                }
                self.markDirty();
            });

            document.addEventListener('pointerup', function(e) {
                delete self.pointerPositions[e.pointerId];

                if (self.pinchState) {
                    var remainingPinchPointers = self.getPinchPoints(self.pinchState.pointerIds);
                    if (remainingPinchPointers.length < 2) {
                        self.pinchState = null;
                        self.board.classList.remove('is-panning');
                    }
                    return;
                }

                if (self.activeAvailabilityDrag && self.activeAvailabilityDrag.pointerId === e.pointerId) {
                    self.activeAvailabilityDrag.pendingPointer = {
                        pointerId: e.pointerId,
                        clientX: e.clientX,
                        clientY: e.clientY
                    };
                    self.flushAvailabilityDrag();
                    self.activeAvailabilityDrag.overlay.classList.remove('is-dragging');
                    self.activeAvailabilityDrag = null;
                    self.hideHUD();
                    return;
                }

                if (self.backgroundPan && self.backgroundPan.pointerId === e.pointerId) {
                    self.backgroundPan = null;
                    self.board.classList.remove('is-panning');
                    return;
                }

                // If it was a simple click without drag movement, jump to the table row
                if (self.clickCandidate && !self.clickCandidate.hasMoved && (Date.now() - self.clickCandidate.startTime < 500)) {
                    var clickedItemId = self.clickCandidate.itemId;
                    self.scrollToTableRow(clickedItemId);
                }
                self.clickCandidate = null;

                if (!self.activeDrag) {
                    return;
                }
                self.activeDrag.barEl.classList.remove('is-dragging');
                self.activeDrag = null;
                self.hideHUD();
            });

            document.addEventListener('pointercancel', function(e) {
                delete self.pointerPositions[e.pointerId];
                self.pinchState = null;
                self.backgroundPan = null;
                self.board.classList.remove('is-panning');
                self.clickCandidate = null;
                if (self.activeAvailabilityDrag && self.activeAvailabilityDrag.pointerId === e.pointerId) {
                    self.cancelAvailabilityDragFrame();
                    self.activeAvailabilityDrag.overlay.classList.remove('is-dragging');
                    self.activeAvailabilityDrag = null;
                    self.hideHUD();
                    return;
                }
                if (!self.activeDrag) {
                    return;
                }
                self.activeDrag.barEl.classList.remove('is-dragging');
                self.activeDrag = null;
                self.hideHUD();
            });

            self.board.addEventListener('wheel', function(e) {
                if (!e.ctrlKey) {
                    return;
                }
                e.preventDefault();
                var delta = e.deltaY;
                if (e.deltaMode === 1) {
                    delta *= 16;
                } else if (e.deltaMode === 2) {
                    delta *= self.board.clientHeight;
                }
                var zoomFactor = Math.exp(-delta * 0.002);
                self.setZoomAt(self.zoomLevel * zoomFactor, e.clientX);
            }, {passive: false});
        },

        /**
         * Update floating HUD tooltip.
         *
         * @param {number} x Screen X.
         * @param {number} y Screen Y.
         * @param {Object} item Activity item.
         */
        updateHUD: function(x, y, item) {
            if (!this.hud) {
                return;
            }
            this.hud.classList.remove('d-none');
            this.hud.style.transform = 'translate3d(' + (x + 15) + 'px, ' + (y - 50) + 'px, 0)';
            this.hudTitle.textContent = item.title + ' (' + formatDuration(item.dateend - item.datestart) + ')';
            this.hudDates.textContent = formatDateTime(item.datestart, this.locale) + '  \u2192  ' +
                formatDateTime(item.dateend, this.locale);
        },

        /**
         * Hide HUD.
         */
        hideHUD: function() {
            if (this.hud) {
                this.hud.classList.add('d-none');
            }
            this.availabilityHudDateText = null;
        },

        /**
         * Scroll smoothly to the corresponding row in the detail table and pulse-highlight it.
         *
         * @param {string} itemId Activity ID.
         */
        scrollToTableRow: function(itemId) {
            var self = this;
            var row = document.querySelector('#reschedule-table tr[data-itemid="' + itemId + '"]');
            if (!row) {
                return;
            }

            // If it is a subtask and hidden, expand its parent.
            var parentKey = row.getAttribute('data-parentkey');
            if (parentKey && (!self.expandedParents || !self.expandedParents[parentKey])) {
                self.toggleParent(parentKey);
            }

            // Smooth scroll into view
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Apply pulse highlight animation
            row.classList.remove('quest-row-highlight');
            void row.offsetWidth; // Force CSS reflow to retrigger animation
            row.classList.add('quest-row-highlight');
            setTimeout(function() {
                row.classList.remove('quest-row-highlight');
            }, 2000);
        },

        /**
         * Update corresponding row in table.
         *
         * @param {Object} item Activity item.
         */
        updateTableRow: function(item) {
            var row = document.querySelector('tr[data-itemid="' + item.id + '"]');
            if (!row) {
                return;
            }
            var colStart = row.querySelector('.col-datestart');
            var colEnd = row.querySelector('.col-dateend');
            var colDur = row.querySelector('.col-duration .badge');

            var startStr = item.startenabled === false ? '' : formatDateTime(item.datestart, this.locale);
            var endStr = item.endenabled === false ? '' : formatDateTime(item.dateend, this.locale);

            if (colStart) {
                var sSpan = colStart.querySelector('.date-text');
                if (sSpan) {
                    sSpan.textContent = startStr;
                } else {
                    colStart.textContent = startStr;
                }
            }
            if (colEnd) {
                var eSpan = colEnd.querySelector('.date-text');
                if (eSpan) {
                    eSpan.textContent = endStr;
                } else {
                    colEnd.textContent = endStr;
                }
            }
            if (colDur) {
                colDur.textContent = formatDuration(item.dateend - item.datestart);
            }

            var leftLabel = document.querySelector('.quest-left-row[data-itemid="' + item.id + '"] .quest-lbl-dur');
            if (leftLabel) {
                leftLabel.textContent = formatDuration(item.dateend - item.datestart);
            }
        },

        /**
         * Return the visible timeline start without changing course constraints.
         *
         * @return {number} Timeline start timestamp.
         */
        getTimelineStart: function() {
            return Number(this.config.timelineStart || this.config.courseStart);
        },

        /**
         * Return the visible timeline end without changing course constraints.
         *
         * @return {number} Timeline end timestamp.
         */
        getTimelineEnd: function() {
            return Number(this.config.timelineEnd || this.config.courseEnd);
        },

        /**
         * Return whether an item can be rescheduled.
         *
         * @param {Object} item Activity item.
         * @return {boolean}
         */
        isItemEditable: function(item) {
            return !!item && item.editable !== false;
        },

        /**
         * Return whether an item can be used to manipulate its schedule.
         * Derived parents are interactive even though their dates are not saved directly.
         *
         * @param {Object} item Activity item.
         * @return {boolean}
         */
        isItemInteractive: function(item) {
            return !!item && (this.isItemEditable(item) || !!item.derived);
        },

        /**
         * Check whether core availability condition times changed.
         *
         * @param {Object} item Current item.
         * @param {Object} initial Original item.
         * @return {boolean}
         */
        hasAvailabilityChanges: function(item, initial) {
            var current = (item && item.availabilityconditions) || [];
            var original = (initial && initial.availabilityconditions) || [];
            var normalise = function(conditions) {
                return conditions.map(function(condition) {
                    return {
                        id: String(condition.id),
                        direction: String(condition.direction || ''),
                        time: Number(condition.time || 0)
                    };
                }).sort(function(a, b) {
                    return a.id.localeCompare(b.id);
                });
            };
            return JSON.stringify(normalise(current)) !== JSON.stringify(normalise(original));
        },

        /**
         * Return an item's parent, if it has one.
         *
         * @param {Object} item Activity item.
         * @return {Object|null} Parent item.
         */
        getParentItem: function(item) {
            if (!item || !item.parentkey) {
                return null;
            }
            return this.items.find(function(candidate) {
                return candidate.id === item.parentkey;
            }) || null;
        },

        /**
         * Recalculate a parent whose dates are derived from its children.
         *
         * @param {Object} child Changed child item.
         */
        refreshDerivedParent: function(child) {
            var parent = this.getParentItem(child);
            if (!parent || !parent.derived) {
                return;
            }
            this.refreshDerivedItem(parent);
        },

        /**
         * Recalculate a derived item's dates from its child items.
         *
         * @param {Object} parent Derived parent item.
         */
        refreshDerivedItem: function(parent) {
            var self = this;
            if (!parent || !parent.derived) {
                return;
            }

            var children = self.items.filter(function(item) {
                return item.parentkey === parent.id && Number(item.dateend) > Number(item.datestart);
            });
            if (!children.length) {
                return;
            }

            var newStart = Math.min.apply(null, children.map(function(item) {
                return Number(item.datestart);
            }));
            var newEnd = Math.max.apply(null, children.map(function(item) {
                return Number(item.dateend);
            }));
            parent.datestart = newStart;
            parent.dateend = newEnd;

            var cStart = self.getTimelineStart();
            var totalSec = Math.max(3600, self.getTimelineEnd() - cStart);
            var bar = self.board.querySelector('.quest-calendar-bar[data-itemid="' + parent.id + '"]');
            if (bar) {
                var sFrac = Math.max(0, Math.min(1, (newStart - cStart) / totalSec));
                var eFrac = Math.max(0, Math.min(1, (newEnd - cStart) / totalSec));
                var wFrac = Math.max(0.005, eFrac - sFrac);
                bar.style.left = (sFrac * 100) + '%';
                bar.style.width = (wFrac * 100) + '%';
                self.updateBarOverflow(bar, parent, totalSec, cStart);
            }
            self.updateTableRow(parent);
        },

        /**
         * Mark state as dirty and enable Save button.
         */
        markDirty: function() {
            this.isDirty = true;
            if (this.saveBtn) {
                this.saveBtn.disabled = false;
            }
            if (this.unsavedAlert) {
                this.unsavedAlert.classList.remove('invisible');
            }
        },

        /**
         * Bind action buttons.
         */
        bindActions: function() {
            var self = this;

            if (self.resetBtn) {
                self.resetBtn.addEventListener('click', function() {
                    self.items = self.initialItems.map(function(it) {
                        return JSON.parse(JSON.stringify(it));
                    });
                    self.renderTimeline();
                    self.items.forEach(function(it) {
                        self.updateTableRow(it);
                    });
                    self.isDirty = false;
                    if (self.saveBtn) {
                        self.saveBtn.disabled = true;
                    }
                    if (self.unsavedAlert) {
                        self.unsavedAlert.classList.add('invisible');
                    }
                });
            }

            if (self.autoSeqBtn) {
                self.autoSeqBtn.addEventListener('click', function() {
                    self.openAutoSequenceModal();
                });
            }

            // Strategy modal interactions
            var strategyItems = document.querySelectorAll('#autosequence-strategy-list .list-group-item');
            strategyItems.forEach(function(item) {
                item.addEventListener('click', function() {
                    var strategy = item.getAttribute('data-strategy');
                    var radio = item.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                    }
                    strategyItems.forEach(function(el) {
                        el.classList.remove('active');
                    });
                    item.classList.add('active');

                    var descPanels = document.querySelectorAll('.strategy-desc-content');
                    descPanels.forEach(function(p) {
                        p.classList.add('d-none');
                    });
                    var activeDesc = document.getElementById('strategy-desc-' + strategy);
                    if (activeDesc) {
                        activeDesc.classList.remove('d-none');
                    }
                });
            });

            var applyBtn = document.getElementById('btn-autosequence-apply');
            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    var selectedRadio = document.querySelector('input[name="autosequence_strategy"]:checked');
                    var strategy = selectedRadio ? selectedRadio.value : 'equal';
                    self.applyAutoSequence(strategy);
                    self.closeAutoSequenceModal();
                });
            }

            var cancelBtn = document.getElementById('btn-autosequence-cancel');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function() {
                    self.closeAutoSequenceModal();
                });
            }

            var modalCloseBtn = document.querySelector('#reschedule-autosequence-modal .btn-close');
            if (modalCloseBtn) {
                modalCloseBtn.addEventListener('click', function() {
                    self.closeAutoSequenceModal();
                });
            }

            if (self.saveBtn) {
                self.saveBtn.addEventListener('click', function() {
                    self.saveSchedule();
                });
            }

            // Click listener for table toggle buttons and editable date cells
            var rescheduleTable = document.getElementById('reschedule-table');
            if (rescheduleTable) {
                rescheduleTable.addEventListener('click', function(e) {
                    var btn = e.target.closest('.toggle-subtasks');
                    if (btn) {
                        e.preventDefault();
                        var pKey = btn.getAttribute('data-parentkey');
                        if (pKey) {
                            self.toggleParent(pKey);
                        }
                        return;
                    }

                    var dateCell = e.target.closest('.col-datestart, .col-dateend');
                    if (dateCell) {
                        e.preventDefault();
                        var tr = dateCell.closest('tr[data-itemid]');
                        if (tr) {
                            var itemId = tr.getAttribute('data-itemid');
                            var isEnd = dateCell.classList.contains('col-dateend');
                            self.openDateModal(itemId, isEnd ? 'end' : 'start');
                        }
                    }
                });

                rescheduleTable.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        var dateCell = e.target.closest('.col-datestart, .col-dateend');
                        if (dateCell) {
                            e.preventDefault();
                            var tr = dateCell.closest('tr[data-itemid]');
                            if (tr) {
                                var itemId = tr.getAttribute('data-itemid');
                                var isEnd = dateCell.classList.contains('col-dateend');
                                self.openDateModal(itemId, isEnd ? 'end' : 'start');
                            }
                        }
                    }
                });
            }

            // Click listener for Gantt left column toggle buttons and row jump
            self.board.addEventListener('click', function(e) {
                var btn = e.target.closest('.quest-toggle-subtasks');
                if (btn) {
                    e.preventDefault();
                    var pKey = btn.getAttribute('data-parentkey');
                    if (pKey) {
                        self.toggleParent(pKey);
                    }
                    return;
                }

                var leftRow = e.target.closest('.quest-left-row');
                if (leftRow) {
                    var lItemId = leftRow.getAttribute('data-itemid');
                    if (lItemId) {
                        self.scrollToTableRow(lItemId);
                    }
                }
            });

            // Date modal action buttons and events
            var dateModalApplyBtn = document.getElementById('btn-date-modal-apply');
            if (dateModalApplyBtn) {
                dateModalApplyBtn.addEventListener('click', function() {
                    self.applyDateModalChanges();
                });
            }

            var dateModalCancelBtn = document.getElementById('btn-date-modal-cancel');
            if (dateModalCancelBtn) {
                dateModalCancelBtn.addEventListener('click', function() {
                    self.closeDateModal();
                });
            }

            var dateModalCloseBtn = document.querySelector('#reschedule-date-modal .btn-close');
            if (dateModalCloseBtn) {
                dateModalCloseBtn.addEventListener('click', function() {
                    self.closeDateModal();
                });
            }

            // Live validation and duration update in date modal
            var dateModalStartInput = document.getElementById('date-modal-start');
            var dateModalEndInput = document.getElementById('date-modal-end');
            var dateModalDurBadge = document.getElementById('date-modal-badge-duration');
            var dateModalErrorEl = document.getElementById('date-modal-error');

            /**
             * Revalidate the date fields and refresh the duration badge.
             */
            function handleDateModalInputChange() {
                if (!dateModalStartInput || !dateModalEndInput) {
                    return;
                }
                var sTs = dateTimeLocalToTimestamp(dateModalStartInput.value);
                var eTs = dateTimeLocalToTimestamp(dateModalEndInput.value);
                if (sTs && eTs && eTs > sTs) {
                    if (dateModalDurBadge) {
                        dateModalDurBadge.textContent = formatDuration(eTs - sTs);
                    }
                    if (dateModalErrorEl) {
                        dateModalErrorEl.classList.add('d-none');
                    }
                    dateModalStartInput.classList.remove('is-invalid');
                    dateModalEndInput.classList.remove('is-invalid');
                } else if (sTs && eTs && eTs <= sTs) {
                    if (dateModalErrorEl) {
                        dateModalErrorEl.classList.remove('d-none');
                    }
                    dateModalEndInput.classList.add('is-invalid');
                }
            }

            if (dateModalStartInput) {
                dateModalStartInput.addEventListener('input', handleDateModalInputChange);
                dateModalStartInput.addEventListener('change', handleDateModalInputChange);
            }
            if (dateModalEndInput) {
                dateModalEndInput.addEventListener('input', handleDateModalInputChange);
                dateModalEndInput.addEventListener('change', handleDateModalInputChange);
            }

            window.addEventListener('beforeunload', function(e) {
                if (self.isDirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },

        /**
         * Open the autosequence modal dialog.
         */
        openAutoSequenceModal: function() {
            var modalEl = document.getElementById('reschedule-autosequence-modal');
            if (!modalEl) {
                return;
            }
            if (window.bootstrap && window.bootstrap.Modal) {
                var modal = typeof window.bootstrap.Modal.getOrCreateInstance === 'function' ?
                    window.bootstrap.Modal.getOrCreateInstance(modalEl) :
                    (window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl));
                modal.show();
            } else if (window.jQuery && typeof window.jQuery(modalEl).modal === 'function') {
                window.jQuery(modalEl).modal('show');
            } else {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                modalEl.removeAttribute('aria-hidden');
                modalEl.setAttribute('aria-modal', 'true');
                if (!document.getElementById('reschedule-modal-backdrop')) {
                    var backdrop = document.createElement('div');
                    backdrop.id = 'reschedule-modal-backdrop';
                    backdrop.className = 'modal-backdrop fade show';
                    document.body.appendChild(backdrop);
                }
            }
        },

        /**
         * Close the autosequence modal dialog.
         */
        closeAutoSequenceModal: function() {
            var modalEl = document.getElementById('reschedule-autosequence-modal');
            if (!modalEl) {
                return;
            }
            if (window.bootstrap && window.bootstrap.Modal) {
                var modal = window.bootstrap.Modal.getInstance(modalEl);
                if (modal) {
                    modal.hide();
                }
            } else if (window.jQuery && typeof window.jQuery(modalEl).modal === 'function') {
                window.jQuery(modalEl).modal('hide');
            } else {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
                var backdrop = document.getElementById('reschedule-modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
            }
        },

        /**
         * Open the date and time editing modal for an activity item.
         *
         * @param {string} itemId Item ID.
         * @param {string} [fieldToFocus] 'start' or 'end'.
         */
        openDateModal: function(itemId, fieldToFocus) {
            var self = this;
            var item = self.items.find(function(it) {
                return it.id === itemId;
            });
            if (!item) {
                return;
            }
            if (!self.isItemInteractive(item)) {
                return;
            }

            var modalEl = document.getElementById('reschedule-date-modal');
            if (!modalEl) {
                return;
            }

            var idInput = document.getElementById('date-modal-itemid');
            var titleEl = document.getElementById('date-modal-activity-title');
            var typeBadge = document.getElementById('date-modal-badge-type');
            var durBadge = document.getElementById('date-modal-badge-duration');
            var startInput = document.getElementById('date-modal-start');
            var endInput = document.getElementById('date-modal-end');
            var errorEl = document.getElementById('date-modal-error');

            if (idInput) {
                idInput.value = item.id;
            }
            if (titleEl) {
                titleEl.textContent = item.title;
            }
            if (typeBadge) {
                typeBadge.textContent = item.typelabel || (item.issubtype ? 'Phase' : 'Activity');
            }
            if (durBadge) {
                durBadge.textContent = formatDuration(item.dateend - item.datestart);
            }
            if (startInput) {
                startInput.value = timestampToDateTimeLocal(item.datestart);
                startInput.classList.remove('is-invalid');
            }
            if (endInput) {
                endInput.value = timestampToDateTimeLocal(item.dateend);
                endInput.classList.remove('is-invalid');
            }
            if (errorEl) {
                errorEl.classList.add('d-none');
            }

            if (window.bootstrap && window.bootstrap.Modal) {
                var modal = typeof window.bootstrap.Modal.getOrCreateInstance === 'function' ?
                    window.bootstrap.Modal.getOrCreateInstance(modalEl) :
                    (window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl));
                modal.show();
            } else if (window.jQuery && typeof window.jQuery(modalEl).modal === 'function') {
                window.jQuery(modalEl).modal('show');
            } else {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                modalEl.removeAttribute('aria-hidden');
                modalEl.setAttribute('aria-modal', 'true');
                if (!document.getElementById('reschedule-modal-backdrop')) {
                    var backdrop = document.createElement('div');
                    backdrop.id = 'reschedule-modal-backdrop';
                    backdrop.className = 'modal-backdrop fade show';
                    document.body.appendChild(backdrop);
                }
            }

            setTimeout(function() {
                if (fieldToFocus === 'end' && endInput) {
                    endInput.focus();
                } else if (startInput) {
                    startInput.focus();
                }
            }, 250);
        },

        /**
         * Close the date and time editing modal.
         */
        closeDateModal: function() {
            var modalEl = document.getElementById('reschedule-date-modal');
            if (!modalEl) {
                return;
            }
            if (window.bootstrap && window.bootstrap.Modal) {
                var modal = window.bootstrap.Modal.getInstance(modalEl);
                if (modal) {
                    modal.hide();
                }
            } else if (window.jQuery && typeof window.jQuery(modalEl).modal === 'function') {
                window.jQuery(modalEl).modal('hide');
            } else {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
                var backdrop = document.getElementById('reschedule-modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
            }
        },

        /**
         * Apply changes made in the date and time editing modal.
         */
        applyDateModalChanges: function() {
            var self = this;
            var idInput = document.getElementById('date-modal-itemid');
            var startInput = document.getElementById('date-modal-start');
            var endInput = document.getElementById('date-modal-end');
            var errorEl = document.getElementById('date-modal-error');

            if (!idInput || !startInput || !endInput) {
                return;
            }

            var itemId = idInput.value;
            var item = self.items.find(function(it) {
                return it.id === itemId;
            });
            if (!item) {
                return;
            }
            if (!self.isItemInteractive(item)) {
                return;
            }

            var newStart = dateTimeLocalToTimestamp(startInput.value);
            var newEnd = dateTimeLocalToTimestamp(endInput.value);

            var parent = self.getParentItem(item);
            if (parent && !parent.derived) {
                newStart = Math.max(newStart, Number(parent.datestart));
                newEnd = Math.min(newEnd, Number(parent.dateend));
                startInput.value = timestampToDateTimeLocal(newStart);
                endInput.value = timestampToDateTimeLocal(newEnd);
            }

            if (!newStart || !newEnd || newEnd <= newStart) {
                if (errorEl) {
                    errorEl.classList.remove('d-none');
                }
                var errorText = document.getElementById('date-modal-error-text');
                if (errorText && parent && !parent.derived) {
                    errorText.textContent = (self.strings && self.strings.subactivityparentbounds) ||
                        'The subactivity must remain within the parent activity timeframe.';
                }
                if (endInput) {
                    endInput.classList.add('is-invalid');
                }
                return;
            }

            var oldStart = item.datestart;
            var oldEnd = item.dateend;
            var startChanged = newStart !== oldStart;
            var endChanged = newEnd !== oldEnd;

            item.datestart = newStart;
            item.dateend = newEnd;
            if (startChanged) {
                item.startenabled = true;
            }
            if (endChanged) {
                item.endenabled = true;
            }

            var cStart = self.getTimelineStart();
            var totalSec = Math.max(3600, self.getTimelineEnd() - cStart);

            // If item is a parent activity, proportionally scale its subactivities
            var children = self.items.filter(function(it) {
                    return it.parentkey === item.id && self.isItemEditable(it);
            });

            if (children.length > 0) {
                var pInitDur = Math.max(1, oldEnd - oldStart);
                var pNewDur = Math.max(1, newEnd - newStart);

                children.forEach(function(c) {
                    var rStart = (c.datestart - oldStart) / pInitDur;
                    var rEnd = (c.dateend - oldStart) / pInitDur;

                    var newCStart = newStart + Math.round(rStart * pNewDur);
                    var newCEnd = newStart + Math.round(rEnd * pNewDur);

                    var step = (pNewDur >= 7200) ? 3600 : 60;
                    newCStart = Math.round(newCStart / step) * step;
                    newCEnd = Math.round(newCEnd / step) * step;

                    if (c.datestart === oldStart) {
                        newCStart = newStart;
                    }
                    if (c.dateend === oldEnd) {
                        newCEnd = newEnd;
                    }

                    if (newCEnd > newEnd) {
                        newCEnd = newEnd;
                    }
                    if (newCStart < newStart) {
                        newCStart = newStart;
                    }
                    if (newCEnd <= newCStart) {
                        newCEnd = Math.min(newEnd, newCStart + step);
                        if (newCEnd <= newCStart) {
                            newCStart = Math.max(newStart, newCEnd - step);
                        }
                    }

                    c.datestart = newCStart;
                    c.dateend = newCEnd;

                    var cBar = self.board.querySelector('.quest-calendar-bar[data-itemid="' + c.id + '"]');
                    if (cBar) {
                        var cSFrac = Math.max(0, Math.min(1, (c.datestart - cStart) / totalSec));
                        var cEFrac = Math.max(0, Math.min(1, (c.dateend - cStart) / totalSec));
                        var cWFrac = Math.max(0.005, cEFrac - cSFrac);
                        cBar.style.left = (cSFrac * 100) + '%';
                        cBar.style.width = (cWFrac * 100) + '%';
                        self.updateBarOverflow(cBar, c, totalSec, cStart);
                    }
                    self.updateTableRow(c);
                });
            }

            // Update bar element for this item
            var bar = self.board.querySelector('.quest-calendar-bar[data-itemid="' + item.id + '"]');
            if (bar) {
                var sFrac = Math.max(0, Math.min(1, (newStart - cStart) / totalSec));
                var eFrac = Math.max(0, Math.min(1, (newEnd - cStart) / totalSec));
                var wFrac = Math.max(0.005, eFrac - sFrac);
                bar.style.left = (sFrac * 100) + '%';
                bar.style.width = (wFrac * 100) + '%';
                self.updateBarOverflow(bar, item, totalSec, cStart);
            }

            self.updateTableRow(item);
            if (item.derived) {
                self.refreshDerivedItem(item);
            } else {
                self.refreshDerivedParent(item);
            }
            self.markDirty();
            self.closeDateModal();
            self.scrollToTableRow(item.id);
        },

        /**
         * Toggle subtasks visibility for a parent activity.
         *
         * @param {string} parentKey Parent item ID.
         */
        toggleParent: function(parentKey) {
            var self = this;
            self.expandedParents = self.expandedParents || {};
            var isNowExpanded = !self.expandedParents[parentKey];
            self.expandedParents[parentKey] = isNowExpanded;

            // 1. Toggle table rows.
            var tableRows = document.querySelectorAll('tr[data-parentkey="' + parentKey + '"]');
            tableRows.forEach(function(row) {
                if (isNowExpanded) {
                    row.classList.remove('d-none');
                } else {
                    row.classList.add('d-none');
                }
            });

            // 2. Toggle Gantt left column rows.
            var leftRows = self.board.querySelectorAll('.quest-left-row[data-parentkey="' + parentKey + '"]');
            leftRows.forEach(function(row) {
                if (isNowExpanded) {
                    row.classList.remove('d-none');
                } else {
                    row.classList.add('d-none');
                }
            });

            // 3. Toggle Gantt right area lanes.
            var lanes = self.board.querySelectorAll('.quest-timeline-lane[data-parentkey="' + parentKey + '"]');
            lanes.forEach(function(lane) {
                if (isNowExpanded) {
                    lane.classList.remove('d-none');
                } else {
                    lane.classList.add('d-none');
                }
            });

            // 4. Update toggle button icons in both table and Gantt.
            var btnSelector = '.toggle-subtasks[data-parentkey="' + parentKey + '"], ' +
                '.quest-toggle-subtasks[data-parentkey="' + parentKey + '"]';
            var buttons = document.querySelectorAll(btnSelector);
            buttons.forEach(function(btn) {
                btn.setAttribute('aria-expanded', isNowExpanded ? 'true' : 'false');
                var icon = btn.querySelector('i.fa');
                if (icon) {
                    icon.className = isNowExpanded ? 'fa fa-minus' : 'fa fa-plus';
                }
            });
        },

        /**
         * Trigger autosequencing modal.
         */
        autoSequence: function() {
            this.openAutoSequenceModal();
        },

        /**
         * Auto-sequence activities according to the selected strategy.
         * Top-level items share course duration. Subtasks/phases are distributed within parent duration.
         *
         * @param {string} strategy 'equal', 'sequential', 'proportional', or 'relative'
         */
        applyAutoSequence: function(strategy) {
            var self = this;

            if (strategy === 'relative') {
                self.applyRelativeSequence();
                return;
            }

            var cStart = self.config.courseStart;
            var cEnd = self.config.courseEnd;
            var totalCourseSec = Math.max(3600, cEnd - cStart);

            // Separate main items from subtypes.
            var mainItems = self.items.filter(function(it) {
                return !it.issubtype;
            });

            if (!mainItems.length) {
                return;
            }

            if (strategy === 'equal') {
                // Strategy 1: Equal division across course timeframe
                var slotDur = Math.max(3600, Math.floor(totalCourseSec / mainItems.length));
                var curTime = cStart;

                mainItems.forEach(function(it, idx) {
                    it.datestart = curTime;
                    it.dateend = (idx === mainItems.length - 1) ? cEnd : Math.min(cEnd, curTime + slotDur);
                    curTime = it.dateend;
                });
            } else if (strategy === 'sequential') {
                // Strategy 2: Sequential chaining preserving original durations
                var seqTime = cStart;
                mainItems.forEach(function(it) {
                    var dur = Math.max(3600, it.dateend - it.datestart);
                    it.datestart = seqTime;
                    it.dateend = seqTime + dur;
                    seqTime = it.dateend;
                });
            } else {
                // Strategy 3: Proportional bounded (clamped to prevent massive outlier domination)
                var rawDurs = mainItems.map(function(it) {
                    return Math.max(3600, it.dateend - it.datestart);
                });
                var avgDur = rawDurs.reduce(function(a, b) {
                    return a + b;
                }, 0) / rawDurs.length;
                // Cap single item at 3x average or 40% of course duration
                var maxAllowedDur = Math.max(3600 * 24, Math.min(avgDur * 3, totalCourseSec * 0.4));
                var cappedDurs = rawDurs.map(function(d) {
                    return Math.min(d, maxAllowedDur);
                });
                var totalCapped = cappedDurs.reduce(function(a, b) {
                    return a + b;
                }, 0);

                var propTime = cStart;
                mainItems.forEach(function(it, idx) {
                    var weight = totalCapped > 0 ? (cappedDurs[idx] / totalCapped) : (1 / mainItems.length);
                    var dur = Math.max(3600, Math.round(weight * totalCourseSec));
                    it.datestart = propTime;
                    it.dateend = (idx === mainItems.length - 1) ? cEnd : Math.min(cEnd, propTime + dur);
                    propTime = it.dateend;
                });
            }

            // Distribute subtasks/phases within their respective parent activity timeframe.
            mainItems.forEach(function(parent) {
                var children = self.items.filter(function(it) {
                    return it.parentkey === parent.id && self.isItemEditable(it);
                });

                if (!children.length) {
                    return;
                }

                var pStart = parent.datestart;
                var pEnd = parent.dateend;
                var pDur = Math.max(3600 * children.length, pEnd - pStart);

                if (strategy === 'equal') {
                    var childSlot = Math.max(3600, Math.floor(pDur / children.length));
                    var cTime = pStart;
                    children.forEach(function(child, cIdx) {
                        child.datestart = cTime;
                        child.dateend = (cIdx === children.length - 1) ? pEnd : Math.min(pEnd, cTime + childSlot);
                        cTime = child.dateend;
                    });
                } else {
                    // Sequential / proportional: scale child durations within parent window
                    var totalChildDur = children.reduce(function(acc, c) {
                        return acc + Math.max(3600, c.dateend - c.datestart);
                    }, 0);

                    var childCurTime = pStart;
                    children.forEach(function(child, cIdx) {
                        if (cIdx === children.length - 1) {
                            child.datestart = childCurTime;
                            child.dateend = pEnd;
                        } else {
                            var origDur = Math.max(3600, child.dateend - child.datestart);
                            var childDur = (totalChildDur > 0) ?
                                Math.round((origDur / totalChildDur) * pDur) :
                                Math.floor(pDur / children.length);
                            childDur = Math.max(3600, childDur);
                            child.datestart = childCurTime;
                            child.dateend = Math.min(pEnd - (children.length - 1 - cIdx) * 3600, childCurTime + childDur);
                            childCurTime = child.dateend;
                        }
                    });
                }
            });

            self.renderTimeline();
            self.items.forEach(function(it) {
                self.updateTableRow(it);
            });
            self.markDirty();
        },

        /**
         * Rebase all editable activities to the current course timeframe.
         *
         * A single affine transformation is applied to every item, so gaps,
         * durations and parent/child positions retain their relative values.
         */
        applyRelativeSequence: function() {
            var self = this;
            var courseStart = Number(self.config.courseStart);
            var courseEnd = Number(self.config.courseEnd);
            var courseDuration = Math.max(3600, courseEnd - courseStart);
            var datedItems = self.items.filter(function(item) {
                return Number(item.dateend) > Number(item.datestart);
            });
            var transformableItems = datedItems.filter(function(item) {
                return self.isItemEditable(item) || item.derived;
            });

            if (!transformableItems.length) {
                return;
            }

            var originalStart = Math.min.apply(null, datedItems.map(function(item) {
                return Number(item.datestart);
            }));
            var originalEnd = Math.max.apply(null, datedItems.map(function(item) {
                return Number(item.dateend);
            }));
            var originalDuration = originalEnd - originalStart;

            if (originalDuration <= 0) {
                return;
            }

            var scale = courseDuration / originalDuration;
            var changed = false;

            transformableItems.forEach(function(item) {
                var oldStart = Number(item.datestart);
                var oldEnd = Number(item.dateend);
                var newStart = Math.round(courseStart + (oldStart - originalStart) * scale);
                var newEnd = Math.round(courseStart + (oldEnd - originalStart) * scale);

                // Rounding must not turn a valid interval into a zero-length one.
                if (newEnd <= newStart) {
                    newEnd = newStart + 1;
                }

                if (oldStart !== newStart || oldEnd !== newEnd) {
                    changed = true;
                }
                item.datestart = newStart;
                item.dateend = newEnd;
            });

            if (!changed) {
                return;
            }

            self.renderTimeline();
            self.items.forEach(function(item) {
                self.updateTableRow(item);
            });
            self.markDirty();
        },

        /**
         * Save schedule via AJAX.
         */
        saveSchedule: function() {
            var self = this;
            if (!self.isDirty) {
                return;
            }

            var changedItems = self.items.filter(function(item) {
                if (!self.isItemEditable(item) && !item.availabilityeditable) {
                    return false;
                }
                var initial = self.initialItems.find(function(original) {
                    return String(original.id) === String(item.id);
                });
                var initialStart = initial && initial.startenabled === false ? 0 :
                    (initial ? Number(initial.datestart) : 0);
                var initialEnd = initial && initial.endenabled === false ? 0 :
                    (initial ? Number(initial.dateend) : 0);
                var itemStart = item.startenabled === false ? 0 : Number(item.datestart);
                var itemEnd = item.endenabled === false ? 0 : Number(item.dateend);
                return !initial || itemStart !== initialStart || itemEnd !== initialEnd ||
                    (initial.startenabled !== false) !== (item.startenabled !== false) ||
                    (initial.endenabled !== false) !== (item.endenabled !== false) ||
                    self.hasAvailabilityChanges(item, initial);
            });
            if (!changedItems.length) {
                self.isDirty = false;
                self.saveBtn.disabled = true;
                if (self.unsavedAlert) {
                    self.unsavedAlert.classList.add('invisible');
                }
                Notification.addNotification({
                    message: (self.strings && self.strings.noschedulablechanges) ||
                        'There are no editable schedule changes to save.',
                    type: 'warning'
                });
                return;
            }

            var originalHtml = self.saveBtn.innerHTML;
            self.saveBtn.disabled = true;
            self.saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Saving...';

            var payload = {
                courseid: self.config.courseid,
                sesskey: self.config.sesskey,
                items: changedItems.map(function(it) {
                    var initial = self.initialItems.find(function(original) {
                        return String(original.id) === String(it.id);
                    });
                    var payloadItem = {
                        id: it.id,
                        datestart: it.datestart,
                        dateend: it.dateend,
                        startenabled: it.startenabled !== false,
                        endenabled: it.endenabled !== false
                    };
                    if (self.hasAvailabilityChanges(it, initial)) {
                        payloadItem.availabilityconditions = (it.availabilityconditions || []).map(function(condition) {
                            return {
                                id: condition.id,
                                direction: condition.direction,
                                time: Number(condition.time)
                            };
                        });
                    }
                    return payloadItem;
                })
            };

            fetch(self.config.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(function(res) {
                return res.text().then(function(text) {
                    var json = null;
                    try {
                        json = JSON.parse(text);
                    } catch (e) {
                        // Not valid JSON.
                    }

                    if (!json) {
                        var snippet = text ? text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim() : '';
                        if (snippet.length > 200) {
                            snippet = snippet.substring(0, 200) + '...';
                        }
                        throw new Error('Server returned HTTP ' + res.status + (snippet ? ': ' + snippet : ''));
                    }
                    return json;
                });
            })
            .then(function(data) {
                self.saveBtn.innerHTML = originalHtml;
                if (data && data.success) {
                    self.initialItems = self.items.map(function(it) {
                        return JSON.parse(JSON.stringify(it));
                    });
                    self.isDirty = false;
                    self.saveBtn.disabled = true;
                    if (self.unsavedAlert) {
                        self.unsavedAlert.classList.add('invisible');
                    }
                    var successMsg = data.message ||
                        (self.strings && self.strings.schedulesaved) ||
                        'Schedule successfully saved.';
                    Notification.addNotification({
                        message: successMsg,
                        type: data.warnings && data.warnings.length ? 'warning' : 'success'
                    });
                } else {
                    self.saveBtn.disabled = false;
                    var headerText = (self.strings && self.strings.error_saving_header) ?
                        self.strings.error_saving_header : 'Could not save schedule';
                    var defaultError = (self.strings && self.strings.error_saving) ?
                        self.strings.error_saving : 'An error occurred while saving the schedule.';

                    var errorList = [];
                    if (data && data.errors && Array.isArray(data.errors) && data.errors.length > 0) {
                        errorList = data.errors;
                    } else if (data && data.message) {
                        errorList = [data.message];
                    } else if (data && data.error) {
                        errorList = [data.error];
                    } else if (data && data.errorcode) {
                        errorList = ['Error code: ' + data.errorcode];
                    } else {
                        errorList = [defaultError];
                    }

                    var html = '<div><strong>' + self.escapeHtml(headerText) + '</strong>';
                    if (errorList.length === 1) {
                        html += '<div class="mt-1">' + self.escapeHtml(errorList[0]) + '</div>';
                    } else {
                        html += '<ul class="mb-0 mt-1 ps-3">';
                        errorList.forEach(function(msg) {
                            html += '<li>' + self.escapeHtml(msg) + '</li>';
                        });
                        html += '</ul>';
                    }
                    if (data && data.debuginfo) {
                        html += '<div class="text-muted smaller mt-1 font-monospace">' + self.escapeHtml(data.debuginfo) + '</div>';
                    }
                    html += '</div>';

                    Notification.addNotification({
                        message: html,
                        type: 'error'
                    });
                }
            })
            .catch(function(err) {
                self.saveBtn.innerHTML = originalHtml;
                self.saveBtn.disabled = false;
                var headerText = (self.strings && self.strings.error_saving_header) ?
                    self.strings.error_saving_header : 'Could not save schedule';
                var errText = (err && err.message) ? err.message : String(err);
                var html = '<div><strong>' + self.escapeHtml(headerText) + '</strong>' +
                    '<div class="mt-1">' + self.escapeHtml(errText) + '</div></div>';
                Notification.addNotification({
                    message: html,
                    type: 'error'
                });
            });
        },

        /**
         * Escape HTML string safely.
         *
         * @param {string} str Input string.
         * @return {string} Escaped string.
         */
        escapeHtml: function(str) {
            if (!str) {
                return '';
            }
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };

    return {
        init: function(cfg) {
            RescheduleCalendar.init(cfg);
        }
    };
});
