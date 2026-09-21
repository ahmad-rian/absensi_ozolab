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
            Gelap, sesuai permintaan: layar gerbang menyala sepanjang hari di
            lorong yang lampunya jarang dimatikan, dan kertas putih seukuran
            televisi menyilaukan dari jarak dekat.

            Hex saja. Fungsi warna modern tidak dikenal Chrome di bawah 111,
            dan perangkat sasaran halaman ini justru di bawah itu. Daftar
            lengkap yang dilarang ada di kepala berkas, berikut tesnya.
        */
        /*
            Dua keluarga huruf, keduanya font SISTEM.

            Halaman ini dilarang menarik berkas dari luar — itu seluruh alasan
            keberadaannya, dan ada tes yang menjaganya. Jadi tidak ada webfont;
            yang bisa diperbaiki adalah MEMILIH font sistem yang benar alih-alih
            jatuh ke bawaan.

            Jam dulu memakai "Courier New", dan itu yang membuatnya terlihat
            tua: ia font mesin tik 1955 yang kebetulan ada di semua komputer.
            Sekarang jam memakai grotesk sistem — Roboto di Android TV, Segoe
            UI di Windows, SF di Apple — dengan angka selebar sama dan spasi
            yang dirapatkan. Itu bentuk yang sama dengan jam di ponsel mereka
            sendiri.

            Mono disisakan untuk yang memang perlu dibaca per karakter: isi
            kotak token dan jejak bacaan kartu. Di sana pun stack-nya
            dimodernkan; `ui-monospace` menunjuk mono bawaan sistem, dan Courier
            baru dipakai kalau benar-benar tidak ada yang lain.
        */
        * { box-sizing: border-box; }

        /*
            Tepat satu layar, tidak pernah menggulir.

            `height` yang dipatok, bukan `min-height`: yang kedua membiarkan
            halaman tumbuh begitu riwayat scan bertambah, dan setelah delapan
            kartu ditempel kotak isian terdorong keluar pandangan. Di gerbang
            tidak ada yang menggulir — tidak ada tetikus, dan remote TV tidak
            bisa.

            Konsekuensinya tiap bagian harus sanggup menyusut; `min-height: 0`
            di setiap wadah flex yang menurunkan ruang itulah yang membuatnya
            mungkin.
        */
        html, body { height: 100%; }
        body {
            margin: 0;
            padding: 20px;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: #0f172a;
            color: #e2e8f0;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-text-size-adjust: 100%;
        }

        /* Isi dibatasi lebarnya. Tanpa ini, di TV 1920px nama siswa terlempar
           jauh ke kanan fotonya dan barisnya mustahil dipindai mata. */
        .wrap {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }

        .bar {
            display: flex;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 1px solid #1e293b;
            margin-bottom: 16px;
            flex: 0 0 auto;
        }
        .bar img { width: 48px; height: 48px; object-fit: contain; }
        .bar .brand { margin-left: 12px; }
        .bar .brand b { display: block; font-size: 24px; font-weight: bold; color: #f8fafc; }
        .bar .brand span {
            display: block;
            margin-top: 2px;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.1em;
            color: #64748b;
        }
        .bar .clock {
            margin-left: auto;
            padding-left: 14px;
            font-size: 46px;
            font-weight: bold;
            color: #f8fafc;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            /* Angka selebar sama, supaya jamnya tidak bergoyang tiap detik. */
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.02em;
            line-height: 1;
        }
        /*
            Detik dikecilkan dan diredupkan.

            Yang dibaca operator dari seberang lorong jam dan menit; detik cuma
            penanda bahwa layarnya masih hidup. Memberinya ukuran yang sama
            membuat ketiganya berebut perhatian.
        */
        .bar .clock .dtk {
            font-size: 0.55em;
            font-weight: bold;
            color: #64748b;
            letter-spacing: 0;
        }

        /*
            Tiga banding satu, MENDATAR.

            Kiri untuk absen, kanan untuk riwayat — bukan bertumpuk. Di layar
            gerbang yang lebar, menumpuk riwayat di bawah membuang seluruh
            ruang kosong di samping hasil scan, sementara riwayatnya sendiri
            cuma kebagian beberapa baris.

            Di bawah 768px keduanya kembali bertumpuk; satu kolom selebar
            seperempat dari 360px tidak muat menampung nama siapa pun.
        */
        .badan {
            flex: 1;
            min-height: 0;
            display: flex;
            align-items: stretch;
        }
        .kiri {
            flex: 3 1 0;
            min-width: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }

        .stage {
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
            border: 1px solid #334155;
            border-radius: 6px;
            background: #1e293b;
            display: flex;
            flex-direction: column;
        }
        .stage.ok { border-color: #10b981; }
        .stage.bad { border-color: #ef4444; }

        .idle {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
            color: #94a3b8;
            font-size: 28px;
        }

        /*
            Pita status: satu bidang warna selebar panel.

            Inilah yang terbaca dari seberang lorong. Lencana kecil di sudut
            tidak cukup — operator berdiri beberapa meter dari layar dan yang
            perlu ia tahu cuma satu hal, kartu ini diterima atau tidak.
        */
        .pita {
            flex: 0 0 auto;
            padding: 12px 20px;
            color: #ffffff;
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .stage.ok .pita { background: #059669; }
        .stage.bad .pita { background: #dc2626; }
        .pita .jam {
            float: right;
            font-variant-numeric: tabular-nums;
            font-weight: normal;
            letter-spacing: 0;
        }

        .isi {
            flex: 1;
            min-height: 0;
            padding: 22px;
            display: flex;
            align-items: center;
            overflow: hidden;
        }

        .photo {
            width: 260px;
            height: 347px;
            object-fit: contain;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 3px;
            flex: 0 0 auto;
        }
        /*
            Tanpa foto: inisial siswa, bukan ikon orang generik.

            Dua huruf nama masih memberi tahu siapa barusan lewat, bahkan
            ketika pas fotonya memang belum dipasang.
        */
        .photo.none {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 84px;
            font-weight: bold;
            color: #475569;
            letter-spacing: 0.02em;
        }

        .who { margin-left: 24px; min-width: 0; }
        .who .name {
            font-size: 58px;
            font-weight: bold;
            color: #f8fafc;
            line-height: 1.05;
            word-wrap: break-word;
        }
        .who .meta { margin-top: 12px; font-size: 21px; color: #94a3b8; }
        .who .meta span { display: block; margin-top: 4px; }

        .fail {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 20px;
            text-align: center;
            overflow: hidden;
        }
        .fail .msg { font-size: 38px; font-weight: bold; color: #f8fafc; }
        .fail .probe {
            margin-top: 12px;
            font-size: 15px;
            color: #64748b;
            font-family: ui-monospace, "SF Mono", "Cascadia Mono", "Roboto Mono", "DejaVu Sans Mono", Consolas, "Courier New", monospace;
        }

        form { margin-top: 14px; flex: 0 0 auto; }
        input[type=text] {
            width: 100%;
            padding: 13px;
            font-size: 19px;
            text-align: center;
            border-radius: 6px;
            border: 1px solid #334155;
            background: #1e293b;
            color: #f8fafc;
            font-family: ui-monospace, "SF Mono", "Cascadia Mono", "Roboto Mono", "DejaVu Sans Mono", Consolas, "Courier New", monospace;
        }
        input[type=text]:focus { outline: none; border-color: #10b981; }
        input[type=text]::placeholder { color: #64748b; }

        /*
            Seperempat sisanya: siapa saja yang barusan scan.

            Berpanel sendiri, bukan daftar telanjang — operator memakainya
            untuk memastikan kartu yang barusan ditempel memang tercatat, dan
            batas panelnya yang memisahkan itu dari hasil scan di atasnya.
            Yang terbaru di atas; begitu ruang habis, yang terpotong dari bawah
            adalah yang paling lama.
        */
        .log {
            flex: 1 1 0;
            min-width: 0;
            min-height: 0;
            margin-left: 14px;
            display: flex;
            flex-direction: column;
            border: 1px solid #334155;
            border-radius: 6px;
            background: #1e293b;
            overflow: hidden;
        }
        .log .kepala {
            flex: 0 0 auto;
            padding: 8px 14px;
            border-bottom: 1px solid #334155;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.1em;
            color: #64748b;
        }
        /* Gulir DI DALAM panel, bukan di halaman. Tanpa ini baris terakhir
           terpotong separuh tanpa satu pun tanda bahwa masih ada di bawahnya. */
        .log .isi-log { flex: 1; min-height: 0; overflow-y: auto; }
        .log .kosong { padding: 14px; font-size: 15px; color: #64748b; }
        .log .row {
            display: flex;
            align-items: flex-start;
            padding: 9px 14px;
            border-bottom: 1px solid #0f172a;
            font-size: 16px;
        }
        .log .row .dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #10b981;
            flex: 0 0 auto;
            /* Sejajar dengan baris nama, bukan dengan tengah dua baris. */
            margin-top: 6px;
        }
        .log .row .dot.bad { background: #ef4444; }
        /*
            Dua baris, bukan satu.

            Kolomnya cuma seperempat layar — sekitar 290px di TV 1080p — dan
            nama berikut jam pada satu baris menyisakan begitu sedikit ruang
            sampai "Muhammad Rizky Ramadhan" terpotong jadi "Muhammad Riz…".
            Nama mendapat barisnya sendiri; kelas dan jam turun ke bawahnya.
        */
        .log .row .txt { margin-left: 10px; min-width: 0; flex: 1; }
        .log .row .txt b {
            display: block;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            font-weight: bold;
        }
        .log .row .txt span {
            display: block;
            margin-top: 1px;
            font-size: 13px;
            color: #64748b;
            font-variant-numeric: tabular-nums;
        }

        .notice { max-width: 640px; margin: auto; text-align: center; padding: 40px 20px; }
        .notice h1 { font-size: 28px; margin: 0; color: #f8fafc; }
        .notice p { font-size: 17px; color: #94a3b8; margin-top: 12px; }

        /*
            Layar sempit: ponsel dan tablet kecil.

            Tautan pendeknya memang dibuka di HP — operator mengeceknya dari
            sana. Tanpa ambang ini nama sekolah membungkus lalu menabrak jam,
            dan jamnya terpotong di tepi kanan.
        */
        @media (max-width: 768px) {
            body { padding: 12px; }

            .bar { padding-bottom: 10px; margin-bottom: 12px; }
            .bar img { width: 34px; height: 34px; }
            .bar .brand { margin-left: 10px; }
            .bar .brand b { font-size: 16px; line-height: 1.15; }
            .bar .brand span { font-size: 9px; }
            .bar .clock { padding-left: 10px; font-size: 25px; }

            .idle { font-size: 18px; padding: 16px; }
            .pita { padding: 9px 14px; font-size: 20px; }

            /* Foto di atas nama: 260px foto berdampingan dengan nama 58px
               mustahil di layar selebar 360. */
            .isi {
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                padding: 14px;
            }
            .photo { width: 132px; height: 176px; }
            .photo.none { font-size: 46px; }

            .who { margin-left: 0; margin-top: 12px; }
            .who .name { font-size: 27px; }
            .who .meta { margin-top: 6px; font-size: 14px; }
            .who .meta span { margin-top: 2px; }

            .fail { padding: 16px 12px; }
            .fail .msg { font-size: 22px; }
            .fail .probe { margin-top: 8px; font-size: 12px; }

            form { margin-top: 10px; }
            input[type=text] { padding: 11px; font-size: 16px; }

            /* Bertumpuk lagi: kolom seperempat dari 360px tidak muat
               menampung nama siapa pun. */
            .badan { flex-direction: column; }
            .kiri { flex: 3 1 0; }
            .log { flex: 1 1 0; margin-left: 0; margin-top: 10px; }
            .log .kepala { padding: 6px 12px; font-size: 9px; }
            .log .row { padding: 7px 12px; font-size: 13px; }
            .log .kosong { padding: 10px 12px; font-size: 13px; }

            .notice { padding: 30px 16px; }
            .notice h1 { font-size: 21px; }
            .notice p { font-size: 15px; }
        }

        /*
            Layar PENDEK, bukan sempit: ponsel yang diputar mendatar, dan box
            TV yang menyetel keluarannya ke 720p lalu di-overscan.

            Foto kembali berdampingan dengan nama — menumpuknya butuh tinggi
            yang justru sedang tidak ada.
        */
        @media (max-height: 560px) {
            .bar { padding-bottom: 7px; margin-bottom: 8px; }
            .bar img { width: 28px; height: 28px; }
            .bar .brand b { font-size: 14px; }
            .bar .brand span { display: none; }
            .bar .clock { font-size: 20px; }

            .pita { padding: 6px 12px; font-size: 16px; }

            .isi {
                flex-direction: row;
                align-items: center;
                text-align: left;
                padding: 10px;
            }
            .photo { width: 84px; height: 112px; }
            .photo.none { font-size: 32px; }
            .who { margin-left: 12px; margin-top: 0; }
            .who .name { font-size: 22px; }
            .who .meta { margin-top: 3px; font-size: 12px; }

            .idle { font-size: 16px; padding: 10px; }
            .fail { padding: 10px; }
            .fail .msg { font-size: 18px; }
            .fail .probe { margin-top: 5px; font-size: 11px; }

            form { margin-top: 7px; }
            input[type=text] { padding: 7px; font-size: 14px; }

            .log { margin-top: 7px; }
            .log .kepala { padding: 4px 12px; }
            /* Layar pendek TAPI lebar (ponsel mendatar): dua kolom justru
               menolong — tingginya yang langka, bukan lebarnya. */
            @media (min-width: 640px) {
                .badan { flex-direction: row; }
                .log { margin-left: 10px; margin-top: 0; }
            }
            .log .row { padding: 4px 12px; font-size: 12px; }
        }

        @media (max-width: 420px) {
            .bar img { width: 26px; height: 26px; }
            .bar .brand b { font-size: 14px; }
            .bar .clock { font-size: 19px; }

            .pita { font-size: 17px; }
            /* Jam di pita disembunyikan: baris riwayat tepat di bawahnya sudah
               mencatat waktu yang sama, dan di lebar ini ia yang pertama
               mendorong teks status keluar layar. */
            .pita .jam { display: none; }

            .photo { width: 112px; height: 150px; }
            .photo.none { font-size: 38px; }
            .who .name { font-size: 23px; }
            .fail .msg { font-size: 19px; }
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
            <div class="clock" id="clock">--.--<span class="dtk">.--</span></div>
        </div>

        <div class="badan">
            <div class="kiri">
                <div class="stage" id="stage">
                    <div class="idle" id="idle">Tempelkan kartu atau tembak QR Code siswa</div>
                </div>

                <form id="manual" autocomplete="off">
                    <input type="text" id="box" placeholder="Tempel kartu / ketik lalu Enter">
                </form>
            </div>

            <div class="log">
                <div class="kepala">RIWAYAT SCAN</div>
                <div class="isi-log" id="log">
                    <div class="kosong">Belum ada kartu yang discan.</div>
                </div>
            </div>
        </div>
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
                var d = new Date();

                /*
                    Detik dipisah ke elemennya sendiri supaya bisa dikecilkan.
                    Aman memakai innerHTML di sini: isinya angka hasil `Date`,
                    tidak ada satu pun masukan pengguna yang lewat jalur ini.
                */
                clockEl.innerHTML = duaAngka(d.getHours()) + '.' + duaAngka(d.getMinutes()) +
                    '<span class="dtk">.' + duaAngka(d.getSeconds()) + '</span>';
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
                entries.unshift({
                    ok: !!data.success,
                    nama: s && s.full_name ? s.full_name : data.message,
                    kelas: s && s.classroom ? s.classroom : '',
                    at: jam(new Date()),
                });
                /*
                    Dua belas, bukan delapan.

                    Panelnya sekarang seperempat layar dan memotong sendiri
                    apa yang tidak muat lewat `overflow: hidden`, jadi batas
                    ini cuma menahan memori — bukan lagi yang menentukan
                    berapa baris terlihat. Di TV 1080p muat sekitar sepuluh.
                */
                entries = entries.slice(0, 12);

                var html = '';
                for (var i = 0; i < entries.length; i++) {
                    var bawah = entries[i].kelas
                        ? entries[i].kelas + ' \u00b7 ' + entries[i].at
                        : entries[i].at;

                    html +=
                        '<div class="row"><div class="dot' + (entries[i].ok ? '' : ' bad') + '"></div>' +
                        '<div class="txt"><b>' + esc(entries[i].nama) + '</b>' +
                        '<span>' + esc(bawah) + '</span></div></div>';
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
