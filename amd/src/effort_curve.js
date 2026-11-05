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
 * Shared effort-curve calculations for the course and global timelines.
 *
 * @module     local_reschedule/effort_curve
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    var DAY = 86400;
    var TAIL_DAYS = 2;
    var DAYS_PER_WEEK = 7;

    /**
     * Return the unscaled daily effort at one instant.
     *
     * @param {number} time Unix timestamp in seconds.
     * @param {Object} period Normalized effort period.
     * @return {number}
     */
    function shape(time, period) {
        if (time <= period.start || time > period.tailEnd) {
            return 0;
        }
        if (period.model === 'ideal') {
            var idealEnd = 4 * Math.exp(-4.5) / Math.pow(1 + Math.exp(-4.5), 2) * (1 - Math.exp(-20));
            if (time > period.end) {
                return idealEnd * Math.exp(-4 * (time - period.end) / DAY);
            }
            var u = (time - period.start) / (period.end - period.start);
            var z = Math.exp(-9 * (u - 0.5));
            return 4 * z / Math.pow(1 + z, 2) * (1 - Math.exp(-20 * u));
        }
        if (time <= period.end) {
            var rise = (time - period.start) / (period.end - period.start);
            return Math.expm1(5 * rise) / Math.expm1(5);
        }
        return Math.exp(-4 * (time - period.end) / DAY);
    }

    /**
     * Scale a period so its integral equals its work-hour estimate.
     *
     * @param {Object} period Period with start, end, effort and model.
     * @return {Object}
     */
    function prepare(period) {
        period.peak = period.model === 'ideal' ?
            period.start + (period.end - period.start) / 2 : period.end;
        period.tailEnd = period.end + TAIL_DAYS * DAY;
        var area = 0;
        var boundaries = period.model === 'real' ?
            [period.start, period.end, period.tailEnd] :
            [period.start, period.peak, period.end, period.tailEnd];
        for (var segment = 0; segment < boundaries.length - 1; segment++) {
            var width = (boundaries[segment + 1] - boundaries[segment]) / 64;
            for (var sample = 0; sample < 64; sample++) {
                area += shape(boundaries[segment] + (sample + 0.5) * width, period) * width / DAY;
            }
        }
        period.scale = area > 0 ? period.effort / area : 0;
        return period;
    }

    /**
     * Return weekly work hours contributed by a prepared period.
     *
     * @param {number} time Unix timestamp in seconds.
     * @param {Object} period Prepared effort period.
     * @return {number}
     */
    function weeklyAt(time, period) {
        return shape(time, period) * period.scale * DAYS_PER_WEEK;
    }

    return {
        DAY: DAY,
        TAIL_DAYS: TAIL_DAYS,
        DAYS_PER_WEEK: DAYS_PER_WEEK,
        shape: shape,
        prepare: prepare,
        weeklyAt: weeklyAt
    };
});
