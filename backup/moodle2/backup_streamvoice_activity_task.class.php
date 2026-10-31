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
 * Backup task for one Stream Voice activity.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/streamvoice/backup/moodle2/backup_streamvoice_stepslib.php');

/**
 * One activity backup.
 */
class backup_streamvoice_activity_task extends backup_activity_task {

    /**
     * No extra settings.
     */
    protected function define_my_settings() {
    }

    /**
     * Backup steps.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_streamvoice_activity_structure_step('streamvoice_structure', 'streamvoice.xml'));
    }

    /**
     * Encode links to this activity.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');
        $content = preg_replace(
            '/(' . $base . '\/mod\/streamvoice\/index.php\?id\=)([0-9]+)/',
            '$@STREAMVOICEINDEX*$2@$',
            $content
        );
        $content = preg_replace(
            '/(' . $base . '\/mod\/streamvoice\/view.php\?id\=)([0-9]+)/',
            '$@STREAMVOICEVIEWBYID*$2@$',
            $content
        );
        return $content;
    }
}
