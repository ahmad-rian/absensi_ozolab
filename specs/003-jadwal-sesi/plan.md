# Implementation Plan: Jadwal & Sesi

**Branch**: `003-jadwal-sesi` (langsung di `main`) | **Date**: 2026-10-14 | **Spec**: [spec.md](./spec.md)

## Summary
Pola sesi (mingguan/rentang/sekali, per program atau grup) diturunkan menjadi sesi per tanggal dengan
salinan jendela waktu; libur per program; `SesiResolver` menentukan sesi aktif untuk peserta pada satu
waktu dalam zona program. Detail di [research.md](./research.md).

## Technical Context
PHP 8.4 / Laravel 13 / Inertia v3 / Pest 4 · tanpa dependency baru · MySQL/SQLite ·
Target: penyusunan jadwal 40 hari × 3 sesi < 1 s; resolver 1 query sesi + 1 query grup.

## Constitution Check
| Prinsip | Status |
|---|---|
| I Laravel Boost | ✅ |
| II Tenancy fail-closed | ✅ tabel baru ber-BelongsToPenyelenggara |
| III Data haji | ✅ tidak menyentuh card_* |
| IV Aditif | ✅ perintah sinkron idempoten |
| V Sekolah | ✅ AttendanceSchedule/PrayerSchedule tidak disentuh |
| VI Operasional | ✅ zona program eksplisit; perintah harian ringan |
| VII Bahasa | ✅ |

## Project Structure
```text
app/Models/{SesiPola,Sesi,Libur,Kehadiran}.php
app/Services/Penyelenggara/{SesiGenerator,SesiResolver}.php
app/Http/Controllers/Penyelenggara/JadwalController.php
app/Console/Commands/SinkronSesiPenyelenggaraCommand.php
resources/js/pages/penyelenggara/program/jadwal.tsx
tests/Feature/Penyelenggara/{Jadwal,SesiResolver}Test.php
```

## Complexity Tracking
Tidak ada pelanggaran.
