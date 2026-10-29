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
 * Instance settings. The values are pushed to Stream, which runs the defense.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_streamvoice_mod_form extends moodleform_mod {

    /**
     * Form definition.
     */
    public function definition() {
        global $CFG, $COURSE;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('select', 'deflang', get_string('deflang', 'streamvoice'), [
            'he' => get_string('lang_he', 'streamvoice'),
            'en' => get_string('lang_en', 'streamvoice'),
        ]);
        $mform->setDefault('deflang', 'he');
        $mform->setType('deflang', PARAM_ALPHA);

        $mform->addElement('header', 'timing', get_string('timing', 'form'));
        $mform->addElement('date_time_selector', 'timeopen', get_string('allowsubmissionsfromdate', 'assign'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'timeclose', get_string('duedate', 'assign'), ['optional' => true]);

        $mform->addElement('header', 'defensehdr', get_string('defensehdr', 'streamvoice'));
        $mform->addElement('text', 'prepseconds', get_string('prepseconds', 'streamvoice'));
        $mform->setType('prepseconds', PARAM_INT);
        $mform->setDefault('prepseconds', 180);
        $mform->addHelpButton('prepseconds', 'prepseconds', 'streamvoice');

        $mform->addElement('text', 'answerseconds', get_string('answerseconds', 'streamvoice'));
        $mform->setType('answerseconds', PARAM_INT);
        $mform->setDefault('answerseconds', 120);
        $mform->addHelpButton('answerseconds', 'answerseconds', 'streamvoice');

        $mform->addElement('text', 'questioncount', get_string('questioncount', 'streamvoice'));
        $mform->setType('questioncount', PARAM_INT);
        $mform->setDefault('questioncount', 3);
        $mform->addHelpButton('questioncount', 'questioncount', 'streamvoice');

        $mform->addElement('textarea', 'fixedquestions', get_string('fixedquestions', 'streamvoice'), 'wrap="virtual" rows="6" cols="70"');
        $mform->setType('fixedquestions', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('fixedquestions', 'fixedquestions', 'streamvoice');

        $mform->addElement('textarea', 'rubric', get_string('rubric', 'streamvoice'), 'wrap="virtual" rows="6" cols="70"');
        $mform->setType('rubric', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('rubric', 'rubric', 'streamvoice');

        $mform->addElement('text', 'weightfile', get_string('weightfile', 'streamvoice'));
        $mform->setType('weightfile', PARAM_INT);
        $mform->setDefault('weightfile', 40);
        $mform->addHelpButton('weightfile', 'weightfile', 'streamvoice');

        $mform->addElement('text', 'weightoral', get_string('weightoral', 'streamvoice'));
        $mform->setType('weightoral', PARAM_INT);
        $mform->setDefault('weightoral', 60);

        $mform->addElement('advcheckbox', 'allowresubmission', get_string('allowresubmission', 'streamvoice'));
        $mform->setDefault('allowresubmission', 0);
        $mform->addHelpButton('allowresubmission', 'allowresubmission', 'streamvoice');

        $maxbytes = (int) get_config('moodlecourse', 'maxbytes');
        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, $maxbytes);
        $mform->addElement('select', 'maxbytes', get_string('maxbytes', 'streamvoice'), $choices);
        $mform->setDefault('maxbytes', 20971520);

        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $file = (int) ($data['weightfile'] ?? 0);
        $oral = (int) ($data['weightoral'] ?? 0);
        if ($file + $oral !== 100) {
            $errors['weightfile'] = get_string('weightsmust100', 'streamvoice');
        }
        $count = (int) ($data['questioncount'] ?? 0);
        if ($count < 1 || $count > 8) {
            $errors['questioncount'] = get_string('questioncountrange', 'streamvoice');
        }
        $prep = (int) ($data['prepseconds'] ?? 0);
        if ($prep < 0 || $prep > 3600) {
            $errors['prepseconds'] = get_string('preprange', 'streamvoice');
        }
        $answer = (int) ($data['answerseconds'] ?? 0);
        if ($answer < 30 || $answer > 900) {
            $errors['answerseconds'] = get_string('answerrange', 'streamvoice');
        }
        return $errors;
    }
}
