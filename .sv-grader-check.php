<?php
define('NO_DEBUG_DISPLAY', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/moodlelib.php');

$admin = get_admin();
if (!$admin) {
    throw new moodle_exception('invaliduser');
}
complete_user_login($admin);
redirect(new moodle_url('/mod/streamvoice/report.php', [
    'id' => 2528,
    'action' => 'grader',
    'userid' => 2,
]));
