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
 * Site administration settings for Stream Voice.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'streamvoice_questions',
        get_string('questionsettings', 'streamvoice'),
        get_string('questionsettings_desc', 'streamvoice')
    ));
    $settings->add(new admin_setting_configtext(
        'streamvoice/generatecount',
        get_string('generatecount', 'streamvoice'),
        get_string('generatecount_help', 'streamvoice'),
        5,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'streamvoice/answercount',
        get_string('answercount', 'streamvoice'),
        get_string('answercount_help', 'streamvoice'),
        3,
        PARAM_INT
    ));

    $url = (new moodle_url('/mod/streamvoice/recordtest.php'))->out(false);
    $settings->add(new admin_setting_heading(
        'streamvoice_recordtest',
        get_string('recordtest', 'streamvoice'),
        get_string('recordtest_desc', 'streamvoice') . "\n\n[" . get_string('recordteststart', 'streamvoice') . '](' . $url . ')'
    ));
}
