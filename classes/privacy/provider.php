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
 * Privacy provider.
 *
 * Moodle does not keep the defense itself. The submitted file, identity,
 * recordings, transcripts and scores are sent to the configured Stream site.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_streamvoice\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;

class provider implements \core_privacy\local\metadata\provider {

    /**
     * Personal data this plugin sends to Stream.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('stream', [
            'email' => 'privacy:metadata:stream:email',
            'firstname' => 'privacy:metadata:stream:firstname',
            'lastname' => 'privacy:metadata:stream:lastname',
            'username' => 'privacy:metadata:stream:username',
            'userid' => 'privacy:metadata:stream:userid',
            'file' => 'privacy:metadata:stream:file',
            'note' => 'privacy:metadata:stream:note',
            'recording' => 'privacy:metadata:stream:recording',
            'transcript' => 'privacy:metadata:stream:transcript',
            'grade' => 'privacy:metadata:stream:grade',
        ], 'privacy:metadata:stream');

        return $collection;
    }
}
