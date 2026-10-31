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
 * Restore structure for one Stream Voice activity.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore one activity and register the copy with Stream.
 */
class restore_streamvoice_activity_structure_step extends restore_activity_structure_step {

    /**
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('streamvoice', '/activity/streamvoice');
        return $this->prepare_activity_structure($paths);
    }

    /**
     * @param array|stdClass $data
     */
    protected function process_streamvoice($data) {
        global $DB;
        $data = (object) $data;
        $data->course = $this->get_courseid();
        $now = time();
        $data->timecreated = $now;
        $data->timemodified = $now;
        foreach (['intro', 'fixedquestions', 'rubric'] as $field) {
            if (!isset($data->$field)) {
                $data->$field = '';
            }
        }
        if (empty($data->deflang)) {
            $data->deflang = 'he';
        }
        $newitemid = $DB->insert_record('streamvoice', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Register the new course module with Stream after the module id exists.
     */
    protected function after_execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/streamvoice/lib.php');
        $this->add_related_files('mod_streamvoice', 'intro', null);
        $instance = $DB->get_record('streamvoice', ['id' => $this->task->get_activityid()], '*', MUST_EXIST);
        streamvoice_grade_item_update($instance);
        streamvoice_push($instance, (int) $this->task->get_moduleid());
    }
}
