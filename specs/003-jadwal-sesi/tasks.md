# Tasks: Jadwal & Sesi (003)

**Tests**: WAJIB, ditulis sebelum implementasi.

## Phase 1: Foundational
- [X] T001 Migrasi `sesi_polas`, `sesis`, `liburs`, `kehadirans` di `database/migrations/`
- [X] T002 Model `SesiPola`, `Sesi`, `Libur`, `Kehadiran` (+factory) di `app/Models/`

## Phase 2: US1 Jadwal harian berulang (P1) + US2 banyak sesi (P1)
- [X] T003 [US1] Tes `JadwalTest.php`: pola Senin–Jumat → sesi tepat di hari kerja; validasi jendela; pulang mengikuti saklar kategori; tabrakan target sama ditolak dengan nama sesi; grup berbeda boleh
- [X] T004 [US1] `SesiGenerator` (turunkan sesi, tabrakan, sinkron sejak hari ini)
- [X] T005 [US1] `JadwalController` (index/store/update/destroy) + rute
- [X] T006 [P] [US1] Halaman `penyelenggara/program/jadwal.tsx` (pola, libur, kalender) + tautan dari detail program

## Phase 3: US3 Sesi sekali & khusus grup (P2) + US4 Libur (P2)
- [X] T007 [US3] Tes sesi sekali & khusus grup berlaku untuk sub-grup; tes libur menonaktifkan sesi
- [X] T008 [US4] Libur store/destroy + rute

## Phase 4: US5 Sejarah tidak berubah (P2)
- [X] T009 [US5] Tes: ubah pola tidak mengubah sesi lampau; sesi hari ini berkehadiran butuh `termasuk_hari_ini`; program diperpendek
- [X] T010 [US5] `SesiResolver` + tes (`SesiResolverTest.php`): sesi aktif, berikutnya, libur, zona waktu program, grup turunan
- [X] T011 Perintah `penyelenggara:sesi-sinkron` + jadwal harian di `bootstrap/app.php`; program diubah → sinkron

## Phase 5: Polish
- [X] T012 Pint, build, suite penuh; commit `feat(003): …`
