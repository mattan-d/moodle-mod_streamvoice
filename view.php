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
 * Activity view, laid out like a regular assignment.
 * Graders see the grading summary. Students submit a file, then continue to the defense.
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

$action = optional_param('action', '', PARAM_ALPHA);
$pageurl = new moodle_url('/mod/streamvoice/view.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$client = new \mod_streamvoice\client();
$cangrade = has_capability('mod/streamvoice:grade', $context);
$cansubmit = streamvoice_user_can_submit($context, $USER);
$seeownsummary = streamvoice_user_sees_own_summary($context, $USER);
$open = streamvoice_submissions_open($instance);

$notices = [];
$submissions = [];
$submission = null;
$submissionknown = false;

if (!$client->is_configured()) {
    $notices[] = [get_string('streamnotconfigured', 'streamvoice'), 'error'];
} else {
    $pushed = streamvoice_push($instance, (int) $cm->id);
    if ($pushed && empty($pushed['ok'])) {
        $notices[] = [streamvoice_error_text($pushed), 'error'];
    }

    if ($cangrade) {
        $listed = $client->list_submissions((int) $cm->id);
        if ($listed['ok']) {
            foreach ($listed['data']['submissions'] ?? [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                streamvoice_apply_remote_grade($instance, $row);
                $submissions[] = $row;
                if (strtolower((string) ($row['email'] ?? '')) === strtolower($USER->email)) {
                    $submission = $row;
                }
            }
            $submissionknown = true;
        } else {
            $notices[] = [streamvoice_error_text($listed), 'error'];
        }
    }

    if (($cansubmit || $seeownsummary) && !$submissionknown) {
        $status = $client->submission_status((int) $cm->id, $USER);
        if ($status['ok']) {
            $row = $status['data']['submission'] ?? null;
            $submission = is_array($row) ? $row : null;
            if ($submission) {
                streamvoice_apply_remote_grade($instance, $submission);
            }
        } else {
            $notices[] = [streamvoice_error_text($status), 'error'];
        }
    }
}

$canedit = $cansubmit && $open && (!$submission || !empty($instance->allowresubmission));
if ($action === 'editsubmission' && !$canedit) {
    $action = '';
}
if ($action === 'defense' && !$submission) {
    $action = '';
}

$maxbytes = (int) $instance->maxbytes;
if ($maxbytes <= 0) {
    $maxbytes = 20971520;
}
$form = null;
$formerror = '';
if ($action === 'editsubmission') {
    $formurl = new moodle_url('/mod/streamvoice/view.php', ['id' => $cm->id, 'action' => 'editsubmission']);
    $PAGE->set_url($formurl);
    $form = new \mod_streamvoice\submit_form($formurl, ['maxbytes' => $maxbytes]);
    if ($form->is_cancelled()) {
        redirect($pageurl);
    }
    if ($data = $form->get_data()) {
        $saved = streamvoice_view_store_artifact($client, $cm, $USER, $data, $maxbytes);
        if ($saved === true) {
            redirect($pageurl);
        }
        $formerror = $saved;
    }
}

$description = '';
if (!empty($instance->intro)) {
    $description = $OUTPUT->box(
        format_module_intro('streamvoice', $instance, $cm->id),
        'generalbox boxaligncenter'
    );
}
$PAGE->activityheader->set_attrs([
    'title' => $PAGE->activityheader->is_title_allowed() ? format_string($instance->name) : '',
    'description' => $description,
]);

echo $OUTPUT->header();
foreach ($notices as $notice) {
    echo $OUTPUT->notification($notice[0], $notice[1]);
}

if ($action === 'editsubmission' && $form) {
    if ($formerror !== '') {
        echo $OUTPUT->notification($formerror, 'error');
    }
    echo $OUTPUT->notification(get_string('beforelock', 'streamvoice'), 'info');
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

if ($cangrade) {
    $gradeurl = new moodle_url('/mod/streamvoice/report.php', ['id' => $cm->id]);
    echo html_writer::start_div('container-fluid tertiary-navigation');
    echo html_writer::start_div('row');
    echo html_writer::start_div('navitem');
    echo html_writer::link($gradeurl, get_string('gradeverb'), ['class' => 'btn btn-primary']);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo streamvoice_view_grading_summary($cm, $instance, $pageurl, count($submissions), $submissions);
}

if ($cansubmit) {
    echo streamvoice_view_submission_actions($cm, $canedit, $submission);
}
if ($seeownsummary) {
    if (!$open && !$submission) {
        $closed = !empty($instance->timeclose) && time() > (int) $instance->timeclose;
        $message = $closed ? get_string('error_closed', 'streamvoice') : get_string('error_not_open', 'streamvoice');
        if (!empty($instance->timeopen) || !empty($instance->timeclose)) {
            echo $OUTPUT->notification($message, 'info');
        }
    }
    echo streamvoice_view_submission_status($instance, $submission);
}
if ($action === 'defense' && $submission && $client->is_configured()) {
    $launch = $client->issue_launch((int) $cm->id, $USER, 'student');
    if ($launch['ok'] && !empty($launch['data']['url'])) {
        echo $OUTPUT->heading(get_string('defensehdr', 'streamvoice'), 3);
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
}

echo $OUTPUT->footer();

/**
 * Save the picked file through Stream and lock it.
 *
 * @param \mod_streamvoice\client $client
 * @param \stdClass $cm
 * @param \stdClass $user
 * @param \stdClass $data
 * @param int $maxbytes
 * @return true|string
 */
function streamvoice_view_store_artifact($client, $cm, $user, $data, $maxbytes) {
    $fs = get_file_storage();
    $usercontext = context_user::instance($user->id);
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', (int) $data->artifact, 'id DESC', false);
    $file = $files ? reset($files) : null;
    if (!$file) {
        return get_string('error_missing_file', 'streamvoice');
    }
    $tempdir = make_temp_directory('streamvoice');
    $dest = $tempdir . '/' . $file->get_filename();
    $file->copy_content_to($dest);
    $result = $client->submit_artifact(
        (int) $cm->id,
        $user,
        $dest,
        $file->get_filename(),
        (string) ($data->note ?? ''),
        $maxbytes
    );
    @unlink($dest);
    if ($result['ok']) {
        return true;
    }
    return streamvoice_error_text($result);
}

/**
 * Grading summary table, matching mod_assign.
 *
 * @param \stdClass $cm
 * @param \stdClass $instance
 * @param \moodle_url $pageurl
 * @param int $submittedcount
 * @param array<int, array<string, mixed>> $submissions
 * @return string
 */
function streamvoice_view_grading_summary($cm, $instance, moodle_url $pageurl, $submittedcount, array $submissions): string {
    global $OUTPUT;

    $needgrading = 0;
    foreach ($submissions as $row) {
        if (!array_key_exists('points', $row) || $row['points'] === null) {
            $needgrading++;
        }
    }
    $participants = count_enrolled_users(context_module::instance($cm->id), 'mod/streamvoice:submit');

    $html = $OUTPUT->container_start('gradingsummary');
    $html .= $OUTPUT->heading(get_string('gradingsummary', 'assign'), 3);
    $html .= groups_print_activity_menu($cm, $pageurl->out(), true);
    $html .= $OUTPUT->box_start('boxaligncenter gradingsummarytable');

    $table = new html_table();
    $table->attributes['class'] = 'generaltable table-bordered';
    $table->caption = get_string('gradingsummary', 'assign');
    $table->captionhide = true;
    $table->data[] = streamvoice_view_row(
        get_string('hiddenfromstudents'),
        $cm->visible ? get_string('no') : get_string('yes')
    );
    $table->data[] = streamvoice_view_row(get_string('numberofparticipants', 'assign'), (string) $participants);
    $table->data[] = streamvoice_view_row(get_string('numberofsubmittedassignments', 'assign'), (string) $submittedcount);
    $table->data[] = streamvoice_view_row(get_string('numberofsubmissionsneedgrading', 'assign'), (string) $needgrading);

    $due = (int) ($instance->timeclose ?? 0);
    if ($due > 0) {
        $remaining = $due - time();
        $table->data[] = streamvoice_view_row(
            get_string('timeremaining', 'assign'),
            $remaining <= 0 ? get_string('assignmentisdue', 'assign') : format_time($remaining)
        );
    }

    $html .= html_writer::table($table);
    $html .= $OUTPUT->box_end();
    $html .= $OUTPUT->container_end();
    return $html;
}

/**
 * Add-submission or continue-defense button, in the same bar assign uses.
 *
 * @param \stdClass $cm
 * @param bool $canedit
 * @param array<string, mixed>|null $submission
 * @return string
 */
function streamvoice_view_submission_actions($cm, $canedit, $submission): string {
    global $OUTPUT;

    $buttons = '';
    if ($canedit) {
        $url = new moodle_url('/mod/streamvoice/view.php', ['id' => $cm->id, 'action' => 'editsubmission']);
        $label = $submission ? get_string('editsubmission', 'assign') : get_string('addsubmission', 'assign');
        $buttons .= html_writer::div(
            $OUTPUT->single_button($url, $label, 'get', ['type' => single_button::BUTTON_PRIMARY]),
            'col-xs-6 me-3'
        );
    }
    if ($submission) {
        $url = new moodle_url('/mod/streamvoice/view.php', ['id' => $cm->id, 'action' => 'defense']);
        $buttons .= html_writer::div(
            $OUTPUT->single_button($url, get_string('continuedefense', 'streamvoice'), 'get', ['type' => single_button::BUTTON_PRIMARY]),
            'col-xs-6 me-3'
        );
    }
    if ($buttons === '') {
        return '';
    }
    return html_writer::div(html_writer::div($buttons, 'row'), 'container-fluid mb-4');
}

/**
 * Submission status table, matching mod_assign.
 *
 * @param \stdClass $instance
 * @param array<string, mixed>|null $submission
 * @return string
 */
function streamvoice_view_submission_status($instance, $submission): string {
    global $OUTPUT;

    $html = $OUTPUT->container_start('submissionstatustable');
    $html .= $OUTPUT->heading(get_string('submissionstatusheading', 'assign'), 3);
    $html .= $OUTPUT->box_start('boxaligncenter submissionsummarytable');

    $table = new html_table();
    $table->attributes['class'] = 'generaltable table-bordered';
    $table->caption = get_string('submissionstatusheading', 'assign');
    $table->captionhide = true;

    if ($submission) {
        $table->data[] = streamvoice_view_row(
            get_string('submissionstatus', 'assign'),
            get_string('submissionstatus_submitted', 'assign'),
            'submissionstatussubmitted'
        );
    } else {
        $table->data[] = streamvoice_view_row(
            get_string('submissionstatus', 'assign'),
            get_string('nosubmissionyet', 'assign')
        );
    }

    $graded = $submission && array_key_exists('points', $submission) && $submission['points'] !== null;
    $table->data[] = streamvoice_view_row(
        get_string('gradingstatus', 'assign'),
        get_string($graded ? 'graded' : 'notgraded', 'assign'),
        $graded ? 'submissiongraded' : 'submissionnotgraded'
    );

    $due = (int) ($instance->timeclose ?? 0);
    if ($due > 0) {
        $table->data[] = streamvoice_view_row(get_string('duedate', 'assign'), userdate($due));
        $remaining = $due - time();
        $table->data[] = streamvoice_view_row(
            get_string('timeremaining', 'assign'),
            $remaining <= 0 ? get_string('assignmentisdue', 'assign') : format_time($remaining)
        );
    }

    $modified = (int) ($submission['timemodified'] ?? 0);
    $table->data[] = streamvoice_view_row(
        get_string('timemodified', 'assign'),
        $modified > 0 ? userdate($modified) : '-'
    );
    if ($submission) {
        $filename = s((string) ($submission['artifactname'] ?? ''));
        $table->data[] = streamvoice_view_row(get_string('pluginname', 'assignsubmission_file'), $filename);
    }

    $html .= html_writer::table($table);
    $html .= $OUTPUT->box_end();
    $html .= $OUTPUT->container_end();
    return $html;
}

/**
 * One label/value row, with the label in a header cell.
 *
 * @param string $label
 * @param string $value
 * @param string $valueclass
 * @return \html_table_row
 */
function streamvoice_view_row(string $label, string $value, string $valueclass = ''): html_table_row {
    $row = new html_table_row();
    $labelcell = new html_table_cell($label);
    $labelcell->header = true;
    $valuecell = new html_table_cell($value);
    if ($valueclass !== '') {
        $valuecell->attributes['class'] = $valueclass;
    }
    $row->cells = [$labelcell, $valuecell];
    return $row;
}
