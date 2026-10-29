<?php
// This file is part of Moodle - http://moodle.org/
//
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
 * Course index of Stream assignment defense activities.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $OUTPUT, $PAGE;

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$PAGE->set_url('/mod/streamvoice/index.php', ['id' => $course->id]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title($course->shortname . ': ' . get_string('modulenameplural', 'streamvoice'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'streamvoice'));

$instances = get_all_instances_in_course('streamvoice', $course);
if (!$instances) {
    notice(get_string('none', 'streamvoice'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

echo html_writer::start_tag('ul');
foreach ($instances as $instance) {
    $url = new moodle_url('/mod/streamvoice/view.php', ['id' => $instance->coursemodule]);
    echo html_writer::tag('li', html_writer::link($url, format_string($instance->name)));
}
echo html_writer::end_tag('ul');
echo $OUTPUT->footer();
