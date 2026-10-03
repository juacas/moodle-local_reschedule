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
$string['effortdrops'] = 'Effort drops';
$string['effortscale'] = 'Weekly effort';
$string['effortweeklyunit'] = 'h/week';
$string['effortsettings'] = 'Effort chart settings';
$string['effortlabel'] = 'Effort';
$string['effortpoints'] = 'pts';
$string['efforttabletitle'] = 'Effort table';
$string['efforttablehelp'] = 'Enter estimated effort hours for each item. One hour equals one effort point. Activities with subactivities show their sum; these totals, adapter-calculated values and point milestones cannot be edited.';
$string['efforttablehours'] = 'Estimated effort (hours)';
$string['efforttablesource'] = 'Source';
$string['efforttableadapter'] = 'Activity adapter';
$string['efforttablechildren'] = 'Sum of subactivities';
$string['efforttabledefault'] = 'Editable estimate';
$string['efforttablemanual'] = 'Edited in this visit';
$string['efforttablemilestone'] = 'Point milestone';
$string['efforttablenotpersisted'] = 'Changes update the Gantt and chart only during this visit. They are lost on page reload; the save dates button does not store them.';
$string['efforttableinvalid'] = 'Enter a non-negative number of hours in every editable row.';
$string['efforttableapply'] = 'Apply effort estimates';
$string['effortmodeltitle'] = 'Daily effort model';
$string['effortmodelchoose'] = 'Choose a model';
$string['effortmodelreal'] = 'Realistic: deadline pressure';
$string['effortmodelrealshort'] = 'Parkinson’s law and Student Syndrome';
$string['effortmodelrealdesc'] = 'Effort starts slowly, rises sharply as the deadline approaches, and peaks at the activity end. A short exponential decay follows. This represents work expanding to fill the available time and a late sense of urgency.';
$string['effortmodelideal'] = 'Ideal: planned pace';
$string['effortmodelidealshort'] = 'S curve with bell-shaped daily effort';
$string['effortmodelidealdesc'] = 'Cumulative work follows an S curve. Daily effort rises, peaks around the midpoint, then eases toward the deadline.';
$string['effortmodelnote'] = 'Both models preserve each dated activity’s assigned effort and include a short exponential residual after its end. Your choice is saved in this browser.';
$string['effortchallengeheading'] = 'The challenge of distributing classroom work over time';
$string['effortchallengeintro'] = 'Assigning a school task opens a window in which two management ideas interact: Parkinson’s law (Parkinson, 1957) and Student Syndrome (Goldratt, 1997). The first describes how work tends to expand to fill the available time; the second, how effort may be postponed until the deadline feels urgent.';
$string['effortchallengeevidence'] = 'Research links distant deadlines to a greater tendency to put tasks off (Steel, 2007). In longitudinal studies of students, procrastination was associated with more stress late in the term and lower grades (Tice and Baumeister, 1997). The exponential rise in this chart is an illustrative model: it does not establish a universal concentration of work in the final 48 hours.';
$string['effortchallengeaction'] = 'To spread effort more healthily, educators can design partial, sequential submissions. Intermediate deadlines can reduce procrastination and improve performance (Ariely and Wertenbroch, 2002); distributed practice also supports retention (Cepeda et al., 2006).';
$string['effortchallengerefs'] = 'References';
$string['rescheduledesc'] = 'Drag activities or adjust their start and end dates within the course timeframe.';
$string['mapping'] = 'Activity date mapping rules';
$string['mapping_desc'] = 'Edit Gantt date rules visually or as JSON. Activities with dedicated adapters are managed in code.';
$string['mappingeditor'] = 'Visual mapping editor';
$string['mappingmodule'] = 'Activity or table';
$string['mappingkind'] = 'Date shape';
$string['mappingrange'] = 'Date range';
$string['mappingmilestone'] = 'Point milestone';
$string['mappingopen'] = 'Start only';
$string['mappingtitlefield'] = 'Title field';
$string['mappinglabel'] = 'Gantt label';
$string['mappingstart'] = 'Start or point field';
$string['mappingend'] = 'End field';
$string['mappingparent'] = 'Parent activity (optional)';
$string['mappingforeignkey'] = 'Parent ID field';
$string['mappingoptional'] = 'Optional date';
$string['mappingdateonly'] = 'Date only';
$string['mappingboundstart'] = 'Constrain to parent start';
$string['mappingboundend'] = 'Constrain to parent end';
$string['mappingavailability'] = 'Availability restrictions';
$string['mappingeffortmodel'] = 'Default effort estimator';
$string['mappingeffortdefault'] = 'Activity adapter default';
$string['mappingeffortfixed'] = 'Fixed hours';
$string['mappingeffortperday'] = 'Hours per day of duration';
$string['mappingefforthours'] = 'Estimated hours';
$string['mappingefforthoursperday'] = 'Hours per day';
$string['mappingeditable'] = 'Allow date editing';
$string['mappingadd'] = 'Add rule';
$string['mappingremove'] = 'Remove';
$string['mappingsuggestions'] = 'Suggested from installed mod_form date fields';
$string['mappingraw'] = 'Edit JSON directly';
$string['mappingoriginform'] = 'date control in mod_form';
$string['mappingoriginschema'] = 'database field heuristic';
$string['mappinginvalidjson'] = 'Enter a JSON array of mapping rules.';
$string['mappinginvalidrules'] = 'One or more mapping rules have invalid fields or identifiers.';
$string['mappingownedrule'] = 'This activity is managed by a code adapter and cannot be mapped here.';
$string['mappingmodule_help'] = 'Name of the installed activity table. For a subactivity stored in another table, enter that table name and select its parent activity too.';
$string['mappingkind_help'] = 'A range has a start and end; a point milestone has one date; start only leaves the interval without an end date. Milestones do not contribute to the effort plot.';
$string['mappingtitlefield_help'] = 'Table column containing the name displayed on each Gantt row, usually name or title.';
$string['mappinglabel_help'] = 'Text identifying this row or phase type in the Gantt. It does not rename the Moodle activity.';
$string['mappingstart_help'] = 'Table column storing the interval start, or the single date of a point milestone.';
$string['mappingend_help'] = 'Table column storing the interval end. Used only for range rules.';
$string['mappingparent_help'] = 'Table name of the main activity to which this row belongs. Leave empty for a main activity; its rule must exist in this JSON.';
$string['mappingforeignkey_help'] = 'On a child table, the column containing the parent record ID. If omitted, the parent table name followed by id is assumed. Not used for phases in the same table.';
$string['mappingoptional_help'] = 'Allow an optional milestone to have no date and be re-enabled in the Gantt.';
$string['mappingdateonly_help'] = 'Indicates that a milestone date has no time component. Moving it snaps to the start of the local day.';
$string['mappingboundstart_help'] = 'When enabled, a subactivity cannot begin before its parent activity. Enabled by default.';
$string['mappingboundend_help'] = 'When enabled, a subactivity cannot end after its parent activity. Enabled by default.';
$string['mappingavailability_help'] = 'Auto displays and edits Moodle date availability restrictions on the main row. Off hides this layer in the Gantt.';
$string['mappingeditable_help'] = 'Allow the native dates of this row to be changed in the Gantt. Disable for a read-only row; course Edit mode is still required for all changes.';
$string['mappingeffortmodel_help'] = 'Choose the initial effort for this rule: the activity adapter value, fixed hours, or hours per 24 hours of duration. The proportional estimator needs a range with both dates.';
$string['mappingefforthours_help'] = 'Fixed effort per item in hours. One hour equals one effort point in the chart.';
$string['mappingefforthoursperday_help'] = 'Hours of effort per 24 hours between actual start and end dates, including fractional days. Recalculated when duration changes.';
$string['mappingraw_help'] = 'JSON representation of these visual rules. Use it for advanced tables or fields; leaving the field refreshes the visual editor when the JSON is valid.';
$string['autosequence'] = 'Auto-sequence';
$string['weekify'] = 'Weekify';
$string['weekifytitle'] = 'Move activities into their weeks';
$string['weekifyintro'] = 'Weekify moves each displayed activity to the existing course week that contains its saved start date. When the activity has no start date, it uses the earliest dated subactivity. It does not change activity dates.';
$string['weekifynote'] = 'Review the moves below before confirming. Activities without a start date or a matching existing week are skipped. Moving to a hidden week may hide the activity.';
$string['weekifyconfirm'] = 'Move activities';
$string['weekifysavefirst'] = 'Save or reset pending date changes before using Weekify.';
$string['weekifyloading'] = 'Checking weekly sections…';
$string['weekifymoves'] = 'activities to move';
$string['weekifyundated'] = 'without a start date';
$string['weekifyoutofrange'] = 'outside existing weeks';
$string['weekifyalready'] = 'already in the correct week';
$string['weekifyunsupported'] = 'cannot be moved';
$string['weekifymore'] = 'more moves';
$string['weekifymoved'] = 'Activities moved';
$string['weekifyfailed'] = 'Activities that could not be moved';
$string['weekifyerror'] = 'Could not complete Weekify. Please try again.';
$string['weekifynotweeks'] = 'Weekify is available only for weekly courses.';
$string['resetschedule'] = 'Reset schedule';
$string['unsavedchanges'] = 'You have unsaved changes.';
$string['schedulesaved'] = 'Activity schedule successfully saved.';
$string['error_saving'] = 'An error occurred while saving the schedule.';
$string['error_saving_header'] = 'Could not save schedule';
$string['error_saving_affected'] = 'Activities to review in the table:';
$string['error_saving_pending'] = 'Activities with changes pending in the table:';
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
$string['autosequenceonlydated'] = 'Only activities with defined start and end dates are distributed. Rows with a missing date keep their current state.';
$string['autosequenceavoidblackout'] = 'Avoid blacklisted days (weekends)';
$string['autosequenceavoidblackoutinfo'] = 'Place calculated start and end dates on working days. Weekend time is skipped; a strategy that cannot fit within the course or parent interval reports why it cannot run.';
$string['autosequenceblackoutempty'] = 'No working time is available in the course date range. Auto-sequence was not applied.';
$string['autosequencenoitems'] = 'There are no eligible activities with complete dates or editable availability windows to sequence.';
$string['autosequencenospace'] = 'The selected strategy needs {required}, but the course interval has only {available}. Shorten activities, extend the course dates, or choose another strategy.';
$string['autosequencenoworkingspace'] = 'The selected strategy needs {required} of working time, but only {available} remains after excluding blacklisted days. Shorten activities, extend the course dates, or change the blackout option.';
$string['autosequenceparentspace'] = 'The subactivities of “{activity}” need {required}, but their parent interval has only {available}. Shorten the subactivities or extend the parent activity.';
$string['autosequenceparentmissing'] = 'The selected subactivity “{activity}” cannot be sequenced because its parent has no complete date range.';
$string['autosequencenospan'] = 'The selected dates have no duration to scale. Define a start and end date for at least one activity.';
$string['autosequenceoverflowtitle'] = 'If activities do not fit (sequential chaining)';
$string['autosequenceoverflowstop'] = 'Stop and explain the problem';
$string['autosequenceoverflowshorten'] = 'Shorten the last activities to fit the course';
$string['autosequenceoverflowkeep'] = 'Keep durations and leave the last activities in place';
$string['autosequenceoverflowhelp'] = 'Each activity keeps at least one hour and enough space for its subactivities. If even that cannot fit, the operation stops. Activities left in place keep their dates.';
$string['editmoderequired'] = 'Turn on the course Edit mode to change activity dates.';
$string['readonlynotice'] = 'Read-only view. Turn on Edit mode to reschedule activities.';
$string['choosestrategy'] = 'Select sequencing strategy';
$string['apply'] = 'Apply';
$string['strategy_equal'] = 'Equal distribution';
$string['strategy_equal_short'] = 'Distribute course duration equally';
$string['strategy_equal_desc'] = 'Divides the total course duration equally among top-level activities with both dates defined. Dated subtasks and phases are distributed evenly within their parent activity timeframe.';
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
$string['availabilitynoteditable'] = 'Date availability restrictions are read-only because their boolean logic is not a simple AND.';
$string['availabilityinvalid'] = 'The date availability restriction is invalid or has changed. Reload the page and try again.';
$string['availabilitychanged'] = 'The date availability restriction no longer exists. Reload the page and try again.';
$string['availabilityinvalidrange'] = 'The end of a date availability range must be later than its start.';
$string['availabilityrestriction'] = 'Date availability restriction';
$string['availabilityrestrictionhint'] = 'Drag the lock or its handles to adjust the date restriction.';
$string['selectionmode'] = 'Selection mode';
$string['snapto'] = 'Snap:';
$string['snaphours'] = 'hours';
$string['snapdays'] = 'days';
$string['selectiondraghint'] = 'Drag bars to adjust the schedule';
$string['selectionselected'] = 'selected';
$string['selectactivity'] = 'Select activity';
$string['selectionempty'] = 'Select at least one activity first.';
$string['selectionweekifyempty'] = 'Select a course activity row to use Weekify.';
$string['unmappedactivitynoteditable'] = 'This activity has no recognised native dates; only its availability restriction can be edited.';
$string['dateinterval'] = 'Date interval';
$string['kuetactivitynoteditable'] = 'The KUET timeframe is calculated from its sessions. Edit programmed sessions to reschedule it.';
$string['kuetactivityderivedhint'] = 'Move or resize this activity to move or resize its programmed sessions.';
$string['noschedulablechanges'] = 'There are no editable schedule changes to save. KUET dates are stored in programmed sessions.';
$string['subactivityparentbounds'] = 'The subactivity must remain within the parent activity timeframe.';
$string['close'] = 'Close';
$string['editdatesmodal'] = 'Edit activity dates';
$string['editdatesmodaltip'] = 'Uncheck a box to remove that date. Changes will update the timeline and table.';
$string['invaliddateorder'] = 'The end date must be later than the start date.';
$string['datefieldrequired'] = 'Each enabled date needs a valid value.';
$string['enablestartdate'] = 'Enable start date';
$string['enableenddate'] = 'Enable end date';
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
$string['milestone'] = 'Milestone';
$string['milestonedate'] = 'Milestone date';
$string['enablemilestone'] = 'Enable this date';
