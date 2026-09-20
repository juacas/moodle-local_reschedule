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
 * English language strings for local_reschedule.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Activity Rescheduler';
$string['reschedule'] = 'Reschedule activities';
$string['rescheduletitle'] = 'Course Activity Scheduling & Timeline';
$string['rescheduledesc'] = 'Drag activities or adjust their start and end dates within the course timeframe.';
$string['mapping'] = 'Activity date mapping rules';
$string['mapping_desc'] = 'Define the table name, title column, type label, start date column, and end date column (one per line). Subtypes/phases must start with a leading dash (-). Optional 6th field specifies a foreign key for child tables.';
$string['autosequence'] = 'Auto-sequence';
$string['resetschedule'] = 'Reset schedule';
$string['unsavedchanges'] = 'You have unsaved changes.';
$string['schedulesaved'] = 'Activity schedule successfully saved.';
$string['error_saving'] = 'An error occurred while saving the schedule.';
$string['error_saving_header'] = 'Could not save schedule';
$string['error_saving_session'] = 'Your session has expired. Please refresh the page and log in again.';
$string['error_saving_permission'] = 'You do not have permission to manage activity schedules in this course.';
$string['error_saving_missing_params'] = 'Required parameters are missing (courseid or sesskey).';
$string['error_saving_network'] = 'Network error or server unavailable.';
$string['durationdays'] = '{$a} days';
$string['durationhours'] = '{$a} hours';
$string['noactivitiesfound'] = 'No activities with scheduled dates found in this course.';
$string['activity'] = 'Activity';
$string['type'] = 'Type';
$string['datestart'] = 'Start Date';
$string['dateend'] = 'End Date';
$string['duration'] = 'Duration';
$string['schedulelegend'] = 'Timeline legend';
$string['mainactivity'] = 'Main activity';
$string['subtypeactivity'] = 'Phase / Subtype';
$string['course'] = 'Course';
$string['coursetimerange'] = 'Course timeframe';
$string['activities'] = 'Activities';
$string['togglesubtasks'] = 'Expand / collapse subtasks';
$string['autosequencetitle'] = 'Auto-sequence activities';
$string['choosestrategy'] = 'Select sequencing strategy';
$string['apply'] = 'Apply';
$string['strategy_equal'] = 'Equal distribution';
$string['strategy_equal_short'] = 'Distribute course duration equally';
$string['strategy_equal_desc'] = 'Divides the total course duration equally among all top-level activities. Subtasks and phases are distributed evenly within their parent activity timeframe.';
$string['strategy_equal_tip'] = 'Ideal for modular courses or standard weekly teaching schedules.';
$string['strategy_sequential'] = 'Sequential chaining';
$string['strategy_sequential_short'] = 'Chain activities preserving current duration';
$string['strategy_sequential_desc'] = 'Places activities one after another consecutively without gaps or overlaps, keeping each activity\'s configured duration starting from the course start date.';
$string['strategy_sequential_tip'] = 'Best when activities already have their planned duration (e.g. 1 week each) and you just want to arrange them in order.';
$string['strategy_proportional'] = 'Bounded proportional';
$string['strategy_proportional_short'] = 'Proportional distribution with outlier limits';
$string['strategy_proportional_desc'] = 'Scales current activity durations to span the entire course while preventing extreme dates (e.g. multi-year deadlines) from dominating the timeline.';
$string['strategy_proportional_tip'] = 'Useful when some activities require more time than others and need to fit the course timeframe.';
$string['strategy_relative'] = 'Relative scaling';
$string['strategy_relative_short'] = 'Fit the current timeline to the course timeframe';
$string['strategy_relative_desc'] = 'Applies the same shift and scale to every editable activity, preserving the current gaps, relative durations and positions while fitting the whole timeline to the course timeframe.';
$string['strategy_relative_tip'] = 'Use this after changing the course start or end date when the existing temporal structure should remain intact.';
$string['activitybeforetimeline'] = 'The activity starts before the visible timeline.';
$string['activityaftertimeline'] = 'The activity ends after the visible timeline.';
$string['activitystartdisabled'] = 'The start date is disabled.';
$string['activityenddisabled'] = 'The end date is disabled.';
$string['kuetactivitynoteditable'] = 'The KUET timeframe is calculated from its sessions. Edit programmed sessions to reschedule it.';
$string['kuetactivityderivedhint'] = 'Move or resize this activity to move or resize its programmed sessions.';
$string['noschedulablechanges'] = 'There are no editable schedule changes to save. KUET dates are stored in programmed sessions.';
$string['subactivityparentbounds'] = 'The subactivity must remain within the parent activity timeframe.';
$string['close'] = 'Close';
$string['editdatesmodal'] = 'Edit activity dates';
$string['editdatesmodaltip'] = 'Manual changes will update the timeline and table automatically.';
$string['invaliddateorder'] = 'The end date must be later than the start date.';
$string['clicktoeditdate'] = 'Click to edit date and time';
$string['timeclose'] = 'Time Close cannot be less than Time Open';
$string['assesstimefinish'] = 'Time To cannot be less than Time From';
$string['assesstimefrom'] = 'Rate items posted From';
$string['assesstimeto'] = 'Rate items posted To';
$string['closedate'] = 'Close date cannot be less than Open Date';
$string['deadline'] = 'Deadline cannot be less than Available From';
$string['dependentdate'] = 'The to and from dates must both be set or both must be disabled';
$string['timedue'] = 'Time Due cannot be less than Time available';
$string['timeend'] = 'Prevent from cannot be less than Allow From';
$string['noteditable'] = 'This activity cannot be edited';
$string['kuetsessionnoteditable'] = 'This manual KUET session cannot be edited from the rescheduler.';
