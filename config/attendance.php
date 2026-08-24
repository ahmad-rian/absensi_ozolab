<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Zona Waktu Operasional
    |--------------------------------------------------------------------------
    |
    | Dipakai oleh App\Support\SchoolTime. Terpisah dari `app.timezone` (UTC)
    | supaya timestamp lama tidak bergeser.
    |
    */
    'timezone' => env('ATTENDANCE_TIMEZONE', 'Asia/Jakarta'),

    /*
    |--------------------------------------------------------------------------
    | Jendela Absensi Default
    |--------------------------------------------------------------------------
    |
    | Nilai awal saat membuat baris `attendance_schedules`. Setelah dibuat,
    | tiap sekolah bebas menyesuaikan lewat menu Jadwal Absensi.
    |
    | 06:00 ── HADIR ── 08:00 ── TERLAMBAT ── 13:00 ── PULANG ── 18:00 ── auto out
    |
    */
    'default_schedule' => [
        'check_in_start' => env('ATTENDANCE_CHECK_IN_START', '06:00'),
        'check_in_end' => env('ATTENDANCE_CHECK_IN_END', '08:00'),
        'late_threshold' => env('ATTENDANCE_LATE_THRESHOLD', '08:00'),
        'check_out_start' => env('ATTENDANCE_CHECK_OUT_START', '13:00'),
        'check_out_end' => env('ATTENDANCE_CHECK_OUT_END', '18:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenggang Peringatan Ketidakhadiran
    |--------------------------------------------------------------------------
    |
    | Jeda setelah jendela absensi tutup sebelum `attendance:notify-absence`
    | menilai hari berjalan, supaya scan di menit terakhir tidak kalah cepat
    | dari tick penjadwal. Sejajar dengan `prayer.absence_grace_minutes`.
    |
    */
    'absence_grace_minutes' => env('ATTENDANCE_ABSENCE_GRACE_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Absen Sholat Dzuhur
    |--------------------------------------------------------------------------
    |
    | Nilai awal; tiap sekolah menimpanya lewat `schools.settings`
    | (prayer_enabled, prayer_start, prayer_end, prayer_all_religions).
    | Hari aktif mengikuti `attendance_schedules`, bukan diatur di sini.
    |
    */
    'prayer' => [
        'enabled' => env('PRAYER_ATTENDANCE_ENABLED', false),
        'start' => env('PRAYER_ATTENDANCE_START', '11:00'),
        'end' => env('PRAYER_ATTENDANCE_END', '13:00'),
        'all_religions' => env('PRAYER_ATTENDANCE_ALL_RELIGIONS', false),

        /**
         * Tenggang setelah jendela sholat tutup sebelum hari ini dievaluasi
         * oleh `prayer:notify-absence`, supaya scan pukul 12:59 tidak kalah
         * cepat dari tick penjadwal pukul 13:00.
         */
        'absence_grace_minutes' => env('PRAYER_ABSENCE_GRACE_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Absen Sholat Dhuha
    |--------------------------------------------------------------------------
    |
    | Default MATI. Sekolah yang sudah berjalan hanya menjalankan Dzuhur; Dhuha
    | baru aktif setelah admin menyalakannya di menu Pengaturan. Jendelanya
    | wajib tidak tumpang tindih dengan jendela Dzuhur — divalidasi di
    | PengaturanController supaya deteksi-jam di scanner tidak pernah ambigu.
    |
    | `all_religions` sengaja tidak diduplikasi di sini: kepesertaan lintas
    | agama itu kebijakan sekolah terhadap SISWA, bukan properti waktu sholat,
    | jadi satu key `prayer_all_religions` dipakai bersama kedua jenis.
    |
    */
    'prayer_dhuha' => [
        'enabled' => env('PRAYER_DHUHA_ENABLED', false),
        'start' => env('PRAYER_DHUHA_START', '07:30'),
        'end' => env('PRAYER_DHUHA_END', '09:00'),
    ],

    /**
     * Hari (ISO: 1=Senin … 7=Minggu) yang aktif secara default.
     */
    'default_active_days' => [1, 2, 3, 4, 5],

    /**
     * Hari yang tetap dibuatkan barisnya tapi non-aktif, supaya admin tinggal
     * mencentang tanpa harus membuat jadwal dari nol.
     */
    'default_inactive_days' => [6],

    'qr_token_secret' => env('QR_TOKEN_SECRET', ''),
    'qr_token_length' => 64,
    'qr_cache_days' => 30,
    'qr_storage_path' => 'qr-codes',

    'scanner_rate_limit' => env('SCANNER_RATE_LIMIT', 60),
];
