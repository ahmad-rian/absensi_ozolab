# Implementation Plan: Scan & Pencatatan Kehadiran

**Branch**: `004-scan-kehadiran` (langsung di `main`) | **Date**: 2026-10-14 | **Spec**: [spec.md](./spec.md)

## Summary
Rute scan program sendiri (kamera & ringan) memverifikasi token kartu peserta, menentukan sesi aktif lewat
`SesiResolver`, dan mencatat kehadiran per sesi (HADIR/TERLAMBAT, datang/pulang) secara idempoten. Admin dan
pendamping mencatat izin/sakit/alpa dengan audit; sapuan per jam menandai alpa. Kartu Bebas/haji tidak disentuh.

## Technical Context
PHP 8.4 · Laravel 13 · Inertia v3 · Pest 4 · tanpa dependency baru · target respons scan < 300 ms p95
(2 query peserta/sesi + 1 tulis), 50 scan/menit/program.

## Constitution Check
| Prinsip | Status |
|---|---|
| I | ✅ |
| II | ✅ rute publik membatasi ke program milik token; area admin ber-scope |
| III | ✅ tidak ada perubahan pada CardAttendanceService, rute /p & /scan-peserta, card_attendances |
| IV | ✅ backfill scanner_token hanya untuk tabel baru `programs`; sapuan idempoten |
| V | ✅ |
| VI | ✅ rute ringan tanpa session; foto tidak dikirim besar (thumb URL) |
| VII | ✅ pesan penolakan menjelaskan alasan |

## Project Structure
```text
app/Services/Penyelenggara/KehadiranRecorder.php · app/Support/ProgramShortLink.php
app/Http/Controllers/PublicProgramScanController.php · app/Http/Controllers/Penyelenggara/KehadiranController.php
app/Console/Commands/TandaiAlpaPenyelenggaraCommand.php
resources/js/pages/scan/program.tsx · resources/js/pages/penyelenggara/program/kehadiran.tsx
tests/Feature/Penyelenggara/{ScanKehadiran,KehadiranManual,TandaiAlpa}Test.php
```

## Complexity Tracking
Tidak ada pelanggaran.
