<?php
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
 * Kaltura grade submission script.
 *
 * @package    mod_kalvidassign
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot . '/mod/kalvidassign/lib.php');
require_once($CFG->dirroot . '/mod/kalvidassign/locallib.php');
require_once($CFG->dirroot . '/mod/kalvidassign/grade_preferences_form.php');

$id = required_param('cmid', PARAM_INT);           // Course Module ID
$tifirst = optional_param('tifirst', '', PARAM_TEXT);
$tilast = optional_param('tilast', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);

$url = new moodle_url('/mod/kalvidassign/grade_submissions.php', array('cmid' => $id));
if (!empty($page)) {
    $url->param('page', $page);
}
if (!empty($tifirst)) {
    $url->param('tifirst', $tifirst);
}
if (!empty($tilast)) {
    $url->param('tilast', $tilast);
}

list($cm, $course, $kalvidassignobj) = kalvidassign_validate_cmid($id);
$context = context_module::instance($cm->id);

$PAGE->set_context($context);
$PAGE->set_url($url);

require_login($course->id, false, $cm);
require_capability('mod/kalvidassign:gradesubmission', $context);

// Ensure we use the appropriate group mode, either course or module
if (($course->groupmodeforce) == 1) {
    $groupmode = $course->groupmode;
} else {
    $groupmode = $cm->groupmode;
}

$preferences = new stdClass;
$preferences->filter = get_user_preferences('kalvidassign_filter', 0);
$preferences->perpage = get_user_preferences('kalvidassign_perpage', 10);
$preferences->quickgrade = get_user_preferences('kalvidassign_quickgrade', 0);
$preferences->group_filter = get_user_preferences('kalvidassign_group_filter', 0);

$prefform = new kalvidassign_gradepreferences_form(null, array('cmid' => $cm->id, 'groupmode' => $groupmode));
$prefform->set_data($preferences);

if ($data = $prefform->get_data()) {

    set_user_preference('kalvidassign_group_filter', $data->group_filter);
    set_user_preference('kalvidassign_filter', $data->filter);

    if ($data->perpage > 0) {
        set_user_preference('kalvidassign_perpage', $data->perpage);
    }

    if (isset($data->quickgrade)) {
        set_user_preference('kalvidassign_quickgrade', $data->quickgrade);
    } else {
        set_user_preference('kalvidassign_quickgrade', '0');
    }

    // Reload the preferences, now that they may have changed.
    $preferences->filter = get_user_preferences('kalvidassign_filter', 0);
    $preferences->perpage = get_user_preferences('kalvidassign_perpage', 10);
    $preferences->quickgrade = get_user_preferences('kalvidassign_quickgrade', 0);
    $preferences->group_filter = get_user_preferences('kalvidassign_group_filter', 0);
}

$gradedata = data_submitted();

// Check if fast grading was passed to the form and process the data
if (!empty($gradedata->mode)) {

    require_sesskey();

    foreach ($gradedata->users as $userid => $val) {

        $param = array('vidassignid' => $kalvidassignobj->id, 'userid' => $userid);
        $usersubmissions = $DB->get_record('kalvidassign_submission', $param);
        if ($usersubmissions) {

            $updated = false;

            if (array_key_exists($userid, $gradedata->menu)) {

                // Update grade
                if (($gradedata->menu[$userid] != $usersubmissions->grade)) {

                    $usersubmissions->grade = $gradedata->menu[$userid];
                    $usersubmissions->timemarked = time();
                    $usersubmissions->teacher = $USER->id;

                    $updated = true;
                }
            }

            if (array_key_exists($userid, $gradedata->submissioncomment)) {

                if (0 != strcmp($usersubmissions->submissioncomment, $gradedata->submissioncomment[$userid])) {
                    $usersubmissions->submissioncomment = $gradedata->submissioncomment[$userid];

                    $updated = true;

                }
            }

            // trigger grade event
            if ($updated) {

                $DB->update_record('kalvidassign_submission', $usersubmissions);

                $grade = new stdClass();
                $grade->userid = $userid;
                $grade = kalvidassign_get_submission_grade_object($kalvidassignobj->id, $userid);

                $kalvidassignobj->cmidnumber = $cm->idnumber;

                kalvidassign_grade_item_update($kalvidassignobj, $grade);

                // Add to log only if updating.
                $event = \mod_kalvidassign\event\grades_updated::create(array(
                    'context'   => $context,
                    'other'     => array(
                        'crud'    => 'u'
                    )
                ));
                $event->trigger();
            }

        } else {

            // No user submission however the instructor has submitted grade data
            $usersubmissions                = new stdClass();
            $usersubmissions->vidassignid   = $cm->instance;
            $usersubmissions->userid        = $userid;
            $usersubmissions->entry_id      = '';
            $usersubmissions->teacher       = $USER->id;
            $usersubmissions->timemarked    = time();

            // Need to prevent completely empty submissions from getting entered
            // into the video submissions' table
            // Check for unchanged grade value and an empty feedback value
            $emptygrade = array_key_exists($userid, $gradedata->menu) && '-1' == $gradedata->menu[$userid];

            $emptycomment = array_key_exists($userid, $gradedata->submissioncomment) && empty($gradedata->submissioncomment[$userid]);

            if ($emptygrade && $emptycomment ) {
                continue;
            }

            if (array_key_exists($userid, $gradedata->menu)) {
                $usersubmissions->grade = $gradedata->menu[$userid];
            }

            if (array_key_exists($userid, $gradedata->submissioncomment)) {
                $usersubmissions->submissioncomment = $gradedata->submissioncomment[$userid];
            }

            // trigger grade event
            $DB->insert_record('kalvidassign_submission', $usersubmissions);

            $grade = new stdClass();
            $grade->userid = $userid;
            $grade = kalvidassign_get_submission_grade_object($kalvidassignobj->id, $userid);

            $kalvidassignobj->cmidnumber = $cm->idnumber;

            kalvidassign_grade_item_update($kalvidassignobj, $grade);

            // Add to log only if updating
            $event = \mod_kalvidassign\event\grades_updated::create(array(
                'context'   => $context,
                'other'     => array(
                    'crud'      => 'c'
                )
            ));
            $event->trigger();

        }
    }

    // Redirect just to be extra safe, ensure that everything is fresh and clean.
    redirect($PAGE->url);
}

$event = \mod_kalvidassign\event\grade_submissions_page_viewed::create(array(
    'objectid'  => $kalvidassignobj->id,
    'context'   => $context
));
$event->trigger();

$PAGE->set_title($kalvidassignobj->name);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('singlesubmissionheader', 'kalvidassign'));
$PAGE->requires->yui_module('moodle-local_kaltura-ltipanel', 'M.local_kaltura.initreviewsubmission');

/** @var mod_kalvidassign_renderer|core_renderer $renderer */
$renderer = $PAGE->get_renderer('mod_kalvidassign');

echo $renderer->header();
echo $renderer->submissions_table(
    $cm,
    $preferences->group_filter,
    $preferences->filter,
    $preferences->perpage,
    $preferences->quickgrade,
    $tifirst,
    $tilast,
    $page
);

$prefform->display();

echo $OUTPUT->footer();
