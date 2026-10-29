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
 * Teacher entry. Pulls grades, then embeds the Stream report.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER, $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('streamvoice', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('streamvoice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/streamvoice:grade', $context);

$PAGE->set_url('/mod/streamvoice/report.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report', 'streamvoice'));

$client = new \mod_streamvoice\client();
if (!$client->is_configured()) {
    echo $OUTPUT->notification(get_string('streamnotconfigured', 'streamvoice'), 'error');
    echo $OUTPUT->footer();
    exit;
}

streamvoice_push($instance, (int) $cm->id);
$listed = $client->list_submissions((int) $cm->id);
if ($listed['ok']) {
    foreach ($listed['data']['submissions'] ?? [] as $row) {
        if (is_array($row)) {
            streamvoice_apply_remote_grade($instance, $row);
        }
    }
} else {
    echo $OUTPUT->notification(streamvoice_error_text($listed), 'error');
}

$launch = $client->issue_launch((int) $cm->id, $USER, 'teacher');
if ($launch['ok'] && !empty($launch['data']['url'])) {
    echo html_writer::tag('iframe', '', [
        'src' => $launch['data']['url'],
        'style' => 'width:100%;min-height:80vh;border:0;background:#fff;',
        'title' => get_string('report', 'streamvoice'),
    ]);
} else if (!$launch['ok']) {
    echo $OUTPUT->notification(streamvoice_error_text($launch), 'error');
}

echo $OUTPUT->footer();
