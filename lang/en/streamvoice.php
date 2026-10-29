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
 * English strings for mod_streamvoice.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Stream assignment defense';
$string['modulename'] = 'Stream assignment defense';
$string['modulenameplural'] = 'Stream assignment defenses';
$string['modulename_help'] = 'Students submit a file. Stream locks it and runs an oral defense: preparation time, recorded answers, transcription, a rubric score, and a consistency check against the submitted work. The aim is to see that the student understands the work, not to detect whether a tool wrote the file.';
$string['pluginadministration'] = 'Stream assignment defense administration';
$string['privacy:metadata'] = 'Stream assignment defense stores submissions, recordings, transcripts and scores in Stream. Moodle keeps the activity settings and the gradebook grade.';

$string['streamvoice:view'] = 'View a Stream assignment defense';
$string['streamvoice:submit'] = 'Submit work for a Stream assignment defense';
$string['streamvoice:addinstance'] = 'Add a Stream assignment defense';
$string['streamvoice:grade'] = 'View Stream assignment defense reports';

$string['deflang'] = 'Defense language';
$string['lang_he'] = 'Hebrew';
$string['lang_en'] = 'English';
$string['defensehdr'] = 'Defense';
$string['prepseconds'] = 'Preparation time (seconds)';
$string['prepseconds_help'] = 'How long the student has to read the questions and write speaker notes before recording starts. Stream enforces this timer.';
$string['answerseconds'] = 'Answer time per question (seconds)';
$string['answerseconds_help'] = 'Maximum length of each recorded answer. Stream rejects a longer recording when it can measure the file.';
$string['questioncount'] = 'Number of questions';
$string['questioncount_help'] = 'Fixed questions from the lecturer are used first. Stream writes the rest from the submitted file.';
$string['fixedquestions'] = 'Fixed questions';
$string['fixedquestions_help'] = 'One question per line. These are asked as written. Leave empty to let Stream build every question from the submission.';
$string['rubric'] = 'Rubric';
$string['rubric_help'] = 'Sent to Stream and used for the oral score. If empty, Stream uses a default rubric: understanding, justification, consistency with the file, and independent reasoning.';
$string['weightfile'] = 'Weight of consistency with the file';
$string['weightfile_help'] = 'Percent of the score that comes from how well the explanation matches the locked file. Together with the oral weight this must equal 100.';
$string['weightoral'] = 'Weight of the oral explanation';
$string['weightsmust100'] = 'The two weights must add up to 100.';
$string['allowresubmission'] = 'Allow a new file before recording starts';
$string['allowresubmission_help'] = 'After the first recording, Stream keeps the file locked.';
$string['maxbytes'] = 'Maximum file size';
$string['questioncountrange'] = 'Use between 1 and 8 questions.';
$string['preprange'] = 'Preparation time must be between 0 and 3600 seconds.';
$string['answerrange'] = 'Answer time must be between 30 and 900 seconds.';

$string['artifact'] = 'Work file';
$string['artifact_help'] = 'Code, a document, or a zip of the project. After you submit, Stream locks the file and opens the defense.';
$string['artifactnote'] = 'Note for the marker (optional)';
$string['submitandlock'] = 'Submit and lock';
$string['beforelock'] = 'After you submit, the file is locked and the defense begins. You will explain the work on camera.';
$string['lockednotice'] = 'Your file is locked. Continue the defense below.';
$string['report'] = 'Defense report';
$string['none'] = 'There are no Stream assignment defenses in this course yet.';

$string['streamnotconfigured'] = 'Stream is not configured. Set the Stream URL and API key in Site administration → Plugins → Local plugins → Stream.';
$string['error_unreachable'] = 'Stream did not respond. Check the Stream URL and that the assignment defense API is available.';
$string['error_invalid_key'] = 'Stream rejected the API key.';
$string['error_locked'] = 'This submission is already locked.';
$string['error_not_open'] = 'Submissions are not open yet.';
$string['error_closed'] = 'Submissions are closed.';
$string['error_missing_file'] = 'Choose a file to submit.';
$string['error_file_too_large'] = 'The file is larger than the allowed size.';
$string['error_unknown_activity'] = 'Stream does not have this activity yet. Open the activity again after saving it.';
$string['error_no_submission'] = 'Submit and lock the work before starting the defense.';
$string['error_not_configured'] = 'Stream is not configured.';
