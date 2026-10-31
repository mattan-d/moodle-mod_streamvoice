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
 * Restore task for one Stream Voice activity.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/streamvoice/backup/moodle2/restore_streamvoice_stepslib.php');

/**
 * One activity restore.
 */
class restore_streamvoice_activity_task extends restore_activity_task {

    /**
     * No extra settings.
     */
    protected function define_my_settings() {
    }

    /**
     * Restore steps.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_streamvoice_activity_structure_step('streamvoice_structure', 'streamvoice.xml'));
    }

    /**
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('streamvoice', ['intro'], 'streamvoice'),
        ];
    }

    /**
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('STREAMVOICEVIEWBYID', '/mod/streamvoice/view.php?id=$1', 'course_module'),
            new restore_decode_rule('STREAMVOICEINDEX', '/mod/streamvoice/index.php?id=$1', 'course'),
        ];
    }
}
