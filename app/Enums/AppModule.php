<?php

namespace App\Enums;

/**
 * Modul aplikasi = satuan hak akses.
 *
 * Satu permission per modul (`<modul>.access`) yang memberi akses penuh ke
 * seluruh aksi modul itu. Role custom bebas mengombinasikan modul apa pun.
 */
enum AppModule: string
{
    case Dashboard = 'dashboard';
    case Siswa = 'siswa';
    case OrangTua = 'orang-tua';
    case Kelas = 'kelas';
    case JadwalAbsensi = 'jadwal-absensi';
    case Absensi = 'absensi';
    case RfidCards = 'rfid-cards';
    case Laporan = 'laporan';
    case Notifikasi = 'notifikasi';
    case Frames = 'frames';
    case CardLayouts = 'card-layouts';
    case CardGeneration = 'card-generation';
    case AlbumLayouts = 'album-layouts';
    case AlbumGeneration = 'album-generation';
    case PhotoSheets = 'photo-sheets';
    case Pengaturan = 'pengaturan';
    case Users = 'users';
    case DriveConfig = 'drive-config';
    case WaConfig = 'wa-config';
    case Roles = 'roles';
    case Schools = 'schools';
    case NotificationGateways = 'notification-gateways';
    case CardForms = 'card-forms';
    case KartuBebas = 'kartu-bebas';
    case Impersonate = 'impersonate';

    public function permission(): string
    {
        return $this->value.'.access';
    }

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard',
            self::Siswa => 'Siswa',
            self::OrangTua => 'Orang Tua',
            self::Kelas => 'Kelas',
            self::JadwalAbsensi => 'Jadwal Absensi',
            self::Absensi => 'Absensi',
            self::RfidCards => 'Kartu RFID',
            self::Laporan => 'Laporan',
            self::Notifikasi => 'Notifikasi',
            self::Frames => 'Frame & Bingkai',
            self::CardLayouts => 'Layout Kartu',
            self::CardGeneration => 'Generate Kartu',
            self::AlbumLayouts => 'Layout Album',
            self::AlbumGeneration => 'Generate Album',
            self::PhotoSheets => 'Generate Pas Foto',
            self::Pengaturan => 'Pengaturan Sekolah',
            self::Users => 'Pengguna',
            self::DriveConfig => 'Google Drive',
            self::WaConfig => 'WhatsApp',
            self::Roles => 'Role & Hak Akses',
            self::Schools => 'Sekolah',
            self::NotificationGateways => 'Gateway Notifikasi',
            self::CardForms => 'Form Kartu Dinamis',
            self::KartuBebas => 'Kartu Bebas',
            self::Impersonate => 'Masuk Sebagai Pengguna',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Dashboard, self::Siswa, self::OrangTua, self::Kelas,
            self::JadwalAbsensi, self::Absensi, self::RfidCards, self::Laporan,
            self::Notifikasi => 'Akademik',

            self::Frames, self::CardLayouts, self::CardGeneration,
            self::AlbumLayouts, self::AlbumGeneration, self::PhotoSheets => 'Kartu & Album',

            self::Pengaturan, self::Users, self::DriveConfig,
            self::WaConfig => 'Administrasi',

            self::Roles, self::Schools, self::NotificationGateways,
            self::CardForms, self::KartuBebas, self::Impersonate => 'Sistem',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function permissions(): array
    {
        return array_map(fn (self $module) => $module->permission(), self::cases());
    }

    /**
     * Modul default per role bawaan. Role custom tidak diatur di sini.
     *
     * @return array<int, self>
     */
    public static function defaultsFor(UserRole $role): array
    {
        return match ($role) {
            UserRole::SuperAdmin => self::cases(),

            UserRole::Admin => [
                self::Dashboard, self::Siswa, self::OrangTua, self::Kelas,
                self::JadwalAbsensi, self::Absensi, self::RfidCards, self::Laporan,
                self::Notifikasi, self::Frames, self::CardLayouts, self::CardGeneration,
                self::AlbumLayouts, self::AlbumGeneration, self::PhotoSheets,
                self::Pengaturan, self::Users, self::DriveConfig, self::WaConfig,
            ],

            UserRole::Guru => [
                self::Dashboard, self::Siswa, self::Absensi,
                self::Laporan, self::Notifikasi,
            ],

            UserRole::OrangTua => [],
        };
    }
}
