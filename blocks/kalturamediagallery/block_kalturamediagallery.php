<?php

defined('MOODLE_INTERNAL') || die();

class block_kalturamediagallery extends block_base {

    /**
     * Sets this block up.
     */
    public function init() {
        $this->title = get_string('pluginname', 'local_kalturamediagallery');
    }

    /**
     * Returns the content of this block.
     * @return stdClass
     */
    public function get_content() {
        if (!is_null($this->content)) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Get the course context here, not just the course, to ensure that we are actually within a course.
        // $this->page->course will be the frontpage course when not actually in a course.
        if ($context = $this->getCourseContext()) {
            $this->content->text = $this->getKalturaMediaGalleryLink($context->instanceid);
        }

        return $this->content;
    }

    /**
     * Return the applicable formats for this block.
     *
     * @return array
     */
    public function applicable_formats() {
        return array(
            'course-view' => true, // Can be added to the course view page.
        );
    }

    /**
     * Returns the URL to the Kaltura media gallery
     *
     * @param int $courseId
     * @return string
     */
    private function getKalturaMediaGalleryLink($courseId) {

        $mediaGalleryUrl = new moodle_url('/local/kalturamediagallery/index.php', array(
            'courseid' => $courseId
        ));
        $link = html_writer::link($mediaGalleryUrl, get_string('nav_mediagallery', 'local_kalturamediagallery'));

        return $link;
    }

    /**
     * Get the current course context.
     *
     * If the context is not of a course or module then return false.
     *
     * @return context_course|false
     */
    private function getCourseContext() {
        return $this->page->context->get_course_context(false);
    }
}