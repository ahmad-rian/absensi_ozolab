# Implementation Plan: Dashboard & Laporan Kehadiran

**Branch**: `005-dashboard-laporan` (langsung di `main`) | **Date**: 2026-10-14 | **Spec**: [spec.md](./spec.md)

## Summary
Dashboard program (ringkasan per sesi hari ini, belum hadir, peringkat & tren) dan ekspor XLSX/PDF
(per grup, per sesi, per peserta) dari satu layanan `RingkasanKehadiran`. Detail di [research.md](./research.md).

## Technical Context
PHP 8.4 · Laravel 13 · Inertia v3 (defer) · OpenSpout · DomPDF · Pest 4 · tanpa dependency baru ·
dashboard 500 peserta × 3 sesi < 2 s; XLSX 30 hari × 500 peserta < 30 s.

## Constitution Check
| Prinsip | Status |
|---|---|
| I–VII | ✅ (tanpa tabel baru; hanya membaca data modul penyelenggara; laporan sekolah & Kartu Bebas tidak disentuh) |

## Project Structure
```text
app/Services/Penyelenggara/RingkasanKehadiran.php
app/Http/Controllers/Penyelenggara/{ProgramDashboard,LaporanProgram}Controller.php
resources/views/pdf/kehadiran-program.blade.php
resources/js/pages/penyelenggara/program/{dashboard,laporan}.tsx
tests/Feature/Penyelenggara/DashboardLaporanTest.php
```

## Complexity Tracking
Tidak ada pelanggaran.
