# Tasks: Kategori, Program, Grup, Peserta & Kartu (002)

**Tests**: WAJIB (Konstitusi I). Tes ditulis sebelum implementasi di tiap story.

## Phase 1: Setup
- [X] T001 Baca `CardParticipantService`, `DynamicCardFiles`, `KartuBebas/LayoutController`, `StudentImportController`, `ReadsSpreadsheetRows` sebagai acuan

## Phase 2: Foundational
- [X] T002 Migrasi tabel baru `kategoris`, `programs`, `grups`, `grup_peserta`, `grup_peserta_riwayats`, `grup_pendamping`, `peserta_imports` di `database/migrations/`
- [X] T003 Migrasi kolom nullable `penyelenggara_id`/`kategori_id` (card_datasets), `penyelenggara_id`/`program_id` (card_forms), `penyelenggara_id`/`program_id`/`archived_at` (card_form_submissions)
- [X] T004 Trait `app/Models/Concerns/CardTenancy.php` (3 keadaan) dipasang di `CardDataset`, `CardForm`, `CardFormSubmission`
- [X] T005 Middleware `app/Http/Middleware/MarkKartuBebasLegacy.php` (alias `kartu-bebas-legacy`) dipasang di grup `kartu-bebas` & `card-forms` di `routes/web.php`
- [X] T006 Pindahkan `SetCurrentPenyelenggara` dari `web` global ke grup `/penyelenggara` (tetap di priority list) di `bootstrap/app.php` + `routes/web.php`
- [X] T007 Modul izin `penyelenggara-kategori`, `penyelenggara-program`, `penyelenggara-peserta`, `penyelenggara-kartu` di `app/Enums/AppModule.php` + default peran
- [X] T008 Tes `tests/Feature/Penyelenggara/KartuBebasTetapTest.php`: Kartu Bebas tidak melihat baris penyelenggara; job generate menemukan baris penyelenggara; baris lama tidak berubah

## Phase 3: US1 Kategori dari template (P1)
- [X] T009 [US1] Tes `KategoriTest.php` (5 template, kolom tambahan, kolom inti tak terhapus, kolom disembunyikan)
- [X] T010 [US1] `app/Support/KategoriTemplates.php` + model `Kategori` (+factory)
- [X] T011 [US1] `KategoriController` (index/store/edit/update/arsip) + rute
- [X] T012 [P] [US1] Halaman `penyelenggara/kategori/index.tsx`, `edit.tsx` (pembangun kolom)

## Phase 4: US2 Program & grup (P1)
- [X] T013 [US2] Tes `ProgramTest.php` & `GrupTest.php` (buat, arsip baca-saja, salin struktur, grup 3 tingkat, hapus ditolak)
- [X] T014 [US2] Model `Program`, `Grup` (+factory); program membuat `CardForm` layout default
- [X] T015 [US2] `ProgramController`, `GrupController` + rute
- [X] T016 [P] [US2] Halaman `penyelenggara/program/index.tsx`, `show.tsx` (tab Grup)

## Phase 5: US3 Peserta & impor (P1)
- [X] T017 [US3] Tes `PesertaTest.php` (CRUD, kuota, arsip/pulihkan, riwayat grup) & `PesertaImporTest.php` (pratinjau valid/gagal, grup persis, kuota, kembar)
- [X] T018 [US3] `PesertaController` memakai `CardParticipantService::save` + kuota + grup + riwayat
- [X] T019 [US3] `PesertaImportParser` + `PesertaImporController` (pratinjau cache, simpan)
- [X] T020 [P] [US3] Halaman `penyelenggara/peserta/index.tsx`, `form.tsx`, impor di `program/show.tsx`

## Phase 6: US4 Kartu (P1)
- [X] T021 [US4] Tes `KartuPesertaTest.php` (QR identik setelah generate ulang, tak ada tombol massal saat saklar mati, Drive ke subfolder penyelenggara)
- [X] T022 [US4] Editor layout dipakai ulang (prop URL) + `LayoutController` penyelenggara
- [X] T023 [US4] Generate per peserta, unduh kartu, ZIP; `DynamicCardFiles::drive()` cabang penyelenggara + `KartuDrive` subfolder
- [X] T024 [US4] Pengaturan root Drive penyelenggara (super admin) di `admin/penyelenggara/index.tsx`

## Phase 7: US5 Pendamping per grup (P2)
- [X] T025 [US5] Tes pendamping hanya melihat grupnya + turunan; tanpa grup = kosong
- [X] T026 [US5] Penugasan pendamping (`grup_pendamping`) + scope peserta untuk pendamping

## Phase 8: Polish
- [X] T027 Menu sidebar penyelenggara (Kategori, Program) + tes node
- [X] T028 Pint, build, suite penuh; Kartu Bebas/CardAttendance/CardParticipant tidak diubah dan hijau
- [X] T029 Commit `feat(002): …`

## Dependencies
Setup → Foundational (T002–T008) → US1 → US2 → US3 → US4 → US5 → Polish.
