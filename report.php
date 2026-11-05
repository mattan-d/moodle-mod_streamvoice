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
    streamvoice_report_download($client, $cm, $byuser[$userid] ?? null, (bool) optional_param('inline', 0, PARAM_BOOL));
}

$gradesaved = streamvoice_report_save_grades($instance, $context);
if ($gradesaved === true) {
    $back = new moodle_url($pageurl);
    if (optional_param('action', '', PARAM_ALPHA) === 'grader') {
        $back->param('action', 'grader');
    }
    if ($userid) {
        $back->param('userid', $userid);
    }
    redirect($back, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
} else if (is_string($gradesaved)) {
    $notices[] = [$gradesaved, 'error'];
}

if (optional_param('action', '', PARAM_ALPHA) === 'grader') {
    streamvoice_report_grader($client, $cm, $course, $instance, $users, $byuser, $userid, $notices);
    exit;
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
echo streamvoice_report_table($cm, $course, $instance, $pageusers, $byuser, $gradinginfo, $pageurl);
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
 * Show generated preview HTML inside the grading frame. The response is sandboxed.
 *
 * @param string $inner
 * @param string $css
 */
function streamvoice_report_send_html(string $inner, string $css): void {
    $html = '<!doctype html><html><head><meta charset="utf-8"><style>' . $css
        . '</style></head><body dir="auto">' . $inner . '</body></html>';
    \core\session\manager::write_close();
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Length: ' . strlen($html));
    header('Content-Disposition: inline');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: sandbox');
    echo $html;
    exit;
}

/**
 * Download the locked file for one submission.
 *
 * @param \mod_streamvoice\client $client
 * @param \stdClass $cm
 * @param array<string, mixed>|null $row
 */
function streamvoice_report_download($client, \stdClass $cm, ?array $row, bool $inline = false): void {
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
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mimetype = mimeinfo('type', $name);
    if (in_array($extension, ['html', 'htm', 'svg', 'xml'], true)) {
        $mimetype = 'text/plain';
    }
    if ($inline && in_array($extension, ['doc', 'docx'], true)) {
        $preview = streamvoice_report_docx_html($file['body']);
        if ($preview === null) {
            $preview = '<p>' . s(get_string('previewunavailable', 'streamvoice')) . '</p>';
        }
        streamvoice_report_send_html(
            $preview,
            'body{margin:0;padding:1.5rem 1.75rem;background:#fff;color:#1d2125;font:1rem/1.65 sans-serif}'
            . 'p{margin:0 0 .7rem}h1,h2,h3,h4{margin:1.15rem 0 .4rem;line-height:1.35}'
            . 'table{border-collapse:collapse;width:100%;margin:0 0 1rem}'
            . 'td{border:1px solid #dee2e6;padding:.4rem .55rem;vertical-align:top}'
        );
    }
    if ($inline && strpos($mimetype, 'text/') === 0) {
        streamvoice_report_send_html(
            s($file['body']),
            'body{margin:0;padding:1.25rem 1.5rem;background:#fff;color:#1d2125;font:1rem/1.7 sans-serif;white-space:pre-wrap;word-break:break-word}'
        );
    }
    if (strpos($mimetype, 'text/') === 0) {
        $mimetype .= '; charset=utf-8';
    }
    $disposition = $inline ? 'inline' : 'attachment';
    \core\session\manager::write_close();
    header('Content-Type: ' . $mimetype);
    header('Content-Length: ' . strlen($file['body']));
    header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
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
    $works = optional_param_array('workgrade', [], PARAM_RAW);
    $defenses = optional_param_array('defensegrade', [], PARAM_RAW);
    $feedbacks = optional_param_array('feedback', [], PARAM_RAW);
    if (!$works && !$defenses) {
        return null;
    }
    $userids = array_unique(array_merge(array_map('intval', array_keys($works)), array_map('intval', array_keys($defenses))));
    $updates = [];
    foreach ($userids as $userid) {
        if ($userid <= 0 || !is_enrolled($context, $userid, '', true)) {
            continue;
        }
        $workraw = trim((string) ($works[$userid] ?? ''));
        $defenseraw = trim((string) ($defenses[$userid] ?? ''));
        if ($workraw === '' || $defenseraw === '') {
            return get_string('bothgradesrequired', 'streamvoice');
        }
        $work = unformat_float($workraw, true);
        $defense = unformat_float($defenseraw, true);
        if ($work === false || $defense === false || $work < 0 || $defense < 0 || $work > $grademax || $defense > $grademax) {
            return get_string('gradeoutofrange', 'streamvoice', format_float($grademax, 2));
        }
        $updates[$userid] = [
            'work' => (float) $work,
            'defense' => (float) $defense,
            'grade' => streamvoice_compose_grade($instance, (float) $work, (float) $defense),
            'feedback' => array_key_exists($userid, $feedbacks) ? trim((string) $feedbacks[$userid]) : null,
        ];
    }
    $cmid = optional_param('id', 0, PARAM_INT);
    $client = new \mod_streamvoice\client();
    foreach ($updates as $userid => $update) {
        if (!streamvoice_set_teacher_grade($instance, $userid, $update['grade'], $update['feedback'])) {
            return get_string('error');
        }
        if ($client->is_configured() && $cmid > 0) {
            $payload = [
                'moodleuserid' => $userid,
                'grade' => $update['grade'],
                'workgrade' => $update['work'],
                'defensegrade' => $update['defense'],
            ];
            if ($update['feedback'] !== null) {
                $payload['feedback'] = $update['feedback'];
            }
            $released = $client->release_grade($cmid, $payload);
            if (empty($released['ok'])) {
                $message = trim((string) ($released['message'] ?? ''));
                return $message !== '' ? $message : get_string('error');
            }
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
/**
 * Teacher grading form. The AI defense score stays on this page. The saved grade is
 * the work grade and the defense grade combined by the activity weights.
 *
 * @param \stdClass $user
 * @param array<string, mixed>|null $row
 * @param array<string, mixed> $report
 * @param \stdClass $instance
 * @param \moodle_url $action
 * @param \stdClass|null $gradeobj
 * @return string
 */
function streamvoice_report_parts_form(
    \stdClass $user,
    ?array $row,
    array $report,
    \stdClass $instance,
    moodle_url $action,
    $gradeobj
): string {
    $grademax = (int) ($instance->grade ?? 0);
    if ($grademax <= 0) {
        $grademax = 100;
    }
    $fileweight = (int) ($instance->weightfile ?? 40);
    $oralweight = (int) ($instance->weightoral ?? 60);
    $oral = $report['oral'] ?? ($row['oral'] ?? null);
    $ai = streamvoice_scale_score($oral, $grademax);
    $consistency = $report['consistency'] ?? ($row['consistency'] ?? null);
    $savedwork = (isset($row['workgrade']) && $row['workgrade'] !== '' && $row['workgrade'] !== null)
        ? (float) $row['workgrade'] : null;
    $saveddefense = (isset($row['defensegrade']) && $row['defensegrade'] !== '' && $row['defensegrade'] !== null)
        ? (float) $row['defensegrade'] : null;
    $defensevalue = $saveddefense !== null ? $saveddefense : $ai;

    $html = '';
    $html .= html_writer::div(get_string('aidefense', 'streamvoice'), 'sv-label');
    $html .= html_writer::div(
        $ai === null ? '-' : format_float($ai, 2) . ' / ' . format_float($grademax, 2),
        'h3 mb-1',
        ['dir' => 'ltr', 'style' => 'unicode-bidi:isolate']
    );
    $html .= html_writer::div(get_string('aidiscretion', 'streamvoice'), 'small text-muted mb-2');
    if ($consistency !== null && $consistency !== '') {
        $html .= html_writer::div(
            get_string('consistency', 'streamvoice') . ': ' . format_float((float) $consistency, 1),
            'small mb-1',
            ['dir' => 'ltr', 'style' => 'unicode-bidi:isolate']
        );
    }
    if (!empty($row['summary'])) {
        $html .= html_writer::div(format_text((string) $row['summary'], FORMAT_PLAIN), 'small text-muted mb-3');
    }

    $finaltext = '';
    if ($savedwork !== null && $defensevalue !== null) {
        $final = streamvoice_compose_grade($instance, $savedwork, (float) $defensevalue);
        $finaltext = get_string('gradeparts', 'streamvoice', (object) [
            'final' => format_float($final, 2),
            'work' => format_float($savedwork, 2),
            'fileweight' => $fileweight,
            'defense' => format_float((float) $defensevalue, 2),
            'oralweight' => $oralweight,
        ]);
    } else if ($gradeobj && $gradeobj->grade !== null && $gradeobj->grade !== '') {
        $finaltext = format_float((float) $gradeobj->grade, 2);
    }

    $field = function (string $label, string $name, ?float $value) use ($user): string {
        $out = html_writer::div($label, 'sv-label mt-3');
        $out .= html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => $name . '[' . (int) $user->id . ']',
            'value' => $value === null ? '' : format_float($value, 2),
            'class' => 'form-control ' . ($name === 'workgrade' ? 'sv-work' : 'sv-defense'),
            'dir' => 'ltr',
            'inputmode' => 'decimal',
            'aria-label' => $label,
            'style' => 'width:8rem;unicode-bidi:isolate',
        ]);
        return $out;
    };

    $html .= html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $action->out(false),
        'class' => 'sv-grade-parts',
        'data-file-weight' => $fileweight,
        'data-oral-weight' => $oralweight,
    ]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'savegrades', 'value' => 1]);
    $html .= $field(get_string('workgrade', 'streamvoice'), 'workgrade', $savedwork);
    $html .= $field(get_string('defensegrade', 'streamvoice'), 'defensegrade', $defensevalue);
    $html .= html_writer::div(get_string('finalgrade', 'grades'), 'sv-label mt-3');
    $html .= html_writer::div($finaltext, 'sv-final h4', ['dir' => 'ltr', 'style' => 'unicode-bidi:isolate']);
    $html .= html_writer::div(get_string('feedback', 'assign'), 'sv-label mt-3');
    $html .= html_writer::tag('textarea', s((string) ($row['teacherfeedback'] ?? '')), [
        'name' => 'feedback[' . (int) $user->id . ']',
        'rows' => 4,
        'class' => 'form-control',
    ]);
    $html .= html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('savechanges'),
        'class' => 'btn btn-primary mt-3',
    ]);
    $html .= html_writer::end_tag('form');
    $html .= html_writer::script(
        "(function () {\n"
        . "    document.querySelectorAll('.sv-grade-parts').forEach(function (form) {\n"
        . "        var fileWeight = Number(form.getAttribute('data-file-weight')) || 0;\n"
        . "        var oralWeight = Number(form.getAttribute('data-oral-weight')) || 0;\n"
        . "        var out = form.querySelector('.sv-final');\n"
        . "        var paint = function () {\n"
        . "            var work = parseFloat((form.querySelector('.sv-work').value || '').replace(',', '.'));\n"
        . "            var defense = parseFloat((form.querySelector('.sv-defense').value || '').replace(',', '.'));\n"
        . "            if (!isFinite(work) || !isFinite(defense)) {\n"
        . "                return;\n"
        . "            }\n"
        . "            var finalGrade = Math.round((work * fileWeight + defense * oralWeight)) / 100;\n"
        . "            out.textContent = finalGrade.toFixed(2) + ' = '\n"
        . "                + work.toFixed(2) + ' × ' + fileWeight + '% + '\n"
        . "                + defense.toFixed(2) + ' × ' + oralWeight + '%';\n"
        . "        };\n"
        . "        form.querySelector('.sv-work').addEventListener('input', paint);\n"
        . "        form.querySelector('.sv-defense').addEventListener('input', paint);\n"
        . "    });\n"
        . "})();"
    );
    return $html;
}

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
        get_string('aidefense', 'streamvoice'),
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
        $detailurl = new moodle_url('/mod/streamvoice/report.php', [
            'id' => $cm->id,
            'action' => 'grader',
            'userid' => $user->id,
        ]);
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

        $ai = ($row && isset($row['oral']) && $row['oral'] !== null && $row['oral'] !== '')
            ? streamvoice_scale_score($row['oral'], $grademax)
            : null;
        $grade = $ai === null
            ? '-'
            : html_writer::span(
                format_float($ai, 2) . ' / ' . format_float($grademax, 2),
                '',
                ['dir' => 'ltr', 'style' => 'unicode-bidi:isolate']
            );

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
        $final = ($gradeobj && $gradeobj->grade !== null && $gradeobj->grade !== '')
            ? html_writer::span(format_float((float) $gradeobj->grade, 2), '', ['dir' => 'ltr', 'style' => 'unicode-bidi:isolate'])
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
    }
    $html .= html_writer::table($summary);
    $html .= $OUTPUT->box_end();
    $html .= $OUTPUT->container_end();

    if ($row) {
        $gradeobj = null;
        $info = grade_get_grades($course->id, 'mod', 'streamvoice', $instance->id, [$user->id]);
        $itemgrades = $info->items[0]->grades ?? null;
        if (is_array($itemgrades) && isset($itemgrades[$user->id])) {
            $gradeobj = $itemgrades[$user->id];
        }
        $html .= streamvoice_report_parts_form(
            $user,
            $row,
            $report,
            $instance,
            new moodle_url('/mod/streamvoice/report.php', ['id' => $cm->id, 'userid' => $user->id]),
            $gradeobj
        );
    }

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
        . '.streamvoice-answer .sv-qhead{display:flex;align-items:baseline;gap:.75rem}'
        . '.streamvoice-answer .sv-qhead h4{flex:1 1 auto;min-width:0;margin:0}'
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
        $head = html_writer::start_div('sv-qhead');
        $head .= html_writer::tag('h4', $number . '. ' . s((string) ($answer['prompt'] ?? '')), ['class' => 'h5 mb-0', 'dir' => 'auto']);
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
            $side .= html_writer::tag('pre', streamvoice_report_transcript((string) $answer['transcript']));
        }
        $body = html_writer::start_div('sv-body');
        $body .= $video;
        if ($side !== '') {
            $body .= html_writer::div($side, 'sv-side', ['dir' => 'auto']);
        }
        $body .= html_writer::end_div();
        $html .= $OUTPUT->box($head . $body, 'generalbox streamvoice-answer');
        $number++;
    }
    return $html;
}

/**
 * Keep timestamp ranges readable inside a right-to-left answer.
 *
 * @param string $transcript
 * @return string
 */
function streamvoice_report_transcript(string $transcript): string {
    $safe = s($transcript);
    $marked = preg_replace('/\[[0-9:.\s-]+\]/u', '<span dir="ltr">$0</span>', $safe);
    return $marked === null ? $safe : $marked;
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

/**
 * A browser can show these types inside the grading page. Other types stay a download,
 * and HTML is never rendered, so a submitted page cannot run as the teacher.
 *
 * @param string $filename
 * @return bool
 */
function streamvoice_report_previewable(string $filename): bool {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, [
        'pdf', 'txt', 'text', 'csv', 'md', 'log', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'json', 'doc', 'docx',
    ], true);
}

/**
 * Full-screen grading page: the submitted file on one side, the grade on the other.
 *
 * @param \mod_streamvoice\client $client
 * @param \stdClass $cm
 * @param \stdClass $course
 * @param \stdClass $instance
 * @param array<int, \stdClass> $users
 * @param array<int, array<string, mixed>> $byuser
 * @param int $userid
 * @param array<int, array{0: string, 1: string}> $notices
 */
function streamvoice_report_grader(
    $client,
    \stdClass $cm,
    \stdClass $course,
    \stdClass $instance,
    array $users,
    array $byuser,
    int $userid,
    array $notices
): void {
    global $DB, $OUTPUT, $PAGE;

    $ordered = array_values($users);
    if ($userid <= 0) {
        foreach ($ordered as $candidate) {
            if (isset($byuser[(int) $candidate->id])) {
                $userid = (int) $candidate->id;
                break;
            }
        }
        if ($userid <= 0 && $ordered) {
            $userid = (int) $ordered[0]->id;
        }
    }

    $pageurl = new moodle_url('/mod/streamvoice/report.php', ['id' => $cm->id, 'action' => 'grader']);
    if ($userid > 0) {
        $pageurl->param('userid', $userid);
    }
    $PAGE->set_url($pageurl);
    $PAGE->set_pagelayout('embedded');
    $PAGE->activityheader->disable();
    $PAGE->add_body_class('sv-grader-page');
    $PAGE->set_title(get_string('grading', 'assign'));
    $PAGE->set_heading(format_string($course->fullname));

    echo $OUTPUT->header();
    foreach ($notices as $notice) {
        echo $OUTPUT->notification($notice[0], $notice[1]);
    }
    if (!$ordered || $userid <= 0) {
        echo $OUTPUT->notification(get_string('nothingtodisplay'), 'info');
        echo $OUTPUT->footer();
        return;
    }

    $user = $users[$userid] ?? $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
    if (!$user) {
        throw new moodle_exception('invaliduser');
    }
    $index = 0;
    foreach ($ordered as $position => $candidate) {
        if ((int) $candidate->id === (int) $user->id) {
            $index = $position;
            break;
        }
    }
    $previous = $index > 0 ? $ordered[$index - 1] : null;
    $next = $index < count($ordered) - 1 ? $ordered[$index + 1] : null;
    $row = $byuser[(int) $user->id] ?? null;

    $options = [];
    foreach ($ordered as $candidate) {
        $options[(int) $candidate->id] = fullname($candidate);
    }
    $chooser = html_writer::start_tag('form', ['method' => 'get', 'action' => $pageurl->out(false), 'class' => 'sv-grader-chooser']);
    $chooser .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    $chooser .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'grader']);
    $chooser .= html_writer::select($options, 'userid', (int) $user->id, false, [
        'class' => 'custom-select',
        'onchange' => 'this.form.submit()',
        'aria-label' => get_string('user'),
    ]);
    $chooser .= html_writer::end_tag('form');

    $nav = html_writer::start_div('sv-grader-nav');
    $nav .= html_writer::start_div('sv-grader-title');
    $nav .= html_writer::link(new moodle_url('/course/view.php', ['id' => $course->id]), format_string($course->shortname));
    $nav .= html_writer::span(' / ', 'text-muted');
    $nav .= html_writer::link(new moodle_url('/mod/streamvoice/view.php', ['id' => $cm->id]), format_string($instance->name));
    $nav .= html_writer::div(
        html_writer::link(new moodle_url('/mod/streamvoice/report.php', ['id' => $cm->id]), get_string('viewgrading', 'assign')),
        'small'
    );
    $nav .= html_writer::end_div();
    $nav .= html_writer::div(
        $OUTPUT->user_picture($user, ['courseid' => $course->id, 'size' => 35]) . html_writer::span(fullname($user), 'sv-grader-name'),
        'sv-grader-user'
    );
    $switcher = '';
    if ($previous) {
        $switcher .= html_writer::link(
            new moodle_url($pageurl, ['userid' => $previous->id]),
            get_string('previous'),
            ['class' => 'btn btn-secondary']
        );
    }
    $switcher .= $chooser;
    if ($next) {
        $switcher .= html_writer::link(
            new moodle_url($pageurl, ['userid' => $next->id]),
            get_string('next'),
            ['class' => 'btn btn-secondary']
        );
    }
    $nav .= html_writer::div($switcher, 'sv-grader-switch');
    $nav .= html_writer::end_div();

    $filename = trim((string) ($row['artifactname'] ?? ''));
    $review = html_writer::start_div('sv-grader-review');
    if ($filename === '') {
        $review .= html_writer::div(get_string('nosubmissionyet', 'assign'), 'sv-grader-empty');
    } else {
        $downloadurl = new moodle_url('/mod/streamvoice/report.php', [
            'id' => $cm->id,
            'userid' => $user->id,
            'download' => 1,
        ]);
        $bar = html_writer::span(s($filename), 'sv-grader-filename');
        $bar .= html_writer::link($downloadurl, get_string('download'), ['class' => 'btn btn-secondary']);
        $review .= html_writer::div($bar, 'sv-grader-filebar');
        if (streamvoice_report_previewable($filename)) {
            $previewurl = new moodle_url($downloadurl, ['inline' => 1]);
            $frame = [
                'src' => $previewurl,
                'title' => $filename,
            ];
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            // Chrome will not paint a PDF or an image inside a sandboxed frame.
            if (!in_array($extension, ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
                $frame['sandbox'] = 'allow-downloads';
            }
            $review .= html_writer::tag('iframe', '', $frame);
        } else {
            $review .= html_writer::div(get_string('previewunavailable', 'streamvoice'), 'sv-grader-empty');
        }
    }
    $review .= html_writer::end_div();

    $grade = html_writer::start_div('sv-grader-grade');
    $answers = [];
    $report = [];
    if ($row && $client->is_configured()) {
        $detail = $client->submission_report((int) $cm->id, (int) ($row['id'] ?? 0));
        if ($detail['ok']) {
            $report = is_array($detail['data']['report'] ?? null) ? $detail['data']['report'] : [];
            $answers = is_array($detail['data']['answers'] ?? null) ? $detail['data']['answers'] : [];
        } else {
            $grade .= $OUTPUT->notification(streamvoice_error_text($detail), 'error');
        }
    }
    $gradeobj = null;
    $info = grade_get_grades($course->id, 'mod', 'streamvoice', $instance->id, [(int) $user->id]);
    $itemgrades = $info->items[0]->grades ?? null;
    if (is_array($itemgrades) && isset($itemgrades[$user->id])) {
        $gradeobj = $itemgrades[$user->id];
    }
    $grade .= streamvoice_report_parts_form($user, $row, $report, $instance, $pageurl, $gradeobj);
    if ($row && $client->is_configured() && ($detail['ok'] ?? false)) {
        $grade .= streamvoice_report_answers($answers);
    }
    $grade .= html_writer::end_div();

    echo html_writer::div($nav . html_writer::div($review . $grade, 'sv-grader-body'), 'sv-grader');
    echo $OUTPUT->footer();
}
