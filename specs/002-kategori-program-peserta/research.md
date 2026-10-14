# Research: Kategori, Program, Grup, Peserta & Kartu (002)

## R1. Peserta & layout memakai tabel kartu yang ada
- **Decision**: Peserta = baris baru `card_form_submissions`; layout kartu program = baris baru `card_forms`;
  skema kategori = baris baru `card_datasets`. Ketiganya diberi kolom nullable `penyelenggara_id`
  (+ `program_id` di forms/submissions, `kategori_id` di datasets, `archived_at` di submissions).
  Baris lama tetap NULL (Konstitusi III).
- **Rationale**: Editor layout, `CardParticipantService` (validasi per tipe + crop), `GenerateDynamicCardJob`,
  `DynamicCardGenerator`, `DynamicCardFiles`, token `kb.` semuanya bertipe `CardForm`/`CardFormSubmission`.
  Tabel baru berarti menulis ulang seluruh mesin kartu.
- **Alternatives**: Tabel `pesertas` sendiri (ditolak: duplikasi mesin kartu, melampaui 5 hari).

## R2. Isolasi tabel kartu bersama (tiga keadaan)
- **Decision**: Trait `CardTenancy` pada `CardDataset`, `CardForm`, `CardFormSubmission`:
  1. konteks penyelenggara terikat → `where penyelenggara_id = id`;
  2. penanda `kartuBebasLegacy` terikat (middleware `kartu-bebas-legacy` di grup rute `kartu-bebas.*` dan
     `card-forms`) → `whereNull('penyelenggara_id')`;
  3. selain itu (job, console, rute token publik) → tanpa filter (perilaku sekarang).
  `SetCurrentPenyelenggara` dipindah dari middleware global `web` ke grup `/penyelenggara` saja, supaya
  super admin yang punya pilihan konteks tidak membawa konteks itu ke Kartu Bebas.
- **Rationale**: Kartu Bebas tidak melihat data penyelenggara; job generate tetap menemukan barisnya.
  Keadaan 3 tidak pernah dicapai akun penyelenggara (mereka terkunci di area sendiri) — dicatat di
  Complexity Tracking.
- **Alternatives**: Scope fail-closed penuh (ditolak: job & scan publik lama berhenti bekerja).

## R3. Kategori & template
- **Decision**: Tabel `kategoris` (penyelenggara, nama, slug, template, istilah peserta/grup, saklar fitur json,
  `card_dataset_id`, archived_at). Skema kolom disimpan di `card_datasets.fields` (format yang sudah dipahami
  editor & validator). 5 template di `App\Support\KategoriTemplates` (array konstan). Kolom inti `nama` dan
  `telepon_wali` dipaksa ada; kolom dihapus = ditandai `hidden: true`, bukan dibuang (FR-004).

## R4. Program, grup, pendamping
- **Decision**: `programs` (kategori, nama, tanggal mulai-selesai, timezone default `Asia/Jakarta`,
  `archived_at`, `card_form_id`). `grups` adjacency list (`parent_id`, `urutan`); turunan dihitung di PHP
  (pohon ≤ ratusan simpul). Pivot `grup_peserta`, log `grup_peserta_riwayats`, pivot `grup_pendamping`.
  Pendamping melihat peserta di grupnya + turunan (FR-010) lewat scope `untukPengguna(User)`.

## R5. Impor
- **Decision**: Pakai `App\Services\Import\Concerns\ReadsSpreadsheetRows` (CSV/XLSX). Parser baru memetakan
  header ke `fields` kategori (cocok label/key persis, tanpa tebakan), pratinjau di-cache 30 menit
  (pola `StudentImportController`), konfirmasi menyimpan baris valid dalam transaksi + cek kuota di awal.

## R6. Kartu & Drive
- **Decision**: Editor layout Kartu Bebas dipakai ulang dengan prop URL simpan (default ke rute Kartu Bebas
  agar halaman lama tidak berubah). Generate per peserta → `CardParticipantService::generate()` yang ada.
  ZIP → pola `AttendanceController::downloadCards`. Drive: `DynamicCardFiles::drive()` bercabang bila
  `penyelenggara_id` terisi → klien OAuth global + root `Setting::getValue('drive_penyelenggara_root_folder_id')`
  ("Tyas General Apps"), subfolder `{penyelenggara}/{program}` lewat `findOrCreateFolder`.
- **Generate massal**: tidak ada tombol "semua" kecuali di balik `generate-massal`.
