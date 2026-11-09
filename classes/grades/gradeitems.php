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
 * The written work can be graded with a Moodle rubric, the same way an assignment is.
 *
 * @package   mod_streamvoice
 * @copyright 2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_streamvoice\grades;

use core_grades\local\gradeitem\advancedgrading_mapping;
use core_grades\local\gradeitem\itemnumber_mapping;

/**
 * Grade item mappings for Stream Voice.
 */
class gradeitems implements itemnumber_mapping, advancedgrading_mapping {

    /**
     * @return array<int, string>
     */
    public static function get_itemname_mapping_for_component(): array {
        return [
            0 => 'work',
        ];
    }

    /**
     * @return string[]
     */
    public static function get_advancedgrading_itemnames(): array {
        return [
            'work',
        ];
    }
}
