# Tasks: Dashboard & Laporan (005)

- [X] T001 Tes `DashboardLaporanTest.php`: angka per sesi cocok, filter grup, pendamping terbatas, belum hadir, program tanpa sesi, XLSX sheet per grup & total sama dengan dashboard, mode sesi & peserta, PDF, rentang > 1 tahun ditolak, peringkat muncul setelah 7 hari
- [X] T002 [US1] `RingkasanKehadiran` (target, ringkas sesi, matriks rentang, peringkat, tren)
- [X] T003 [US1] `ProgramDashboardController` (defer) + halaman `program/dashboard.tsx` dengan skeleton
- [X] T004 [US2] `LaporanProgramController` (XLSX/PDF tiga mode) + view PDF + halaman form laporan
- [X] T005 [US3] Peringkat & tren di dashboard
- [X] T006 Beranda penyelenggara: daftar program aktif + ringkasan hari ini; tautan dari detail program
- [X] T007 Pint, build, suite penuh, commit

## Audit 2026-10-14 — P1

Tes: `tests/Feature/Penyelenggara/AuditP1Test.php`, `tests/Unit/akses-penyelenggara.test.mjs`.

- [X] T-A20 Dashboard program: tautan Peserta/Kehadiran/Laporan per izin, sesi hari libur tidak dihitung; Beranda pendamping hanya programnya — `ProgramDashboardController`, `DashboardController`, `program/dashboard.tsx`
- [X] T-A21 Laporan per grup dikelompokkan per id grup berlabel jalur, peserta muncul di tiap grupnya, ringkasan dari matriks, sesi libur dikecualikan — `LaporanProgramController`, `RingkasanKehadiran::matriks`
