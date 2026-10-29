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
 * Thin client for the Stream Vivago API.
 *
 * This class does not decide questions, scores, or lock rules.
 * It only carries the activity definition and the submitted file to Stream.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_streamvoice;

defined('MOODLE_INTERNAL') || die();

class client {

    /**
     * Stream base URL from local_stream.
     */
    public static function base_url(): ?string {
        $url = get_config('local_stream', 'streamurl');
        return is_string($url) && $url !== '' ? rtrim($url, '/') : null;
    }

    /**
     * Stream API key from local_stream.
     */
    public static function api_key(): ?string {
        $key = get_config('local_stream', 'streamkey');
        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Whether local_stream has a URL and key.
     */
    public static function is_configured(): bool {
        return self::base_url() !== null && self::api_key() !== null;
    }

    /**
     * Payload Stream stores as the defense definition.
     *
     * @param \stdClass $instance
     * @param int $cmid
     * @return array<string, mixed>
     */
    public static function activity_payload(\stdClass $instance, int $cmid): array {
        global $CFG;
        $host = parse_url($CFG->wwwroot, PHP_URL_HOST);
        $grade = (int) ($instance->grade ?? 100);
        return [
            'action' => 'upsert_activity',
            'host' => is_string($host) ? $host : '',
            'courseid' => (int) $instance->course,
            'cmid' => $cmid,
            'name' => (string) $instance->name,
            'intro' => (string) ($instance->intro ?? ''),
            'lang' => (string) ($instance->deflang ?? 'he'),
            'prepseconds' => (int) ($instance->prepseconds ?? 180),
            'answerseconds' => (int) ($instance->answerseconds ?? 120),
            'questioncount' => (int) ($instance->questioncount ?? 3),
            'fixedquestions' => (string) ($instance->fixedquestions ?? ''),
            'rubric' => (string) ($instance->rubric ?? ''),
            'weightfile' => (int) ($instance->weightfile ?? 40),
            'weightoral' => (int) ($instance->weightoral ?? 60),
            'grademax' => $grade > 0 ? $grade : 100,
            'allowresubmission' => !empty($instance->allowresubmission) ? 1 : 0,
            'timeopen' => (int) ($instance->timeopen ?? 0),
            'timeclose' => (int) ($instance->timeclose ?? 0),
            'callbackurl' => $CFG->wwwroot . '/mod/streamvoice/callback.php',
        ];
    }

    /**
     * @param \stdClass $instance
     * @param int $cmid
     * @return array{ok: bool, message: string, code: string, data: array<string, mixed>}
     */
    public function upsert_activity(\stdClass $instance, int $cmid): array {
        return $this->post_json(self::activity_payload($instance, $cmid));
    }

    /**
     * @param int $cmid
     * @return array{ok: bool, message: string, code: string, data: array<string, mixed>}
     */
    public function delete_activity(int $cmid): array {
        return $this->post_json([
            'action' => 'delete_activity',
            'host' => self::host(),
            'cmid' => $cmid,
        ]);
    }

    /**
     * @param int $cmid
     * @param \stdClass $user
     * @return array{ok: bool, message: string, code: string, data: array<string, mixed>}
     */
    public function submission_status(int $cmid, \stdClass $user): array {
        return $this->post_json([
            'action' => 'submission_status',
            'host' => self::host(),
            'cmid' => $cmid,
            'email' => $user->email,
        ]);
    }

    /**
     * @param int $cmid
     * @return array{ok: bool, message: string, code: string, data: array<string, mixed>}
     */
    public function list_submissions(int $cmid): array {
        return $this->post_json([
            'action' => 'list_submissions',
            'host' => self::host(),
            'cmid' => $cmid,
        ]);
    }

    /**
     * @param int $cmid
     * @param \stdClass $user
     * @param string $role student|teacher
     * @return array{ok: bool, message: string, code: string, data: array<string, mixed>}
     */
    public function issue_launch(int $cmid, \stdClass $user, string $role): array {
        return $this->post_json([
            'action' => 'issue_launch',
            'host' => self::host(),
            'cmid' => $cmid,
            'role' => $role,
            'email' => $user->email,
            'moodleuserid' => (int) $user->id,
        ]);
    }

    /**
     * Forward the student's file. Stream locks it and builds the questions.
     *
     * @param int $cmid
     * @param \stdClass $user
     * @param string $filepath
     * @param string $filename
     * @param string $note
     * @param int $maxbytes
     * @return array{ok: bool, message: string, code: string, data: array<string, mixed>}
     */
    public function submit_artifact(
        int $cmid,
        \stdClass $user,
        string $filepath,
        string $filename,
        string $note,
        int $maxbytes
    ): array {
        if (!self::is_configured()) {
            return $this->fail('not_configured', get_string('streamnotconfigured', 'streamvoice'));
        }
        if (!is_readable($filepath)) {
            return $this->fail('missing_file', get_string('error_missing_file', 'streamvoice'));
        }
        $post = [
            'action' => 'submit_artifact',
            'host' => self::host(),
            'cmid' => $cmid,
            'email' => $user->email,
            'firstname' => $user->firstname ?? '',
            'lastname' => $user->lastname ?? '',
            'username' => $user->username ?? '',
            'moodleuserid' => (int) $user->id,
            'note' => $note,
            'maxbytes' => $maxbytes,
            'file' => new \CURLFile($filepath, 'application/octet-stream', $filename),
        ];
        return $this->request($post, 120);
    }

    /**
     * @return string
     */
    public static function host(): string {
        global $CFG;
        $host = parse_url($CFG->wwwroot, PHP_URL_HOST);
        return is_string($host) ? strtolower($host) : '';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, message: string, code: string, data: array<string, mixed>}
     */
    private function post_json(array $payload): array {
        if (!self::is_configured()) {
            return $this->fail('not_configured', get_string('streamnotconfigured', 'streamvoice'));
        }
        return $this->request(json_encode($payload), 30, true);
    }

    /**
     * @param array<string, mixed>|string $body
     * @return array{ok: bool, message: string, code: string, data: array<string, mixed>}
     */
    private function request($body, int $timeout, bool $json = false): array {
        $url = self::base_url() . '/webservice/api/streamvoice';
        $curl = new \curl();
        $headers = ['Authorization: Bearer ' . self::api_key()];
        if ($json) {
            $headers[] = 'Content-Type: application/json';
        }
        $curl->setHeader($headers);
        $curl->setopt([
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_CONNECTTIMEOUT' => 10,
        ]);
        try {
            $response = $curl->post($url, $body);
        } catch (\Throwable $e) {
            return $this->fail('unreachable', get_string('error_unreachable', 'streamvoice'));
        }
        $info = $curl->get_info();
        $http = (int) ($info['http_code'] ?? 0);
        $data = json_decode((string) $response, true);
        if (!is_array($data) || $http === 0) {
            return $this->fail('unreachable', get_string('error_unreachable', 'streamvoice'));
        }
        if (!empty($data['error'])) {
            return [
                'ok' => false,
                'code' => (string) ($data['code'] ?? 'stream'),
                'message' => (string) ($data['message'] ?? ''),
                'data' => $data,
            ];
        }
        return ['ok' => true, 'code' => '', 'message' => '', 'data' => $data];
    }

    /**
     * @return array{ok: bool, message: string, code: string, data: array<string, mixed>}
     */
    private function fail(string $code, string $message): array {
        return ['ok' => false, 'code' => $code, 'message' => $message, 'data' => []];
    }
}
