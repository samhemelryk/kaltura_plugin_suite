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
 * Kaltura video assignment renderer script.
 *
 * @package    mod_kalvidassign
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Remote Learner.net Inc http://www.remote-learner.net
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/tablelib.php');
require_once($CFG->dirroot . '/local/kaltura/locallib.php');
require_once($CFG->dirroot . '/mod/kalvidassign/locallib.php');

/**
 * This class renders the submission pages.
 */
class mod_kalvidassign_renderer extends plugin_renderer_base {
    /**
     * The function displays information about the assignment settings.
     * @param object $data information about the current row being rendered.
     * @return string HTML markup.
     */
    public function mod_info($kalvideoobj, $context) {
        $html = '';

        if (!empty($kalvideoobj->timeavailable)) {
            $html .= html_writer::start_tag('p');
            $html .= html_writer::tag('b', get_string('availabledate', 'kalvidassign').': ');
            $html .= userdate($kalvideoobj->timeavailable);
            $html .= html_writer::end_tag('p');
        }

        if (!empty($kalvideoobj->timedue)) {
            $html .= html_writer::start_tag('p');
            $html .= html_writer::tag('b', get_string('duedate', 'kalvidassign').': ');
            $html .= userdate($kalvideoobj->timedue);
            $html .= html_writer::end_tag('p');
        }

        // Display a count of the numuber of submissions
        if (has_capability('mod/kalvidassign:gradesubmission', $context)) {

            $count = kalvidassign_count_submissions($kalvideoobj);

            if ($count) {
                $html .= html_writer::start_tag('p');
                $html .= get_string('numberofsubmissions', 'kalvidassign', $count);
                $html .= html_writer::end_tag('p');
            }

        }

        return $html;
    }

    /**
     * This function returns HTML markup to render a form and submission buttons.
     * @param object $cm A course module object.
     * @param int $userid The current user id.
     * @param bool $disablesubmit Set to true to disable the submit button.
     * @return string Returns HTML markup.
     */
    public function student_submit_buttons($cm, $userid, $disablesubmit = false) {
        $html = '';

        $target = new moodle_url('/mod/kalvidassign/submission.php');

        $html .= html_writer::start_tag('form', array('method' => 'POST', 'action' => $target));

        $attr = array(
            'type' => 'hidden',
            'name' => 'entry_id',
            'id' => 'entry_id',
            'value' => ''
        );
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array(
            'type' => 'hidden',
            'name' => 'cmid',
            'value' => $cm->id
        );
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        );
        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'id' => 'width', 'name' => 'width', 'value' => 0));
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'id' => 'height', 'name' => 'height', 'value' => 0));
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'id' => 'source', 'name' => 'source', 'value' => 0));
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'id' => 'metadata', 'name' => 'metadata', 'value' => 0));

        $html .= html_writer::start_tag('center');

        $attr = array(
            'type' => 'button',
            'id' => 'id_add_video',
            'name' => 'add_video',
            'value' => get_string('addvideo', 'kalvidassign')
        );
        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }
        $html .= html_writer::empty_tag('input', $attr);

        $html .= '&nbsp;';

        $attr = array(
            'type' => 'submit',
            'name' => 'submit_video',
            'id' => 'submit_video',
            'disabled' => 'disabled',
            'value' => get_string('submitvideo', 'kalvidassign')
        );
        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::end_tag('center');

        $html .= html_writer::end_tag('form');

        return $html;
    }

    /**
     * This function returns HTML markup to render a form and submission buttons.
     * @param object $cm A course module object.
     * @param int $userid The current user id.
     * @param bool $disablesubmit Set to true to disable the submit button.
     * @return string Returns HTML markup.
     */
    public function student_resubmit_buttons($cm, $userid, $disablesubmit = false) {
        global $DB;

        $param = array('vidassignid' => $cm->instance, 'userid' => $userid);
        $submissionrec = $DB->get_record('kalvidassign_submission', $param);

        $html = '';

        $target = new moodle_url('/mod/kalvidassign/submission.php');

        $attr = array('method' => 'POST', 'action' => $target);

        $html .= html_writer::start_tag('form', $attr);

        $attr = array(
            'type' => 'hidden',
            'name'  => 'cmid',
            'value' => $cm->id
        );

        $html .= html_writer::empty_tag('input', $attr);

        $attr = array(
            'type' => 'hidden',
            'name'  => 'entry_id',
            'id'    => 'entry_id',
            'value' => $submissionrec->entry_id
        );

        $html .= html_writer::empty_tag('input', $attr);

        $attr = array(
            'type' => 'hidden',
            'name'  => 'sesskey',
            'value' => sesskey()
        );

        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'id' => 'width', 'name' => 'width', 'value' => 0));
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'id' => 'height', 'name' => 'height', 'value' => 0));
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'id' => 'source', 'name' => 'source', 'value' => 0));
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'id' => 'metadata', 'name' => 'metadata', 'value' => 0));

        $html .= html_writer::start_tag('center');

        // Add submit and review buttons.
        $attr = array(
            'type' => 'button',
            'name' => 'add_video',
            'id' => 'id_add_video',
            'value' => get_string('replacevideo', 'kalvidassign')
        );

        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $attr);

        $html .= '&nbsp;&nbsp;';

        $attr = array(
            'type' => 'submit',
            'id'   => 'submit_video',
            'name' => 'submit_video',
            'disabled' => 'disabled',
            'value' => get_string('submitvideo', 'kalvidassign')
        );

        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::end_tag('center');

        $html .= html_writer::end_tag('form');

        return $html;
    }

    /**
     * This function returns HTML markup to render a form and submission buttons.
     * @param object $cm A course module object.
     * @param int $userid The current user id.
     * @param bool $disablesubmit Set to true to disable the submit button.
     * @return string Returns HTML markup.
     */
    public function instructor_buttons($cm, $userid) {
        $html = '';

        $target = new moodle_url('/mod/kalvidassign/grade_submissions.php');

        $html .= html_writer::start_tag('form', array('method' => 'POST', 'action' => $target));
        $html .= html_writer::start_tag('center');

        $attr = array('type' => 'hidden',
                     'name' => 'sesskey',
                     'value' => sesskey());
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name' => 'cmid',
                     'value' => $cm->id);
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'submit',
                     'name' => 'grade_submissions',
                     'value' => get_string('gradesubmission', 'kalvidassign'));
        $html .= html_writer::empty_tag('input', $attr);
        $html .= html_writer::end_tag('center');
        $html .= html_writer::end_tag('form');

        return $html;
    }

    /**
     * This function returns HTML markup to render a the submissions table
     * @param object $cm A course module object.
     * @param int $groupfilter The group id to filter against.
     * @param string $filter Filter users who have submitted, submitted and graded or everyone.
     * @param int $perpage The number of submissions to display on a page.
     * @param bool $quickgrade True if the quick grade table needs to be rendered, otherwsie false.
     * @param string $tifirst The first initial of the first name.
     * @param string $tilast The first initial of the last name.
     * @param int $page The current page to render.
     * @return string Returns HTML markup.
     */
    public function submissions_table($cm, $groupfilter = 0, $filter = 'all', $perpage, $quickgrade = false, $tifirst = '', $tilast = '', $page = 0) {
        $html = '';

        // Get a list of users who have submissions and retrieve grade data for those users.
        $users = kalvidassign_get_submissions($cm->instance, $filter);

        $define_columns = array('picture', 'fullname', 'selectgrade', 'submissioncomment', 'timemodified', 'timemarked', 'status', 'grade');

        if (empty($users)) {
            $users = array();
        }

        $entryids = array();

        foreach ($users as $usersubmission) {
            $entryids[$usersubmission->entry_id] = $usersubmission->entry_id;
        }

        // Compare student who have submitted to the assignment with students who are
        // currently enrolled in the course
        $students = array_keys(kalvidassign_get_assignment_students($cm));
        $users = array_intersect(array_keys($users), $students);

        if (empty($students)) {
            $html .= html_writer::tag('p', get_string('noenrolledstudents', 'kalvidassign'));
            return $html;
        }

        $gradinginfo = grade_get_grades($cm->course, 'mod', 'kalvidassign', $cm->instance, $users);

        $where = '';
        switch ($filter) {
            case KALASSIGN_SUBMITTED:
                $where = ' kvs.timemodified > 0 AND ';
                break;
            case KALASSIGN_REQ_GRADING:
                $where = ' kvs.timemarked < kvs.timemodified AND ';
                break;
        }

        // Determine logic needed for groups mode
        $param        = array();
        $groupswhere  = '';
        $groupscolumn = '';
        $groupsjoin   = '';
        $groups       = array();
        $mergedgroups = array();
        $groupids     = '';
        $context      = context_course::instance($cm->course);

        // Get all groups that the user belongs to, check if the user has capability to access all groups
        if (!has_capability('moodle/site:accessallgroups', $context)) {
            // It's very important we use the group limited user function here
            $groups = groups_get_user_groups($cm->course);

            if (empty($groups)) {
                $message = get_string('nosubmissions', 'kalvidassign');
                $html .= html_writer::tag('center', $message);
                return $html;
            }
            // Collapse all the group ids into one array for use later.
            // We have to do this here as the user groups function returns different data than the all groups function.
            foreach ($groups as $group) {
                foreach ($group as $value) {
                    $value = trim($value);
                    if (!in_array($value, (array)$mergedgroups)) {
                        $mergedgroups[] = $value;
                    }
                }
            }
        } else {
            // Here we can use the all groups function as it ensures non-group-bound users can see/grade all groups.
            $groups = groups_get_all_groups($cm->course);
            // Collapse all the group ids into one array for use later.
            // We have to do this here (and differntly than above) as the all groups function returns different data than the user groups function.
            foreach ($groups as $group) {
                $mergedgroups[] = $group->id;
            }
        }

        // Create a comma separated list of group ids
        $groupids .= implode(',', (array)$mergedgroups);
        // If the user is not a member of any groups, set $groupids = 0 to avoid issues.
        $groupids = $groupids ? $groupids : 0;

        // Ignore all this if there are no course groups
        if (groups_get_all_groups($cm->course)) {
            switch (groups_get_activity_groupmode($cm)) {
                case NOGROUPS:
                    // No groups, do nothing if all groups selected.
                    // If non-group limited, user can select and limit by group.
                    if (0 != $groupfilter) {
                        $groupscolumn = ', gm.groupid ';
                        $groupsjoin   = ' RIGHT JOIN {groups_members} gm ON gm.userid = u.id RIGHT JOIN {groups} g ON g.id = gm.groupid ';
                        $param['courseid'] = $cm->course;
                        $groupswhere  .= ' AND g.courseid = :courseid ';
                        $param['groupid'] = $groupfilter;
                        $groupswhere .= ' AND gm.groupid = :groupid ';
                    }
                    break;
                case SEPARATEGROUPS:
                    // If separate groups, but displaying all users then we must display only users
                    // who are in the same group as the current user. Otherwise, show only groupmembers
                    // of the selected group. 
                    if (0 == $groupfilter) {
                        $groupscolumn = ', gm.groupid ';
                        $groupsjoin   = ' INNER JOIN {groups_members} gm ON gm.userid = u.id INNER JOIN {groups} g ON g.id = gm.groupid ';
                        $param['courseid'] = $cm->course;
                        $groupswhere  .= ' AND g.courseid = :courseid ';
                        $param['groupid'] = $groupfilter;
                        $groupswhere .= ' AND g.id IN ('.$groupids.') ';
                    } else {
                        $groupscolumn = ', gm.groupid ';
                        $groupsjoin   = ' INNER JOIN {groups_members} gm ON gm.userid = u.id INNER JOIN {groups} g ON g.id = gm.groupid ';
                        $param['courseid'] = $cm->course;
                        $groupswhere  .= ' AND g.courseid = :courseid ';
                        $param['groupid'] = $groupfilter;
                        $groupswhere .= ' AND g.id IN ('.$groupids.') AND g.id = :groupid ';

                    }
                    break;

                case VISIBLEGROUPS:
                    // if visible groups but displaying a specific group then we must display users within
                    // that group, if displaying all groups then display all users in the course
                    if (0 != $groupfilter) {

                        $groupscolumn = ', gm.groupid ';
                        $groupsjoin   = ' RIGHT JOIN {groups_members} gm ON gm.userid = u.id RIGHT JOIN {groups} g ON g.id = gm.groupid ';

                        $param['courseid'] = $cm->course;
                        $groupswhere  .= ' AND g.courseid = :courseid ';

                        $param['groupid'] = $groupfilter;
                        $groupswhere .= ' AND gm.groupid = :groupid ';

                    }
                    break;
            }
        }

        $table = new \mod_kalvidassign\output\submissions_table('kal_vid_submit_table', $cm, $gradinginfo, $quickgrade, $tifirst, $tilast, $page);

        // In order for the sortable first and last names to work.  User ID has to be the first column returned and must be
        // returned as id.  Otherwise the table will display links to user profiles that are incorrect or do not exist
        $columns = user_picture::fields('u').', kvs.id AS submitid, ';
        $columns .= ' kvs.grade, kvs.submissioncomment, kvs.timemodified, kvs.entry_id, kvs.source, kvs.width, kvs.height, kvs.timemarked, ';
        $columns .= 'kvs.metadata, 1 AS status, 1 AS selectgrade'.$groupscolumn;
        $where .= ' u.deleted = 0 AND u.id IN ('.implode(',', $students).') '.$groupswhere;

        $param['instanceid'] = $cm->instance;
        $from = "{user} u LEFT JOIN {kalvidassign_submission} kvs ON kvs.userid = u.id AND kvs.vidassignid = :instanceid ".$groupsjoin;

        $baseurl = new moodle_url('/mod/kalvidassign/grade_submissions.php', array('cmid' => $cm->id));

        $col1 = get_string('fullname', 'kalvidassign');
        $col2 = get_string('grade', 'kalvidassign');
        $col3 = get_string('submissioncomment', 'kalvidassign');
        $col4 = get_string('timemodified', 'kalvidassign');
        $col5 = get_string('grademodified', 'kalvidassign');
        $col6 = get_string('status', 'kalvidassign');
        $col7 = get_string('finalgrade', 'kalvidassign');

        $table->set_sql($columns, $from, $where, $param);
        $table->define_baseurl($baseurl);
        $table->collapsible(true);

        $table->define_columns($define_columns);
        $table->define_headers(array('', $col1, $col2, $col3, $col4, $col5, $col6, $col7));

        $html .= html_writer::start_tag('center');

        $attributes = array('action' => new moodle_url('grade_submissions.php'), 'id' => 'fastgrade', 'method' => 'post');
        $html .= html_writer::start_tag('form', $attributes);

        $attributes = array('type' => 'hidden', 'name' => 'cmid', 'value' => $cm->id);
        $html .= html_writer::empty_tag('input', $attributes);

        $attributes['name'] = 'mode';
        $attributes['value'] = 'fastgrade';

        $html .= html_writer::empty_tag('input', $attributes);

        $attributes['name'] = 'sesskey';
        $attributes['value'] = sesskey();

        $html .= html_writer::empty_tag('input', $attributes);

        $table->out($perpage, true);

        if ($quickgrade) {
            $attributes = array('type' => 'submit', 'name' => 'save_feedback', 'value' => get_string('savefeedback', 'kalvidassign'));

            $html .= html_writer::empty_tag('input', $attributes);
        }

        $html .= html_writer::end_tag('form');
        $html .= html_writer::end_tag('center');

        return $html;
    }

    /**
     * Displays the assignments listing table.
     *
     * @param object $course The course odject.
     * @return string The HTML markup for this.
     */
    public function kalvidassignments_table($course) {
        global $CFG, $DB, $PAGE, $OUTPUT, $USER;

        $html = '';
        $html .= html_writer::start_tag('center');

        if (!$cms = get_coursemodules_in_course('kalvidassign', $course->id, 'm.timedue')) {
            $html .= get_string('noassignments', 'mod_kalvidassign');
            $html .= $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$course->id);
        }

        $strsectionname  = get_string('sectionname', 'format_'.$course->format);
        $usesections = course_format_uses_sections($course->format);
        $modinfo = get_fast_modinfo($course);

        if ($usesections) {
            $sections = $modinfo->get_section_info_all();
        }
        $courseindexsummary = new \mod_kalvidassign\output\course_index_summary($usesections, $strsectionname);

        $assignmentcount = 0;

        foreach ($modinfo->instances['kalvidassign'] as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            $assignmentcount++;
            $timedue = $cms[$cm->id]->timedue;

            $sectionname = '';
            if ($usesections && $cm->sectionnum) {
                $sectionname = get_section_name($course, $sections[$cm->sectionnum]);
            }

            $submitted = '';
            $context = context_module::instance($cm->id);

            if (has_capability('mod/kalvidassign:gradesubmission', $context)) {
                $submitted = $DB->count_records('kalvidassign_submission', array('vidassignid' => $cm->instance));
            } else if (has_capability('mod/kalvidassign:submit', $context)) {
                if ($DB->count_records('kalvidassign_submission', array('vidassignid' => $cm->instance, 'userid' => $USER->id)) > 0) {
                    $submitted = get_string('submitted', 'mod_kalvidassign');
                } else {
                    $submitted = get_string('nosubmission', 'mod_kalvidassign');
                }
            }

            $gradinginfo = grade_get_grades($course->id, 'mod', 'kalvidassign', $cm->instance, $USER->id);
            if (isset($gradinginfo->items[0]->grades[$USER->id]) && !$gradinginfo->items[0]->grades[$USER->id]->hidden ) {
                $grade = $gradinginfo->items[0]->grades[$USER->id]->str_grade;
            } else {
                $grade = '-';
            }

            $courseindexsummary->add_assign_info($cm->id, $cm->name, $sectionname, $timedue, $submitted, $grade);
        }

        if ($assignmentcount > 0) {
            $pagerenderer = $PAGE->get_renderer('mod_kalvidassign');
            $html .= $pagerenderer->render($courseindexsummary);
        }

        $html .= html_writer::end_tag('center');
        return $html;
    }

    /**
     * This function displays HTML markup needed by the ltipanel YUI module to display a popup window containing the LTI launch.
     * @param object $submission A Kaltura video assignment video submission table object.
     * @param int $courseid The course id.
     * @param int $cmid The ccourse module id.
     * @return string HTML markup.
     */
    public function video_container_markup($submission, $courseid, $cmid) {
        $source = new moodle_url('/local/kaltura/pix/vidThumb.png');
        $alt    = get_string('video_thumbnail', 'mod_kalvidassign');
        $title  = get_string('video_thumbnail', 'mod_kalvidassign');
        $url = null;

        $attr = array(
            'id' => 'video_thumbnail',
            'src' => $source->out(),
            'alt' => $alt,
            'title' => $title
        );

        // If the submission object contains a source URL then display the video as part of an LTI launch.
        if (!empty($submission->source)) {
            $attr['style'] = 'display: none';

            $params = array(
                'courseid' => $courseid,
                'height' => $submission->height,
                'width' => $submission->width,
                'withblocks' => 0,
                'source' => local_kaltura_add_kaf_uri_token($submission->source),
                'cmid' => $cmid
            );
            $url = new moodle_url('/mod/kalvidassign/lti_launch.php', $params);
        }

        $output = html_writer::empty_tag('img', $attr);

        $params = array(
            'id' => 'contentframe',
            'class' => 'kaltura-player-iframe',
            'src' => ($url instanceof moodle_url) ? $url->out(false) : '',
            'allowfullscreen' => "true",
            'height' => '100%',
            'width' => !empty($submission->width) ? $submission->width : ''
        );

        if (empty($submission->source)) {
            $params['style'] = 'display: none';
        }

        $iframe = html_writer::tag('iframe', '', $params);
        $iframeContainer = html_writer::tag('div', $iframe, array(
            'class' => 'kaltura-player-container'
        ));

        $output .= $iframeContainer;

        return $output;
    }

    /**
     * Display the feedback to the student
     *
     * This default method prints the teacher picture and name, date when marked,
     * grade and teacher submissioncomment.
     *
     * @param stdClass $kalvidassign
     * @param context $context
     * @return string The HTML markup for this.
     */
    public function grade_feedback($kalvidassign, $context) {
        global $USER, $CFG, $DB, $OUTPUT;

        require_once($CFG->libdir.'/gradelib.php');

        // Check if the user is enrolled to the coruse and can submit to the assignment
        if (!is_enrolled($context, $USER, 'mod/kalvidassign:submit')) {
            // can not submit assignments -> no feedback
            return '';
        }

        // Get the user's submission obj
        $gradinginfo = grade_get_grades($kalvidassign->course, 'mod', 'kalvidassign', $kalvidassign->id, $USER->id);

        $item = $gradinginfo->items[0];
        $grade = $item->grades[$USER->id];

        // Hidden or error.
        if ($grade->hidden or $grade->grade === false) {
            return '';
        }

        // Nothing to show yet.
        if ($grade->grade === null and empty($grade->str_feedback)) {
            return '';
        }

        $gradedate = $grade->dategraded;
        $gradeby   = $grade->usermodified;

        // We need the teacher info
        if (!$teacher = $DB->get_record('user', array('id'=>$gradeby))) {
            print_error('cannotfindteacher');
        }

        // Print the feedback
        $html = '';
        $html .= $OUTPUT->heading(get_string('feedbackfromteacher', 'kalvidassign', fullname($teacher)));

        $html .= '<table cellspacing="0" class="feedback">';

        $html .= '<tr>';
        $html .= '<td class="left picture">';
        if ($teacher) {
            $html .= $OUTPUT->user_picture($teacher);
        }
        $html .= '</td>';
        $html .= '<td class="topic">';
        $html .= '<div class="from">';
        if ($teacher) {
            $html .= '<div class="fullname">'.fullname($teacher).'</div>';
        }
        $html .= '<div class="time">'.userdate($gradedate).'</div>';
        $html .= '</div>';
        $html .= '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td class="left side">&nbsp;</td>';
        $html .= '<td class="content">';
        $html .= '<div class="grade">';
        $html .= get_string("grade").': '.$grade->str_long_grade;
        $html .= '</div>';
        $html .= '<div class="clearer"></div>';

        $html .= '<div class="comment">';
        $html .= $grade->str_feedback;
        $html .= '</div>';
        $html .= '</tr>';

        $html .= '</table>';
        return $html;
    }

    /**
     * Render a course index summary.
     *
     * @param \mod_kalvidassign\output\course_index_summary $indexsummary Structure for index summary.
     * @return string HTML for assignments summary table
     */
    public function render_course_index_summary(\mod_kalvidassign\output\course_index_summary $indexsummary) {
        $strplural = get_string('modulenameplural', 'kalvidassign');
        $strsectionname  = $indexsummary->courseformatname;
        $strduedate = get_string('duedate', 'kalvidassign');
        $strsubmission = get_string('submission', 'kalvidassign');
        $strgrade = get_string('grade');

        $table = new html_table();
        if ($indexsummary->usesections) {
            $table->head  = array ($strsectionname, $strplural, $strduedate, $strsubmission, $strgrade);
            $table->align = array ('left', 'left', 'center', 'right', 'right');
        } else {
            $table->head  = array ($strplural, $strduedate, $strsubmission, $strgrade);
            $table->align = array ('left', 'left', 'center', 'right');
        }
        $table->data = array();

        $currentsection = '';
        foreach ($indexsummary->assignments as $info) {
            $params = array('id' => $info['cmid']);
            $link = html_writer::link(new moodle_url('/mod/kalvidassign/view.php', $params), $info['cmname']);
            $due = $info['timedue'] ? userdate($info['timedue']) : '-';

            $printsection = '';
            if ($indexsummary->usesections) {
                if ($info['sectionname'] !== $currentsection) {
                    if ($info['sectionname']) {
                        $printsection = $info['sectionname'];
                    }
                    if ($currentsection !== '') {
                        $table->data[] = 'hr';
                    }
                    $currentsection = $info['sectionname'];
                }
            }

            if ($indexsummary->usesections) {
                $row = array($printsection, $link, $due, $info['submissioninfo'], $info['gradeinfo']);
            } else {
                $row = array($link, $due, $info['submissioninfo'], $info['gradeinfo']);
            }
            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    /**
     * Returns a submission confirmation notice and continue button.
     *
     * @param cm_info $cm
     * @return string
     */
    public function submission_confirmation($cm) {
        $url = new moodle_url('/mod/kalvidassign/view.php', array('id' => $cm->id));

        $html = $this->output->notification(get_string('assignmentsubmitted', 'kalvidassign'), 'notifysuccess');
        $html .= '<center>';
        $html .= $this->output->single_button($url, get_string('continue'), 'post');
        $html .= '</center>';

        return $html;
    }
}
