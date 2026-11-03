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
 * Grading page, laid out like the assignment submissions table.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->libdir . '/gradelib.php');

global $DB, $USER, $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT);
$statusfilter = optional_param('status', '', PARAM_ALPHA);
$search = trim(optional_param('search', '', PARAM_NOTAGS));
$userid = optional_param('userid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('streamvoice', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('streamvoice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/streamvoice:grade', $context);

$pageurl = new moodle_url('/mod/streamvoice/report.php', ['id' => $cm->id]);
if ($statusfilter !== '') {
    $pageurl->param('status', $statusfilter);
}
if ($search !== '') {
    $pageurl->param('search', $search);
}

$PAGE->set_url($pageurl);
$PAGE->set_title($context->get_course_context()->get_context_name(false, true) . ': ' .
    get_string('gradeitem:submissions', 'assign'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->navbar->add(get_string('gradeitem:submissions', 'assign'), new moodle_url('/mod/streamvoice/report.php', ['id' => $cm->id]));
$PAGE->activityheader->set_attrs([
    'description' => '',
    'hidecompletion' => true,
]);

$client = new \mod_streamvoice\client();
$notices = [];
$submissions = [];
if (!$client->is_configured()) {
    $notices[] = [get_string('streamnotconfigured', 'streamvoice'), 'error'];
} else {
    streamvoice_push($instance, (int) $cm->id);
    $listed = $client->list_submissions((int) $cm->id);
    if ($listed['ok']) {
        foreach ($listed['data']['submissions'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            streamvoice_apply_remote_grade($instance, $row);
            $submissions[] = $row;
        }
    } else {
        $notices[] = [streamvoice_error_text($listed), 'error'];
    }
}

$users = streamvoice_report_participants($context, $submissions);
$byuser = [];
foreach ($submissions as $row) {
    $ownerid = (int) ($row['moodleuserid'] ?? 0);
    if ($ownerid > 0) {
        $byuser[$ownerid] = $row;
    }
}

if ($userid) {
    $user = $users[$userid] ?? $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
    if (!$user) {
        throw new moodle_exception('invaliduser');
    }
    streamvoice_report_page_start($notices);
    echo streamvoice_report_detail($client, $cm, $course, $instance, $user, $byuser[$userid] ?? null);
    echo $OUTPUT->footer();
    exit;
}

$visible = [];
$needle = core_text::strtolower($search);
foreach ($users as $user) {
    $row = $byuser[(int) $user->id] ?? null;
    if ($search !== '') {
        $haystack = core_text::strtolower(fullname($user) . ' ' . $user->email);
        if (core_text::strpos($haystack, $needle) === false) {
            continue;
        }
    }
    if ($statusfilter === 'nosubmission' && $row) {
        continue;
    }
    if ($statusfilter === 'submitted' && !$row) {
        continue;
    }
    if ($statusfilter === 'needgrading') {
        $lines = streamvoice_grading_lines($row);
        if (!$row || $lines['gradingclass'] === 'submissiongraded') {
            continue;
        }
    }
    $visible[] = $user;
}

$gradinginfo = $users
    ? grade_get_grades($course->id, 'mod', 'streamvoice', $instance->id, array_keys($users))
    : (object) ['items' => []];

streamvoice_report_page_start($notices);
echo streamvoice_report_actionbar($cm, $course, $statusfilter, $search);
echo streamvoice_report_table($cm, $course, $instance, $visible, $byuser, $gradinginfo, $pageurl);
echo $OUTPUT->footer();

/**
 * Print the Moodle header and any Stream notices.
 *
 * @param array<int, array{0: string, 1: string}> $notices
 */
function streamvoice_report_page_start(array $notices): void {
    global $OUTPUT;
    echo $OUTPUT->header();
    // Boost Union prints Moodle's performance placeholder in the footer. This
    // site does not show performance stats, so the raw token would stay visible.
    echo html_writer::tag('style', '.footer-content-debugging{display:none !important}');
    echo html_writer::script(
        "document.addEventListener('DOMContentLoaded', function () {\n"
        . "    document.querySelectorAll('.footer-content-debugging').forEach(function (el) { el.remove(); });\n"
        . "    document.querySelectorAll('body *').forEach(function (el) {\n"
        . "        el.childNodes.forEach(function (node) {\n"
        . "            if (node.nodeType === 3 && node.nodeValue.indexOf('%%') !== -1) {\n"
        . "                node.nodeValue = node.nodeValue.replace(/%%(?:PERFORMANCEINFO|ENDHTML)-[^%]*%%/g, '');\n"
        . "            }\n"
        . "        });\n"
        . "    });\n"
        . "});"
    );
    foreach ($notices as $notice) {
        echo $OUTPUT->notification($notice[0], $notice[1]);
    }
}

/**
 * Enrolled participants, plus anyone who already submitted.
 *
 * @param \context_module $context
 * @param array<int, array<string, mixed>> $submissions
 * @return array<int, \stdClass>
 */
function streamvoice_report_participants(\context_module $context, array $submissions): array {
    global $DB;
    $users = get_enrolled_users(
        $context,
        'mod/streamvoice:submit',
        0,
        'u.*',
        'u.lastname, u.firstname, u.id',
        0,
        0,
        true
    );
    foreach ($submissions as $row) {
        $ownerid = (int) ($row['moodleuserid'] ?? 0);
        if ($ownerid > 0 && !isset($users[$ownerid])) {
            $extra = $DB->get_record('user', ['id' => $ownerid, 'deleted' => 0]);
            if ($extra) {
                $users[$ownerid] = $extra;
            }
        }
    }
    return $users;
}

/**
 * The submissions bar used on the assignment grading page.
 *
 * @param \stdClass $cm
 * @param \stdClass $course
 * @param string $statusfilter
 * @param string $search
 * @return string
 */
function streamvoice_report_actionbar(\stdClass $cm, \stdClass $course, string $statusfilter, string $search): string {
    global $OUTPUT;

    $base = new moodle_url('/mod/streamvoice/report.php', ['id' => $cm->id]);
    $selecturl = new moodle_url($base);
    if ($search !== '') {
        $selecturl->param('search', $search);
    }
    $options = [
        '' => get_string('all'),
        'nosubmission' => get_string('filternotsubmitted', 'assign'),
        'submitted' => get_string('filtersubmitted', 'assign'),
        'needgrading' => get_string('filterrequiregrading', 'assign'),
    ];
    $select = new single_select($selecturl, 'status', $options, $statusfilter, null);
    $select->set_label(get_string('status', 'assign'), ['class' => 'accesshide']);

    $html = html_writer::start_div('container-fluid tertiary-navigation full-width-bottom-border mb-2');
    $html .= html_writer::start_div('row');
    $html .= html_writer::div(html_writer::tag('h2', get_string('gradeitem:submissions', 'assign')), 'navitem');
    $html .= html_writer::div('', 'navitem-divider d-none d-sm-flex');

    $searchurl = new moodle_url($base);
    if ($statusfilter !== '') {
        $searchurl->param('status', $statusfilter);
    }
    $searchform = html_writer::start_tag('form', ['method' => 'get', 'action' => $searchurl->out(false), 'class' => 'd-flex']);
    $searchform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    if ($statusfilter !== '') {
        $searchform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'status', 'value' => $statusfilter]);
    }
    $searchform .= html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'search',
        'value' => $search,
        'class' => 'form-control',
        'placeholder' => get_string('search'),
        'aria-label' => get_string('search'),
    ]);
    $searchform .= html_writer::end_tag('form');
    $html .= html_writer::div($searchform, 'navitem');
    $html .= html_writer::div('', 'navitem-divider d-none d-sm-flex');

    if (groups_get_activity_groupmode($cm, $course)) {
        $groupurl = new moodle_url($base, $statusfilter !== '' ? ['status' => $statusfilter] : null);
        $html .= html_writer::div(groups_print_activity_menu($cm, $groupurl->out(), true), 'navitem');
        $html .= html_writer::div('', 'navitem-divider d-none d-sm-flex');
    }

    $html .= html_writer::div($OUTPUT->render($select), 'navitem');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

/**
 * Participant table matching the assignment grading table columns.
 *
 * @param \stdClass $cm
 * @param \stdClass $course
 * @param \stdClass $instance
 * @param array<int, \stdClass> $users
 * @param array<int, array<string, mixed>> $byuser
 * @param \stdClass $gradinginfo
 * @param \moodle_url $pageurl
 * @return string
 */
function streamvoice_report_table(
    \stdClass $cm,
    \stdClass $course,
    \stdClass $instance,
    array $users,
    array $byuser,
    \stdClass $gradinginfo,
    moodle_url $pageurl
): string {
    global $OUTPUT;

    $table = new flexible_table('streamvoice-grading-' . $cm->id);
    $table->define_baseurl($pageurl);
    $table->define_columns(['fullname', 'status', 'grade', 'files', 'timemodified', 'finalgrade']);
    $table->define_headers([
        get_string('fullnameuser'),
        get_string('status', 'assign'),
        get_string('gradenoun'),
        get_string('pluginname', 'assignsubmission_file'),
        get_string('lastmodifiedsubmission', 'assign'),
        get_string('finalgrade', 'grades'),
    ]);
    $table->set_attribute('class', 'generaltable flexible table table-striped table-hover gradingtable');
    $table->set_attribute('id', 'streamvoice-grading');
    $table->sortable(false);
    $table->collapsible(false);
    $table->pageable(false);
    $table->setup();

    $grademax = (int) ($instance->grade ?? 0);
    if ($grademax <= 0) {
        $grademax = 100;
    }

    foreach ($users as $user) {
        $row = $byuser[(int) $user->id] ?? null;
        $detailurl = new moodle_url('/mod/streamvoice/report.php', ['id' => $cm->id, 'userid' => $user->id]);
        $picture = $OUTPUT->user_picture($user, ['courseid' => $course->id, 'size' => 35]);
        $name = html_writer::link($detailurl, fullname($user));
        $fullname = html_writer::div($picture . $name, 'd-flex align-items-center');

        $lines = streamvoice_grading_lines($row);
        $status = html_writer::div(
            html_writer::div($lines['submission'], $lines['submissionclass']),
            'submissioninfo'
        );
        if ($lines['grading'] !== '') {
            $status .= html_writer::div($lines['grading'], $lines['gradingclass'] . ' mt-1');
        }

        if ($row && array_key_exists('points', $row) && $row['points'] !== null) {
            $grade = format_float((float) $row['points'], 2) . ' / ' . format_float($grademax, 2);
        } else {
            $grade = '-';
        }

        $filename = $row ? s((string) ($row['artifactname'] ?? '')) : '-';
        if ($filename === '') {
            $filename = '-';
        }
        $modified = (int) ($row['timemodified'] ?? 0);
        $timemodified = $modified > 0 ? userdate($modified) : '-';

        $final = '-';
        $itemgrades = $gradinginfo->items[0]->grades ?? null;
        if (is_array($itemgrades) && isset($itemgrades[$user->id]->str_grade) && $itemgrades[$user->id]->str_grade !== '') {
            $final = $itemgrades[$user->id]->str_grade;
        }

        $table->add_data([$fullname, $status, $grade, $filename, $timemodified, $final]);
    }

    ob_start();
    $table->finish_output();
    return ob_get_clean();
}

/**
 * One participant, with the defense underneath the same status summary.
 *
 * @param \mod_streamvoice\client $client
 * @param \stdClass $cm
 * @param \stdClass $course
 * @param \stdClass $instance
 * @param \stdClass $user
 * @param array<string, mixed>|null $row
 * @return string
 */
function streamvoice_report_detail($client, \stdClass $cm, \stdClass $course, \stdClass $instance, \stdClass $user, ?array $row): string {
    global $OUTPUT;

    $back = new moodle_url('/mod/streamvoice/report.php', ['id' => $cm->id]);
    $html = html_writer::div(
        html_writer::link($back, get_string('backtosubmissions', 'streamvoice'), ['class' => 'btn btn-secondary']),
        'mb-3'
    );
    $html .= html_writer::start_div('d-flex align-items-center mb-3');
    $html .= $OUTPUT->user_picture($user, ['courseid' => $course->id, 'size' => 50, 'includefullname' => false, 'class' => 'me-3']);
    $html .= $OUTPUT->heading(fullname($user), 2, 'h3 mb-0');
    $html .= html_writer::end_div();

    $answers = [];
    $report = [];
    if ($row && $client->is_configured()) {
        $detail = $client->submission_report((int) $cm->id, (int) ($row['id'] ?? 0));
        if ($detail['ok']) {
            $report = is_array($detail['data']['report'] ?? null) ? $detail['data']['report'] : [];
            $answers = is_array($detail['data']['answers'] ?? null) ? $detail['data']['answers'] : [];
        } else {
            $html .= $OUTPUT->notification(streamvoice_error_text($detail), 'error');
        }
    }

    $lines = streamvoice_grading_lines($row);
    $html .= $OUTPUT->container_start('submissionstatustable mt-3');
    $html .= $OUTPUT->heading(get_string('submissionstatusheading', 'assign'), 3);
    $html .= $OUTPUT->box_start('boxaligncenter submissionsummarytable');
    $summary = new html_table();
    $summary->attributes['class'] = 'generaltable table-bordered';
    $summary->data[] = streamvoice_report_row(get_string('status', 'assign'), html_writer::div($lines['submission'], $lines['submissionclass']));
    if ($lines['grading'] !== '') {
        $summary->data[] = streamvoice_report_row(
            get_string('gradingstatus', 'assign'),
            html_writer::div($lines['grading'], $lines['gradingclass'])
        );
    }
    if ($row) {
        $grademax = (int) ($instance->grade ?? 0);
        if ($grademax <= 0) {
            $grademax = 100;
        }
        $grade = (array_key_exists('points', $row) && $row['points'] !== null)
            ? format_float((float) $row['points'], 2) . ' / ' . format_float($grademax, 2)
            : '-';
        $summary->data[] = streamvoice_report_row(
            get_string('gradenoun'),
            html_writer::span($grade, '', ['dir' => 'ltr', 'style' => 'unicode-bidi:isolate'])
        );
        $summary->data[] = streamvoice_report_row(
            get_string('pluginname', 'assignsubmission_file'),
            s((string) ($row['artifactname'] ?? ''))
        );
        if (isset($report['consistency']) && $report['consistency'] !== null) {
            $summary->data[] = streamvoice_report_row(
                get_string('consistency', 'streamvoice'),
                format_float((float) $report['consistency'], 1)
            );
        }
        if (isset($report['oral']) && $report['oral'] !== null) {
            $summary->data[] = streamvoice_report_row(
                get_string('oralscore', 'streamvoice'),
                format_float((float) $report['oral'], 1)
            );
        }
        if (!empty($row['summary'])) {
            $summary->data[] = streamvoice_report_row(get_string('feedback', 'assign'), format_text((string) $row['summary'], FORMAT_PLAIN));
        }
    }
    $html .= html_writer::table($summary);
    $html .= $OUTPUT->box_end();
    $html .= $OUTPUT->container_end();

    if ($row && $client->is_configured() && ($detail['ok'] ?? false)) {
        $html .= streamvoice_report_answers($answers);
    }
    return $html;
}

/**
 * Recorded answers, drawn in the page instead of a nested frame.
 *
 * @param mixed $answers
 * @return string
 */
function streamvoice_report_answers($answers): string {
    global $OUTPUT;

    $html = html_writer::tag('style',
        '.streamvoice-answer .sv-body{display:flex;flex-wrap:wrap;gap:1.25rem;align-items:stretch;margin-top:1rem}'
        . '.streamvoice-answer video{display:block;width:min(100%,32rem);max-width:32rem;height:auto;flex:0 1 32rem;align-self:flex-start;margin:0;background:#1d2125;border-radius:.25rem}'
        . '.streamvoice-answer .sv-side{flex:1 1 16rem;min-width:min(100%,16rem);display:flex;flex-direction:column}'
        . '.streamvoice-answer .sv-label{font-weight:700;margin:0 0 .35rem}'
        . '.streamvoice-answer pre{flex:1;white-space:pre-wrap;min-height:8rem;max-height:22rem;overflow:auto;margin:0;background:#f8f9fa;border-radius:.25rem;padding:.75rem}'
    );
    $html .= $OUTPUT->heading(get_string('answers', 'streamvoice'), 3, 'h4 mt-4');
    if (!is_array($answers) || !$answers) {
        return $html . $OUTPUT->notification(get_string('noanswers', 'streamvoice'), 'info');
    }

    $number = 1;
    foreach ($answers as $answer) {
        if (!is_array($answer)) {
            continue;
        }
        $head = html_writer::start_div('d-flex align-items-baseline flex-wrap');
        $head .= html_writer::tag('h4', $number . '. ' . s((string) ($answer['prompt'] ?? '')), ['class' => 'h5 mb-0']);
        if (isset($answer['score']) && $answer['score'] !== null && $answer['score'] !== '') {
            $head .= html_writer::span(
                get_string('answerscore', 'streamvoice') . ' ' . s((string) $answer['score']),
                'text-nowrap font-weight-bold',
                ['style' => 'margin-inline-start:.75rem']
            );
        }
        $head .= html_writer::end_div();

        $media = (string) ($answer['media'] ?? '');
        $video = '';
        if ($media !== '') {
            $video = html_writer::tag('video', '', [
                'controls' => 'controls',
                'preload' => 'metadata',
                'src' => $media,
            ]);
        }
        $side = '';
        if (!empty($answer['feedback'])) {
            $side .= html_writer::div(format_text((string) $answer['feedback'], FORMAT_PLAIN), 'mb-3');
        }
        if (!empty($answer['transcript'])) {
            $side .= html_writer::div(get_string('transcript', 'streamvoice'), 'sv-label');
            $side .= html_writer::tag('pre', s((string) $answer['transcript']));
        }
        $body = html_writer::start_div('sv-body');
        $body .= $video;
        if ($side !== '') {
            $body .= html_writer::div($side, 'sv-side');
        }
        $body .= html_writer::end_div();
        $html .= $OUTPUT->box($head . $body, 'generalbox streamvoice-answer');
        $number++;
    }
    return $html;
}

/**
 * @param string $label
 * @param string $value
 * @return \html_table_row
 */
function streamvoice_report_row(string $label, string $value): html_table_row {
    $row = new html_table_row();
    $labelcell = new html_table_cell($label);
    $labelcell->header = true;
    $valuecell = new html_table_cell($value);
    $row->cells = [$labelcell, $valuecell];
    return $row;
}
