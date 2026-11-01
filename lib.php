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
        default:
            return null;
    }
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
    if ((int) $instance->grade <= 0) {
        return;
    }
    if (!array_key_exists('points', $row) || $row['points'] === null) {
        return;
    }
    $userid = (int) ($row['moodleuserid'] ?? 0);
    if ($userid <= 0) {
        return;
    }
    streamvoice_update_grades($instance, $userid, (float) $row['points'], (string) ($row['summary'] ?? ''));
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
 * @param \stdClass $instance
 * @param int $userid
 * @param float|null $rawgrade
 * @param string|null $feedback
 */
function streamvoice_update_grades($instance, $userid, $rawgrade = null, $feedback = null) {
    $grades = new \stdClass();
    $grades->userid = $userid;
    $grades->rawgrade = $rawgrade;
    if ($feedback !== null) {
        $grades->feedback = $feedback;
        $grades->feedbackformat = FORMAT_PLAIN;
    }
    streamvoice_grade_item_update($instance, $grades);
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
    $graded = (array_key_exists('points', $row) && $row['points'] !== null) || $reportstatus === 'final';
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
