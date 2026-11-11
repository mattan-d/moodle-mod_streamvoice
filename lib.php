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
 * Activity shell for Stream assignment defense. Defense logic is implemented in Stream.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Supported features.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed
 */
function streamvoice_supports($feature) {
    if (defined('FEATURE_MOD_PURPOSE') && $feature === FEATURE_MOD_PURPOSE) {
        return defined('MOD_PURPOSE_ASSESSMENT') ? MOD_PURPOSE_ASSESSMENT : null;
    }
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_ADVANCED_GRADING:
            return true;
        default:
            return null;
    }
}

/**
 * Keep the activity logo in its own colours in the activity chooser.
 *
 * @return bool
 */
function streamvoice_is_branded(): bool {
    return true;
}

/**
 * @param \stdClass $data
 * @param mixed $mform
 * @return int
 */
function streamvoice_add_instance(\stdClass $data, $mform = null) {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('streamvoice', $data);
    streamvoice_grade_item_update($data);
    $cmid = (int) ($data->coursemodule ?? 0);
    if ($cmid) {
        streamvoice_push($data, $cmid);
    }
    return $data->id;
}

/**
 * @param \stdClass $data
 * @param mixed $mform
 * @return bool
 */
function streamvoice_update_instance(\stdClass $data, $mform = null) {
    global $DB;
    $data->timemodified = time();
    $data->id = $data->instance;
    $result = $DB->update_record('streamvoice', $data);
    streamvoice_grade_item_update($data);
    $cmid = (int) ($data->coursemodule ?? 0);
    if ($cmid) {
        streamvoice_push($data, $cmid);
    }
    return $result;
}

/**
 * @param int $id
 * @return bool
 */
function streamvoice_delete_instance($id) {
    global $DB;
    $instance = $DB->get_record('streamvoice', ['id' => $id]);
    if (!$instance) {
        return false;
    }
    $cm = get_coursemodule_from_instance('streamvoice', $instance->id, $instance->course, false, IGNORE_MISSING);
    if ($cm) {
        $client = new \mod_streamvoice\client();
        $client->delete_activity((int) $cm->id);
    }
    $DB->delete_records('streamvoice', ['id' => $id]);
    streamvoice_grade_item_update($instance, null, true);
    return true;
}

/**
 * Send the activity definition to Stream. Failures are reported by the caller.
 *
 * @param \stdClass $instance
 * @param int $cmid
 * @return array{ok: bool, message: string, code: string, data: array<string, mixed>}|null
 */
function streamvoice_push(\stdClass $instance, int $cmid): ?array {
    $client = new \mod_streamvoice\client();
    if (!$client->is_configured()) {
        return null;
    }
    return $client->upsert_activity($instance, $cmid);
}

/**
 * Duplication renames the activity after restore, so Stream is updated from the final name.
 *
 * @param \core\event\course_module_created $event
 */
function streamvoice_course_module_created(\core\event\course_module_created $event): void {
    global $DB;
    if (($event->other['modulename'] ?? '') !== 'streamvoice') {
        return;
    }
    $instance = $DB->get_record('streamvoice', ['id' => (int) $event->other['instanceid']]);
    if (!$instance) {
        return;
    }
    streamvoice_push($instance, (int) $event->objectid);
    streamvoice_collapse_grade_items($instance);
}

/**
 * Copy a Stream score into the gradebook when Stream has one.
 *
 * @param \stdClass $instance
 * @param array<string, mixed> $row
 */
function streamvoice_apply_remote_grade(\stdClass $instance, array $row): void {
    // The recommended score stays in Stream for the teacher. The gradebook
    // receives a grade only when the teacher saves a final grade.
}

/**
 * Keep one grade item per activity. A course copy can restore the gradebook
 * item and also create one here, and Moodle then refuses to store the score.
 *
 * @param \stdClass $streamvoice
 */
function streamvoice_collapse_grade_items(\stdClass $streamvoice): void {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $items = $DB->get_records('grade_items', [
        'courseid' => $streamvoice->course,
        'itemtype' => 'mod',
        'itemmodule' => 'streamvoice',
        'iteminstance' => $streamvoice->id,
        'itemnumber' => 0,
    ], 'id ASC');
    if (count($items) < 2) {
        return;
    }
    $keepid = (int) reset($items)->id;
    foreach ($items as $item) {
        if ($DB->record_exists_select('grade_grades', 'itemid = ? AND rawgrade IS NOT NULL', [$item->id])) {
            $keepid = (int) $item->id;
            break;
        }
    }
    foreach ($items as $item) {
        if ((int) $item->id === $keepid) {
            continue;
        }
        if ($DB->record_exists_select('grade_grades', 'itemid = ? AND rawgrade IS NOT NULL', [$item->id])) {
            continue;
        }
        $gradeitem = grade_item::fetch(['id' => $item->id]);
        if ($gradeitem) {
            $gradeitem->delete('mod/streamvoice');
        }
    }
}

/**
 * @param \stdClass $streamvoice
 * @param mixed $grades
 * @param bool $delete
 * @return int
 */
function streamvoice_grade_item_update($streamvoice, $grades = null, $delete = false) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if (!$delete) {
        streamvoice_collapse_grade_items($streamvoice);
    }
    $item = ['itemname' => $streamvoice->name];
    if (!empty($streamvoice->cmidnumber)) {
        $item['idnumber'] = $streamvoice->cmidnumber;
    }
    if ($delete) {
        return grade_update(
            'mod/streamvoice',
            $streamvoice->course,
            'mod',
            'streamvoice',
            $streamvoice->id,
            0,
            null,
            $item,
            ['deleted' => 1]
        );
    }
    $grade = (int) ($streamvoice->grade ?? 0);
    if ($grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax'] = $grade;
        $item['grademin'] = 0;
    } else if ($grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid'] = -$grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }
    return grade_update(
        'mod/streamvoice',
        $streamvoice->course,
        'mod',
        'streamvoice',
        $streamvoice->id,
        0,
        $grades,
        $item
    );
}

/**
 * Moodle asks the activity for its grades when the course gradebook is refreshed.
 * The saved final grade already lives in the gradebook, so a refresh must not replace it.
 *
 * @param \stdClass $instance
 * @param int $userid
 */
function streamvoice_update_grades($instance, $userid = 0) {
    streamvoice_grade_item_update($instance);
}

/**
 * Store the teacher's final grade in the course gradebook and recalculate the course total.
 *
 * @param \stdClass $instance
 * @param int $userid
 * @param float $finalgrade
 * @param string|null $feedback Written feedback. Null leaves the existing feedback unchanged.
 * @return bool
 */
function streamvoice_set_teacher_grade(\stdClass $instance, int $userid, float $finalgrade, ?string $feedback = null): bool {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->libdir . '/grade/grade_item.php');
    require_once($CFG->libdir . '/grade/grade_grade.php');

    streamvoice_grade_item_update($instance);
    $item = \grade_item::fetch([
        'courseid' => $instance->course,
        'itemtype' => 'mod',
        'itemmodule' => 'streamvoice',
        'iteminstance' => $instance->id,
        'itemnumber' => 0,
    ]);
    if (!$item || (int) $item->gradetype === GRADE_TYPE_NONE) {
        return false;
    }

    $existing = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
    if ($existing && !empty($existing->overridden)) {
        $existing->grade_item = $item;
        $existing->set_overridden(false, false);
    }

    $grades = new \stdClass();
    $grades->userid = $userid;
    $grades->rawgrade = $finalgrade;
    if ($feedback !== null) {
        $grades->feedback = $feedback;
        $grades->feedbackformat = FORMAT_PLAIN;
    }
    if (streamvoice_grade_item_update($instance, $grades) != GRADE_UPDATE_OK) {
        return false;
    }

    $regraded = grade_regrade_final_grades($instance->course, $userid, $item);
    if ($regraded !== true) {
        $regraded = grade_regrade_final_grades($instance->course);
    }
    if ($regraded !== true) {
        return false;
    }

    $saved = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
    return $saved && $saved->finalgrade !== null
        && !grade_floats_different((float) $saved->finalgrade, $finalgrade);
}

/**
 * Scale a 0–100 AI score onto the activity grade range.
 *
 * @param mixed $score
 * @param int $grademax
 * @return float|null
 */
function streamvoice_scale_score($score, int $grademax): ?float {
    if ($score === null || $score === '') {
        return null;
    }
    return round(((float) $score) / 100 * $grademax, 2);
}

/**
 * Final grade from the teacher's work grade and defense grade.
 *
 * @param \stdClass $instance
 * @param float $work
 * @param float $defense
 * @return float
 */
function streamvoice_compose_grade(\stdClass $instance, float $work, float $defense): float {
    $file = (int) ($instance->weightfile ?? 40);
    $oral = (int) ($instance->weightoral ?? 60);
    if ($file < 0 || $oral < 0 || $file + $oral !== 100) {
        $file = 40;
        $oral = 60;
    }
    return round(($work * $file + $defense * $oral) / 100, 2);
}

/**
 * @param \stdClass $cm
 * @return \cached_cm_info|null
 */
function streamvoice_get_coursemodule_info($cm) {
    global $DB;
    $instance = $DB->get_record('streamvoice', ['id' => $cm->instance], 'id, name, intro, introformat');
    if (!$instance) {
        return null;
    }
    $info = new \cached_cm_info();
    $info->name = $instance->name;
    $info->content = format_module_intro('streamvoice', $instance, $cm->id, false);
    return $info;
}

/**
 * @param \settings_navigation $settings
 * @param \navigation_node $navref
 */
function streamvoice_extend_settings_navigation(\settings_navigation $settings, \navigation_node $navref) {
    global $PAGE;
    if (empty($PAGE->cm) || $PAGE->cm->modname !== 'streamvoice') {
        return;
    }
    if (has_capability('mod/streamvoice:grade', $PAGE->cm->context)) {
        $navref->add(
            get_string('report', 'streamvoice'),
            new \moodle_url('/mod/streamvoice/report.php', ['id' => $PAGE->cm->id]),
            \navigation_node::TYPE_SETTING
        );
    }
}

/**
 * Whether this user may submit a file.
 *
 * Site administrators pass the capability check, matching mod_assign.
 * An enrolled student passes even when another role is also assigned.
 *
 * @param \context_module $context
 * @param \stdClass $user
 * @return bool
 */
function streamvoice_user_can_submit(\context_module $context, \stdClass $user): bool {
    if (has_capability('mod/streamvoice:submit', $context, $user)) {
        return true;
    }
    return streamvoice_user_is_course_student($context, $user);
}

/**
 * Whether to show this user's own submission status table.
 *
 * mod_assign shows that table only with viewownsubmissionsummary, and it
 * ignores the site-admin override. Students, including a switched role, see it.
 *
 * @param \context_module $context
 * @param \stdClass $user
 * @return bool
 */
function streamvoice_user_sees_own_summary(\context_module $context, \stdClass $user): bool {
    if (has_capability('mod/streamvoice:submit', $context, $user, false)) {
        return true;
    }
    return streamvoice_user_is_course_student($context, $user);
}

/**
 * Enrolled user whose roles in this course include student.
 *
 * @param \context_module $context
 * @param \stdClass $user
 * @return bool
 */
function streamvoice_user_is_course_student(\context_module $context, \stdClass $user): bool {
    $coursecontext = $context->get_course_context();
    if (!is_enrolled($coursecontext, $user, '', true)) {
        return false;
    }
    foreach (get_user_roles($coursecontext, (int) $user->id, true) as $role) {
        if ($role->shortname === 'student') {
            return true;
        }
    }
    return false;
}

/**
 * Labels for one grading-table row, in the same shape as mod_assign.
 *
 * Stream keeps internal codes such as awaiting_model. Those stay in the API.
 * This returns the words a marker should see.
 *
 * @param array<string, mixed>|null $row
 * @return array{submission: string, submissionclass: string, grading: string, gradingclass: string}
 */
function streamvoice_grading_lines(?array $row): array {
    if (!$row) {
        return [
            'submission' => get_string('submissionstatus_', 'assign'),
            'submissionclass' => 'submissionstatus',
            'grading' => '',
            'gradingclass' => '',
        ];
    }

    $reportstatus = (string) ($row['reportstatus'] ?? '');
    $status = (string) ($row['status'] ?? '');
    $graded = !empty($row['released']);
    $lines = [
        'submission' => get_string('submissionstatus_submitted', 'assign'),
        'submissionclass' => 'submissionstatussubmitted',
        'grading' => '',
        'gradingclass' => '',
    ];
    if ($graded) {
        $lines['grading'] = get_string('graded', 'assign');
        $lines['gradingclass'] = 'submissiongraded';
        return $lines;
    }

    $pending = [
        'awaiting_model' => 'status_awaiting_model',
        'awaiting_transcript' => 'status_awaiting_transcript',
        'provisional' => 'status_provisional',
    ];
    if (isset($pending[$reportstatus])) {
        $lines['grading'] = get_string($pending[$reportstatus], 'streamvoice');
        $lines['gradingclass'] = 'submissionnotgraded';
        return $lines;
    }
    if ($status === 'prep') {
        $lines['grading'] = get_string('status_prep', 'streamvoice');
    } else if ($status === 'recording') {
        $lines['grading'] = get_string('status_recording', 'streamvoice');
    } else {
        $lines['grading'] = get_string('notgraded', 'assign');
    }
    $lines['gradingclass'] = 'submissionnotgraded';
    return $lines;
}

/**
 * Whether the activity window currently accepts a file.
 *
 * @param \stdClass $instance
 * @return bool
 */
function streamvoice_submissions_open(\stdClass $instance): bool {
    $now = time();
    if (!empty($instance->timeopen) && $now < (int) $instance->timeopen) {
        return false;
    }
    if (!empty($instance->timeclose) && $now > (int) $instance->timeclose) {
        return false;
    }
    return true;
}

/**
 * Map a Stream error code to a language string when one exists.
 *
 * @param array{ok: bool, message: string, code: string, data: array<string, mixed>} $result
 * @return string
 */
/**
 * A Word document cannot be painted by the browser. Turn a docx package into the text a marker reads.
 *
 * Only text nodes are copied, and they are escaped, so the document cannot run as the teacher.
 *
 * @param string $bytes
 * @return string|null HTML fragment, or null when the bytes are not a docx package
 */
function streamvoice_report_docx_html(string $bytes): ?string {
    if (strncmp($bytes, 'PK', 2) !== 0 || !class_exists('ZipArchive') || strlen($bytes) > 20000000) {
        return null;
    }
    $path = tempnam(sys_get_temp_dir(), 'svdocx');
    if ($path === false) {
        return null;
    }
    file_put_contents($path, $bytes);
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        @unlink($path);
        return null;
    }
    $document = $zip->getFromName('word/document.xml');
    $numbering = $zip->getFromName('word/numbering.xml');
    $styles = $zip->getFromName('word/styles.xml');
    $zip->close();
    @unlink($path);
    if (!is_string($document) || $document === '' || strlen($document) > 8000000) {
        return null;
    }

    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $dom->loadXML($document, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded || !$dom->documentElement) {
        return null;
    }

    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $headings = streamvoice_report_docx_headings(is_string($styles) ? $styles : '');
    $formats = streamvoice_report_docx_numbering(is_string($numbering) ? $numbering : '');
    $counters = [];
    $html = streamvoice_report_docx_children($dom->documentElement, $ns, $headings, $formats, $counters);
    $html = trim($html);
    return $html === '' ? null : $html;
}

/**
 * Paragraph styles whose name is a heading.
 *
 * @param string $xml
 * @return array<string, int>
 */
function streamvoice_report_docx_headings(string $xml): array {
    $headings = [];
    if ($xml === '') {
        return $headings;
    }
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $dom->loadXML($xml, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return $headings;
    }
    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    foreach ($dom->getElementsByTagNameNS($ns, 'style') as $style) {
        if ($style->getAttributeNS($ns, 'type') !== 'paragraph') {
            continue;
        }
        $id = $style->getAttributeNS($ns, 'styleId');
        if ($id === '') {
            continue;
        }
        $level = 0;
        foreach ($style->childNodes as $child) {
            if (!$child instanceof DOMElement || $child->namespaceURI !== $ns) {
                continue;
            }
            if ($child->localName === 'name' && preg_match('/heading\s*([1-6])/i', $child->getAttributeNS($ns, 'val'), $match)) {
                $level = (int) $match[1];
            }
            if ($child->localName === 'pPr') {
                foreach ($child->childNodes as $prop) {
                    if ($prop instanceof DOMElement && $prop->localName === 'outlineLvl' && $prop->namespaceURI === $ns) {
                        $level = min(6, (int) $prop->getAttributeNS($ns, 'val') + 1);
                    }
                }
            }
        }
        if ($level > 0) {
            $headings[$id] = $level;
        }
    }
    return $headings;
}

/**
 * List format for each numbering id and level.
 *
 * @param string $xml
 * @return array<string, array<int, string>>
 */
function streamvoice_report_docx_numbering(string $xml): array {
    $formats = [];
    if ($xml === '') {
        return $formats;
    }
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $dom->loadXML($xml, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return $formats;
    }
    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $abstract = [];
    foreach ($dom->getElementsByTagNameNS($ns, 'abstractNum') as $node) {
        $id = $node->getAttributeNS($ns, 'abstractNumId');
        foreach ($node->getElementsByTagNameNS($ns, 'lvl') as $level) {
            if ($level->parentNode !== $node) {
                continue;
            }
            $fmt = 'bullet';
            foreach ($level->childNodes as $child) {
                if ($child instanceof DOMElement && $child->localName === 'numFmt' && $child->namespaceURI === $ns) {
                    $fmt = $child->getAttributeNS($ns, 'val') ?: 'bullet';
                }
            }
            $abstract[$id][(int) $level->getAttributeNS($ns, 'ilvl')] = $fmt;
        }
    }
    foreach ($dom->getElementsByTagNameNS($ns, 'num') as $node) {
        $id = $node->getAttributeNS($ns, 'numId');
        $link = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'abstractNumId' && $child->namespaceURI === $ns) {
                $link = $child->getAttributeNS($ns, 'val');
            }
        }
        if ($id !== '' && isset($abstract[$link])) {
            $formats[$id] = $abstract[$link];
        }
    }
    return $formats;
}

/**
 * @param DOMElement $node
 * @param array<string, int> $headings
 * @param array<string, array<int, string>> $formats
 * @param array<string, array<int, int>> $counters
 */
function streamvoice_report_docx_children(
    DOMElement $node,
    string $ns,
    array $headings,
    array $formats,
    array &$counters
): string {
    $html = '';
    foreach ($node->childNodes as $child) {
        if (!$child instanceof DOMElement || $child->namespaceURI !== $ns) {
            continue;
        }
        if ($child->localName === 'p') {
            $html .= streamvoice_report_docx_paragraph($child, $ns, $headings, $formats, $counters);
        } else if ($child->localName === 'tbl') {
            $html .= streamvoice_report_docx_table($child, $ns, $headings, $formats, $counters);
        } else if ($child->localName !== 'r' && $child->localName !== 'del') {
            $html .= streamvoice_report_docx_children($child, $ns, $headings, $formats, $counters);
        }
    }
    return $html;
}

/**
 * @param array<string, int> $headings
 * @param array<string, array<int, string>> $formats
 * @param array<string, array<int, int>> $counters
 */
function streamvoice_report_docx_paragraph(
    DOMElement $paragraph,
    string $ns,
    array $headings,
    array $formats,
    array &$counters
): string {
    $style = '';
    $level = 0;
    $bidi = false;
    $numid = '';
    $ilvl = 0;
    foreach ($paragraph->childNodes as $child) {
        if (!$child instanceof DOMElement || $child->namespaceURI !== $ns || $child->localName !== 'pPr') {
            continue;
        }
        foreach ($child->childNodes as $prop) {
            if (!$prop instanceof DOMElement || $prop->namespaceURI !== $ns) {
                continue;
            }
            if ($prop->localName === 'pStyle') {
                $style = $prop->getAttributeNS($ns, 'val');
            } else if ($prop->localName === 'bidi') {
                $bidi = $prop->getAttributeNS($ns, 'val') !== '0';
            } else if ($prop->localName === 'outlineLvl') {
                $level = min(6, (int) $prop->getAttributeNS($ns, 'val') + 1);
            } else if ($prop->localName === 'numPr') {
                foreach ($prop->childNodes as $num) {
                    if (!$num instanceof DOMElement || $num->namespaceURI !== $ns) {
                        continue;
                    }
                    if ($num->localName === 'numId') {
                        $numid = $num->getAttributeNS($ns, 'val');
                    } else if ($num->localName === 'ilvl') {
                        $ilvl = (int) $num->getAttributeNS($ns, 'val');
                    }
                }
            }
        }
    }
    if ($level === 0 && isset($headings[$style])) {
        $level = $headings[$style];
    }
    $text = streamvoice_report_docx_runs($paragraph, $ns);
    if (trim(strip_tags($text)) === '' && $numid === '') {
        return '<p>&#160;</p>';
    }
    $marker = '';
    if ($numid !== '' && $numid !== '0') {
        $counters[$numid][$ilvl] = ($counters[$numid][$ilvl] ?? 0) + 1;
        foreach (array_keys($counters[$numid]) as $deeper) {
            if ($deeper > $ilvl) {
                unset($counters[$numid][$deeper]);
            }
        }
        $fmt = $formats[$numid][$ilvl] ?? 'bullet';
        $marker = ($fmt === 'decimal' || $fmt === 'decimalZero')
            ? $counters[$numid][$ilvl] . '. '
            : '• ';
    }
    $tag = $level > 0 ? 'h' . min(4, $level) : 'p';
    $dir = $bidi ? 'rtl' : 'auto';
    $indent = $ilvl > 0 ? ' style="padding-inline-start:' . (1.25 * $ilvl) . 'rem"' : '';
    return '<' . $tag . ' dir="' . $dir . '"' . $indent . '>' . s($marker) . $text . '</' . $tag . '>';
}

/**
 * @param array<string, int> $headings
 * @param array<string, array<int, string>> $formats
 * @param array<string, array<int, int>> $counters
 */
function streamvoice_report_docx_table(
    DOMElement $table,
    string $ns,
    array $headings,
    array $formats,
    array &$counters
): string {
    $html = '<table>';
    foreach ($table->childNodes as $row) {
        if (!$row instanceof DOMElement || $row->namespaceURI !== $ns || $row->localName !== 'tr') {
            continue;
        }
        $html .= '<tr>';
        foreach ($row->childNodes as $cell) {
            if (!$cell instanceof DOMElement || $cell->namespaceURI !== $ns || $cell->localName !== 'tc') {
                continue;
            }
            $html .= '<td>' . streamvoice_report_docx_children($cell, $ns, $headings, $formats, $counters) . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</table>';
}

/**
 * Visible text of a paragraph. Deleted revisions are left out.
 */
function streamvoice_report_docx_runs(DOMElement $node, string $ns): string {
    $html = '';
    foreach ($node->childNodes as $child) {
        if (!$child instanceof DOMElement || $child->namespaceURI !== $ns || $child->localName === 'pPr') {
            continue;
        }
        if ($child->localName === 'del') {
            continue;
        }
        if ($child->localName === 't') {
            $html .= s($child->textContent);
            continue;
        }
        if ($child->localName === 'tab') {
            $html .= '&#160;&#160;&#160;&#160;';
            continue;
        }
        if ($child->localName === 'br' || $child->localName === 'cr') {
            $html .= '<br>';
            continue;
        }
        $inner = streamvoice_report_docx_runs($child, $ns);
        if ($child->localName !== 'r' || $inner === '') {
            $html .= $inner;
            continue;
        }
        $bold = false;
        $italic = false;
        foreach ($child->childNodes as $prop) {
            if (!$prop instanceof DOMElement || $prop->namespaceURI !== $ns || $prop->localName !== 'rPr') {
                continue;
            }
            foreach ($prop->childNodes as $flag) {
                if (!$flag instanceof DOMElement || $flag->namespaceURI !== $ns) {
                    continue;
                }
                $off = $flag->getAttributeNS($ns, 'val') === '0' || $flag->getAttributeNS($ns, 'val') === 'false';
                if (($flag->localName === 'b' || $flag->localName === 'bCs') && !$off) {
                    $bold = true;
                }
                if (($flag->localName === 'i' || $flag->localName === 'iCs') && !$off) {
                    $italic = true;
                }
            }
        }
        if ($bold) {
            $inner = '<strong>' . $inner . '</strong>';
        }
        if ($italic) {
            $inner = '<em>' . $inner . '</em>';
        }
        $html .= $inner;
    }
    return $html;
}

/**
 * Map a Stream error code to a language string when one exists.
 *
 * @param array{ok: bool, message: string, code: string, data: array<string, mixed>} $result
 * @return string
 */
function streamvoice_error_text(array $result): string {
    $code = preg_replace('/[^a-z0-9_]/', '', (string) ($result['code'] ?? ''));
    if ($code !== '' && get_string_manager()->string_exists('error_' . $code, 'streamvoice')) {
        return get_string('error_' . $code, 'streamvoice');
    }
    if (!empty($result['message'])) {
        return $result['message'];
    }
    return get_string('error_unreachable', 'streamvoice');
}
