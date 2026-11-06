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
 * Browser recording check for site administrators. Nothing is uploaded.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/streamvoice/recordtest.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('recordtest', 'streamvoice'));
$PAGE->set_heading(get_string('recordtest', 'streamvoice'));
$PAGE->navbar->add(get_string('recordtest', 'streamvoice'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('recordtest', 'streamvoice'));
echo html_writer::tag('p', get_string('recordtestintro', 'streamvoice'));

echo html_writer::tag('style',
    '.sv-recordtest video{display:block;width:min(100%,32rem);max-height:20rem;background:#1d2125;border-radius:.25rem;margin:.75rem 0}'
    . '.sv-recordtest .sv-actions{display:flex;gap:.5rem;margin:.75rem 0}'
    . '.sv-meter{height:.5rem;background:#e9ecef;border-radius:.25rem;overflow:hidden;max-width:32rem}'
    . '.sv-meter span{display:block;height:100%;width:0;background:#0f6cbf}'
    . '.sv-recordtest .sv-result{font-weight:700;margin-top:.75rem}'
);

echo html_writer::start_div('sv-recordtest', [
    'id' => 'sv-recordtest',
    'data-sound' => get_string('recordtestsound', 'streamvoice'),
    'data-silent' => get_string('recordtestsilent', 'streamvoice', '___'),
    'data-level' => get_string('recordtestlevel', 'streamvoice'),
    'data-failed' => get_string('recordtestfailed', 'streamvoice'),
    'data-mic' => get_string('recordtestmic', 'streamvoice'),
]);
echo html_writer::tag('video', '', [
    'id' => 'sv-preview',
    'autoplay' => 'autoplay',
    'muted' => 'muted',
    'playsinline' => 'playsinline',
]);
echo html_writer::div(html_writer::tag('span', ''), 'sv-meter', ['id' => 'sv-meter']);
echo html_writer::tag('p', '', ['id' => 'sv-level']);
echo html_writer::tag('label', get_string('recordtestmic', 'streamvoice'), [
    'for' => 'sv-mic',
    'id' => 'sv-mic-label',
    'hidden' => 'hidden',
]);
echo html_writer::tag('select', '', ['id' => 'sv-mic', 'hidden' => 'hidden', 'class' => 'custom-select']);
echo html_writer::start_div('sv-actions');
echo html_writer::tag('button', get_string('recordtestrecord', 'streamvoice'), [
    'type' => 'button',
    'id' => 'sv-record',
    'class' => 'btn btn-primary',
]);
echo html_writer::tag('button', get_string('recordteststop', 'streamvoice'), [
    'type' => 'button',
    'id' => 'sv-stop',
    'class' => 'btn btn-secondary',
    'disabled' => 'disabled',
]);
echo html_writer::end_div();
echo html_writer::tag('video', '', [
    'id' => 'sv-playback',
    'controls' => 'controls',
    'playsinline' => 'playsinline',
    'hidden' => 'hidden',
]);
echo html_writer::tag('p', '', ['id' => 'sv-result', 'class' => 'sv-result']);
echo html_writer::end_div();

echo html_writer::tag('script', <<<'JS'
(function () {
    var root = document.getElementById('sv-recordtest');
    var preview = document.getElementById('sv-preview');
    var playback = document.getElementById('sv-playback');
    var record = document.getElementById('sv-record');
    var stop = document.getElementById('sv-stop');
    var meter = document.querySelector('#sv-meter span');
    var level = document.getElementById('sv-level');
    var result = document.getElementById('sv-result');
    var stream = null;
    var recorder = null;
    var chunks = [];
    var audioContext = null;
    var meterSource = null;
    var raf = 0;
    var peak = 0;
    var micName = '';
    var micSelect = document.getElementById('sv-mic');
    var micLabel = document.getElementById('sv-mic-label');

    function paint(value) {
        meter.style.width = Math.min(100, Math.round(value * 100)) + '%';
        level.textContent = root.dataset.level + ' ' + Math.round(Math.max(peak, value) * 100) + '%';
    }

    function watch(media) {
        var track = media.getAudioTracks()[0];
        if (!track || !audioContext) {
            return;
        }
        if (meterSource) {
            meterSource.disconnect();
        }
        meterSource = audioContext.createMediaStreamSource(new MediaStream([track]));
        var source = meterSource;
        var analyser = audioContext.createAnalyser();
        analyser.fftSize = 2048;
        source.connect(analyser);
        var data = new Uint8Array(analyser.fftSize);
        var tick = function () {
            if (audioContext.state === 'suspended') {
                audioContext.resume();
            }
            analyser.getByteTimeDomainData(data);
            var max = 0;
            for (var i = 0; i < data.length; i++) {
                var sample = Math.abs(data[i] - 128) / 128;
                if (sample > max) {
                    max = sample;
                }
            }
            if (max > peak) {
                peak = max;
            }
            paint(max);
            raf = requestAnimationFrame(tick);
        };
        tick();
    }

    function finishWatch() {
        cancelAnimationFrame(raf);
        if (meterSource) {
            meterSource.disconnect();
            meterSource = null;
        }
    }

    function stopTracks() {
        if (stream) {
            stream.getTracks().forEach(function (track) { track.stop(); });
            stream = null;
        }
    }

    function listMics(selectedId) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
            return;
        }
        navigator.mediaDevices.enumerateDevices().then(function (devices) {
            var mics = devices.filter(function (device) { return device.kind === 'audioinput'; });
            if (!mics.length) {
                return;
            }
            micSelect.innerHTML = '';
            mics.forEach(function (device, index) {
                var option = document.createElement('option');
                option.value = device.deviceId;
                option.textContent = device.label || (root.dataset.mic + ' ' + (index + 1));
                if (device.deviceId === selectedId) {
                    option.selected = true;
                }
                micSelect.appendChild(option);
            });
            micLabel.hidden = false;
            micSelect.hidden = false;
        });
    }

    async function filePeak(blob) {
        if (!audioContext || !blob.size) {
            return null;
        }
        try {
            var copy = blob.slice(0, blob.size, blob.type);
            var decoded = await audioContext.decodeAudioData(await copy.arrayBuffer());
            var samples = decoded.getChannelData(0);
            var max = 0;
            for (var i = 0; i < samples.length; i += 16) {
                var sample = Math.abs(samples[i]);
                if (sample > max) {
                    max = sample;
                }
            }
            return max;
        } catch (err) {
            return null;
        }
    }

    record.onclick = async function () {
        record.disabled = true;
        result.textContent = '';
        playback.hidden = true;
        playback.removeAttribute('src');
        peak = 0;
        paint(0);
        if (!audioContext) {
            audioContext = new AudioContext();
        }
        await audioContext.resume();
        var chosen = micSelect.value;
        var audio = chosen ? {deviceId: {exact: chosen}} : true;
        var attempts = [
            {audio: audio, video: {facingMode: 'user', width: {ideal: 640}, height: {ideal: 360}, frameRate: {ideal: 15}}},
            {audio: audio, video: true},
            {audio: audio}
        ];
        var lastError = null;
        stream = null;
        for (var i = 0; i < attempts.length; i++) {
            try {
                var candidate = await navigator.mediaDevices.getUserMedia(attempts[i]);
                if (candidate.getAudioTracks().length === 0) {
                    candidate.getTracks().forEach(function (track) { track.stop(); });
                    continue;
                }
                stream = candidate;
                break;
            } catch (err) {
                lastError = err;
            }
        }
        if (!stream && chosen) {
            try {
                stream = await navigator.mediaDevices.getUserMedia({audio: true, video: true});
            } catch (err) {
                lastError = err;
            }
        }
        if (!stream) {
            result.textContent = lastError ? lastError.message : root.dataset.failed;
            record.disabled = false;
            return;
        }
        var audioTrack = stream.getAudioTracks()[0];
        micName = audioTrack.label || root.dataset.mic;
        listMics(audioTrack.getSettings().deviceId || chosen);
        var picture = stream.getVideoTracks();
        preview.srcObject = picture.length ? new MediaStream(picture) : null;
        if (picture.length) {
            preview.play().catch(function () {});
        }
        watch(stream);
        chunks = [];
        var hasVideo = stream.getVideoTracks().length > 0;
        var preferred = hasVideo ? 'video/webm;codecs=vp8,opus' : 'audio/webm';
        var mime = (window.MediaRecorder && MediaRecorder.isTypeSupported(preferred)) ? preferred : (hasVideo ? 'video/webm' : '');
        var options = {};
        if (mime) {
            options.mimeType = mime;
        }
        if (hasVideo) {
            options.videoBitsPerSecond = 450000;
        }
        try {
            recorder = new MediaRecorder(stream, options);
        } catch (err) {
            recorder = new MediaRecorder(stream, mime ? {mimeType: mime} : undefined);
        }
        recorder.ondataavailable = function (event) {
            if (event.data && event.data.size) {
                chunks.push(event.data);
            }
        };
        recorder.onstop = function () {
            var blob = new Blob(chunks, {type: recorder.mimeType || 'video/webm'});
            finishWatch();
            stopTracks();
            preview.srcObject = null;
            playback.src = URL.createObjectURL(blob);
            playback.hidden = false;
            playback.muted = false;
            playback.volume = 1;
            filePeak(blob).then(function (fromFile) {
                var heard = Math.max(peak, fromFile || 0);
                peak = heard;
                paint(heard);
                result.textContent = heard >= 0.02 ? root.dataset.sound : root.dataset.silent.replace('___', micName);
                record.disabled = false;
                stop.disabled = true;
            });
        };
        recorder.start();
        stop.disabled = false;
    };

    stop.onclick = function () {
        stop.disabled = true;
        if (recorder && recorder.state === 'recording') {
            recorder.stop();
        }
    };
})();
JS
);
echo $OUTPUT->footer();
