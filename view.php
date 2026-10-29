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
 * Activity view. Students submit a file; Stream runs the defense in an embedded page.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER, $PAGE, $OUTPUT;

$id = optional_param('id', 0, PARAM_INT);
$n = optional_param('n', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('streamvoice', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $instance = $DB->get_record('streamvoice', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($n) {
    $instance = $DB->get_record('streamvoice', ['id' => $n], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $instance->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('streamvoice', $instance->id, $course->id, false, MUST_EXIST);
} else {
    require_login(null, false);
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/mod/streamvoice/view.php'));
    $PAGE->set_pagelayout('standard');
    $title = get_string('modulenameplural', 'streamvoice');
    $PAGE->set_title($title);
    $PAGE->set_heading($title);

    $visible = [];
    foreach (enrol_get_my_courses() as $mycourse) {
        $modinfo = get_fast_modinfo($mycourse);
        foreach ($modinfo->get_instances_of('streamvoice') as $activity) {
            if ($activity->uservisible && $activity->url) {
                $visible[] = $activity;
            }
        }
    }
    if (count($visible) === 1) {
        redirect($visible[0]->url);
    }

    echo $OUTPUT->header();
    if (!$visible) {
        echo $OUTPUT->notification(get_string('none', 'streamvoice'), 'info');
    } else {
        echo html_writer::start_tag('ul');
        foreach ($visible as $activity) {
            $label = format_string($activity->name) . ' (' . format_string($activity->get_course()->shortname) . ')';
            echo html_writer::tag('li', html_writer::link($activity->url, $label));
        }
        echo html_writer::end_tag('ul');
    }
    echo $OUTPUT->footer();
    exit;
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/streamvoice:view', $context);

$PAGE->set_url('/mod/streamvoice/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$client = new \mod_streamvoice\client();
$cagrade = has_capability('mod/streamvoice:grade', $context);
$cansubmit = has_capability('mod/streamvoice:submit', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->name));
echo format_module_intro('streamvoice', $instance, $cm->id);

if (!$client->is_configured()) {
    echo $OUTPUT->notification(get_string('streamnotconfigured', 'streamvoice'), 'error');
    echo $OUTPUT->footer();
    exit;
}

$pushed = streamvoice_push($instance, (int) $cm->id);
if ($pushed && empty($pushed['ok'])) {
    echo $OUTPUT->notification(streamvoice_error_text($pushed), 'error');
}

if ($cagrade) {
    $listed = $client->list_submissions((int) $cm->id);
    if ($listed['ok']) {
        foreach ($listed['data']['submissions'] ?? [] as $row) {
            if (is_array($row)) {
                streamvoice_apply_remote_grade($instance, $row);
            }
        }
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
    exit;
}

if (!$cansubmit) {
    echo $OUTPUT->footer();
    exit;
}

$status = $client->submission_status((int) $cm->id, $USER);
if (!$status['ok']) {
    echo $OUTPUT->notification(streamvoice_error_text($status), 'error');
    echo $OUTPUT->footer();
    exit;
}

$submission = $status['data']['submission'] ?? null;
if (is_array($submission)) {
    streamvoice_apply_remote_grade($instance, $submission);
    $launch = $client->issue_launch((int) $cm->id, $USER, 'student');
    if ($launch['ok'] && !empty($launch['data']['url'])) {
        echo $OUTPUT->notification(get_string('lockednotice', 'streamvoice'), 'info');
        echo html_writer::tag('iframe', '', [
            'src' => $launch['data']['url'],
            'allow' => 'camera; microphone; autoplay',
            'style' => 'width:100%;min-height:80vh;border:0;background:#fff;',
            'title' => format_string($instance->name),
        ]);
    } else if (!$launch['ok']) {
        echo $OUTPUT->notification(streamvoice_error_text($launch), 'error');
    }
    echo $OUTPUT->footer();
    exit;
}

$now = time();
if (!empty($instance->timeopen) && $now < (int) $instance->timeopen) {
    echo $OUTPUT->notification(get_string('error_not_open', 'streamvoice'), 'info');
    echo $OUTPUT->footer();
    exit;
}
if (!empty($instance->timeclose) && $now > (int) $instance->timeclose) {
    echo $OUTPUT->notification(get_string('error_closed', 'streamvoice'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$maxbytes = (int) $instance->maxbytes;
if ($maxbytes <= 0) {
    $maxbytes = 20971520;
}
$form = new \mod_streamvoice\submit_form(null, ['maxbytes' => $maxbytes]);
if ($data = $form->get_data()) {
    $fs = get_file_storage();
    $usercontext = context_user::instance($USER->id);
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', (int) $data->artifact, 'id DESC', false);
    $file = $files ? reset($files) : null;
    if (!$file) {
        echo $OUTPUT->notification(get_string('error_missing_file', 'streamvoice'), 'error');
    } else {
        $tempdir = make_temp_directory('streamvoice');
        $dest = $tempdir . '/' . $file->get_filename();
        $file->copy_content_to($dest);
        $result = $client->submit_artifact(
            (int) $cm->id,
            $USER,
            $dest,
            $file->get_filename(),
            (string) ($data->note ?? ''),
            $maxbytes
        );
        @unlink($dest);
        if ($result['ok']) {
            redirect(new moodle_url('/mod/streamvoice/view.php', ['id' => $cm->id]));
        }
        echo $OUTPUT->notification(streamvoice_error_text($result), 'error');
    }
}

echo $OUTPUT->notification(get_string('beforelock', 'streamvoice'), 'info');
$form->display();
echo $OUTPUT->footer();
