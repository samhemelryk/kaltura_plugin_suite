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
 * Kaltura video assignment renderable script.
 *
 * @package    mod_kalvidassign
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2014 Remote Learner.net Inc http://www.remote-learner.net
 */

namespace mod_kalvidassign\output;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use html_writer;
use moodle_url;

/**
 * Table class for displaying video submissions for grading
 */
class submissions_table extends \table_sql {
    /* @var bool Set to true if a quick grade form needs to be rendered. */
    public $quickgrade;
    /* @var stdClass An object returned from @see grade_get_grades(). */
    public $gradinginfo;
    /* @var int The course module instnace id. */
    public $cminstance;
    /* @var int The maximum grade point set for the activity instance. */
    public $grademax;
    /* @var int The number of columns of the quick grade textarea element. */
    public $cols = 20;
    /* @var int The number of rows of the quick grade textarea element. */
    public $rows = 4;
    /* @var string The first initial of the first name filter. */
    public $tifirst;
    /* @var string The first initial of the last name filter. */
    public $tilast;
    /* @var int The current page number. */
    public $page;
    /* @var int The current course ID */
    public $courseId;

    /**
     * Constructor function for the submissions table class.
     * @param int $uniqueid Unique id.
     * @param \cm_info $cm Course module.
     * @param stdClass $gradinginfo An object returned from @see grade_get_grades().
     * @param bool $quickgrade Set to true if a quick grade form needs to be rendered.
     * @param string $tifirst The first initial of the first name filter.
     * @param string $tilast The first initial of the first name filter.
     * @param int $page The current page number.
     */
    public function __construct($uniqueid, $cm, $gradinginfo, $quickgrade = false, $tifirst = '', $tilast = '', $page = 0) {
        global $DB;

        parent::__construct($uniqueid);

        $this->quickgrade = $quickgrade;
        $this->gradinginfo = $gradinginfo;

        $instance = $DB->get_record('kalvidassign', array('id' => $cm->instance), 'id,grade');

        $this->courseId = $cm->course;

        $instance->cmid = $cm->id;

        $this->cminstance = $instance;

        $this->grademax = $this->gradinginfo->items[0]->grademax;

        $this->tifirst      = $tifirst;
        $this->tilast       = $tilast;
        $this->page         = $page;
    }

    /**
     * The function renders the picture column.
     * @param stdClass $data information about the current row being rendered.
     * @return string HTML markup.
     */
    public function col_picture($data) {
        global $OUTPUT;

        $user = new stdClass();
        $user->id = $data->id;
        $user->picture = $data->picture;
        $user->imagealt = $data->imagealt;
        $user->firstname = $data->firstname;
        $user->lastname = $data->lastname;
        $user->email = $data->email;
        $user->alternatename = $data->alternatename;
        $user->middlename = $data->middlename;
        $user->firstnamephonetic = $data->firstnamephonetic;
        $user->lastnamephonetic = $data->lastnamephonetic;

        $output = $OUTPUT->user_picture($user);

        $attr = array('type' => 'hidden', 'name' => 'users['.$data->id.']', 'value' => $data->id);
        $output .= html_writer::empty_tag('input', $attr);

        return $output;
    }

    /**
     * The function renders the select grade column.
     * @param stdClass $data information about the current row being rendered.
     * @return string HTML markup.
     */
    public function col_selectgrade($data) {
        global $CFG;

        $output      = '';
        $finalgrade = false;

        if (array_key_exists($data->id, $this->gradinginfo->items[0]->grades)) {

            $finalgrade = $this->gradinginfo->items[0]->grades[$data->id];

            if ($CFG->enableoutcomes) {

                $finalgrade->formatted_grade = $this->gradinginfo->items[0]->grades[$data->id]->str_grade;
            } else {

                // Equation taken from mod/assignment/lib.php display_submissions()
                $finalgrade->formatted_grade = round($finalgrade->grade, 2).' / '.round($this->grademax, 2);
            }
        }

        if (!is_bool($finalgrade) && ($finalgrade->locked || $finalgrade->overridden) ) {

            $locked_overridden = 'locked';

            if ($finalgrade->overridden) {
                $locked_overridden = 'overridden';
            }
            $attr = array('id' => 'g'.$data->id, 'class' => $locked_overridden);

            $output = html_writer::tag('div', $finalgrade->formatted_grade, $attr);

        } else if (!empty($this->quickgrade)) {

            $attributes = array();

            $grades_menu = make_grades_menu($this->cminstance->grade);

            $default = array(-1 => get_string('nograde'));

            $grade = null;

            if (!empty($data->timemarked)) {
                $grade = $data->grade;
            }

            $output = html_writer::select($grades_menu, 'menu['.$data->id.']', $grade, $default, $attributes);

        } else {

            $output = get_string('nograde');

            if (!empty($data->timemarked)) {
                $output = $this->display_grade($data->grade);
            }
        }

        return $output;
    }

    /**
     * The function renders the submissions comment column.
     * @param stdClass $data information about the current row being rendered.
     * @return string HTML markup.
     */
    public function col_submissioncomment($data) {
        global $OUTPUT;

        $output     = '';
        $finalgrade = false;

        if (array_key_exists($data->id, $this->gradinginfo->items[0]->grades)) {
            $finalgrade = $this->gradinginfo->items[0]->grades[$data->id];
        }

        if ( (!is_bool($finalgrade) && ($finalgrade->locked || $finalgrade->overridden)) ) {

            $output = shorten_text(strip_tags($data->submissioncomment), 15);

        } else if (!empty($this->quickgrade)) {

            $param = array(
                'id' => 'comments_'.$data->submitid,
                'rows' => $this->rows,
                'cols' => $this->cols,
                'name' => 'submissioncomment['.$data->id.']');

            $output .= html_writer::start_tag('textarea', $param);
            $output .= $data->submissioncomment;
            $output .= html_writer::end_tag('textarea');

        } else {
            $output = shorten_text(strip_tags($data->submissioncomment), 15);
        }

        return $output;
    }

    /**
     * The function renders the grade marked column.
     * @param stdClass $data information about the current row being rendered.
     * @return string HTML markup.
     */
    public function col_grademarked($data) {

        $output = '';

        if (!empty($data->timemarked)) {
            $output = userdate($data->timemarked);
        }

        return $output;
    }

    /**
     * The function renders the time modified column.
     * @param stdClass $data information about the current row being rendered.
     * @return string HTML markup.
     */
    public function col_timemodified($data) {
        $data->source = local_kaltura_add_kaf_uri_token($data->source);
        $attr = array('name' => 'media_submission');
        $output = html_writer::start_tag('div', $attr);

        $attr = array('id' => 'ts'.$data->id);

        $date_modified = $data->timemodified;
        $date_modified = is_null($date_modified) || empty($data->timemodified) ? '' : userdate($date_modified);

        $output .= html_writer::tag('div', $date_modified, $attr);

        $output .= html_writer::empty_tag('br');

        // If the metadata property is empty only display an anchor tag.  Otherwise display a thumbnail image.
        if (!empty($data->entry_id)) {

            // Decode the additional video metadata.
            $metadata = local_kaltura_decode_object_for_storage($data->metadata);

            // Check if the metadata thumbnailurl property is empty.  If not then display the thumbnail.  Otherwise display a text link.
            if (!empty($metadata->thumbnailurl) && !is_null($metadata->thumbnailurl)) {

                $output .= html_writer::start_tag('center');
                $metadata = local_kaltura_decode_object_for_storage($data->metadata);

                $attr = array('src' => $metadata->thumbnailurl, 'class' => 'kalsubthumb');
                $thumbnail = html_writer::empty_tag('img', $attr);

                $attr = array('name' => 'submission_source', 'href' => $this->_generateLtiLaunchLink($data->source, $data), 'class' => 'kalsubthumbanchor');
                $output .= html_writer::tag('a', $thumbnail, $attr);
                $output .= html_writer::end_tag('center');

            } else {

                $output .= html_writer::start_tag('center');
                $attr = array('name' => 'submission_source', 'href' => $this->_generateLtiLaunchLink($data->source, $data), 'class' => 'kalsubanchor');
                $output .= html_writer::tag('a', get_string('viewsubmission', 'kalvidassign'), $attr);
                $output .= html_writer::end_tag('center');
            }
        }

        // Display hidden elements.
        if (!empty($data->entry_id)) {
            $attr = array('type' => 'hidden', 'name' => 'width', 'value' => $data->width);
            $output .= html_writer::empty_tag('input', $attr);

            $attr = array('type' => 'hidden', 'name' => 'height', 'value' => $data->height);
            $output .= html_writer::empty_tag('input', $attr);
        }

        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * The function renders the grade column.
     * @param stdClass $data information about the current row being rendered.
     * @return string HTML markup.
     */
    public function col_grade($data) {
        $finalgrade = false;

        if (array_key_exists($data->id, $this->gradinginfo->items[0]->grades)) {
            $finalgrade = $this->gradinginfo->items[0]->grades[$data->id];
        }

        $finalgrade = (!is_bool($finalgrade)) ? $finalgrade->str_grade : '-';

        $attr = array('id' => 'finalgrade_'.$data->id);
        $output = html_writer::tag('span', $finalgrade, $attr);

        return $output;
    }

    /**
     * The function renders the time marked column.
     * @param stdClass $data information about the current row being rendered.
     * @return string HTML markup.
     */
    public function col_timemarked($data) {
        $output = '-';

        if (0 < $data->timemarked) {

            $attr = array('id' => 'tt'.$data->id);
            $output = html_writer::tag('div', userdate($data->timemarked), $attr);

        } else {
            $otuput = '-';
        }

        return $output;
    }

    /**
     * The function renders the submission status column.
     * @param stdClass $data information about the current row being rendered.
     * @return string HTML markup.
     */
    public function col_status($data) {
        global $OUTPUT, $CFG;

        require_once(dirname(dirname(dirname(__FILE__))).'/lib/weblib.php');

        $url = new moodle_url('/mod/kalvidassign/single_submission.php', array('cmid' => $this->cminstance->cmid, 'userid' => $data->id, 'sesskey' => sesskey()));

        if (!empty($this->tifirst)) {
            $url->param('tifirst', $this->tifirst);
        }

        if (!empty($this->tilast)) {
            $url->param('tilast', $this->tilast);
        }

        if (!empty($this->page)) {
            $url->param('page', $this->page);
        }

        $buttontext = '';
        // Check if the user submitted the assignment.
        $submitted = !is_null($data->timemarked);

        if ($data->timemarked > 0) {
            $class = 's1';
            $buttontext = get_string('update');
        } else {
            $class = 's0';
            $buttontext  = get_string('grade');
        }

        if (!$submitted) {
            $class ='s1';
            $buttontext = get_string('nosubmission', 'kalvidassign');
        }

        $attr = array('id' => 'up'.$data->id,
            'class' => $class);

        $output = html_writer::link($url, $buttontext, $attr);

        return $output;
    }

    /**
     *  Return a grade in user-friendly form, whether it's a scale or not
     *
     * @param mixed $grade
     * @return string User-friendly representation of grade
     *
     * TODO: Move this to locallib.php
     */
    public function display_grade($grade) {
        global $DB;

        // Cache scales for each assignment - they might have different scales!!
        static $kalscalegrades = array();

        // Normal number
        if ($this->cminstance->grade >= 0) {
            if ($grade == -1) {
                return '-';
            } else {
                return $grade.' / '.$this->cminstance->grade;
            }

        } else {
            // Scale
            if (empty($kalscalegrades[$this->cminstance->id])) {

                if ($scale = $DB->get_record('scale', array('id'=>-($this->cminstance->grade)))) {

                    $kalscalegrades[$this->cminstance->id] = make_menu_from_list($scale->scale);
                } else {

                    return '-';
                }
            }

            if (isset($kalscalegrades[$this->cminstance->id][$grade])) {
                return $kalscalegrades[$this->cminstance->id][$grade];
            }
            return '-';
        }
    }

    private function _generateLtiLaunchLink($source, $data)
    {
        $cmid = $this->cminstance->cmid;

        $width = 485;
        $height = 450;
        if(isset($data->height) && isset($data->width))
        {
            $width = $data->width;
            $height = $data->height;
        }
        $realSource = local_kaltura_add_kaf_uri_token($source);
        $hashedSource = base64_encode($realSource);

        $target = new moodle_url('/mod/kalvidassign/lti_launch_grade.php?cmid='.$cmid.'&source='.urlencode($source).'&height='.$height.'&width='.$width.'&courseid='.$this->courseId);
        return $target;
    }
}