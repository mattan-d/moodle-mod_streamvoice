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
 * College seminar rubric for the written work, edited like an assignment rubric.
 *
 * @package   mod_streamvoice
 * @copyright 2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_streamvoice;

defined('MOODLE_INTERNAL') || die();

/**
 * A shared seminar rubric, copied into each activity so a teacher can change it.
 */
class workrubric {

    /**
     * The activity rubric, creating the college template and the activity copy when needed.
     *
     * @param \context $context Module context.
     * @return \gradingform_rubric_controller
     */
    public static function controller(\context $context): \gradingform_rubric_controller {
        global $CFG;
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        require_once($CFG->dirroot . '/grade/grading/form/rubric/lib.php');

        $template = self::template();
        $manager = get_grading_manager($context, 'mod_streamvoice', 'work');
        if ($manager->get_active_method() !== 'rubric') {
            $manager->set_active_method('rubric');
        }
        /** @var \gradingform_rubric_controller $controller */
        $controller = $manager->get_controller('rubric');
        if (!$controller->is_form_defined() && $template->is_form_defined()) {
            $controller->update_definition($template->get_definition_copy($controller));
            $controller->get_definition(true);
        }
        return $controller;
    }

    /**
     * Rubric the teacher fills in, plus the link to edit its criteria.
     *
     * @param \context $context
     * @param int $teacherid
     * @param int $studentid
     * @param \moodle_url $returnurl
     * @return string
     */
    public static function html(\context $context, int $teacherid, int $studentid, \moodle_url $returnurl): string {
        global $PAGE;

        $controller = self::controller($context);
        if (!$controller->is_form_available()) {
            return '';
        }
        $current = $controller->get_current_instance($teacherid, $studentid);
        $filling = $current ? $current->get_rubric_filling() : ['criteria' => []];
        $PAGE->requires->js_init_call(
            'M.gradingform_rubric.init',
            [['name' => 'workrubric']],
            true,
            ['name' => 'gradingform_rubric', 'fullpath' => '/grade/grading/form/rubric/js/rubric.js']
        );
        $renderer = $controller->get_renderer($PAGE);
        $definition = $controller->get_definition();
        $html = \html_writer::start_div('sv-workrubric');
        $html .= \html_writer::div(get_string('workrubric', 'streamvoice'), 'sv-label mt-3');
        $html .= \html_writer::div(get_string('workrubricintro', 'streamvoice'), 'small text-muted mb-2');
        $html .= \html_writer::link(
            $controller->get_editor_url($returnurl),
            get_string('editworkrubric', 'streamvoice'),
            ['class' => 'small']
        );
        $html .= $renderer->display_rubric(
            $definition->rubric_criteria,
            $controller->get_options(),
            \gradingform_rubric_controller::DISPLAY_EVAL,
            'workrubric',
            $filling
        );
        $html .= $renderer->display_rubric_mapping_explained($controller->get_min_max_score());
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Grade from a completed rubric, on the activity scale. Null when a level is still missing.
     *
     * @param \context $context
     * @param int $teacherid
     * @param int $studentid
     * @param array<string, mixed> $data
     * @param int $grademax
     * @return float|null
     */
    public static function grade(\context $context, int $teacherid, int $studentid, array $data, int $grademax): ?float {
        $controller = self::controller($context);
        if (!$controller->is_form_available()) {
            return null;
        }
        $controller->set_grade_range(make_grades_menu($grademax), true);
        $current = $controller->get_current_instance($teacherid, $studentid);
        $instance = $controller->get_or_create_instance($current ? $current->get_id() : 0, $teacherid, $studentid);
        if (!$instance->validate_grading_element($data)) {
            return null;
        }
        $grade = $instance->submit_and_get_grade($data, $studentid);
        if (!is_numeric($grade) || (float) $grade < 0) {
            return null;
        }
        return (float) $grade;
    }

    /**
     * @return \gradingform_rubric_controller
     */
    private static function template(): \gradingform_rubric_controller {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        require_once($CFG->dirroot . '/grade/grading/form/rubric/lib.php');

        $areaid = (int) get_config('streamvoice', 'seminarareaid');
        if (!$areaid || !$DB->record_exists('grading_areas', ['id' => $areaid, 'activemethod' => 'rubric'])) {
            $areaid = get_grading_manager()->create_shared_area('rubric');
            set_config('seminarareaid', $areaid, 'streamvoice');
        }
        /** @var \gradingform_rubric_controller $controller */
        $controller = get_grading_manager($areaid)->get_controller('rubric');
        if (!$controller->is_form_defined()) {
            $definition = new \stdClass();
            $definition->name = get_string('seminarubric', 'streamvoice');
            $definition->description_editor = [
                'text' => get_string('seminarubric_desc', 'streamvoice'),
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ];
            $definition->status = \gradingform_controller::DEFINITION_STATUS_READY;
            $definition->rubric = [
                'options' => \gradingform_rubric_controller::get_default_options(),
                'criteria' => self::seminar_criteria(),
            ];
            $controller->update_definition($definition);
            $controller->get_definition(true);
        }
        return $controller;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function seminar_criteria(): array {
        $rows = [
            ['research', ['research0', 'research1', 'research2', 'research3']],
            ['sources', ['sources0', 'sources1', 'sources2', 'sources3']],
            ['argument', ['argument0', 'argument1', 'argument2', 'argument3']],
            ['structure', ['structure0', 'structure1', 'structure2', 'structure3']],
        ];
        $criteria = [];
        $criterionid = 1;
        $levelid = 1;
        foreach ($rows as $index => $row) {
            $levels = [];
            foreach ($row[1] as $score => $key) {
                $levels['NEWID' . $levelid] = [
                    'score' => $score,
                    'definition' => get_string($key, 'streamvoice'),
                    'definitionformat' => FORMAT_PLAIN,
                ];
                $levelid++;
            }
            $criteria['NEWID' . $criterionid] = [
                'sortorder' => $index + 1,
                'description' => get_string($row[0], 'streamvoice'),
                'descriptionformat' => FORMAT_PLAIN,
                'levels' => $levels,
            ];
            $criterionid++;
        }
        return $criteria;
    }
}
