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
 * Upgrade steps for mod_streamvoice.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_streamvoice_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026111002) {
        $table = new xmldb_table('streamvoice');
        $generate = new xmldb_field('generatecount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '5', 'questioncount');
        if (!$dbman->field_exists($table, $generate)) {
            $dbman->add_field($table, $generate);
        }
        $answer = new xmldb_field('answercount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '3', 'generatecount');
        if (!$dbman->field_exists($table, $answer)) {
            $dbman->add_field($table, $answer);
        }
        unset_config('generatecount', 'streamvoice');
        unset_config('answercount', 'streamvoice');
        upgrade_mod_savepoint(true, 2026111002, 'streamvoice');
    }

    return true;
}
