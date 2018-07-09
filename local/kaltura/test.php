<?php

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/local/kaltura/locallib.php');

require_login();
require_capability('moodle/site:config', $context);

$context = context_system::instance();

$PAGE->set_url(new moodle_url('/local/kaltura/test.php'));
$PAGE->set_context($context);

echo $OUTPUT->header();

$session = local_kaltura_login(true, '', 2);

if ($session) {
    echo 'Connection successful';
} else {
    echo 'Connection not successful';
}

echo $OUTPUT->footer();
