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
 * Kaltura video resource view page.
 *
 * @package    mod_kalvidres
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

$id = required_param('id', PARAM_INT);

// Retrieve module instance.
list ($course, $cm) = get_course_and_cm_from_cmid($id, 'kalvidres');
$context = context_module::instance($cm->id);
$PAGE->set_url('/mod/kalvidres/view.php', array('id' => $id));
$PAGE->set_context($context);

$kalvidres = $DB->get_record('kalvidres', array("id" => $cm->instance), '*', MUST_EXIST);

require_course_login($course, true, $cm);

$PAGE->set_title(format_string($kalvidres->name));
$PAGE->set_heading($course->fullname);
$pageclass = 'kaltura-kalvidres-body';
$PAGE->add_body_class($pageclass);

$event = \mod_kalvidres\event\video_resource_viewed::create(array(
    'objectid' => $kalvidres->id,
    'context' => $context
));
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Require a YUI module to make the object tag be as large as possible.
$params = array(
    'bodyclass' => $pageclass,
    'lastheight' => null,
    'padding' => 15,
    'width' => $kalvidres->width,
    'height' => $kalvidres->height
);
$PAGE->requires->yui_module('moodle-local_kaltura-lticontainer', 'M.local_kaltura.init', array($params), null, true);

/** @var mod_kalvidres_renderer|core_renderer $renderer */
$renderer = $PAGE->get_renderer('mod_kalvidres');

echo $renderer->header();

$description = format_module_intro('kalvidres', $kalvidres, $cm->id);
if (!empty($description)) {
    echo $renderer->box($description, 'generalbox');
}

echo $renderer->display_iframe($kalvidres, $course->id);
echo $renderer->footer();
