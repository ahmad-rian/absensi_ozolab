<?php

namespace App\Enums;

/**
 * Modul workspace Absensi General untuk akun staf Tyas, satu per menu.
 *
 * Terpisah dari AppModule supaya tidak muncul di matriks Role & Hak Akses
 * sekolah dan tidak bisa diberikan admin sekolah. Super admin lolos semua
 * lewat Gate::before; staf mendapatkannya dari peran staf (template) dan/atau
 * centang langsung per akun.
 */
enum ModulGeneral: string
{
    case Ringkasan = 'ringkasan';
    case Penyelenggara = 'penyelenggara';
    case PenyelenggaraMasuk = 'penyelenggara-masuk';
    case Kategori = 'kategori';
    case Program = 'program';
    case HajiDashboard = 'haji-dashboard';
    case HajiPeserta = 'haji-peserta';
    case HajiFormat = 'haji-format';
    case HajiAbsensi = 'haji-absensi';
    case HajiLaporan = 'haji-laporan';
    case HajiGenerate = 'haji-generate';
    case DesainFrame = 'desain-frame';
    case DesainLayout = 'desain-layout';
    case DesainRiwayat = 'desain-riwayat';
    case PenggunaStaf = 'pengguna-staf';
    case PenggunaPenyelenggara = 'pengguna-penyelenggara';
    case PengaturanUmum = 'pengaturan-umum';
    case PengaturanDrive = 'pengaturan-drive';
    case PengaturanWhatsapp = 'pengaturan-whatsapp';

    public function permission(): string
    {
        return 'general-'.$this->value.'.access';
    }

    public function label(): string
    {
        return match ($this) {
            self::Ringkasan => 'Ringkasan',
            self::Penyelenggara => 'Kelola Penyelenggara',
            self::PenyelenggaraMasuk => 'Masuk Area Penyelenggara',
            self::Kategori => 'Semua Kategori',
            self::Program => 'Semua Program',
            self::HajiDashboard => 'Dashboard Haji',
            self::HajiPeserta => 'Peserta',
            self::HajiFormat => 'Format Data',
            self::HajiAbsensi => 'Absensi & Link Scan',
            self::HajiLaporan => 'Laporan Absensi',
            self::HajiGenerate => 'Generate Kartu',
            self::DesainFrame => 'Frame & Bingkai',
            self::DesainLayout => 'Layout Kartu',
            self::DesainRiwayat => 'Riwayat Kartu',
            self::PenggunaStaf => 'Staf & Peran Staf',
            self::PenggunaPenyelenggara => 'Akun Penyelenggara',
            self::PengaturanUmum => 'Umum & Branding',
            self::PengaturanDrive => 'Google Drive',
            self::PengaturanWhatsapp => 'WhatsApp',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::Ringkasan => 'Statistik semua penyelenggara dan pintasan.',
            self::Penyelenggara => 'Lihat, tambah, ubah penyelenggara dan undang admin-nya.',
            self::PenyelenggaraMasuk => 'Masuk ke area penyelenggara mana pun sebagai admin penuh (tim, peserta, kartu).',
            self::Kategori => 'Tambah, arsip, hapus kategori; ubah kolom data di area penyelenggara.',
            self::Program => 'Daftar program semua penyelenggara dan halaman programnya.',
            self::HajiDashboard => 'Ringkasan peserta dan kehadiran Haji (kartu lama).',
            self::HajiPeserta => 'Tambah, ubah, hapus peserta dan unduh kartunya.',
            self::HajiFormat => 'Kelola format data dan lihat link form publik.',
            self::HajiAbsensi => 'Catat kehadiran, link scan, terima kartu lama, unduh kartu.',
            self::HajiLaporan => 'Laporan kehadiran dan ekspor Excel/PDF.',
            self::HajiGenerate => 'Isi data dan buat kartu baru dari layout.',
            self::DesainFrame => 'Unggah dan ubah frame/bingkai kartu.',
            self::DesainLayout => 'Buat dan ubah layout kartu (sekaligus form publiknya).',
            self::DesainRiwayat => 'Riwayat kartu yang sudah dibuat.',
            self::PenggunaStaf => 'Kelola akun staf General dan peran staf.',
            self::PenggunaPenyelenggara => 'Kelola akun admin/pendamping penyelenggara dan undang admin.',
            self::PengaturanUmum => 'Nama workspace, logo, dan bawaan penyelenggara baru.',
            self::PengaturanDrive => 'Folder Google Drive penyimpanan kartu.',
            self::PengaturanWhatsapp => 'Gateway WhatsApp workspace General.',
        };
    }

    public function grup(): string
    {
        return match ($this) {
            self::Ringkasan, self::Penyelenggara, self::PenyelenggaraMasuk,
            self::Kategori, self::Program => 'Utama',
            self::HajiDashboard, self::HajiPeserta, self::HajiFormat,
            self::HajiAbsensi, self::HajiLaporan, self::HajiGenerate => 'Haji (kartu lama)',
            self::DesainFrame, self::DesainLayout, self::DesainRiwayat => 'Desain Kartu',
            self::PenggunaStaf, self::PenggunaPenyelenggara => 'Pengguna',
            self::PengaturanUmum, self::PengaturanDrive, self::PengaturanWhatsapp => 'Pengaturan',
        };
    }

    /**
     * Modul yang hanya boleh diberikan super admin — memegangnya berarti
     * bisa menaikkan hak akses sendiri atau orang lain.
     */
    public function khususSuperAdmin(): bool
    {
        return $this === self::PenggunaStaf;
    }

    /**
     * @return array<int, string>
     */
    public static function permissions(): array
    {
        return array_map(fn (self $m) => $m->permission(), self::cases());
    }

    /**
     * Izin modul Haji/Desain — dipakai rute bersama (drive, dashboard).
     *
     * @return array<int, string>
     */
    public static function izinHaji(): array
    {
        return array_map(fn (self $m) => $m->permission(), array_filter(
            self::cases(),
            fn (self $m) => in_array($m->grup(), ['Haji (kartu lama)', 'Desain Kartu'], true),
        ));
    }

    /**
     * Katalog untuk matriks centang, dikelompokkan.
     *
     * @return array<int, array{grup: string, modul: array<int, array{permission: string, label: string, keterangan: string, khusus: bool}>}>
     */
    public static function katalog(): array
    {
        return collect(self::cases())
            ->groupBy(fn (self $m) => $m->grup())
            ->map(fn ($daftar, string $grup) => [
                'grup' => $grup,
                'modul' => $daftar->map(fn (self $m) => [
                    'permission' => $m->permission(),
                    'label' => $m->label(),
                    'keterangan' => $m->keterangan(),
                    'khusus' => $m->khususSuperAdmin(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
