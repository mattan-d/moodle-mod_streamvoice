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
 * Activity shell for Vivago. Defense logic is implemented in Stream.
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
            return false;
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
 * @param \stdClass $streamvoice
 * @param mixed $grades
 * @param bool $delete
 * @return int
 */
function streamvoice_grade_item_update($streamvoice, $grades = null, $delete = false) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

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
