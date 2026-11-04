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
$sifirst = streamvoice_report_letter(optional_param('sifirst', '', PARAM_RAW));
$silast = streamvoice_report_letter(optional_param('silast', '', PARAM_RAW));
$page = optional_param('page', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$download = optional_param('download', 0, PARAM_BOOL);

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
if ($sifirst !== '') {
    $pageurl->param('sifirst', $sifirst);
}
if ($silast !== '') {
    $pageurl->param('silast', $silast);
}
if ($page > 0) {
    $pageurl->param('page', $page);
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

if ($download && $userid) {
    streamvoice_report_download($client, $cm, $byuser[$userid] ?? null);
}

$gradesaved = streamvoice_report_save_grades($instance, $context);
if ($gradesaved === true) {
    $back = new moodle_url($pageurl);
    if ($userid) {
        $back->param('userid', $userid);
    }
    redirect($back, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
} else if (is_string($gradesaved)) {
    $notices[] = [$gradesaved, 'error'];
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
    if ($sifirst !== '' && streamvoice_report_letter($user->firstname) !== $sifirst) {
        continue;
    }
    if ($silast !== '' && streamvoice_report_letter($user->lastname) !== $silast) {
        continue;
    }
    $visible[] = $user;
}

$perpage = (int) get_config('moodlecourse', 'participantsperpage');
if ($perpage < 1) {
    $perpage = 20;
}
$usercount = count($visible);
$lastpage = $usercount > 0 ? (int) ceil($usercount / $perpage) - 1 : 0;
if ($page < 0 || $page > $lastpage) {
    $page = 0;
    $pageurl->remove_params('page');
}
$pageusers = array_slice($visible, $page * $perpage, $perpage);

$gradinginfo = $users
    ? grade_get_grades($course->id, 'mod', 'streamvoice', $instance->id, array_keys($users))
    : (object) ['items' => []];

streamvoice_report_page_start($notices);
echo streamvoice_report_actionbar($cm, $course, $statusfilter, $search, $sifirst, $silast);
echo streamvoice_report_initials($pageurl, $sifirst, $silast);
echo streamvoice_report_paging($usercount, $page, $perpage, $pageurl);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'savegrades', 'value' => 1]);
echo streamvoice_report_table($cm, $course, $instance, $pageusers, $byuser, $gradinginfo, $pageurl);
echo html_writer::div(
    html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('savechanges'),
        'class' => 'btn btn-primary',
    ]),
    'mt-3'
);
echo html_writer::end_tag('form');
echo streamvoice_report_paging($usercount, $page, $perpage, $pageurl);
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
 * First letter of a name, using the same case as Moodle's initials bar.
 *
 * @param string $value
 * @return string
 */
function streamvoice_report_letter(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $letter = core_text::strtoupper(core_text::substr($value, 0, 1));
    if ($letter === '' || !preg_match('/^\p{L}$/u', $letter)) {
        return '';
    }
    return $letter;
}

/**
 * First-name and last-name letter bars, as on the participants page.
 *
 * @param \moodle_url $pageurl
 * @param string $sifirst
 * @param string $silast
 * @return string
 */
function streamvoice_report_initials(moodle_url $pageurl, string $sifirst, string $silast): string {
    global $OUTPUT;
    $url = new moodle_url($pageurl);
    $url->remove_params('page');
    $html = html_writer::start_div('initialbars mb-3');
    $html .= $OUTPUT->initials_bar($sifirst, 'firstinitial', get_string('firstname'), 'sifirst', $url);
    $html .= $OUTPUT->initials_bar($silast, 'lastinitial', get_string('lastname'), 'silast', $url);
    $html .= html_writer::end_div();
    return $html;
}

/**
 * Page links for the submissions list.
 *
 * @param int $total
 * @param int $page
 * @param int $perpage
 * @param \moodle_url $pageurl
 * @return string
 */
function streamvoice_report_paging(int $total, int $page, int $perpage, moodle_url $pageurl): string {
    global $OUTPUT;
    if ($total <= $perpage) {
        return '';
    }
    $bar = new paging_bar($total, $page, $perpage, $pageurl);
    $bar->pagevar = 'page';
    return $OUTPUT->render($bar);
}

/**
 * Download the locked file for one submission.
 *
 * @param \mod_streamvoice\client $client
 * @param \stdClass $cm
 * @param array<string, mixed>|null $row
 */
function streamvoice_report_download($client, \stdClass $cm, ?array $row): void {
    if (!$row || empty($row['id']) || trim((string) ($row['artifactname'] ?? '')) === '') {
        throw new moodle_exception('filenotfound', 'error');
    }
    $file = $client->download_artifact((int) $cm->id, (int) $row['id']);
    if (!$file['ok']) {
        throw new moodle_exception('filenotfound', 'error');
    }
    $name = clean_filename(basename(str_replace('\\', '/', (string) $row['artifactname'])));
    if ($name === '' || $name === '.') {
        $name = 'submission';
    }
    \core\session\manager::write_close();
    header('Content-Type: ' . mimeinfo('type', $name));
    header('Content-Length: ' . strlen($file['body']));
    header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $file['body'];
    exit;
}

/**
 * Save marker edits from the submissions table. Unchanged values are left alone.
 *
 * @param \stdClass $instance
 * @param \context $context
 * @return true|string|null true when saved, an error string, or null when this is not a save
 */
function streamvoice_report_save_grades(\stdClass $instance, \context $context) {
    if (!data_submitted() || !confirm_sesskey() || !optional_param('savegrades', 0, PARAM_BOOL)) {
        return null;
    }
    $grademax = (int) ($instance->grade ?? 0);
    if ($grademax <= 0) {
        return null;
    }
    $posted = optional_param_array('grade', [], PARAM_RAW);
    $original = optional_param_array('gradeorig', [], PARAM_RAW);
    if (!$posted) {
        return null;
    }
    $updates = [];
    foreach ($posted as $userid => $raw) {
        $userid = (int) $userid;
        if ($userid <= 0 || !is_enrolled($context, $userid, '', true)) {
            continue;
        }
        $raw = trim((string) $raw);
        $was = trim((string) ($original[$userid] ?? ''));
        if ($raw === '' || $raw === $was) {
            continue;
        }
        $value = unformat_float($raw, true);
        if ($value === false || $value < 0 || $value > $grademax) {
            return get_string('gradeoutofrange', 'streamvoice', format_float($grademax, 2));
        }
        $previous = unformat_float($was, true);
        if ($previous !== false && !grade_floats_different((float) $value, (float) $previous)) {
            continue;
        }
        $updates[$userid] = (float) $value;
    }
    foreach ($updates as $userid => $value) {
        if (!streamvoice_set_teacher_grade($instance, $userid, $value)) {
            return get_string('error');
        }
    }
    return $updates ? true : null;
}

/**
 * Editable final grade. The hidden field remembers what was shown, so an untouched score is not overridden.
 *
 * @param \stdClass $user
 * @param array<string, mixed>|null $row
 * @param \stdClass|null $gradeobj
 * @return string
 */
function streamvoice_report_grade_input(\stdClass $user, ?array $row, $gradeobj): string {
    $book = null;
    if ($gradeobj && isset($gradeobj->grade) && $gradeobj->grade !== null && $gradeobj->grade !== '') {
        $book = (float) $gradeobj->grade;
    }
    $auto = null;
    if ($row && array_key_exists('points', $row) && $row['points'] !== null && $row['points'] !== '') {
        $auto = (float) $row['points'];
    }
    $shown = $book !== null ? $book : $auto;
    $display = $shown === null ? '' : format_float($shown, 2);
    $id = 'streamvoice-grade-' . (int) $user->id;
    $html = html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'grade[' . (int) $user->id . ']',
        'id' => $id,
        'value' => $display,
        'class' => 'form-control',
        'dir' => 'ltr',
        'size' => 6,
        'style' => 'width:6rem;unicode-bidi:isolate',
        'aria-label' => get_string('finalgrade', 'grades') . ' ' . fullname($user),
    ]);
    $html .= html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'gradeorig[' . (int) $user->id . ']',
        'value' => $display,
    ]);
    return $html;
}

/**
 * The submissions bar used on the assignment grading page.
 *
 * @param \stdClass $cm
 * @param \stdClass $course
 * @param string $statusfilter
 * @param string $search
 * @param string $sifirst
 * @param string $silast
 * @return string
 */
function streamvoice_report_actionbar(
    \stdClass $cm,
    \stdClass $course,
    string $statusfilter,
    string $search,
    string $sifirst,
    string $silast
): string {
    global $OUTPUT;

    $base = new moodle_url('/mod/streamvoice/report.php', ['id' => $cm->id]);
    $selecturl = new moodle_url($base);
    if ($search !== '') {
        $selecturl->param('search', $search);
    }
    if ($sifirst !== '') {
        $selecturl->param('sifirst', $sifirst);
    }
    if ($silast !== '') {
        $selecturl->param('silast', $silast);
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
    if ($sifirst !== '') {
        $searchform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sifirst', 'value' => $sifirst]);
    }
    if ($silast !== '') {
        $searchform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'silast', 'value' => $silast]);
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
        $groupurl = new moodle_url($selecturl);
        if ($statusfilter !== '') {
            $groupurl->param('status', $statusfilter);
        }
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
            $grade = html_writer::span(
                format_float((float) $row['points'], 2) . ' / ' . format_float($grademax, 2),
                '',
                ['dir' => 'ltr', 'style' => 'unicode-bidi:isolate']
            );
        } else {
            $grade = '-';
        }

        $artifact = trim((string) ($row['artifactname'] ?? ''));
        if ($row && $artifact !== '') {
            $fileurl = new moodle_url('/mod/streamvoice/report.php', [
                'id' => $cm->id,
                'userid' => $user->id,
                'download' => 1,
            ]);
            $filename = html_writer::link($fileurl, s($artifact));
        } else {
            $filename = '-';
        }
        $modified = (int) ($row['timemodified'] ?? 0);
        $timemodified = $modified > 0 ? userdate($modified) : '-';

        $itemgrades = $gradinginfo->items[0]->grades ?? null;
        $gradeobj = (is_array($itemgrades) && isset($itemgrades[$user->id])) ? $itemgrades[$user->id] : null;
        $final = $grademax > 0
            ? streamvoice_report_grade_input($user, $row, $gradeobj)
            : '-';

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
        $gradecell = html_writer::span($grade, '', ['dir' => 'ltr', 'style' => 'unicode-bidi:isolate']);
        $weights = is_array($report['weights'] ?? null) ? $report['weights'] : [];
        if ($grade !== '-'
                && isset($report['consistency'], $report['oral'], $weights['file'], $weights['oral'])
                && $report['consistency'] !== null && $report['oral'] !== null) {
            $gradecell .= html_writer::div(
                get_string('gradebreakdown', 'streamvoice', (object) [
                    'grade' => format_float((float) $row['points'], 2),
                    'consistency' => format_float((float) $report['consistency'], 1),
                    'fileweight' => (int) $weights['file'],
                    'oral' => format_float((float) $report['oral'], 1),
                    'oralweight' => (int) $weights['oral'],
                ]),
                'text-muted small',
                ['dir' => 'ltr', 'style' => 'unicode-bidi:isolate']
            );
        }
        $summary->data[] = streamvoice_report_row(get_string('gradenoun'), $gradecell);
        $artifact = trim((string) ($row['artifactname'] ?? ''));
        if ($artifact !== '') {
            $fileurl = new moodle_url('/mod/streamvoice/report.php', [
                'id' => $cm->id,
                'userid' => $user->id,
                'download' => 1,
            ]);
            $filecell = html_writer::link($fileurl, s($artifact));
        } else {
            $filecell = '-';
        }
        $summary->data[] = streamvoice_report_row(
            get_string('pluginname', 'assignsubmission_file'),
            $filecell
        );
        if ($grademax > 0) {
            $gradeobj = null;
            $info = grade_get_grades($course->id, 'mod', 'streamvoice', $instance->id, [$user->id]);
            $itemgrades = $info->items[0]->grades ?? null;
            if (is_array($itemgrades) && isset($itemgrades[$user->id])) {
                $gradeobj = $itemgrades[$user->id];
            }
            $editor = html_writer::start_tag('form', [
                'method' => 'post',
                'action' => (new moodle_url('/mod/streamvoice/report.php', ['id' => $cm->id, 'userid' => $user->id]))->out(false),
                'class' => 'd-flex align-items-center',
            ]);
            $editor .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $editor .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'savegrades', 'value' => 1]);
            $editor .= streamvoice_report_grade_input($user, $row, $gradeobj);
            $editor .= html_writer::empty_tag('input', [
                'type' => 'submit',
                'value' => get_string('savechanges'),
                'class' => 'btn btn-secondary',
                'style' => 'margin-inline-start:.5rem',
            ]);
            $editor .= html_writer::end_tag('form');
            $summary->data[] = streamvoice_report_row(get_string('finalgrade', 'grades'), $editor);
        }
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
        . '.streamvoice-answer video,.streamvoice-answer iframe.sv-player{display:block;width:min(100%,32rem);max-width:32rem;aspect-ratio:16/9;flex:0 1 32rem;align-self:flex-start;margin:0;border:0;border-radius:.25rem;background:#1d2125}'
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
        $embed = (string) ($answer['embed'] ?? '');
        if ($embed === '' && $media !== '') {
            $parts = parse_url($media);
            $query = [];
            if (is_array($parts)) {
                parse_str((string) ($parts['query'] ?? ''), $query);
            }
            $playid = (int) ($query['videoid'] ?? 0);
            $playtoken = (string) ($query['token'] ?? '');
            if ($playid > 0 && $playtoken !== '' && !empty($parts['host'])) {
                $embed = ($parts['scheme'] ?? 'https') . '://' . $parts['host']
                    . (isset($parts['port']) ? ':' . $parts['port'] : '')
                    . '/embed/' . $playid . '?sv=' . rawurlencode($playtoken);
            }
        }
        $video = '';
        if ($embed !== '') {
            $video = html_writer::tag('iframe', '', [
                'class' => 'sv-player',
                'src' => $embed,
                'title' => get_string('answers', 'streamvoice'),
                'allow' => 'autoplay; fullscreen; encrypted-media',
                'allowfullscreen' => 'allowfullscreen',
            ]);
        } else if ($media !== '') {
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
