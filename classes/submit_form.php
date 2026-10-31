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
 * File picker for the work that Stream will lock.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_streamvoice;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class submit_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $maxbytes = (int) ($this->_customdata['maxbytes'] ?? 20971520);

        $mform->addElement('filepicker', 'artifact', get_string('artifact', 'streamvoice'), null, [
            'maxbytes' => $maxbytes,
            'accepted_types' => '*',
        ]);
        $mform->addRule('artifact', null, 'required', null, 'client');
        $mform->addHelpButton('artifact', 'artifact', 'streamvoice');

        $mform->addElement('textarea', 'note', get_string('artifactnote', 'streamvoice'), 'wrap="virtual" rows="4" cols="60"');
        $mform->setType('note', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('submitandlock', 'streamvoice'));
    }
}
