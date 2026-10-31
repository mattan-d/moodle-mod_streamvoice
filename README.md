# Stream Voice

Moodle activity module (`mod_streamvoice`) for an oral defense of submitted work.

A student submits a file. Stream Voice locks that file and asks the student to explain it on camera: preparation time with speaker notes, then a timed recording for each question. Stream transcribes the answers, scores them against a rubric, and checks that the explanation matches the locked file. The score is written back to the Moodle gradebook.

The aim is to see that the student understands the work and can justify it. The activity does not try to detect whether a tool wrote the file.

Stream Voice is the activity shell inside Moodle. Question generation, the timer, recording, transcription, the rubric and the score run in [Stream](https://stream-platform.cloud/).

## Requirements

* Moodle 4.5 or later.
* The [Stream](https://moodle.org/plugins/local_stream) local plugin (`local_stream` 2023060700 or later), with a Stream site URL and API key.
* A Stream site that provides the Stream Voice API.
* A camera and microphone for students.

`version.php` currently declares `requires` as 2017111300. The activity is developed and used on Moodle 4.5.

## Installation

1. Install and configure **Site administration → Plugins → Local plugins → Stream** (Stream URL and API key).
2. Copy this directory into `mod/streamvoice` on the Moodle server.
3. Log in as an administrator and open **Site administration → Notifications** to install the plugin.
4. Turn editing on in a course and add a **Stream Voice** activity.

## Activity settings

* **Preparation time** and **answer time** are enforced by Stream.
* **Number of questions** is from 1 to 8. Fixed questions, one per line, are asked first. Stream writes any remaining questions from the submitted file.
* **Rubric** is sent to Stream. If it is empty, Stream scores understanding, justification, consistency with the file, and independent reasoning.
* **Consistency weight** and **oral weight** must add up to 100. The gradebook grade is that score as a percentage of the activity grade.
* A new file can be allowed only before the first recording. After that, the file stays locked.

## What people do

**Students** submit a file, read the questions during preparation time, record an answer to each question, and can refresh the score after the recordings are in.

**Teachers** open the Stream Voice report from the activity. It lists submissions, status, the score, recordings and transcripts.

**Administrators** can see Stream Voice usage (activities, locked submissions, questions, recordings and scores) on the Stream usage report.

## Privacy

Moodle keeps the activity settings and the gradebook grade.

The submitted file, recordings, transcripts, questions and the automatic score are stored on the configured Stream server. See the privacy metadata string in this plugin, and the privacy information of the Stream site you connect.

## Backup and restore

The activity supports Moodle backup and restore, including duplication inside a course. Duplication copies the activity settings. It does not copy student submissions or recordings.

## License

GNU GPL v3 or later. See <https://www.gnu.org/licenses/gpl-3.0.html>.

## Support

CentricApp LTD — <dev@centricapp.co>

* Product page: <https://stream-platform.cloud/#stream-voice>
* Stream for Moodle: <https://moodle.org/plugins/local_stream>
