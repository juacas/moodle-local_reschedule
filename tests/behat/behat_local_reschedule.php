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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Behat steps for local_reschedule.
 *
 * @package    local_reschedule
 * @category   test
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Prefer the active Moodle checkout when this plugin is reached through a symlink.
// Behat contexts may also be discovered before Moodle's config.php is available.
if (interface_exists('behat_session_interface')) {
    $behatinterface = new ReflectionClass('behat_session_interface');
    $moodleroot = dirname($behatinterface->getFileName(), 4);
    require_once($moodleroot . '/lib/behat/behat_base.php');
} else if (!empty($GLOBALS['CFG']->dirroot)) {
    require_once($GLOBALS['CFG']->dirroot . '/lib/behat/behat_base.php');
} else {
    require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');
}

/**
 * Navigation steps which resolve generated records by stable names.
 *
 * @package    local_reschedule
 * @category   test
 * @copyright  2026 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_reschedule extends behat_base {
    /**
     * Opens the read-only rescheduler for a course.
     *
     * @Given /^I open the rescheduler for course "([^"]*)"$/
     * @param string $shortname Course short name.
     */
    public function i_open_the_rescheduler_for_course(string $shortname): void {
        $course = $this->get_course($shortname);
        $this->visit_local_page('/local/reschedule/index.php', ['id' => $course->id]);
    }

    /**
     * Opens the rescheduler with editing forced for a course.
     *
     * @Given /^I open the editable rescheduler for course "([^"]*)"$/
     * @param string $shortname Course short name.
     */
    public function i_open_the_editable_rescheduler_for_course(string $shortname): void {
        $course = $this->get_course($shortname);
        $this->visit_local_page('/local/reschedule/index.php', [
            'id' => $course->id,
            'editmode' => 1,
        ]);
    }

    /**
     * Opens the rescheduler in scoped edit mode for one course activity.
     *
     * @Given /^I open the scoped rescheduler for activity "([^"]*)" in course "([^"]*)"$/
     * @param string $activityname Activity name.
     * @param string $shortname Course short name.
     */
    public function i_open_the_scoped_rescheduler_for_activity_in_course(
        string $activityname,
        string $shortname
    ): void {
        $course = $this->get_course($shortname);
        $cm = $this->get_course_module($course, $activityname);
        $this->visit_local_page('/local/reschedule/index.php', [
            'id' => $course->id,
            'cmid' => $cm->id,
            'editmode' => 2,
        ]);
    }

    /**
     * Opens the cross-course workload report for a course.
     *
     * @Given /^I open the workload report for course "([^"]*)"$/
     * @param string $shortname Course short name.
     */
    public function i_open_the_workload_report_for_course(string $shortname): void {
        $course = $this->get_course($shortname);
        $this->visit_local_page('/local/reschedule/globalreport.php', ['id' => $course->id]);
    }

    /**
     * Verifies that opening the rescheduler directly raises a permission exception.
     *
     * The error page is inspected and then replaced before the step finishes. Moodle's
     * after-step exception detector therefore sees the safe course page while this step
     * still verifies the original exception and its error code.
     *
     * @When /^I try to open the rescheduler for course "([^"]*)" and a permission exception is returned$/
     * @param string $shortname Course short name.
     */
    public function i_try_to_open_the_rescheduler_and_a_permission_exception_is_returned(string $shortname): void {
        $course = $this->get_course($shortname);
        $this->assert_permission_exception('/local/reschedule/index.php', ['id' => $course->id], $course);
    }

    /**
     * Verifies that opening the workload report directly raises a permission exception.
     *
     * @When /^I try to open the workload report for course "([^"]*)" and a permission exception is returned$/
     * @param string $shortname Course short name.
     */
    public function i_try_to_open_the_workload_report_and_a_permission_exception_is_returned(string $shortname): void {
        $course = $this->get_course($shortname);
        $this->assert_permission_exception('/local/reschedule/globalreport.php', ['id' => $course->id], $course);
    }

    /**
     * Finds a generated course by short name.
     *
     * @param string $shortname Course short name.
     * @return stdClass
     */
    private function get_course(string $shortname): stdClass {
        global $DB;

        return $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
    }

    /**
     * Finds a course module by its displayed name.
     *
     * @param stdClass $course Course record.
     * @param string $activityname Activity name.
     * @return cm_info
     */
    private function get_course_module(stdClass $course, string $activityname): cm_info {
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            if ($cm->name === $activityname) {
                return $cm;
            }
        }

        throw new RuntimeException("Activity '{$activityname}' was not found in course '{$course->shortname}'.");
    }

    /**
     * Visits a plugin page using Behat's configured base URL.
     *
     * @param string $path Site-relative path.
     * @param array $params Query parameters.
     */
    private function visit_local_page(string $path, array $params): void {
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $this->getSession()->visit($this->locate_path($path . '?' . $query));
    }

    /**
     * Opens an endpoint, asserts its Moodle permission exception, and leaves a safe page loaded.
     *
     * @param string $path Site-relative endpoint.
     * @param array $params Query parameters.
     * @param stdClass $course Course record used for the safe destination.
     */
    private function assert_permission_exception(string $path, array $params, stdClass $course): void {
        $this->visit_local_page($path, $params);

        $page = $this->getSession()->getPage();
        $fatalerror = $page->find('xpath', "//div[@data-rel='fatalerror']");
        $pagetext = $page->getText();
        $haspermissionmessage = strpos($pagetext, 'Sorry, but you do not currently have permissions to do that') !== false;
        $haserrorcode = strpos($pagetext, 'nopermissions') !== false;

        // Leave the exception page before Moodle's automatic after-step check runs.
        $this->visit_local_page('/course/view.php', ['id' => $course->id]);

        if (!$fatalerror || !$haspermissionmessage || !$haserrorcode) {
            throw new RuntimeException('Expected a Moodle nopermissions exception, but the endpoint did not return it.');
        }
    }
}
