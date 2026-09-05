{{--
    Konsol scan versi ringan.

    Sengaja TIDAK memakai Vite, React, Inertia, maupun Tailwind. Perangkat
    sasarannya box Android TV di gerbang, yang browsernya kerap Chrome 80-100 —
    di bawah Chrome 111 yang dibutuhkan oklch() pada resources/css/app.css, jadi
    seluruh variabel warna aplikasi utama gagal diparse di sana.

    Aturan yang harus dijaga saat menyunting berkas ini:
    - warna hex saja, tanpa oklch()/color-mix()
    - tanpa aspect-ratio (Chrome 88+) — pakai piksel eksplisit
    - tanpa gap pada flexbox (Chrome 84+) — pakai margin
    - JS tanpa optional chaining (?.) dan nullish coalescing (??) — Chrome 80+
    - tanpa kamera: html5-qrcode beban CPU terberat, dan box TV tidak punya kamera

    Endpoint POST-nya memakai rute public.scanner.scan yang sudah ada, jadi tidak
    ada satu pun logika absensi yang punya salinan kedua di sini.
--}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Absensi {{ $school->name }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #0f172a;
            color: #e2e8f0;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-text-size-adjust: 100%;
        }
        body { padding: 16px; }

        .bar {
            display: flex;
            align-items: center;
            margin-bottom: 14px;
        }
        .bar img { width: 40px; height: 40px; object-fit: contain; border-radius: 8px; }
        .bar .brand { margin-left: 10px; }
        .bar .brand b { display: block; font-size: 18px; color: #f8fafc; }
        .bar .brand span { display: block; font-size: 12px; color: #94a3b8; }
        .bar .clock {
            margin-left: auto;
            font-size: 34px;
            font-weight: bold;
            color: #f8fafc;
            font-family: "Courier New", monospace;
        }

        .stage {
            min-height: 340px;
            border-radius: 14px;
            background: #1e293b;
            border: 2px solid #334155;
            padding: 20px;
            display: flex;
            align-items: center;
        }
        .stage.ok { background: #064e3b; border-color: #10b981; }
        .stage.bad { background: #7f1d1d; border-color: #ef4444; }

        .idle { width: 100%; text-align: center; color: #94a3b8; font-size: 22px; }

        .photo {
            width: 240px;
            height: 320px;
            object-fit: contain;
            background: #0f172a;
            border: 3px solid #10b981;
            border-radius: 10px;
            flex: 0 0 auto;
        }
        .photo.none {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 72px;
            color: #475569;
        }

        .who { margin-left: 22px; min-width: 0; }
        .who .name {
            font-size: 42px;
            font-weight: bold;
            color: #ffffff;
            line-height: 1.1;
            word-wrap: break-word;
        }
        .who .meta { margin-top: 10px; font-size: 22px; color: #d1fae5; }
        .who .meta span { display: block; margin-top: 4px; }
        .who .tag {
            display: inline-block;
            margin-top: 12px;
            padding: 6px 16px;
            border-radius: 999px;
            background: #10b981;
            color: #04291d;
            font-size: 22px;
            font-weight: bold;
        }

        .fail { width: 100%; text-align: center; }
        .fail .mark { font-size: 72px; color: #fecaca; }
        .fail .msg { margin-top: 10px; font-size: 34px; font-weight: bold; color: #ffffff; }

        form { margin-top: 14px; }
        input[type=text] {
            width: 100%;
            padding: 14px;
            font-size: 20px;
            text-align: center;
            border-radius: 10px;
            border: 2px solid #334155;
            background: #1e293b;
            color: #f8fafc;
            font-family: "Courier New", monospace;
        }
        input[type=text]:focus { outline: none; border-color: #10b981; }

        .log { margin-top: 14px; }
        .log .row {
            display: flex;
            align-items: center;
            padding: 8px 10px;
            border-bottom: 1px solid #334155;
            font-size: 16px;
        }
        .log .row .dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #10b981;
            flex: 0 0 auto;
        }
        .log .row .dot.bad { background: #ef4444; }
        .log .row .txt { margin-left: 10px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .log .row .at { margin-left: auto; color: #94a3b8; font-family: "Courier New", monospace; }

        .notice { text-align: center; padding: 60px 20px; }
        .notice h1 { font-size: 28px; color: #f8fafc; }
        .notice p { font-size: 18px; color: #94a3b8; }
    </style>
</head>
<body>

@if (! $school->is_active || ! $featureEnabled)
    <div class="notice">
        <h1>{{ $school->name }}</h1>
        <p>
            @if (! $school->is_active)
                Halaman absensi sekolah ini sedang tidak aktif.
            @else
                Absensi sekolah sedang dimatikan oleh admin.
            @endif
        </p>
    </div>
@else
    <div class="bar">
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="">
        @endif
        <div class="brand">
            <b>{{ $school->name }}</b>
            <span>Absensi Digital &middot; mode ringan</span>
        </div>
        <div class="clock" id="clock">--:--:--</div>
    </div>

    <div class="stage" id="stage">
        <div class="idle" id="idle">Tempelkan kartu atau tembak QR Code siswa</div>
    </div>

    <form id="manual" autocomplete="off">
        <input type="text" id="box" placeholder="Tempel kartu / ketik lalu Enter">
    </form>

    <div class="log" id="log"></div>

    <script>
        (function () {
            'use strict';

            var SCAN_URL = @json($scanUrl);
            var SAME_CARD_MS = 1500;
            var RESULT_MS = 2500;
            var KEY_IDLE_MS = 400;
            var MAX_BUFFER = 64;

            var meta = document.querySelector('meta[name="csrf-token"]');
            var CSRF = meta ? meta.getAttribute('content') : '';

            var stage = document.getElementById('stage');
            var idle = document.getElementById('idle');
            var box = document.getElementById('box');
            var logEl = document.getElementById('log');
            var clockEl = document.getElementById('clock');

            var inFlight = {};
            var recent = {};
            var resultTimer = null;
            var entries = [];

            function tick() {
                clockEl.textContent = new Date().toLocaleTimeString('id-ID');
            }
            tick();
            setInterval(tick, 1000);

            /* Bunyi: WebAudio kalau ada, kalau tidak diam saja. */
            var Ctx = window.AudioContext || window.webkitAudioContext;
            var audio = null;

            function beep(freq, ms) {
                if (!Ctx) return;
                try {
                    if (!audio) audio = new Ctx();
                    var osc = audio.createOscillator();
                    var gain = audio.createGain();
                    osc.frequency.value = freq;
                    gain.gain.value = 0.15;
                    osc.connect(gain);
                    gain.connect(audio.destination);
                    osc.start();
                    setTimeout(function () {
                        try { osc.stop(); } catch (e) {}
                    }, ms);
                } catch (e) {}
            }

            function say(text) {
                if (!text) return;
                if (!('speechSynthesis' in window) || !window.SpeechSynthesisUtterance) return;
                try {
                    var u = new SpeechSynthesisUtterance(text);
                    u.lang = 'id-ID';
                    window.speechSynthesis.speak(u);
                } catch (e) {}
            }

            function esc(v) {
                var d = document.createElement('div');
                d.textContent = v === null || v === undefined ? '' : String(v);
                return d.innerHTML;
            }

            function resetStage() {
                stage.className = 'stage';
                stage.innerHTML = '';
                stage.appendChild(idle);
            }

            function showResult(data) {
                var s = data.student;
                stage.innerHTML = '';

                if (data.success && s) {
                    stage.className = 'stage ok';
                    var photo = s.photo_url
                        ? '<img class="photo" src="' + esc(s.photo_url) + '" alt="">'
                        : '<div class="photo none">&#128100;</div>';
                    var lines = '';
                    if (s.classroom) { lines += '<span>Kelas ' + esc(s.classroom) + '</span>'; }
                    if (s.nis) { lines += '<span>NIS ' + esc(s.nis) + '</span>'; }
                    lines += '<span>' + esc(s.status) + ' &middot; ' + esc(s.time) + '</span>';

                    stage.innerHTML =
                        photo +
                        '<div class="who">' +
                        '<div class="name">' + esc(s.full_name) + '</div>' +
                        '<div class="meta">' + lines + '</div>' +
                        '<div class="tag">' + esc(s.type_label) + '</div>' +
                        '</div>';
                } else {
                    stage.className = 'stage bad';
                    stage.innerHTML =
                        '<div class="fail"><div class="mark">&#10007;</div>' +
                        '<div class="msg">' + esc(data.message) + '</div></div>';
                }

                if (resultTimer) clearTimeout(resultTimer);
                resultTimer = setTimeout(resetStage, RESULT_MS);
            }

            function pushLog(data) {
                var s = data.student;
                var label = s && s.full_name ? s.full_name : data.message;
                if (s && s.classroom) { label += ' · ' + s.classroom; }
                entries.unshift({
                    ok: !!data.success,
                    text: label,
                    at: new Date().toLocaleTimeString('id-ID'),
                });
                entries = entries.slice(0, 8);

                var html = '';
                for (var i = 0; i < entries.length; i++) {
                    html +=
                        '<div class="row"><div class="dot' + (entries[i].ok ? '' : ' bad') + '"></div>' +
                        '<div class="txt">' + esc(entries[i].text) + '</div>' +
                        '<div class="at">' + esc(entries[i].at) + '</div></div>';
                }
                logEl.innerHTML = html;
            }

            function submitScan(raw) {
                var token = String(raw).replace(/^\s+|\s+$/g, '');
                if (!token || token.length < 3) return;
                if (inFlight[token]) return;

                var now = new Date().getTime();
                for (var key in recent) {
                    if (now - recent[key] > SAME_CARD_MS) { delete recent[key]; }
                }
                if (recent[token]) return;

                inFlight[token] = true;

                fetch(SCAN_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ token: token })
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        delete inFlight[token];
                        /* Hanya yang berhasil ditahan; yang gagal boleh langsung diulang. */
                        if (data.success) { recent[token] = new Date().getTime(); }
                        showResult(data);
                        pushLog(data);
                        if (data.success) {
                            beep(880, 120);
                            say(data.student ? data.student.full_name : '');
                        } else {
                            beep(220, 300);
                            say(data.message);
                        }
                    })
                    .catch(function () {
                        delete inFlight[token];
                        var fail = { success: false, message: 'Gagal menghubungi server.', student: null };
                        showResult(fail);
                        pushLog(fail);
                        beep(220, 300);
                    });
            }

            /* Barcode gun / pembaca RFID mode HID: mengetik lalu menekan Enter. */
            var buffer = '';
            var keyTimer = null;

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.code === 'NumpadEnter') {
                    e.preventDefault();
                    if (keyTimer) clearTimeout(keyTimer);
                    if (buffer.length >= 3) submitScan(buffer);
                    buffer = '';
                    box.value = '';
                    return;
                }
                if (e.key && e.key.length === 1) {
                    buffer = (buffer + e.key).slice(-MAX_BUFFER);
                    if (keyTimer) clearTimeout(keyTimer);
                    keyTimer = setTimeout(function () { buffer = ''; }, KEY_IDLE_MS);
                }
            });

            document.getElementById('manual').addEventListener('submit', function (e) {
                e.preventDefault();
                var v = box.value.replace(/^\s+|\s+$/g, '');
                if (v.length >= 3) submitScan(v);
                box.value = '';
            });

            /* Fokus selalu direbut supaya remote TV maupun reader mendarat di tempat benar. */
            function grabFocus() {
                try { box.focus(); } catch (e) {}
            }
            grabFocus();
            setInterval(grabFocus, 3000);
            document.addEventListener('click', grabFocus);

            /* Layar jangan tidur. Box lama tidak punya API ini — abaikan diam-diam. */
            if (navigator.wakeLock && navigator.wakeLock.request) {
                try {
                    navigator.wakeLock.request('screen').catch(function () {});
                } catch (e) {}
            }
        })();
    </script>
@endif

</body>
</html>
