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
    {{-- Alamatnya memuat kode akses sekolah. Terindeks = bocor. --}}
    <meta name="robots" content="noindex, nofollow">
    {{-- Favicon kosong, bukan tanpa favicon sama sekali. Tanpa baris ini
         peramban tetap meminta /favicon.ico, dan permintaan itu masuk ke
         Laravel lengkap dengan seluruh middleware hanya untuk dijawab 404. --}}
    <link rel="icon" href="data:,">
    <title>Absensi {{ $school->name }}</title>
    <style>
        /*
            Palet dipilih, bukan diwarisi.

            Netral condong dingin supaya senada dengan keluarga slate yang
            sudah dipakai aplikasinya, dengan satu hijau dan satu merah yang
            cukup gelap untuk terbaca sebagai teks putih di atasnya. Hex saja:
            fungsi warna modern tidak dikenal Chrome di bawah 111, dan
            perangkat sasaran halaman ini justru di bawah itu. Daftar lengkap
            yang dilarang ada di kepala berkas, dan ada tes yang menjaganya.
        */
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-text-size-adjust: 100%;
        }
        body {
            padding: 24px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Isi dibatasi lebarnya. Tanpa ini, di TV 1920px nama siswa terlempar
           jauh ke kanan fotonya dan barisnya jadi mustahil dipindai mata. */
        .wrap {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .bar {
            display: flex;
            align-items: center;
            padding-bottom: 14px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }
        .bar img { width: 52px; height: 52px; object-fit: contain; }
        .bar .brand { margin-left: 12px; }
        .bar .brand b { display: block; font-size: 26px; font-weight: bold; }
        .bar .brand span {
            display: block;
            margin-top: 2px;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.1em;
            color: #64748b;
        }
        .bar .clock {
            margin-left: auto;
            font-size: 48px;
            font-weight: bold;
            font-family: "Courier New", monospace;
            /* Angka selebar sama, supaya jamnya tidak bergoyang tiap detik. */
            font-variant-numeric: tabular-nums;
        }

        .stage {
            flex: 1;
            min-height: 420px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
        }
        /* Keadaan hasil memakai kertas putih; warnanya dibawa pita status. */
        .stage.ok, .stage.bad { background: #ffffff; }
        .stage.ok { border-color: #047857; }
        .stage.bad { border-color: #b91c1c; }

        .idle {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
            color: #64748b;
            font-size: 30px;
        }

        /*
            Pita status: satu bidang warna selebar panel.

            Inilah yang terbaca dari seberang lorong. Lencana kecil di sudut
            tidak cukup — operator berdiri beberapa meter dari layar dan yang
            perlu ia tahu hanya satu hal, kartu ini diterima atau tidak.
        */
        .pita {
            padding: 14px 22px;
            color: #ffffff;
            font-size: 34px;
            font-weight: bold;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .stage.ok .pita { background: #047857; }
        .stage.bad .pita { background: #b91c1c; }
        .pita .jam {
            float: right;
            font-family: "Courier New", monospace;
            font-variant-numeric: tabular-nums;
            font-weight: normal;
        }

        .isi { flex: 1; padding: 26px; display: flex; align-items: center; }

        .photo {
            width: 300px;
            height: 400px;
            object-fit: contain;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 2px;
            flex: 0 0 auto;
        }
        /*
            Tanpa foto: inisial siswa, bukan ikon orang generik.

            Sebelumnya di sini ada emoji 👤 — hiasan yang tidak memberi tahu
            apa pun. Dua huruf nama masih memberi tahu siapa yang barusan
            lewat, bahkan ketika pas fotonya memang belum dipasang.
        */
        .photo.none {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 96px;
            font-weight: bold;
            color: #94a3b8;
            letter-spacing: 0.02em;
        }

        .who { margin-left: 26px; min-width: 0; }
        .who .name {
            font-size: 64px;
            font-weight: bold;
            line-height: 1.05;
            word-wrap: break-word;
        }
        .who .meta { margin-top: 14px; font-size: 22px; color: #64748b; }
        .who .meta span { display: block; margin-top: 5px; }

        .fail {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px 22px;
            text-align: center;
        }
        .fail .msg { font-size: 42px; font-weight: bold; }
        .fail .probe {
            margin-top: 14px;
            font-size: 16px;
            color: #64748b;
            font-family: "Courier New", monospace;
        }

        form { margin-top: 16px; }
        input[type=text] {
            width: 100%;
            padding: 14px;
            font-size: 20px;
            text-align: center;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #0f172a;
            font-family: "Courier New", monospace;
        }
        input[type=text]:focus { outline: none; border-color: #0f172a; }

        .log { margin-top: 20px; }
        .log .row {
            display: flex;
            align-items: center;
            padding: 9px 2px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 16px;
        }
        .log .row .dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #047857;
            flex: 0 0 auto;
        }
        .log .row .dot.bad { background: #b91c1c; }
        .log .row .txt { margin-left: 12px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .log .row .at {
            margin-left: auto;
            padding-left: 12px;
            color: #64748b;
            font-family: "Courier New", monospace;
            font-variant-numeric: tabular-nums;
        }

        .notice { max-width: 640px; margin: 0 auto; text-align: center; padding: 80px 20px; }
        .notice h1 { font-size: 30px; margin: 0; }
        .notice p { font-size: 18px; color: #64748b; margin-top: 12px; }

        /*
            Layar sempit: ponsel dan tablet kecil.

            Halaman ini dibuat untuk box Android TV, dan seluruh ukurannya px
            tetap tanpa satu pun media query. Begitu tautan pendeknya dibuka di
            ponsel — dan itu memang terjadi, operator mengeceknya dari HP —
            nama sekolah bertabrakan dengan jam, jamnya terpotong di tepi
            kanan, dan kotak menganggur setinggi 420px memenuhi seluruh layar.

            Dua ambang, bukan satu: 768px membereskan tata letaknya, 420px
            mengurus ponsel yang benar-benar sempit.
        */
        @media (max-width: 768px) {
            body { padding: 14px; }

            .bar { padding-bottom: 10px; margin-bottom: 14px; }
            .bar img { width: 36px; height: 36px; }
            .bar .brand { margin-left: 10px; }
            .bar .brand b { font-size: 17px; line-height: 1.15; }
            .bar .brand span { font-size: 10px; }
            .bar .clock {
                /* Jam dipersempit lebih dulu: ia yang mendorong nama sekolah
                   sampai membungkus dan menabrak. */
                padding-left: 10px;
                font-size: 26px;
            }

            /*
                Panggung berhenti memanjang mengikuti tinggi layar.

                Di TV ia memang harus tumbuh — hasil scan yang besar itu
                gunanya. Di ponsel yang tinggi dan sempit, tumbuh berarti satu
                kotak kosong sepanjang layar dengan satu kalimat mengambang di
                tengahnya, dan riwayat scan terdorong keluar pandangan.
            */
            .stage { flex: 0 0 auto; min-height: 220px; }
            .idle { font-size: 19px; padding: 26px 16px; }

            .pita { padding: 10px 14px; font-size: 21px; }

            /* Foto di atas nama, bukan di sampingnya: 300px foto + nama 64px
               mustahil berdampingan di layar selebar 360. */
            .isi {
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 18px;
            }
            .photo { width: 150px; height: 200px; }
            .photo.none { font-size: 52px; }

            .who { margin-left: 0; margin-top: 14px; }
            .who .name { font-size: 30px; }
            .who .meta { margin-top: 8px; font-size: 15px; }
            .who .meta span { margin-top: 3px; }

            .fail { padding: 22px 14px; }
            .fail .msg { font-size: 24px; }
            .fail .probe { font-size: 13px; }

            input[type=text] { padding: 12px; font-size: 16px; }

            .log { margin-top: 14px; }
            .log .row { font-size: 14px; padding: 8px 2px; }

            .notice { padding: 50px 16px; }
            .notice h1 { font-size: 22px; }
            .notice p { font-size: 15px; }
        }

        @media (max-width: 420px) {
            .bar img { width: 30px; height: 30px; }
            .bar .brand b { font-size: 15px; }
            .bar .clock { font-size: 21px; }

            .pita { font-size: 18px; }
            /* Jam di pita disembunyikan: baris log tepat di bawahnya sudah
               mencatat waktu yang sama, dan di lebar ini ia yang pertama
               mendorong teks status keluar layar. */
            .pita .jam { display: none; }

            .photo { width: 124px; height: 166px; }
            .photo.none { font-size: 42px; }
            .who .name { font-size: 25px; }
            .fail .msg { font-size: 20px; }
        }
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
    <div class="wrap">
        <div class="bar">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="">
            @endif
            <div class="brand">
                <b>{{ $school->name }}</b>
                <span>ABSENSI DIGITAL</span>
            </div>
            <div class="clock" id="clock">--.--.--</div>
        </div>

        <div class="stage" id="stage">
            <div class="idle" id="idle">Tempelkan kartu atau tembak QR Code siswa</div>
        </div>

        <form id="manual" autocomplete="off">
            <input type="text" id="box" placeholder="Tempel kartu / ketik lalu Enter">
        </form>

        <div class="log" id="log"></div>
    </div>

    <script>
        (function () {
            'use strict';

            var SCAN_URL = @json($scanUrl);
            var SAME_CARD_MS = 1500;
            var RESULT_MS = 2500;
            var KEY_IDLE_MS = 600;
            var MAX_BUFFER = 64;
            /* Panjang minimal untuk dikirim tanpa Enter. Token QR dan UID kartu
               selalu jauh lebih panjang. */
            var MIN_TOKEN = 6;
            /* Rata-rata jeda antar-tombol yang masih dianggap diketik mesin.
               Pembaca kartu 5-20 ms; manusia tercepat pun di atas 50 ms. */
            var MACHINE_MS_PER_KEY = 50;

            /* Bunyi dan suara bisa dimatikan lewat ?diam=1 untuk gerbang
               yang ramai — mesin TTS Android jauh lebih berat daripada
               halamannya sendiri. */
            var DIAM = /[?&]diam=1\b/.test(window.location.search);

            var stage = document.getElementById('stage');
            var idle = document.getElementById('idle');
            var box = document.getElementById('box');
            var logEl = document.getElementById('log');
            var clockEl = document.getElementById('clock');

            var inFlight = {};
            var recent = {};
            var resultTimer = null;
            var entries = [];
            /* Bentuk bacaan terakhir, dipakai hanya saat menampilkan kegagalan. */
            var terakhir = null;

            /* Dirakit sendiri, bukan lewat pemformat berlokal bawaan Date.
               Locale non-default memaksa jalur ICU, dan ini berjalan sekali per
               detik selamanya di perangkat yang justru paling lemah. Hasilnya
               sama persis untuk HH:MM:SS. Ada tes penjaga yang melarang
               pemformat itu kembali ke berkas ini. */
            function duaAngka(n) {
                return n < 10 ? '0' + n : String(n);
            }

            function jam(d) {
                return duaAngka(d.getHours()) + '.' + duaAngka(d.getMinutes()) + '.' + duaAngka(d.getSeconds());
            }

            function tick() {
                clockEl.textContent = jam(new Date());
            }
            tick();
            setInterval(tick, 1000);

            /* Bunyi: WebAudio kalau ada, kalau tidak diam saja. */
            var Ctx = window.AudioContext || window.webkitAudioContext;
            var audio = null;

            function beep(freq, ms) {
                if (!Ctx || DIAM) return;
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
                if (!text || DIAM) return;
                if (!('speechSynthesis' in window) || !window.SpeechSynthesisUtterance) return;
                try {
                    /* Dibatalkan dulu. Tanpa ini antrean TTS menumpuk saat
                       siswa datang beruntun, dan gerbang membacakan nama anak
                       yang sudah lewat setengah menit lalu. */
                    window.speechSynthesis.cancel();

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

            /*
                Inisial dari nama, maksimal dua huruf.

                Dipakai saat pas fotonya belum ada. Sebelumnya di sini ada
                emoji orang — hiasan yang tidak memberi tahu apa pun. Dua huruf
                masih memberi tahu siapa yang barusan lewat.
            */
            function inisial(nama) {
                var kata = String(nama || '').split(/\s+/);
                var hasil = '';

                for (var i = 0; i < kata.length && hasil.length < 2; i++) {
                    if (kata[i]) { hasil += kata[i].charAt(0); }
                }

                return hasil.toUpperCase() || '?';
            }

            function showResult(data) {
                var s = data.student;
                stage.innerHTML = '';

                if (data.success && s) {
                    stage.className = 'stage ok';

                    var photo = s.photo_url
                        ? '<img class="photo" src="' + esc(s.photo_url) + '" alt="">'
                        : '<div class="photo none">' + esc(inisial(s.full_name)) + '</div>';

                    var lines = '';
                    if (s.classroom) { lines += '<span>Kelas ' + esc(s.classroom) + '</span>'; }
                    if (s.nis) { lines += '<span>NIS ' + esc(s.nis) + '</span>'; }
                    if (s.status) { lines += '<span>' + esc(s.status) + '</span>'; }

                    /* Pita: satu kata besar yang terbaca dari seberang lorong,
                       jam scan di ujung kanannya. */
                    stage.innerHTML =
                        '<div class="pita">' + esc(s.type_label) +
                        '<span class="jam">' + esc(s.time) + '</span></div>' +
                        '<div class="isi">' + photo +
                        '<div class="who">' +
                        '<div class="name">' + esc(s.full_name) + '</div>' +
                        '<div class="meta">' + lines + '</div>' +
                        '</div></div>';

                    /*
                        Berkas fotonya hilang dari disk: tampilkan inisial, bukan
                        ikon gambar rusak bawaan peramban.

                        Bukan kasus karangan — `photo_path` bisa menunjuk berkas
                        yang sudah tidak ada, dan yang terlihat operator selama
                        ini cuma kotak abu berlogo sobek di tengah layar gerbang.
                    */
                    var gambar = stage.querySelector('img.photo');

                    if (gambar) {
                        gambar.onerror = function () {
                            var ganti = document.createElement('div');
                            ganti.className = 'photo none';
                            ganti.textContent = inisial(s.full_name);

                            if (gambar.parentNode) {
                                gambar.parentNode.replaceChild(ganti, gambar);
                            }
                        };
                    }
                } else {
                    stage.className = 'stage bad';
                    /* Bentuk bacaan ikut ditampilkan saat gagal. Operator memegang
                       kartunya: kalau tertulis "terbaca 18 karakter" padahal
                       tokennya 35, bacaan terpotongnya kelihatan saat itu juga —
                       tanpa perlu membuka server. */
                    var jejak = terakhir
                        ? '<div class="probe">terbaca ' + terakhir.len + ' karakter &middot; ' +
                          esc(terakhir.awal) + '&hellip;' + esc(terakhir.akhir) + '</div>'
                        : '';
                    stage.innerHTML =
                        '<div class="pita">TIDAK DITERIMA</div>' +
                        '<div class="fail"><div class="msg">' + esc(data.message) + '</div>' + jejak + '</div>';
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
                    at: jam(new Date()),
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
                terakhir = {
                    len: token.length,
                    awal: token.substring(0, 3),
                    akhir: token.length > 3 ? token.substring(token.length - 3) : ''
                };

                fetch(SCAN_URL, {
                    method: 'POST',
                    /* Tanpa X-CSRF-TOKEN: rute gerbang memang dikecualikan
                       dari CSRF dan session. Penjaganya kode sekolah di URL
                       plus token kartu. */
                    headers: {
                        'Content-Type': 'application/json',
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

            /* Barcode gun / pembaca RFID mode HID: mengetik cepat, lalu SEBAGIAN
               menekan Enter. Yang tidak menekan Enter ditangani timer di bawah. */
            var buffer = '';
            var firstAt = 0;
            var lastAt = 0;
            var keyTimer = null;

            function resetBuffer() {
                buffer = '';
                firstAt = 0;
                lastAt = 0;
                box.value = '';
            }

            /* Kotak isian adalah sumber UTAMA, bukan cadangan: elemen input
               tidak punya timer dan tidak bisa terpotong, sedangkan buffer bisa
               terpangkas kalau satu keystroke tertunda melewati KEY_IDLE_MS di
               perangkat lemot. Buffer hanya dipakai kalau fokus tidak di kotak. */
            function flushBuffer() {
                var dariInput = box.value.replace(/^\s+|\s+$/g, '');
                var token = dariInput !== '' ? dariInput : buffer;

                resetBuffer();

                if (token.length >= 3) submitScan(token);
            }

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.code === 'NumpadEnter' || e.keyCode === 13) {
                    e.preventDefault();
                    if (keyTimer) clearTimeout(keyTimer);
                    keyTimer = null;
                    flushBuffer();
                    return;
                }
                if (e.key && e.key.length === 1) {
                    var now = new Date().getTime();
                    if (buffer === '') firstAt = now;
                    lastAt = now;
                    buffer = (buffer + e.key).slice(-MAX_BUFFER);

                    if (keyTimer) clearTimeout(keyTimer);
                    keyTimer = setTimeout(function () {
                        keyTimer = null;

                        /* Pembaca yang tidak mengirim Enter: ketikannya berhenti
                           begitu saja. Dulu buffer-nya justru DIBUANG di sini. */
                        var perKey = buffer.length > 1 ? (lastAt - firstAt) / (buffer.length - 1) : Infinity;

                        if (buffer.length >= MIN_TOKEN && perKey <= MACHINE_MS_PER_KEY) {
                            flushBuffer();
                        } else {
                            resetBuffer();
                        }
                    }, KEY_IDLE_MS);
                }
            });

            document.getElementById('manual').addEventListener('submit', function (e) {
                e.preventDefault();
                var v = box.value.replace(/^\s+|\s+$/g, '');
                if (v.length >= 3) submitScan(v);
                box.value = '';
            });

            /*
                Fokus direbut saat benar-benar lepas, bukan tiap tiga detik.

                Versi sebelumnya memanggil box.focus() lewat setInterval
                selamanya. Selain memaksa recalc terus-menerus di perangkat
                yang paling tidak sanggup menanggungnya, pada sebagian build
                Android panggilan focus() berulang memunculkan papan ketik
                layar di tengah gerbang yang sedang dipakai.
            */
            function grabFocus() {
                try { box.focus(); } catch (e) {}
            }
            grabFocus();
            box.addEventListener('focusout', function () {
                /* Ditunda satu putaran: focusout menyala juga saat fokus
                   berpindah ke elemen lain di halaman yang sama. */
                setTimeout(grabFocus, 0);
            });
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
