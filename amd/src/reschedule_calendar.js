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
        locale: undefined,

        /**
         * Initialize calendar timeline.
         *
         * @param {Object} cfg Configuration object from PHP.
         */
        init: function(cfg) {
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

            this.items = (rawItems || []).map(function(item) {
                return Object.assign({}, item);
            });
            this.initialItems = this.items.map(function(item) {
                return Object.assign({}, item);
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
            var cStart = self.config.courseStart;
            var cEnd = self.config.courseEnd;
            var totalSec = Math.max(3600, cEnd - cStart);

            var bands = self.calculateBands(cStart, cEnd);

            var baseCount = Math.max(12, bands.b3.length);
            var wrapperWidth = self.wrapper ? (self.wrapper.clientWidth - 280) : 1000;
            self.trackWidthPx = Math.max(wrapperWidth, baseCount * 36);

            var board = document.createElement('div');
            board.className = 'quest-timeline-table d-flex';

            // Left column: Sticky activity titles
            var leftCol = document.createElement('div');
            leftCol.className = 'quest-timeline-left-column flex-shrink-0';
            leftCol.style.width = '280px';

            var leftHeader = document.createElement('div');
            leftHeader.className = 'quest-left-header p-2 border-bottom border-end d-flex flex-column justify-content-center';
            leftHeader.innerHTML = '<div class="fw-bold small text-dark"><i class="fa fa-tasks me-1 text-primary"></i> ' +
                self.items.length + ' Items</div><div class="smaller text-muted">Drag bars to adjust schedule</div>';
            leftCol.appendChild(leftHeader);

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
            rightArea.appendChild(bandsHeader);

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
                rightArea.appendChild(lane);
            });

            board.appendChild(rightArea);
            self.board.innerHTML = '';
            self.board.appendChild(board);
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
                (this.isItemEditable(item) ? '' : ' is-disabled');
            if (!this.isItemEditable(item)) {
                bar.setAttribute('aria-disabled', 'true');
            }
            bar.setAttribute('data-itemid', item.id);

            var sFrac = Math.max(0, Math.min(1, (item.datestart - cStart) / totalSec));
            var eFrac = Math.max(0, Math.min(1, (item.dateend - cStart) / totalSec));
            var wFrac = Math.max(0.005, eFrac - sFrac);

            bar.style.left = (sFrac * 100) + '%';
            bar.style.width = (wFrac * 100) + '%';

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
         * Bind drag events.
         */
        bindGlobalEvents: function() {
            var self = this;

            self.board.addEventListener('pointerdown', function(e) {
                var bar = e.target.closest('.quest-calendar-bar');
                if (!bar) {
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
                if (!self.isItemEditable(item)) {
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

                self.activeDrag = {
                    item: item,
                    barEl: bar,
                    mode: mode,
                    startX: e.clientX,
                    trackRect: trackRect,
                    initialStart: item.datestart,
                    initialEnd: item.dateend,
                    duration: Math.max(1, item.dateend - item.datestart),
                    children: childSnapshots,
                    totalSec: Math.max(3600, self.config.courseEnd - self.config.courseStart)
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
                var minDur = 3600;

                var newStart = drag.item.datestart;
                var newEnd = drag.item.dateend;

                if (drag.mode === 'move') {
                    var minShift = cStart - drag.initialStart;
                    var maxShift = cEnd - drag.initialEnd;
                    if (drag.children && drag.children.length > 0) {
                        drag.children.forEach(function(c) {
                            minShift = Math.max(minShift, cStart - c.initialStart);
                            maxShift = Math.min(maxShift, cEnd - c.initialEnd);
                        });
                    }
                    if (minShift > maxShift) {
                        minShift = cStart - drag.initialStart;
                        maxShift = cEnd - drag.initialEnd;
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
                                var cSFrac = Math.max(0, Math.min(1, (c.item.datestart - cStart) / drag.totalSec));
                                var cEFrac = Math.max(0, Math.min(1, (c.item.dateend - cStart) / drag.totalSec));
                                var cWFrac = Math.max(0.005, cEFrac - cSFrac);
                                c.barEl.style.left = (cSFrac * 100) + '%';
                                c.barEl.style.width = (cWFrac * 100) + '%';
                            }
                            self.updateTableRow(c.item);
                        });
                    }
                } else if (drag.mode === 'resize-start') {
                    newStart = Math.min(drag.initialEnd - minDur, Math.max(cStart, drag.initialStart + dt));
                    newEnd = drag.initialEnd;
                } else if (drag.mode === 'resize-end') {
                    newStart = drag.initialStart;
                    newEnd = Math.max(drag.initialStart + minDur, Math.min(cEnd, drag.initialEnd + dt));
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
                            var cSFrac = Math.max(0, Math.min(1, (newCStart - cStart) / drag.totalSec));
                            var cEFrac = Math.max(0, Math.min(1, (newCEnd - cStart) / drag.totalSec));
                            var cWFrac = Math.max(0.005, cEFrac - cSFrac);
                            c.barEl.style.left = (cSFrac * 100) + '%';
                            c.barEl.style.width = (cWFrac * 100) + '%';
                        }
                        self.updateTableRow(c.item);
                    });
                }

                var sFrac = Math.max(0, Math.min(1, (newStart - cStart) / drag.totalSec));
                var eFrac = Math.max(0, Math.min(1, (newEnd - cStart) / drag.totalSec));
                var wFrac = Math.max(0.005, eFrac - sFrac);

                drag.barEl.style.left = (sFrac * 100) + '%';
                drag.barEl.style.width = (wFrac * 100) + '%';

                self.updateHUD(e.clientX, e.clientY, drag.item);
                self.updateTableRow(drag.item);
                self.markDirty();
            });

            document.addEventListener('pointerup', function() {
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

            document.addEventListener('pointercancel', function() {
                self.clickCandidate = null;
                if (!self.activeDrag) {
                    return;
                }
                self.activeDrag.barEl.classList.remove('is-dragging');
                self.activeDrag = null;
                self.hideHUD();
            });
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
            this.hud.style.left = (x + 15) + 'px';
            this.hud.style.top = (y - 50) + 'px';
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

            var startStr = formatDateTime(item.datestart, this.locale);
            var endStr = formatDateTime(item.dateend, this.locale);

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
         * Return whether an item can be rescheduled.
         *
         * @param {Object} item Activity item.
         * @return {boolean}
         */
        isItemEditable: function(item) {
            return !!item && item.editable !== false;
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
                this.unsavedAlert.classList.remove('d-none');
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
                        return Object.assign({}, it);
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
                        self.unsavedAlert.classList.add('d-none');
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
            if (!self.isItemEditable(item)) {
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
            if (!self.isItemEditable(item)) {
                return;
            }

            var newStart = dateTimeLocalToTimestamp(startInput.value);
            var newEnd = dateTimeLocalToTimestamp(endInput.value);

            if (!newStart || !newEnd || newEnd <= newStart) {
                if (errorEl) {
                    errorEl.classList.remove('d-none');
                }
                if (endInput) {
                    endInput.classList.add('is-invalid');
                }
                return;
            }

            var oldStart = item.datestart;
            var oldEnd = item.dateend;

            item.datestart = newStart;
            item.dateend = newEnd;

            var totalSec = Math.max(3600, self.config.courseEnd - self.config.courseStart);
            var cStart = self.config.courseStart;

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
            }

            self.updateTableRow(item);
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
                    icon.className = isNowExpanded ? 'fa fa-minus' : 'fa-plus';
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
         * @param {string} strategy 'equal', 'sequential', or 'proportional'
         */
        applyAutoSequence: function(strategy) {
            var self = this;
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
         * Save schedule via AJAX.
         */
        saveSchedule: function() {
            var self = this;
            if (!self.isDirty) {
                return;
            }

            var originalHtml = self.saveBtn.innerHTML;
            self.saveBtn.disabled = true;
            self.saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Saving...';

            var payload = {
                courseid: self.config.courseid,
                sesskey: self.config.sesskey,
                items: self.items.map(function(it) {
                    return {
                        id: it.id,
                        datestart: it.datestart,
                        dateend: it.dateend
                    };
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
                        return Object.assign({}, it);
                    });
                    self.isDirty = false;
                    self.saveBtn.disabled = true;
                    if (self.unsavedAlert) {
                        self.unsavedAlert.classList.add('d-none');
                    }
                    var successMsg = data.message ||
                        (self.strings && self.strings.schedulesaved) ||
                        'Schedule successfully saved.';
                    Notification.addNotification({
                        message: successMsg,
                        type: 'success'
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
