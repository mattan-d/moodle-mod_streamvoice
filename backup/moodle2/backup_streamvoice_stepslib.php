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
 * Backup structure for one Stream Voice activity.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Settings of one activity. Student submissions stay in Stream and are not copied.
 */
class backup_streamvoice_activity_structure_step extends backup_activity_structure_step {

    /**
     * @return backup_nested_element
     */
    protected function define_structure() {
        $streamvoice = new backup_nested_element('streamvoice', ['id'], [
            'name', 'intro', 'introformat', 'timeopen', 'timeclose',
            'prepseconds', 'answerseconds', 'questioncount', 'fixedquestions', 'rubric',
            'weightfile', 'weightoral', 'allowresubmission', 'maxbytes', 'deflang', 'grade',
            'timecreated', 'timemodified',
        ]);

        $streamvoice->set_source_table('streamvoice', ['id' => backup::VAR_ACTIVITYID]);
        $streamvoice->annotate_files('mod_streamvoice', 'intro', null);

        return $this->prepare_activity_structure($streamvoice);
    }
}
