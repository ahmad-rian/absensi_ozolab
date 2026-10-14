<!--
Sync Impact Report
- Version change: (template) → 1.0.0
- Principles added: I. Konvensi Laravel Boost; II. Tenancy Fail-Closed; III. Data Haji & Kartu
  Tercetak Tidak Berubah; IV. Perubahan Data Aditif & Tersimulasi; V. Absensi Sekolah Tidak
  Disentuh; VI. Batas Operasional Server; VII. Bahasa Pengguna
- Sections added: Batasan Teknis, Alur Kerja & Gerbang Kualitas, Governance
- Sections removed: none
- Templates: ✅ .specify/templates/plan-template.md (Constitution Check reads gates from this file,
  no edit needed) · ✅ spec-template.md (no mandatory section change) · ✅ tasks-template.md
  (test tasks already supported) · ✅ .claude/skills/speckit-* (generic, no agent-specific refs to fix)
- Deferred TODOs: none
-->
# Tyas Absensi Constitution

## Core Principles

### I. Konvensi Laravel Boost
Semua kode MUST mengikuti `CLAUDE.md` (Laravel Boost guidelines): file dibuat lewat
`php artisan make:* --no-interaction`, mengikuti struktur dan penamaan file tetangga, Inertia v3,
Wayfinder untuk rute di frontend, dan `vendor/bin/pint --dirty --format agent` sebelum commit.
Setiap perubahan perilaku MUST punya Pest feature test yang gagal sebelum perbaikan dan lulus
sesudahnya. Dependency baru MUST mendapat persetujuan user.

### II. Tenancy Fail-Closed
Setiap model milik tenant (sekolah atau penyelenggara) MUST di-scope otomatis, dan konteks
tenant yang kosong MUST menghasilkan nol baris, bukan semua baris. Respons MUST tidak membocorkan
keberadaan data tenant lain (tidak ada selisih 403/404 yang bisa dipakai menebak).
Rasional: `BelongsToSchool` saat ini fail-open untuk user tanpa `school_id`; pola itu MUST NOT
ditiru, dan role non-sekolah MUST NOT bisa mencapai rute `/admin` sekolah.

### III. Data Haji & Kartu Tercetak Tidak Berubah (NON-NEGOTIABLE)
- Baris yang sudah ada di prod pada `card_datasets`, `card_forms`, `card_form_submissions`, dan
  `card_attendances` MUST NOT di-UPDATE atau di-DELETE oleh fitur, migrasi, atau perintah baru.
- Fitur baru menautkan data lama lewat tabel penghubung terpisah; kolom baru pada tabel lama (bila
  ada) MUST nullable dan MUST tetap kosong untuk baris lama.
- Alur haji yang berjalan (layout, peserta, scan `/p/{kode}` dan `scan-peserta`, laporan, kartu
  lama via `accept_legacy_qr`) MUST tetap bekerja persis seperti sebelumnya; tes `CardAttendanceTest`
  dan `CardParticipantTest` MUST lulus tanpa diubah.
- Format token `kb.{ulid}.{hmac32}`, `students.qr_token`, dan link `/scan/{token}` MUST NOT berubah.
Rasional: ribuan kartu sudah dicetak dan dipakai; kartu yang tidak terbaca berarti peserta tidak
bisa absen.

### IV. Perubahan Data Aditif & Tersimulasi
Migrasi MUST aditif (tabel/kolom baru, nullable atau berdefault). Perintah yang mengubah data
prod MUST punya mode simulasi (`--dry-run`/default simulasi), idempoten bila dijalankan ulang,
dan menghasilkan laporan (terminal + XLSX untuk volume besar). Tidak ada penghapusan data prod
tanpa persetujuan eksplisit user.

### V. Absensi Sekolah Tidak Disentuh
Fitur baru MUST NOT mengubah perilaku `attendances`, `attendance_schedules`,
`prayer_attendances`, `library_visits`, rute `/scan/{token}`, `/g/{kode}`, maupun notifikasi
sekolah. Pola boleh ditiru (mis. `AttendanceRecorder`, `PrayerSchedule`), kode sekolah tidak
diubah kecuali untuk menutup celah keamanan, dengan tes regresi.

### VI. Batas Operasional Server
Waktu absensi MUST lewat `SchoolTime` (wall clock lokal; `app.timezone` UTC). Render berat MUST
di antrean `cards`; generate massal MUST di balik saklar `generate-massal`. Klien Google memakai
satu instance per proses. Hasil render disimpan di Google Drive karena disk VPS (60 GB) adalah
batas. Rute scan publik tanpa session/CSRF dan tanpa mengirim foto besar.

### VII. Bahasa Pengguna
Teks UI, pesan error, dan laporan MUST berbahasa Indonesia, menyebut apa yang salah dan cara
memperbaikinya, dengan istilah yang dikenal pengguna (peserta, rombongan, sesi), bukan istilah
sistem.

## Batasan Teknis

Stack: PHP 8.4, Laravel 13, Inertia v3 + React 19, Tailwind v4, Pest 4, MySQL (prod) / SQLite
(tes). Prod: VPS 2 core / 4 GB / 60 GB, aaPanel, worker supervisor, Redis. Kode MUST mengikuti
jebakan yang sudah tercatat: nama index MySQL ≤ 64 karakter, query tanggal kompatibel SQLite,
unique index memperhitungkan `deleted_at`.

## Alur Kerja & Gerbang Kualitas

- Setiap fitur melalui Spec Kit: specify → clarify → plan (Constitution Check) → tasks → analyze →
  implement; `/speckit-analyze` tanpa temuan CRITICAL sebelum implement.
- Pekerjaan langsung di `main`; satu commit per spec atau per kelompok tugas yang koheren,
  format `<type>: <deskripsi>`, tanpa trailer Co-Authored-By atau Claude-Session.
- Sebelum commit: tes terkait + suite penuh hijau (kecuali kegagalan lama yang tercatat), Pint,
  `npm run build` bila frontend berubah.
- Deploy perintah data: jalankan simulasi di prod, laporkan angkanya ke user, baru eksekusi.

## Governance

Konstitusi ini mengalahkan kebiasaan lain di repo bila bertentangan; `CLAUDE.md` tetap berlaku
untuk konvensi teknis. Amandemen dilakukan lewat commit yang memperbarui file ini dan versinya
(semver: MAJOR untuk menghapus/mendefinisikan ulang prinsip, MINOR untuk prinsip/bagian baru,
PATCH untuk klarifikasi). Setiap `plan.md` MUST memuat Constitution Check terhadap Prinsip I–VII;
pelanggaran MUST dicatat di Complexity Tracking beserta alasannya.

**Version**: 1.0.0 | **Ratified**: 2026-10-14 | **Last Amended**: 2026-10-14
