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
 * Kaltura video assignment submission script.
 *
 * @package    mod_kalvidassign
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot . '/mod/kalvidassign/locallib.php');

$entryid = required_param('entry_id', PARAM_TEXT);
$source = required_param('source', PARAM_URL);
$cmid  = required_param('cmid', PARAM_INT);
$width  = required_param('width', PARAM_TEXT);
$height  = required_param('height', PARAM_TEXT);
$metadata  = required_param('metadata', PARAM_TEXT);

if (empty($entryid)) {
    print_error('emptyentryid', 'kalvidassign', new moodle_url('/mod/kalvidassign/view.php', array('id' => $cmid)));
}

list($cm, $course, $kalvidassignobj) = kalvidassign_validate_cmid($cmid);

require_course_login($course, true, $cm);
require_sesskey();

if (kalvidassign_assignemnt_submission_expired($kalvidassignobj)) {
    print_error('assignmentexpired', 'kalvidassign', 'course/view.php?id='.$course->id);
}

$source = local_kaltura_build_kaf_uri($source);

$param = array('vidassignid' => $kalvidassignobj->id, 'userid' => $USER->id);
$submission = $DB->get_record('kalvidassign_submission', $param, '*', IGNORE_MISSING);

if ($submission) {

    $submission->entry_id = $entryid;
    $submission->source = $source;
    $submission->width = $width;
    $submission->height = $height;
    $submission->timemodified = time();
    $submission->metadata = $metadata;

    if (0 == $submission->timecreated) {
        $submission->timecreated = $submission->timemodified;
    }

    $DB->update_record('kalvidassign_submission', $submission);

} else {

    $submission = new stdClass();
    $submission->entry_id = $entryid;
    $submission->userid = $USER->id;
    $submission->vidassignid = $kalvidassignobj->id;
    $submission->grade = -1;
    $submission->source = $source;
    $submission->width = $width;
    $submission->height = $height;
    $submission->metadata = $metadata;
    $submission->timecreated = time();
    $submission->timemodified = $submission->timecreated;

    $DB->insert_record('kalvidassign_submission', $submission);

}

$event = \mod_kalvidassign\event\assignment_submitted::create(array(
    'objectid'  => $kalvidassignobj->id,
    'context'   => context_module::instance($cm->id)
));
$event->trigger();

$PAGE->set_url('/mod/kalvidassign/view.php', array('id' => $cm->id));
$PAGE->set_title($kalvidassignobj->name);
$PAGE->set_heading($course->fullname);

/** @var mod_kalvidassign_renderer|core_renderer $renderer */
$renderer = $PAGE->get_renderer('mod_kalvidassign');

echo $renderer->header();
echo $renderer->submission_confirmation($cm);

// Email an alert to the teacher
if ($kalvidassignobj->emailteachers) {
    kalvidassign_email_teachers($cm, $kalvidassignobj->name, $submission, $PAGE->context);
}

echo $renderer->footer();
