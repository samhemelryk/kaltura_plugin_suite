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
 * Kaltura video assignment single submission script.
 *
 * @package    mod_kalvidassign
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot . '/mod/kalvidassign/lib.php');
require_once($CFG->dirroot . '/mod/kalvidassign/locallib.php');
require_once($CFG->dirroot . '/mod/kalvidassign/single_submission_form.php');

$id = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$tifirst = optional_param('tifirst', '', PARAM_TEXT);
$tilast = optional_param('tilast', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);


list($cm, $course, $kalvidassignobj) = kalvidassign_validate_cmid($id);
$context = context_module::instance($cm->id);

$url = new moodle_url('/mod/kalvidassign/single_submission.php', array('cmid' => $id, 'userid' => $userid));

$PAGE->set_url($url);
$PAGE->set_context($context);

require_login($course->id, false, $cm);
require_capability('mod/kalvidassign:gradesubmission', $context);
require_sesskey();

$previousurl = new moodle_url('/mod/kalvidassign/grade_submissions.php', array('cmid' => $cm->id, 'tifirst' => $tifirst, 'tilast' => $tilast, 'page' => $page));

// Get a single submission record
$submission = kalvidassign_get_submission($cm->instance, $userid);

// Get the submission user and the time they submitted the video
$user  = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

$submissionuserpic = $OUTPUT->user_picture($user);
$submissionmodified = ' - ';
$datestringlate = ' - ';
$datestring = ' - ';

$submissionuserinfo = fullname($user);

// Get grading information
$gradinginfo    = grade_get_grades($cm->course, 'mod', 'kalvidassign', $cm->instance, array($userid));
$gradingdisabled = $gradinginfo->items[0]->grades[$userid]->locked || $gradinginfo->items[0]->grades[$userid]->overridden;

// Get marking teacher information and the time the submission was marked
$teacher = '';
if (!empty($submission)) {
    $datestringlate     = kalvidassign_display_lateness($submission->timemodified, $kalvidassignobj->timedue);
    $submissionmodified = userdate($submission->timemodified);
    $datestring         = userdate($submission->timemarked)."&nbsp; (".format_time(time() - $submission->timemarked).")";

    $submissionuserinfo .= '<br />'.$submissionmodified.$datestringlate;

    $teacher = $DB->get_record('user', array('id' => $submission->teacher), '*', IGNORE_MISSING);
}

$markingteacherpic   = '';
$markingtreacherinfo = '';

if (!empty($teacher)) {
    $markingteacherpic   = $OUTPUT->user_picture($teacher);
    $markingtreacherinfo = fullname($teacher).'<br />'.$datestring;
}

// Setup form data
$formdata                           = new stdClass();
$formdata->submissionuserpic        = $submissionuserpic;
$formdata->submissionuserinfo       = $submissionuserinfo;
$formdata->markingteacherpic        = $markingteacherpic;
$formdata->markingteacherinfo       = $markingtreacherinfo;
$formdata->grading_info             = $gradinginfo;
$formdata->gradingdisabled          = $gradingdisabled;
$formdata->cm                       = $cm;
$formdata->context                  = $context;
$formdata->cminstance               = $kalvidassignobj;
$formdata->submission               = $submission;
$formdata->userid                   = $userid;
$formdata->enableoutcomes           = $CFG->enableoutcomes;
$formdata->submissioncomment_editor = array('text' => $submission->submissioncomment, 'format' => FORMAT_HTML);
$formdata->tifirst                  = $tifirst;
$formdata->tilast                   = $tilast;
$formdata->page                     = $page;

$submissionform = new kalvidassign_singlesubmission_form(null, $formdata);
$submissionform->set_data($formdata);

if ($submissionform->is_cancelled()) {

    redirect($previousurl);

} else if ($submitted_data = $submissionform->get_data()) {

    if (!isset($submitted_data->cancel) && isset($submitted_data->xgrade) && isset($submitted_data->submissioncomment_editor)) {

        // Flag used when an instructor is about to grade a user who does not have
        // a submittion (see KALDEV-126)
        $updategrade = true;

        if ($submission) {

            $submissionchanged = strcmp($submission->submissioncomment, $submitted_data->submissioncomment_editor['text']);
            if ($submission->grade == $submitted_data->xgrade && $submissionchanged) {
                $updategrade = false;
            }
            if ($submissionchanged || $updategrade) {
                $submission->grade = $submitted_data->xgrade;
                $submission->submissioncomment = $submitted_data->submissioncomment_editor['text'];
                $submission->format = $submitted_data->submissioncomment_editor['format'];
                $submission->timemarked = time();
                $submission->teacher = $USER->id;
                $DB->update_record('kalvidassign_submission', $submission);
            }

        } else {

            // Check for unchanged values
            if ('-1' == $submitted_data->xgrade && empty($submitted_data->submissioncomment_editor['text'])) {

                $updategrade = false;
            } else {

                $submission = new stdClass();
                $submission->vidassignid        = $cm->instance;
                $submission->userid             = $userid;
                $submission->grade              = $submitted_data->xgrade;
                $submission->submissioncomment  = $submitted_data->submissioncomment_editor['text'];
                $submission->format             = $submitted_data->submissioncomment_editor['format'];
                $submission->timemarked         = time();
                $submission->teacher            = $USER->id;

                $DB->insert_record('kalvidassign_submission', $submission);
            }
        }

        if ($updategrade) {
            $kalvidassignobj->cmidnumber = $cm->idnumber;

            $gradeobj = kalvidassign_get_submission_grade_object($kalvidassignobj->id, $userid);

            kalvidassign_grade_item_update($kalvidassignobj, $gradeobj);

            // Add to log.
            $event = \mod_kalvidassign\event\grades_updated::create(array(
                        'context'   => context_module::instance($cm->id),
            ));
            $event->trigger();
        }

        // Handle outcome data
        if (!empty($CFG->enableoutcomes)) {
            require_once($CFG->libdir.'/gradelib.php');

            $data = array();
            $gradinginfo = grade_get_grades($course->id, 'mod', 'kalvidassign', $kalvidassignobj->id, $userid);

            if (!empty($gradinginfo->outcomes)) {
                foreach ($gradinginfo->outcomes as $n => $old) {
                    $name = 'outcome_'.$n;
                    if (isset($submitted_data->{$name}[$userid]) and
                        $old->grades[$userid]->grade != $submitted_data->{$name}[$userid]) {

                        $data[$n] = $submitted_data->{$name}[$userid];
                    }
                }
            }

            if (count($data) > 0) {
                grade_update_outcomes('mod/kalvidassign', $course->id, 'mod', 'kalvidassign', $kalvidassignobj->id, $userid, $data);
            }
        }

    }

    redirect($previousurl);

}

// OK, they are viewing the page, record it.
$event = \mod_kalvidassign\event\single_submission_page_viewed::create(array(
    'objectid'  => $kalvidassignobj->id,
    'context' => context_module::instance($cm->id)
));
$event->trigger();

$prevousurlstring = get_string('singlesubmissionheader', 'kalvidassign');

$PAGE->set_title($kalvidassignobj->name);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($prevousurlstring, $previousurl);
$PAGE->requires->css('/local/kaltura/styles.css');

/** @var mod_kalvidassign_renderer|core_renderer $renderer */
$renderer = $PAGE->get_renderer('mod_kalvidassign');

echo $renderer->header();
echo $renderer->heading(get_string('gradesubmissionforuser', 'kalvidassign', fullname($user)));

$submissionform->display();

echo $renderer->footer();
