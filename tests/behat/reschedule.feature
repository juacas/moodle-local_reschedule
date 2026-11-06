@local @local_reschedule
Feature: Access the course rescheduler and workload report
  In order to inspect and change a course schedule safely
  As a user with the appropriate course permissions
  I need the rescheduler to enforce read-only, editable, scoped, and report access

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname          | shortname | format | startdate           | enddate             |
      | Reschedule course | C1        | weeks  | ##2026-11-01 00:00## | ##2027-01-31 23:59## |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | idnumber | name               | allowsubmissionsfromdate | duedate               | cutoffdate            |
      | assign   | C1     | assign1  | Planned assignment | ##2026-11-02 09:00##     | ##2026-11-09 23:59## | ##2026-11-16 23:59## |
      | assign   | C1     | assign2  | Other assignment   | ##2026-11-18 09:00##     | ##2026-11-25 23:59## | ##2026-11-30 23:59## |

  Scenario: A teacher opens the rescheduler in read-only mode by default
    Given I log in as "teacher1"
    When I open the rescheduler for course "C1"
    Then I should see "Course Activity Scheduling & Timeline"
    And I should see "Read-only view. Turn on Edit mode to reschedule activities."
    And I should see "Planned assignment" in the "#reschedule-table" "css_element"
    And "#reschedule-page-root[data-editing='0']" "css_element" should exist

  @javascript
  Scenario: A teacher can force edit mode and the timeline is initialised
    Given I log in as "teacher1"
    When I open the editable rescheduler for course "C1"
    And I wait until the page is ready
    Then "#reschedule-page-root[data-editing='1']" "css_element" should exist
    And I should not see "Read-only view. Turn on Edit mode to reschedule activities."
    And I should see "Planned assignment" in the "#reschedule-timeline-app" "css_element"
    And "#reschedule-timeline-app .quest-timeline-lane[data-itemid]" "css_element" should exist

  Scenario: Scoped edit mode keeps the other course activities visible and read-only
    Given I log in as "teacher1"
    When I open the scoped rescheduler for activity "Planned assignment" in course "C1"
    Then I should see "Scoped edit mode: only \"Planned assignment\" (Assignment) and its subactivities can be changed."
    And I should see "Planned assignment" in the "tr.is-scoped-read-write" "css_element"
    And I should see "Other assignment" in the "tr.is-scoped-read-only" "css_element"

  Scenario: A student cannot open the rescheduler
    Given I log in as "student1"
    When I open the rescheduler for course "C1"
    Then I should see "Sorry, but you do not currently have permissions to do that"

  Scenario: An administrator can open the cross-course workload report
    Given I log in as "admin"
    When I open the workload report for course "C1"
    Then I should see "Student workload report"
    And I should see "Courses included in the report"
    And I should see "Reschedule course"

  Scenario: A teacher without the restricted report capability cannot open it
    Given I log in as "teacher1"
    When I open the workload report for course "C1"
    Then I should see "Sorry, but you do not currently have permissions to do that"
