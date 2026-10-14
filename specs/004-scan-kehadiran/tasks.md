# Tasks: Scan & Kehadiran (004)

**Tests**: WAJIB, sebelum implementasi.

## Phase 1: Foundational
- [X] T001 Migrasi `programs.scanner_token` + backfill; hook pembuatan di `Program`
- [X] T002 `app/Support/ProgramShortLink.php` (kode 8 karakter, resolve tepat satu)

## Phase 2: US1 Scan di pintu (P1) + US2 perangkat lemah (P1)
- [X] T003 [US1] Tes `ScanKehadiranTest.php`: hadir/terlambat, idempoten, pulang, tanpa sesi + berikutnya, libur, token program lain/kartu Bebas ditolak, huruf besar/kecil, peserta diarsip, link diganti
- [X] T004 [US1] `KehadiranRecorder::scan()` (lock + unique catch)
- [X] T005 [US2] `PublicProgramScanController` + rute `/hadir/{kode}`, `/h/{kode}` (tanpa session)
- [X] T006 [P] [US2] Halaman `scan/program.tsx` + daftar publik di `app.tsx`

## Phase 3: US3 Manual izin/sakit/alpa (P1)
- [X] T007 [US3] Tes `KehadiranManualTest.php`: catat sakit + log, scan tidak menimpa, pendamping grup lain ditolak, riwayat perubahan
- [X] T008 [US3] `KehadiranController` (index per sesi + filter, update, rotate link) + halaman `penyelenggara/program/kehadiran.tsx`

## Phase 4: US4 Alpa otomatis (P2)
- [X] T009 [US4] Tes `TandaiAlpaTest.php`: alpa setelah jendela tutup, libur & manual dilewati, idempoten, peserta baru tidak dialpa-kan mundur
- [X] T010 [US4] Perintah `penyelenggara:tandai-alpa` + jadwal per jam

## Phase 5: Polish
- [X] T011 Tautan scan & tombol ganti link di detail program; menu
- [X] T012 Regresi `CardAttendanceTest` (tanpa perubahan), Pint, build, suite penuh, commit

## Audit 2026-10-14 — P0
- [X] T-A01 `Sesi::punyaKehadiran()` lintas konteks (sinkron console tidak lagi menghapus sesi berkehadiran)
- [X] T-A02 Sapuan alpa: `whereDoesntHave` tanpa scope, insert per 1000, program aktif saja, bandingkan waktu daftar dengan tutup jendela, `withoutOverlapping(30)`
- [X] T-A03 Hapus grup yang punya jadwal ditolak (riwayat kehadiran aman)
- [X] T-A04 FK `grups.parent_id` cascade (migrasi baru)
- [X] T-A05 Scan bersamaan: transaksi 3 percobaan (deadlock InnoDB)

## Audit 2026-10-14 — P1

Tes: `tests/Feature/Penyelenggara/AuditP1Test.php`, `tests/Unit/akses-penyelenggara.test.mjs`.

- [X] T-A17 Hadir/Terlambat manual dianggap sudah datang: scan pulang jalan, scan datang hanya mengisi jam — `KehadiranRecorder::catat`
- [X] T-A18 Izin/Sakit disembunyikan & ditolak server bila saklar `izin_sakit` mati; daftar grup kehadiran dibatasi grup pendamping — `KehadiranController`
- [X] T-A19 Catat manual (`kehadiran.update`) tetap di izin `penyelenggara-peserta` supaya pendamping bisa mencatat
