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
            'question' => 'Apakah sistem ini gratis?',
            'answer' => 'Kami menyediakan paket gratis untuk sekolah dengan maksimal 100 siswa, termasuk fitur dasar seperti scan QR dan rekap kehadiran. Untuk fitur lengkap seperti notifikasi WhatsApp dan laporan lanjutan, tersedia paket berbayar dengan harga terjangkau.',
        ],
        [
            'question' => 'Bagaimana cara mendaftarkan sekolah?',
            'answer' => 'Cukup klik tombol "Daftar Sekarang", isi data sekolah dan admin utama, lalu verifikasi email. Dalam 5 menit sekolah Anda sudah bisa mulai menggunakan sistem absensi digital. Tim kami juga siap membantu proses onboarding.',
        ],
        [
            'question' => 'Apakah perlu hardware khusus untuk scan QR?',
            'answer' => 'Tidak perlu. Cukup gunakan smartphone atau tablet dengan kamera untuk memindai QR Code. Sistem kami berbasis web sehingga bisa diakses dari browser mana saja tanpa instalasi aplikasi khusus. Untuk kartu RFID, pembaca kartu USB biasa sudah cukup.',
        ],
        [
            'question' => 'Berapa biaya pengiriman notifikasi WhatsApp?',
            'answer' => 'Biaya notifikasi WhatsApp sudah termasuk dalam paket berlangganan, tanpa biaya tambahan per pesan. Kami menggunakan WhatsApp Business API resmi untuk memastikan pengiriman yang andal dan cepat ke semua nomor orang tua.',
        ],
        [
            'question' => 'Bagaimana keamanan data siswa dijaga?',
            'answer' => 'Data disimpan dengan enkripsi end-to-end pada server yang berlokasi di Indonesia. Kami mematuhi regulasi perlindungan data pribadi dan hanya pihak sekolah yang berwenang yang dapat mengakses informasi siswa.',
        ],
        [
            'question' => 'Bisakah digunakan offline?',
            'answer' => 'Fitur scan QR memerlukan koneksi internet untuk mencatat data secara real-time. Namun, data yang sudah tercatat dapat diakses offline melalui fitur ekspor. Kami juga sedang mengembangkan mode offline penuh untuk area dengan koneksi terbatas.',
        ],
    ],

];
