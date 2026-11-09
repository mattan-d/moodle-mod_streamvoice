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

$string['pluginname'] = 'Stream Voice';
$string['modulename'] = 'Stream Voice';
$string['modulenameplural'] = 'Stream Voice';
$string['modulename_help'] = 'Students submit a file. Stream Voice locks it and runs an oral defense: preparation time, recorded answers, transcription, a rubric score, and a consistency check against the submitted work. The aim is to see that the student understands the work, not to detect whether a tool wrote the file.';
$string['pluginadministration'] = 'Stream Voice administration';
$string['privacy:metadata'] = 'Stream Voice stores submissions, recordings, transcripts and scores in Stream. Moodle keeps the activity settings and the gradebook grade.';

$string['streamvoice:view'] = 'View Stream Voice';
$string['streamvoice:submit'] = 'Submit work in Stream Voice';
$string['streamvoice:addinstance'] = 'Add a Stream Voice activity';
$string['streamvoice:grade'] = 'View Stream Voice reports';

$string['deflang'] = 'Stream Voice language';
$string['lang_he'] = 'Hebrew';
$string['lang_en'] = 'English';
$string['defensehdr'] = 'Stream Voice';
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
$string['weightfile'] = 'Weight of the work grade';
$string['weightfile_help'] = 'Percent of the final grade that comes from the grade for the submitted work. Together with the defense weight this must equal 100.';
$string['weightoral'] = 'Weight of the defense grade';
$string['weightsmust100'] = 'The two weights must add up to 100.';
$string['allowresubmission'] = 'Allow a new file before recording starts';
$string['allowresubmission_help'] = 'After the first recording, Stream keeps the file locked.';
$string['maxbytes'] = 'Maximum file size';
$string['questioncountrange'] = 'Use between 1 and 8 questions.';
$string['preprange'] = 'Preparation time must be between 0 and 3600 seconds.';
$string['answerrange'] = 'Answer time must be between 30 and 900 seconds.';

$string['artifact'] = 'Work file';
$string['artifact_help'] = 'Code, a document, or a zip of the project. After you submit, Stream Voice locks the file.';
$string['artifactnote'] = 'Note for the marker (optional)';
$string['submitandlock'] = 'Submit and lock';
$string['beforelock'] = 'After you submit, the file is locked and Stream Voice begins. You will explain the work on camera.';
$string['lockednotice'] = 'Your file is locked. Continue to Stream Voice below.';
$string['continuedefense'] = 'Continue to Stream Voice';
$string['status_awaiting_model'] = 'Waiting for the automatic score';
$string['status_awaiting_transcript'] = 'Waiting for the transcript';
$string['status_provisional'] = 'Provisional score';
$string['status_prep'] = 'Preparing';
$string['status_recording'] = 'Recording';
$string['backtosubmissions'] = 'Back to submissions';
$string['report'] = 'Stream Voice report';
$string['answers'] = 'Answers';
$string['answerscore'] = 'Score';
$string['gradeoutofrange'] = 'Enter a grade between 0 and {$a}.';
$string['transcript'] = 'Transcript';
$string['consistency'] = 'Consistency with the file';
$string['oralscore'] = 'Oral explanation';
$string['gradebreakdown'] = '{$a->grade} = consistency {$a->consistency} × {$a->fileweight}% + oral {$a->oral} × {$a->oralweight}%';
$string['recommendedgrade'] = 'Recommended score';
$string['aidefense'] = 'AI defense score';
$string['workgrade'] = 'Grade for the work';
$string['defensegrade'] = 'Defense grade';
$string['answernum'] = 'Answer {$a}';
$string['defensehow'] = 'Each answer is scored from 0 to 100 against the rubric. The defense grade is their average, shown out of {$a}.';
$string['defensesame'] = 'The remark is the same on every answer. Each answer still has its own score, from what was said.';
$string['oralrubricused'] = 'Rubric used for this score';
$string['oralrubricedit'] = 'Rubric for defenses scored from now on';
$string['oralrubricnote'] = 'A change here applies to defenses scored from now on. Scores already shown are not recalculated.';
$string['workrubric'] = 'Rubric for the work';
$string['workrubricintro'] = 'The institution seminar rubric. Choosing a level on every criterion sets the work grade. Editing changes this activity, not the shared rubric.';
$string['editworkrubric'] = 'Edit rubric';
$string['seminarubric'] = 'Seminar paper';
$string['seminarubric_desc'] = 'Institution rubric for a seminar paper. Criteria and levels can be changed on each activity.';
$string['research'] = 'Research question and focus';
$string['research0'] = 'No research question, or it is unrelated to the work.';
$string['research1'] = 'There is a topic, but the question is broad or unfocused.';
$string['research2'] = 'A clear question, with a reasonable scope.';
$string['research3'] = 'A focused question that guides the whole paper.';
$string['sources'] = 'Evidence and sources';
$string['sources0'] = 'Claims without a source, or sources that do not belong.';
$string['sources1'] = 'Few sources, and they are only partly used.';
$string['sources2'] = 'Relevant sources, cited where they are used.';
$string['sources3'] = 'Varied sources used for the argument, not only listed.';
$string['argument'] = 'Argument and analysis';
$string['argument0'] = 'Description without analysis.';
$string['argument1'] = 'Partial analysis, with gaps in the argument.';
$string['argument2'] = 'A consistent argument grounded in the sources.';
$string['argument3'] = 'Analysis that separates claims, evidence, and limits.';
$string['structure'] = 'Structure, writing, and conclusions';
$string['structure0'] = 'Hard to follow, and no conclusion.';
$string['structure1'] = 'A partial structure, and the conclusion repeats the body.';
$string['structure2'] = 'A clear structure, and a conclusion that answers the question.';
$string['structure3'] = 'A structure that serves the argument, and a conclusion that also says what is still open.';
$string['aidiscretion'] = 'Only the teacher sees the AI score. Use it as the defense grade, or change it.';
$string['gradeparts'] = '{$a->final} = work {$a->work} × {$a->fileweight}% + defense {$a->defense} × {$a->oralweight}%';
$string['bothgradesrequired'] = 'Enter a grade for the work and a defense grade.';
$string['recommendednote'] = 'Note on the recommended score';
$string['previewunavailable'] = 'This file cannot be shown here. Download it to open it.';
$string['noanswers'] = 'No recorded answers yet.';
$string['none'] = 'There is no Stream Voice activity in this course yet.';

$string['streamnotconfigured'] = 'Stream is not configured. Set the Stream URL and API key in Site administration → Plugins → Local plugins → Stream.';
$string['error_unreachable'] = 'Stream did not respond. Check the Stream URL and that the Stream Voice API is available.';
$string['error_invalid_key'] = 'Stream rejected the API key.';
$string['error_locked'] = 'This submission is already locked.';
$string['error_not_open'] = 'Submissions are not open yet.';
$string['error_closed'] = 'Submissions are closed.';
$string['error_missing_file'] = 'Choose a file to submit.';
$string['error_file_too_large'] = 'The file is larger than the allowed size.';
$string['error_unknown_activity'] = 'Stream does not have this activity yet. Open the activity again after saving it.';
$string['error_no_submission'] = 'Submit and lock the work before starting Stream Voice.';
$string['error_not_configured'] = 'Stream is not configured.';

$string['recordtest'] = 'Recording test';
$string['recordtest_desc'] = 'Record a few seconds in the browser and play them back. The test is not saved and is not sent to Stream. It uses the same camera and microphone setup as a defense recording.';
$string['recordteststart'] = 'Open the recording test';
$string['recordtestintro'] = 'Say a few words, stop, and listen. The live picture stays silent so the microphone does not howl. The playback below is the recording itself, with sound. Nothing is uploaded.';
$string['recordtestrecord'] = 'Record';
$string['recordteststop'] = 'Stop';
$string['recordtestlevel'] = 'Microphone level';
$string['recordtestmic'] = 'Microphone';
$string['recordtestsound'] = 'The microphone was heard in this recording.';
$string['recordtestsilent'] = 'No sound arrived from «{$a}». If that is not your microphone, choose another one above and try again.';
$string['recordtestfailed'] = 'The camera or microphone could not be opened.';
