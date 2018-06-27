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
 * Kaltura video assignment view script.
 *
 * @package    mod_kalvidassign
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot . '/local/kaltura/locallib.php');
require_once($CFG->dirroot . '/mod/kalvidassign/locallib.php');

$id = required_param('id', PARAM_INT);
list($cm, $course, $kalvidassign) = kalvidassign_validate_cmid($id);

require_course_login($course, true, $cm);

$PAGE->set_url('/mod/kalvidassign/view.php', array('id' => $id));
$PAGE->set_title(format_string($kalvidassign->name));
$PAGE->set_heading($course->fullname);
$pageclass = 'kaltura-kalvidassign-body';
$PAGE->add_body_class($pageclass);

$context = context_module::instance($cm->id);

$event = \mod_kalvidassign\event\assignment_details_viewed::create(array(
            'objectid' => $kalvidassign->id,
            'context' => context_module::instance($cm->id)
        ));
$event->trigger();

// Update 'viewed' state if required by completion system
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->requires->css('/mod/kalvidassign/styles.css');
$PAGE->requires->css('/local/kaltura/styles.css');

// Here we can assume that the user has permission to submit no matter what their role is.
// The old method denied module-level locally assigned instructors the right to submit items,
// even if they were also students within the course.
$param = array('vidassignid' => $kalvidassign->id, 'userid' => $USER->id);
$submission = $DB->get_record('kalvidassign_submission', $param, '*', IGNORE_MISSING);
$disabled = kalvidassign_assignemnt_submission_expired($kalvidassign);

$params = array(
    'withblocks' => 0,
    'courseid' => $course->id,
    'width' => KALTURA_PANEL_WIDTH,
    'height' => KALTURA_PANEL_HEIGHT,
    'cmid' => $cm->id
);

$url = new moodle_url('/mod/kalvidassign/lti_launch.php', $params);

$params = array(
    'addvidbtnid' => 'id_add_video',
    'ltilaunchurl' => $url->out(false),
    'height' => KALTURA_PANEL_HEIGHT,
    'width' => KALTURA_PANEL_WIDTH
);

$PAGE->requires->yui_module('moodle-local_kaltura-ltipanel', 'M.local_kaltura.initmediaassignment', array($params), null, true);

// Require a YUI module to make the object tag be as large as possible.
$params = array(
    'bodyclass' => $pageclass,
    'lastheight' => null,
    'padding' => 15
);
if(isset($submission->width) && isset($submission->height)) {
    $params['width'] = $submission->width;
    $params['height'] = $submission->height;
}
$PAGE->requires->yui_module('moodle-local_kaltura-lticontainer', 'M.local_kaltura.init', array($params), null, true);
$PAGE->requires->string_for_js('replacevideo', 'kalvidassign');

/** @var mod_kalvidassign_renderer $renderer */
$renderer = $PAGE->get_renderer('mod_kalvidassign');

echo $renderer->header();
echo $renderer->box_start('generalbox');
echo $renderer->mod_info($kalvidassign, $context);
echo format_module_intro('kalvidassign', $kalvidassign, $cm->id);
echo $renderer->box_end();

// If the entry_id field is not empty but the source field is empty, then the data for this activity has not yet been migrated.
if (!empty($submission->entry_id) && empty($submission->source)) {
    notice(get_string('activity_not_migrated', 'kalvidassign'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

echo $renderer->video_container_markup($submission, $course->id, $cm->id);

if (empty($submission->entry_id) && empty($submission->timecreated)) {
    echo $renderer->student_submit_buttons($cm, $USER->id, $disabled);
    echo $renderer->grade_feedback($kalvidassign, $context);
} else {
    if ($disabled || !$kalvidassign->resubmit) {
        $disabled = true;
    }
    echo $renderer->student_resubmit_buttons($cm, $USER->id, $disabled);
    echo $renderer->grade_feedback($kalvidassign, $context);
}
// Limit the instructor buttons to ONLY those users with the role appropriate for them.
if (has_capability('mod/kalvidassign:gradesubmission', $context)) {
    echo $renderer->instructor_buttons($cm, $USER->id);
}
echo $renderer->footer();
