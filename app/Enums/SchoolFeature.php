<?php

namespace App\Enums;

/**
 * Fitur aplikasi = satuan yang bisa dimatikan per sekolah.
 *
 * Sengaja terpisah dari AppModule: AppModule menjawab "siapa boleh" (permission
 * global, di-sync RolePermissionSeeder), SchoolFeature menjawab "tenant ini
 * pakai atau tidak" (disimpan di kolom `schools.settings`). Satu fitur bisa
 * menutup beberapa modul sekaligus, dan sebagian fitur (Sholat Dhuha, Notif
 * Alpa Sholat) tidak punya modul maupun menu sama sekali.
 *
 * Menumpangkan case-case ini ke AppModule akan memicu
 * `Permission::whereNotIn(...)->delete()` di RolePermissionSeeder dan membuat
 * permission sampah yang ikut muncul di halaman Role & Hak Akses.
 *
 * Modul Dashboard, Pengaturan, dan seluruh grup 'Sistem' sengaja tidak ada di
 * sini — lihat alwaysOnModules().
 */
enum SchoolFeature: string
{
    // --- Akademik ---
    case MasterSiswa = 'master_siswa';
    case AbsensiSekolah = 'absensi_sekolah';
    case SholatDzuhur = 'sholat_dzuhur';
    case SholatDhuha = 'sholat_dhuha';
    case Laporan = 'laporan';

    // --- Notifikasi ---
    case NotifAbsensi = 'notif_absensi';
    case NotifAlpaSholat = 'notif_alpa_sholat';
    case InboxNotifikasi = 'inbox_notifikasi';

    // --- Kartu & Album ---
    case KartuAlbum = 'kartu_album';

    // --- Halaman Publik ---
    case PendaftaranPublik = 'pendaftaran_publik';
    case PendaftaranTelegram = 'pendaftaran_telegram';

    // --- Administrasi ---
    case ManajemenPengguna = 'manajemen_pengguna';
    case IntegrasiDrive = 'integrasi_drive';
    case IntegrasiWhatsapp = 'integrasi_whatsapp';

    /**
     * Key penyimpanan di `schools.settings`.
     *
     * Tiga fitur memakai key yang SUDAH ADA, bukan key `feature_*` kembar:
     * PrayerSettings dan DispatchAttendanceNotifications sudah membacanya, jadi
     * saklar di tab Fitur menulis ke sumber kebenaran yang sama. Tanpa ini kita
     * harus menyinkronkan dua key selamanya — dan sinkronisasi itu pasti
     * meleset suatu hari.
     */
    public function settingKey(): string
    {
        return match ($this) {
            self::SholatDzuhur => 'prayer_enabled',
            self::SholatDhuha => 'prayer_dhuha_enabled',
            self::NotifAbsensi => 'whatsapp_enabled',
            default => 'feature_'.$this->value,
        };
    }

    /**
     * Fitur yang sudah ada sebelum panel ini WAJIB default aktif supaya tidak
     * ada sekolah yang kehilangan fungsi setelah deploy. Hanya fitur baru yang
     * default mati.
     *
     * Ketiga fitur yang memakai key lama HARUS mengambil default dari sumber
     * yang sama dengan pembacanya. Sebelumnya SholatDzuhur default `true` di
     * sini sementara `PrayerSettings` membacanya dari config yang default
     * `false` — selama key-nya belum pernah ditulis, kedua jalur memberi
     * jawaban berlawanan, dan menyimpan tab Fitur akan menyalakan absen sholat
     * untuk sekolah yang tidak pernah memintanya.
     */
    public function defaultEnabled(): bool
    {
        return match ($this) {
            self::SholatDzuhur => (bool) config('attendance.prayer.enabled', false),
            self::SholatDhuha => (bool) config('attendance.prayer_dhuha.enabled', false),
            // Cocok dengan default di DispatchAttendanceNotifications.
            self::NotifAbsensi => true,
            self::NotifAlpaSholat => false,
            default => true,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MasterSiswa => 'Master Data Siswa',
            self::AbsensiSekolah => 'Absensi Sekolah',
            self::SholatDzuhur => 'Absen Sholat Dzuhur',
            self::SholatDhuha => 'Absen Sholat Dhuha',
            self::Laporan => 'Laporan',
            self::NotifAbsensi => 'Notifikasi Absensi',
            self::NotifAlpaSholat => 'Notifikasi Alpa Sholat',
            self::InboxNotifikasi => 'Inbox Notifikasi',
            self::KartuAlbum => 'Kartu & Album',
            self::PendaftaranPublik => 'Pendaftaran Siswa Publik',
            self::PendaftaranTelegram => 'Pendaftaran Telegram Orang Tua',
            self::ManajemenPengguna => 'Manajemen Pengguna',
            self::IntegrasiDrive => 'Integrasi Google Drive',
            self::IntegrasiWhatsapp => 'Integrasi WhatsApp',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MasterSiswa => 'Menu Siswa, Orang Tua, dan Kelas. Mematikannya juga menutup API siswa.',
            self::AbsensiSekolah => 'Menu Absensi & Jadwal Absensi, plus halaman scan gerbang.',
            self::SholatDzuhur => 'Absen sholat dzuhur di mushola lewat halaman scan sholat.',
            self::SholatDhuha => 'Absen sholat dhuha pagi. Jendela waktunya terpisah dari dzuhur.',
            self::Laporan => 'Menu Laporan beserta export CSV dan PDF.',
            self::NotifAbsensi => 'Kirim notifikasi ke orang tua tiap siswa scan masuk atau pulang.',
            self::NotifAlpaSholat => 'Kirim peringatan ke orang tua bila siswa tidak sholat beberapa hari berturut-turut.',
            self::InboxNotifikasi => 'Menu Notifikasi berisi riwayat pengiriman pesan.',
            self::KartuAlbum => 'Frame, Layout Kartu, Generate Kartu, Layout Album, dan Generate Album.',
            self::PendaftaranPublik => 'Halaman /daftar untuk pendaftaran siswa baru mandiri.',
            self::PendaftaranTelegram => 'Halaman /daftar-telegram untuk orang tua menautkan bot Telegram.',
            self::ManajemenPengguna => 'Menu Pengguna untuk menambah admin dan guru.',
            self::IntegrasiDrive => 'Menu Google Drive untuk sinkronisasi foto siswa.',
            self::IntegrasiWhatsapp => 'Menu WhatsApp untuk memantau status gateway.',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::MasterSiswa, self::AbsensiSekolah, self::SholatDzuhur,
            self::SholatDhuha, self::Laporan => 'Akademik',

            self::NotifAbsensi, self::NotifAlpaSholat,
            self::InboxNotifikasi => 'Notifikasi',

            self::KartuAlbum => 'Kartu & Album',

            self::PendaftaranPublik, self::PendaftaranTelegram => 'Halaman Publik',

            self::ManajemenPengguna, self::IntegrasiDrive,
            self::IntegrasiWhatsapp => 'Administrasi',
        };
    }

    /**
     * Modul yang ikut mati bersama fitur ini. Kosong = fitur perilaku murni
     * (tidak punya menu maupun rute admin sendiri).
     *
     * @return array<int, AppModule>
     */
    public function modules(): array
    {
        return match ($this) {
            self::MasterSiswa => [AppModule::Siswa, AppModule::OrangTua, AppModule::Kelas],
            self::AbsensiSekolah => [AppModule::Absensi, AppModule::JadwalAbsensi],
            self::Laporan => [AppModule::Laporan],
            self::InboxNotifikasi => [AppModule::Notifikasi],
            self::KartuAlbum => [
                AppModule::Frames, AppModule::CardLayouts, AppModule::CardGeneration,
                AppModule::AlbumLayouts, AppModule::AlbumGeneration,
            ],
            self::ManajemenPengguna => [AppModule::Users],
            self::IntegrasiDrive => [AppModule::DriveConfig],
            self::IntegrasiWhatsapp => [AppModule::WaConfig],
            default => [],
        };
    }

    /**
     * Modul yang tidak pernah bisa dimatikan.
     *
     * Dashboard & Pengaturan: mematikannya menghapus satu-satunya panel untuk
     * menyalakannya kembali — kunci-diri permanen tanpa akses database. Grup
     * 'Sistem': lintas-tenant dan super-admin-only, jadi "dimatikan untuk satu
     * sekolah" tidak punya arti.
     *
     * @return array<int, AppModule>
     */
    public static function alwaysOnModules(): array
    {
        return array_values(array_filter(
            AppModule::cases(),
            fn (AppModule $module) => self::forModule($module) === null,
        ));
    }

    public static function forModule(AppModule $module): ?self
    {
        foreach (self::cases() as $feature) {
            if (in_array($module, $feature->modules(), true)) {
                return $feature;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $feature) => $feature->value, self::cases());
    }
}
