# Implementation Plan: Kategori, Program, Grup, Peserta & Kartu

**Branch**: `002-kategori-program-peserta` (langsung di `main`) | **Date**: 2026-10-14 | **Spec**: [spec.md](./spec.md)

## Summary

Admin penyelenggara membuat kategori dari 5 template, program, grup bertingkat, peserta (manual & impor),
dan kartu ber-QR. Peserta/layout/skema memakai ulang tabel kartu (`card_*`) dengan kolom tenant nullable;
isolasi tiga keadaan lewat trait `CardTenancy`. Detail di [research.md](./research.md).

## Technical Context

**Language/Version**: PHP 8.4, TypeScript (React 19) · **Primary Dependencies**: Laravel 13, Inertia v3,
OpenSpout (impor), Browsershot (render kartu, sudah ada), Google Drive API (sudah ada) — tanpa dependency baru
· **Storage**: MySQL/SQLite · **Testing**: Pest 4 + node test · **Project Type**: monolit web ·
**Performance Goals**: pratinjau impor 1.000 baris < 30 s; daftar peserta 500 baris < 1 s ·
**Constraints**: baris `card_*` lama tidak berubah; Kartu Bebas berperilaku sama ·
**Scale/Scope**: ≤ 5.000 peserta per penyelenggara, ~12 halaman baru

## Constitution Check

| Prinsip | Status | Catatan |
|---|---|---|
| I. Laravel Boost | ✅ | make:*, Pest per story, Wayfinder |
| II. Tenancy fail-closed | ⚠️ dibenarkan | Tabel baru fail-closed; tabel kartu bersama memakai 3 keadaan (lihat Complexity Tracking) |
| III. Data haji tidak berubah | ✅ | Hanya kolom nullable baru; tidak ada UPDATE baris lama; tes Kartu Bebas tidak diubah |
| IV. Aditif & tersimulasi | ✅ | Migrasi aditif; impor punya pratinjau sebelum simpan |
| V. Sekolah tidak disentuh | ✅ | Tidak menyentuh tabel/rute sekolah |
| VI. Operasional | ✅ | Render di antrean `cards`; tidak ada generate massal tanpa saklar; Drive klien tunggal |
| VII. Bahasa | ✅ | Copy Indonesia, istilah per kategori |

## Project Structure

```text
app/Models/{Kategori,Program,Grup,PesertaImport}.php · app/Models/Concerns/CardTenancy.php
app/Support/KategoriTemplates.php · app/Services/Penyelenggara/{PesertaImportParser,KartuDrive}.php
app/Http/Middleware/MarkKartuBebasLegacy.php
app/Http/Controllers/Penyelenggara/{Kategori,Program,Grup,Peserta,PesertaImpor,Layout,Kartu}Controller.php
resources/js/pages/penyelenggara/{kategori,program,peserta}/*.tsx
tests/Feature/Penyelenggara/{Kategori,Program,Grup,Peserta,PesertaImpor,KartuPeserta,KartuBebasTetap}Test.php
```

**Structure Decision**: pola folder fitur `Penyelenggara/*` dari spec 001.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| Tabel `card_*` tidak fail-closed di job/console/rute token | Job generate & scan publik lama memuat baris tanpa konteks tenant | Fail-closed penuh mematikan generate kartu dan scan haji yang berjalan (melanggar Prinsip III) |
