<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Alamat kanonik
    |--------------------------------------------------------------------------
    |
    | Dipakai untuk <link rel="canonical">, og:url, sitemap, dan llms.txt —
    | SELALU alamat ini, bukan host yang kebetulan menyajikan permintaan.
    |
    | Itu inti perbaikannya. Halaman ini pernah tersaji di host lain, Google
    | mengindeksnya di sana, dan tanpa canonical tidak ada yang memberi tahu
    | Google host mana yang benar — hasil pencarian sampai sekarang menampilkan
    | judul dan deskripsi situs ini di bawah domain yang bukan miliknya.
    |
    | Kalau `url()->current()` yang dipakai, canonical-nya ikut salah persis di
    | keadaan yang mau diperbaiki. Karena itu nilainya dari konfigurasi.
    |
    */

    'canonical_url' => rtrim((string) env('SEO_CANONICAL_URL', env('APP_URL', 'http://localhost')), '/'),

    'site_name' => env('SEO_SITE_NAME', 'Tyas Photo'),

    'locale' => 'id_ID',

    'default' => [
        'title' => 'Tyas Photo — Absensi Sekolah Digital dengan QR & RFID',
        'description' => 'Platform absensi sekolah berbasis QR Code dan kartu RFID. Rekap kehadiran otomatis, notifikasi WhatsApp ke orang tua, kartu pelajar dan pas foto siap cetak. Daftarkan sekolah dalam 5 menit.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Halaman yang boleh diindeks
    |--------------------------------------------------------------------------
    |
    | Daftar putih, bukan daftar hitam. Aplikasi ini penuh halaman bertoken —
    | /scan/{token}, /g/{kode}, /f/{token} — dan token yang terindeks sama saja
    | dengan token yang bocor. Komponen yang tidak disebut di sini otomatis
    | `noindex, nofollow`.
    |
    */

    'indexable' => [
        'welcome' => [
            'title' => 'Tyas Photo — Absensi Sekolah Digital dengan QR & RFID',
            'description' => 'Platform absensi sekolah berbasis QR Code dan kartu RFID. Rekap kehadiran otomatis, notifikasi WhatsApp ke orang tua, kartu pelajar dan pas foto siap cetak. Daftarkan sekolah dalam 5 menit.',
            'path' => '/',
        ],
        'student-register' => [
            'title' => 'Daftar Sekolah — Tyas Photo',
            'description' => 'Daftarkan sekolah Anda ke Tyas Photo. Isi data sekolah dan admin utama, lalu mulai mencatat kehadiran siswa dengan QR Code atau kartu RFID hari itu juga.',
            'path' => '/daftar',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Penyedia
    |--------------------------------------------------------------------------
    */

    'organization' => [
        'name' => 'Ozolab',
        'url' => 'https://ozolab.id',
        'area' => 'Purwokerto, Jawa Tengah, Indonesia',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pertanyaan yang sering diajukan
    |--------------------------------------------------------------------------
    |
    | Satu sumber untuk dua pemakai: bagian FAQ di halaman utama, dan skema
    | FAQPage yang dibaca mesin pencari serta ringkasan AI. Dua salinan pasti
    | menyimpang, dan yang menyimpang diam-diam adalah yang dibaca mesin.
    |
    */

    'faq' => [
        [
            'question' => 'Bagaimana cara mendaftarkan siswa?',
            'answer' => 'Orang tua membuka halaman Daftar, memilih sekolah dan kelas, mengisi data siswa dan nomor WhatsApp, lalu membuat kata sandi Portal Orang Tua. Foto siswa diambil di studio saat sesi foto sekolah.',
        ],
        [
            'question' => 'Kata sandi di formulir pendaftaran itu untuk apa?',
            'answer' => 'Untuk masuk ke Portal Orang Tua, bukan kata sandi email Anda. Buat kata sandi baru minimal 8 karakter. Kalau sudah punya akun untuk anak yang lain, isi dengan kata sandi akun itu.',
        ],
        [
            'question' => 'Apakah perlu hardware khusus untuk scan QR?',
            'answer' => 'Tidak. Pemindai dibuka di browser dan bisa memakai kamera HP atau tablet. Barcode reader USB biasa juga bisa dipakai di gerbang tanpa memasang aplikasi.',
        ],
        [
            'question' => 'Kapan orang tua menerima pesan WhatsApp?',
            'answer' => 'Saat anak tercatat terlambat atau tidak hadir. Riwayat kehadiran lengkap bisa dilihat kapan saja di Portal Orang Tua, dan kabar juga bisa diterima lewat Telegram.',
        ],
        [
            'question' => 'Siapa yang bisa melihat data siswa?',
            'answer' => 'Admin dan guru sekolah sesuai perannya, serta orang tua untuk anaknya sendiri. Setiap sekolah hanya bisa melihat data sekolahnya sendiri.',
        ],
        [
            'question' => 'Apakah scan bisa dipakai tanpa internet?',
            'answer' => 'Belum. Setiap scan langsung dicatat ke server, jadi perangkat di gerbang perlu tersambung internet.',
        ],
    ],

];
