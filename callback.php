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
 * Grade callback from Stream. The bearer token is the local_stream API key.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

header('Content-Type: application/json; charset=utf-8');

/**
 * Bearer token from the incoming request.
 */
function streamvoice_callback_bearer(): string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    $header = (string) $value;
                }
            }
        }
    }
    if (preg_match('/Bearer\s+(\S+)/', (string) $header, $matches)) {
        return $matches[1];
    }
    return '';
}

$expected = (string) get_config('local_stream', 'streamkey');
$given = streamvoice_callback_bearer();
if ($expected === '' || $given === '' || !hash_equals($expected, $given)) {
    http_response_code(401);
    echo json_encode(['error' => true, 'code' => 'invalid_key']);
    exit;
}

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => true, 'code' => 'bad_body']);
    exit;
}

$cmid = (int) ($body['cmid'] ?? 0);
$cm = $cmid ? get_coursemodule_from_id('streamvoice', $cmid, 0, false, IGNORE_MISSING) : false;
if (!$cm) {
    http_response_code(404);
    echo json_encode(['error' => true, 'code' => 'unknown_activity']);
    exit;
}

global $DB;
$instance = $DB->get_record('streamvoice', ['id' => $cm->instance]);
if (!$instance) {
    http_response_code(404);
    echo json_encode(['error' => true, 'code' => 'unknown_activity']);
    exit;
}
streamvoice_apply_remote_grade($instance, [
    'moodleuserid' => (int) ($body['moodleuserid'] ?? 0),
    'points' => $body['points'] ?? null,
    'summary' => (string) ($body['summary'] ?? ''),
]);

echo json_encode(['error' => false]);
