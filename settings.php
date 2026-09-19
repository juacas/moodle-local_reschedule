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
 * Settings configuration for local_reschedule.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_reschedule', get_string('pluginname', 'local_reschedule'));

    $defaultmapping = "assign,name,Assignment,allowsubmissionsfromdate,duedate\n" .
        "quiz,name,Quiz,timeopen,timeclose\n" .
        "workshop,name,Workshop,submissionstart,assessmentend\n" .
        "-workshop,name,Workshop - Submission Phase,submissionstart,submissionend\n" .
        "-workshop,name,Workshop - Assessment Phase,assessmentstart,assessmentend\n" .
        "lesson,name,Lesson,available,deadline\n" .
        "feedback,name,Feedback,timeopen,timeclose\n" .
        "choice,name,Choice,timeopen,timeclose\n" .
        "data,name,Database,timeavailablefrom,timeavailableto\n" .
        "scorm,name,SCORM,timeopen,timeclose\n" .
        "quest,name,Questournament,datestart,dateend\n" .
        "-quest_submissions,title,Quest Challenge,datestart,dateend,questid\n" .
        "kuet,name,Kuet,startdate,enddate\n" .
        "-kuet_sessions,name,Kuet Session,startdate,enddate,kuetid";

    $settings->add(new admin_setting_configtextarea(
        'local_reschedule/mapping',
        get_string('mapping', 'local_reschedule'),
        get_string('mapping_desc', 'local_reschedule'),
        $defaultmapping,
        PARAM_RAW,
        60,
        14
    ));

    $ADMIN->add('localplugins', $settings);
}
