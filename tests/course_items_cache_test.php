<?php
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_reschedule;

use advanced_testcase;

/**
 * Tests the application cache for extracted course items.
 *
 * @package local_reschedule
 * @copyright 2026 Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_reschedule\course_items_cache
 */
final class course_items_cache_test extends advanced_testcase {
    /**
     * Verify cache variants are separated and invalidated per course.
     *
     * @covers ::get
     * @covers ::set
     * @covers ::invalidate
     * @covers ::invalidate_all
     */
    public function test_course_items_cache_lifecycle(): void {
        $this->resetAfterTest();
        $courseid = 24501;
        $firstvariant = 'first';
        $secondvariant = 'second';
        $firstitems = [['id' => 'activity-a']];
        $seconditems = [['id' => 'activity-b']];

        course_items_cache::invalidate($courseid);
        $this->assertNull(course_items_cache::get($courseid, $firstvariant));

        course_items_cache::set($courseid, $firstvariant, $firstitems);
        course_items_cache::set($courseid, $secondvariant, $seconditems);
        $this->assertSame($firstitems, course_items_cache::get($courseid, $firstvariant));
        $this->assertSame($seconditems, course_items_cache::get($courseid, $secondvariant));

        course_items_cache::invalidate($courseid);
        $this->assertNull(course_items_cache::get($courseid, $firstvariant));
        $this->assertNull(course_items_cache::get($courseid, $secondvariant));

        course_items_cache::set($courseid, $firstvariant, $firstitems);
        course_items_cache::invalidate_all();
        $this->assertNull(course_items_cache::get($courseid, $firstvariant));
    }
}
