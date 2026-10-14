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
