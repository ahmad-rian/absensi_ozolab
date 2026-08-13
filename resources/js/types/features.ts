/**
 * Cermin dari App\Enums\SchoolFeature. Ditulis manual — proyek ini tidak punya
 * codegen enum, dan menambah satu belum sebanding dengan 14 baris ini.
 *
 * Server selalu mengirim peta yang UTUH lewat shared prop `features`, jadi
 * `features[key] === false` boleh dipakai sebagai satu-satunya penanda "mati".
 */
export type SchoolFeatureKey =
    | 'master_siswa'
    | 'absensi_sekolah'
    | 'absensi_rfid'
    | 'kunjungan_perpustakaan'
    | 'sholat_dzuhur'
    | 'sholat_dhuha'
    | 'laporan'
    | 'notif_absensi'
    | 'notif_alpa_sholat'
    | 'inbox_notifikasi'
    | 'kartu_album'
    | 'pendaftaran_publik'
    | 'pendaftaran_telegram'
    | 'manajemen_pengguna'
    | 'integrasi_drive'
    | 'integrasi_whatsapp';

export type SchoolFeatureMap = Record<SchoolFeatureKey, boolean>;
