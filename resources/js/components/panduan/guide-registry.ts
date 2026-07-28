import type { SchoolFeatureKey } from '@/types';

export type GuideTopic = {
    id: string;
    title: string;
    summary: string;
    /** Kata kunci tambahan untuk pencarian — istilah yang dipakai admin sehari-hari. */
    keywords: string[];
    /** Permission modul. Kosong = selalu tampil (mis. Mulai Cepat). */
    permission?: string;
    /** Fitur sekolah. Topiknya hilang kalau sekolah mematikan fiturnya. */
    feature?: SchoolFeatureKey;
    /** Hanya untuk super admin — modul lintas sekolah. */
    superAdminOnly?: boolean;
};

export type GuideChapter = {
    id: string;
    title: string;
    description: string;
    topics: GuideTopic[];
};

/**
 * Satu-satunya sumber daftar topik panduan.
 *
 * Daftar isi, pencarian, dan penyaringan per role semuanya diturunkan dari sini,
 * memakai `permission` dan `feature` yang PERSIS sama dengan yang dipakai
 * app-sidebar.tsx dan middleware di routes/web.php. Konsekuensinya benar dengan
 * sendirinya: admin sekolah tidak melihat panduan modul Sistem, guru hanya
 * melihat yang bisa dia buka, dan topik fitur yang dimatikan sekolahnya hilang.
 */
export const GUIDE_CHAPTERS: GuideChapter[] = [
    {
        id: 'mulai',
        title: 'Mulai Cepat',
        description: 'Kenali aplikasi ini dan apa yang harus dilakukan lebih dulu.',
        topics: [
            {
                id: 'apa-ini',
                title: 'Apa itu Absensi Ozolab',
                summary: 'Gambaran singkat dan peran tiap pengguna.',
                keywords: ['pengenalan', 'role', 'peran', 'super admin', 'guru', 'orang tua'],
            },
            {
                id: 'langkah-pertama',
                title: 'Tiga langkah pertama',
                summary: 'Yang harus disiapkan sebelum absensi bisa dipakai.',
                keywords: ['setup', 'awal', 'persiapan', 'mulai'],
            },
        ],
    },
    {
        id: 'alur',
        title: 'Alur Kerja',
        description: 'Urutan langkah untuk pekerjaan yang melibatkan beberapa menu sekaligus.',
        topics: [
            {
                id: 'sekolah-baru',
                title: 'Menyiapkan sekolah baru',
                summary: 'Dari kelas kosong sampai siswa bisa scan.',
                keywords: ['sekolah baru', 'setup', 'awal', 'kelas', 'jadwal'],
                permission: 'kelas.access',
                feature: 'master_siswa',
            },
            {
                id: 'tahun-ajaran',
                title: 'Awal tahun ajaran',
                summary: 'Aktifkan tahun ajaran, buat kelas, naikkan siswa.',
                keywords: ['tahun ajaran', 'semester', 'kenaikan', 'naik kelas'],
                permission: 'kelas.access',
                feature: 'master_siswa',
            },
            {
                id: 'impor-siswa',
                title: 'Impor siswa dari Excel',
                summary: 'Siapkan berkas, unggah, baca review, terapkan.',
                keywords: ['impor', 'import', 'excel', 'xlsx', 'csv', 'massal'],
                permission: 'siswa.access',
                feature: 'master_siswa',
            },
            {
                id: 'kenaikan-kelas',
                title: 'Kenaikan kelas',
                summary: 'Syaratnya, cara membaca review, dan efeknya ke laporan lama.',
                keywords: ['kenaikan', 'naik kelas', 'promosi', 'riwayat'],
                permission: 'kelas.access',
                feature: 'master_siswa',
            },
            {
                id: 'absensi-harian',
                title: 'Menyiapkan absensi harian',
                summary: 'Jadwal, jam, tautan scan, dan perangkat di gerbang.',
                keywords: ['scan', 'gerbang', 'qr', 'jam masuk', 'jadwal'],
                permission: 'absensi.access',
                feature: 'absensi_sekolah',
            },
            {
                id: 'absen-sholat',
                title: 'Menyalakan absen sholat',
                summary: 'Dhuha dan Dzuhur, satu tautan, plus peringatan penting soal notifikasi.',
                keywords: ['sholat', 'dhuha', 'dzuhur', 'mushola', 'alpa sholat'],
                permission: 'pengaturan.access',
            },
        ],
    },
    {
        id: 'modul',
        title: 'Panduan per Menu',
        description: 'Setiap menu: untuk apa, cara pakai, dan hal yang sering keliru.',
        topics: [
            {
                id: 'siswa',
                title: 'Siswa',
                summary: 'Data induk siswa, QR, kartu, statistik, dan pas foto.',
                keywords: ['siswa', 'murid', 'nis', 'nisn', 'qr'],
                permission: 'siswa.access',
                feature: 'master_siswa',
            },
            {
                id: 'orang-tua',
                title: 'Orang Tua',
                summary: 'Data wali dan tujuan notifikasi.',
                keywords: ['orang tua', 'wali', 'whatsapp', 'email', 'telegram'],
                permission: 'orang-tua.access',
                feature: 'master_siswa',
            },
            {
                id: 'kelas',
                title: 'Kelas',
                summary: 'Rombel, tingkat, wali kelas, dan tahun ajaran.',
                keywords: ['kelas', 'rombel', 'wali kelas', 'tahun ajaran'],
                permission: 'kelas.access',
                feature: 'master_siswa',
            },
            {
                id: 'jadwal-absensi',
                title: 'Jadwal Absensi',
                summary: 'Hari aktif dan jam masuk/pulang per hari.',
                keywords: ['jadwal', 'jam', 'hari', 'terlambat', 'libur'],
                permission: 'jadwal-absensi.access',
                feature: 'absensi_sekolah',
            },
            {
                id: 'absensi',
                title: 'Absensi',
                summary: 'Melihat dan mencatat kehadiran secara manual.',
                keywords: ['absensi', 'kehadiran', 'izin', 'sakit', 'alpa'],
                permission: 'absensi.access',
                feature: 'absensi_sekolah',
            },
            {
                id: 'laporan',
                title: 'Laporan',
                summary: 'Rekap kehadiran dan ekspor CSV/PDF.',
                keywords: ['laporan', 'rekap', 'ekspor', 'csv', 'pdf'],
                permission: 'laporan.access',
                feature: 'laporan',
            },
            {
                id: 'notifikasi',
                title: 'Notifikasi',
                summary: 'Riwayat pesan terkirim dan yang gagal.',
                keywords: ['notifikasi', 'inbox', 'pesan', 'gagal kirim'],
                permission: 'notifikasi.access',
                feature: 'inbox_notifikasi',
            },
            {
                id: 'kartu-album',
                title: 'Kartu & Album',
                summary: 'Frame, layout, generate kartu, dan album foto.',
                keywords: ['kartu', 'osis', 'perpustakaan', 'album', 'frame', 'cetak'],
                permission: 'card-layouts.access',
                feature: 'kartu_album',
            },
            {
                id: 'pengguna',
                title: 'Pengguna',
                summary: 'Menambah admin dan guru, serta hak akses tambahan.',
                keywords: ['pengguna', 'user', 'admin', 'guru', 'akses'],
                permission: 'users.access',
                feature: 'manajemen_pengguna',
            },
            {
                id: 'drive',
                title: 'Google Drive',
                summary: 'Folder foto siswa dan hasil kartu.',
                keywords: ['drive', 'google', 'foto', 'folder'],
                permission: 'drive-config.access',
                feature: 'integrasi_drive',
            },
            {
                id: 'whatsapp',
                title: 'WhatsApp',
                summary: 'Status gateway notifikasi sekolah.',
                keywords: ['whatsapp', 'wa', 'gateway', 'fonnte'],
                permission: 'wa-config.access',
                feature: 'integrasi_whatsapp',
            },
            {
                id: 'pengaturan',
                title: 'Pengaturan Sekolah',
                summary: 'Lima tab: Umum, Tampilan, Fitur, Absen Sholat, Notifikasi.',
                keywords: ['pengaturan', 'setting', 'logo', 'tahun ajaran', 'fitur'],
                permission: 'pengaturan.access',
            },
            {
                id: 'sekolah',
                title: 'Sekolah',
                summary: 'Membuat dan mengelola sekolah, serta token scan.',
                keywords: ['sekolah', 'tenant', 'token', 'scanner'],
                permission: 'schools.access',
                superAdminOnly: true,
            },
            {
                id: 'role',
                title: 'Role & Hak Akses',
                summary: 'Role bawaan dan role custom.',
                keywords: ['role', 'permission', 'hak akses'],
                permission: 'roles.access',
                superAdminOnly: true,
            },
            {
                id: 'gateway',
                title: 'Gateway Notifikasi',
                summary: 'Kredensial WhatsApp, Telegram, dan SMTP per sekolah.',
                keywords: ['gateway', 'smtp', 'telegram', 'fonnte', 'kredensial'],
                permission: 'notification-gateways.access',
                superAdminOnly: true,
            },
            {
                id: 'kartu-bebas',
                title: 'Kartu Bebas / Haji',
                summary: 'Kartu dengan kolom bebas untuk peserta di luar siswa.',
                keywords: ['kartu bebas', 'haji', 'form dinamis'],
                permission: 'kartu-bebas.access',
                superAdminOnly: true,
            },
        ],
    },
    {
        id: 'fitur',
        title: 'Fitur & Saklar',
        description: 'Apa yang hilang saat sebuah fitur dimatikan.',
        topics: [
            {
                id: 'daftar-fitur',
                title: 'Daftar fitur dan efeknya',
                summary: 'Empat belas saklar di Pengaturan tab Fitur.',
                keywords: ['fitur', 'saklar', 'matikan', 'aktifkan', 'menu hilang'],
                permission: 'pengaturan.access',
            },
        ],
    },
    {
        id: 'masalah',
        title: 'Pemecahan Masalah',
        description: 'Keluhan yang paling sering muncul dan penyebabnya.',
        topics: [
            {
                id: 'keluhan-umum',
                title: 'Keluhan umum',
                summary: 'Scan ditolak, notifikasi tidak sampai, menu hilang, foto kosong.',
                keywords: ['error', 'gagal', 'tidak bisa', 'masalah', 'ditolak', 'hilang'],
            },
        ],
    },
    {
        id: 'orang-tua',
        title: 'Untuk Orang Tua',
        description: 'Teks siap salin untuk disebarkan ke wali murid.',
        topics: [
            {
                id: 'teks-orang-tua',
                title: 'Teks siap salin',
                summary: 'Cara hubungkan Telegram, arti notifikasi, dan cara daftar mandiri.',
                keywords: ['orang tua', 'wali', 'telegram', 'broadcast', 'sosialisasi'],
                permission: 'orang-tua.access',
                feature: 'master_siswa',
            },
        ],
    },
];
