# Tasks: Penyelenggara & Tenancy

**Input**: Design documents from `/specs/001-penyelenggara-tenant/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/routes.md, quickstart.md

**Tests**: WAJIB (Konstitusi Prinsip I — setiap perubahan perilaku punya Pest feature test). Tulis tes dulu, pastikan gagal, lalu implementasi.

**Organization**: Dikelompokkan per user story agar tiap story bisa diuji sendiri.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: bisa paralel (file berbeda, tidak bergantung tugas yang belum selesai)
- **[Story]**: US1–US4 dari spec.md

## Path Conventions

Monolit Laravel: `app/`, `database/`, `routes/`, `resources/js/`, `tests/Feature/`.

---

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 Baca `CLAUDE.md`, `.specify/memory/constitution.md`, dan `app/Models/Concerns/BelongsToSchool.php`, `app/Http/Middleware/SetCurrentSchool.php`, `app/Http/Middleware/EnsureFeatureEnabled.php`, `app/Support/SchoolFeatures.php` sebagai pola acuan (tidak ada perubahan file)
- [X] T002 Jalankan baseline `php artisan test --compact --parallel` dan catat kegagalan lama (AdminUserPassword, ParentPasswordProgress, QrTokenTolerance) di catatan kerja

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Semua user story bergantung pada fase ini.

- [X] T003 Buat migrasi `create_penyelenggaras_table` (ulid, name, slug unique, is_active, participant_quota nullable, wa_daily_quota default 50, settings json, timestamps, softDeletes) via `php artisan make:migration` di `database/migrations/`
- [X] T004 Buat migrasi `add_penyelenggara_id_to_users_table` (ulid nullable, index, FK nullOnDelete) di `database/migrations/`
- [X] T005 Buat migrasi `create_penyelenggara_invitations_table` (kolom sesuai data-model.md, nama index ≤ 64 karakter) di `database/migrations/`
- [X] T006 [P] Tambah case `AdminPenyelenggara`, `Pendamping` di `app/Enums/UserRole.php`
- [X] T007 [P] Tambah case `PenyelenggaraBeranda`, `PenyelenggaraTim`, `PenyelenggaraHakAkses` (grup "Penyelenggara") + default per peran di `app/Enums/AppModule.php`
- [X] T008 [P] Buat enum `app/Enums/PenyelenggaraFeature.php` (case `NotifikasiWali`, `settingKey()`, `label()`, `defaultEnabled()`)
- [X] T009 Buat model `app/Models/Penyelenggara.php` (HasUlids, SoftDeletes, LogsActivity, casts settings/is_active, `getSetting()/setSetting()`, slug unik otomatis, relasi users/invitations) + `database/factories/PenyelenggaraFactory.php` (state `inactive`)
- [X] T010 Buat trait fail-closed `app/Models/Concerns/BelongsToPenyelenggara.php` (creating isi `penyelenggara_id` dari `app('currentPenyelenggara')`; global scope `penyelenggara` → `whereRaw('1 = 0')` bila konteks kosong; scope `acrossPenyelenggara()`)
- [X] T011 Tambah `penyelenggara_id` ke fillable/relasi `penyelenggara()`, `isPenyelenggaraUser()`, dan cabang `homeRoute()` → `penyelenggara.dashboard` di `app/Models/User.php`
- [X] T012 Buat `app/Support/PenyelenggaraFeatures.php` (meniru `SchoolFeatures`: `for($p)->enabled()`, `toArray()`)
- [X] T013 Buat middleware `app/Http/Middleware/SetCurrentPenyelenggara.php` (super admin: session `current_penyelenggara_id`; user penyelenggara: `users.penyelenggara_id`; bind `currentPenyelenggara`)
- [X] T014 [P] Buat middleware `app/Http/Middleware/EnsurePenyelenggaraUser.php` (alias `penyelenggara`: hanya super admin atau user ber-`penyelenggara_id`; selain itu 403; penyelenggara nonaktif → halaman nonaktif untuk non-super-admin)
- [X] T015 [P] Buat middleware `app/Http/Middleware/EnsureBukanPenyelenggara.php` (alias `bukan-penyelenggara`: user ber-`penyelenggara_id` → redirect `penyelenggara.dashboard`)
- [X] T016 [P] Buat middleware `app/Http/Middleware/EnsurePenyelenggaraFeatureEnabled.php` (alias `fitur-penyelenggara:`; super admin tidak menembus)
- [X] T017 Daftarkan alias + `prependToPriorityList(SubstituteBindings::class, SetCurrentPenyelenggara::class)` di `bootstrap/app.php`
- [X] T018 Sinkronkan role & permission baru di `database/seeders/RolePermissionSeeder.php`
- [X] T019 Tambah shared props `currentPenyelenggara`, `penyelenggaras` (super admin), `penyelenggaraFeatures` di `app/Http/Middleware/HandleInertiaRequests.php`
- [X] T020 [P] Buat layout `resources/js/layouts/penyelenggara-layout.tsx` + `resources/js/components/penyelenggara-sidebar.tsx` (meniru `kartu-bebas-layout.tsx`/`kartu-bebas-sidebar.tsx`, gating permission + saklar fitur)
- [X] T021a Jaga invarian user penyelenggara (`penyelenggara_id` terisi ⇒ `school_id` null) di `app/Models/User.php` (saving hook) + asersi di `tests/Feature/Penyelenggara/TenantScopeTest.php`
- [X] T021 Tes fondasi `tests/Feature/Penyelenggara/TenantScopeTest.php`: tanpa konteks → 0 baris; konteks A → hanya A; creating mengisi `penyelenggara_id`

**Checkpoint**: fondasi siap; `php artisan test --compact --filter=TenantScope` hijau.

---

## Phase 3: User Story 1 - Super admin mendaftarkan penyelenggara & admin pertama (P1) 🎯 MVP

**Goal**: Super admin membuat penyelenggara, mengundang admin; admin menerima undangan dan masuk ke area penyelenggara.

**Independent Test**: lihat spec US1 — penyelenggara tersimpan, undangan terkirim, tautan menghasilkan akun Admin Penyelenggara yang mendarat di `/penyelenggara`.

### Tests for User Story 1

- [X] T022 [P] [US1] Tes CRUD super admin + slug unik + validasi di `tests/Feature/Penyelenggara/PenyelenggaraCrudTest.php`
- [X] T023 [P] [US1] Tes undangan (kirim → Notification::fake, terima → login & redirect, kedaluwarsa, dipakai ulang, email sudah dipakai) di `tests/Feature/Penyelenggara/UndanganTest.php`

### Implementation for User Story 1

- [X] T024 [US1] Model `app/Models/PenyelenggaraInvitation.php` (BelongsToPenyelenggara, LogsActivity, `status()` turunan, `issue()` mengembalikan token mentah, simpan SHA-256) + factory
- [X] T025 [US1] Notifikasi `app/Notifications/UndanganPenyelenggara.php` (mail, Bahasa Indonesia, tautan `undangan.show`, masa berlaku 7 hari)
- [X] T026 [US1] Controller `app/Http/Controllers/Admin/PenyelenggaraController.php` (index/store/show/update + aksi undang admin pertama) dengan Form Request di `app/Http/Requests/`
- [X] T027 [US1] Controller publik `app/Http/Controllers/UndanganController.php` (show/accept; accept membuat user dengan role undangan, `penyelenggara_id`, `school_id` null, login, tandai `accepted_at`)
- [X] T028 [US1] Rute sesuai `contracts/routes.md` (grup `/admin/penyelenggara` super-admin termasuk `admin.penyelenggara.invite`, `/undangan/{token}` guest + throttle 10/menit, `/penyelenggara` dashboard) di `routes/web.php`
- [X] T029 [P] [US1] Halaman `resources/js/pages/admin/penyelenggara/index.tsx` dan `show.tsx` (daftar, form, daftar undangan + status)
- [X] T030 [P] [US1] Halaman `resources/js/pages/undangan/show.tsx` (form sandi; pesan tidak berlaku + cara minta ulang) dan `resources/js/pages/penyelenggara/dashboard.tsx` (kerangka kosong)
- [X] T031 [US1] Menu "Penyelenggara" super admin di `resources/js/components/app-sidebar.tsx` (grup Sistem, `superAdmin: true`)

**Checkpoint**: US1 jalan dan teruji sendiri.

---

## Phase 4: User Story 2 - Data penyelenggara terisolasi total (P1)

**Goal**: Isolasi A↔B dan penyelenggara↔sekolah.

**Independent Test**: lihat spec US2.

### Tests for User Story 2

- [X] T032 [P] [US2] Tes isolasi di `tests/Feature/Penyelenggara/IsolasiTest.php`: detail milik B dari akun A → 404 identik dengan id acak; akun penyelenggara → `/admin/siswa`, `/admin/siswa/{id}`, `/admin/absensi`, `/admin/dashboard`, `POST /admin/switch-school` ditolak/redirect; akun sekolah & orang tua → `/penyelenggara` 403
- [X] T033 [P] [US2] Tes regresi: user sekolah/super admin tetap mendarat di tujuan lama setelah login di `tests/Feature/Penyelenggara/LoginRedirectTest.php`

### Implementation for User Story 2

- [X] T034 [US2] Pasang `bukan-penyelenggara` pada grup `Route::middleware(['auth','verified'])->prefix('admin')` dan rute `admin/switch-school` di `routes/web.php`
- [X] T035 [US2] Pastikan semua binding area penyelenggara memakai model ber-trait (404 seragam) dan grup `/penyelenggara` memakai `penyelenggara` + `SetCurrentPenyelenggara` di `routes/web.php`

**Checkpoint**: US1 + US2 hijau.

---

## Phase 5: User Story 3 - Super admin berpindah konteks (P2)

- [X] T036 [P] [US3] Tes switch konteks + saklar fitur tidak ditembus super admin + tanpa pilihan menampilkan pemilih di `tests/Feature/Penyelenggara/SwitchKonteksTest.php`
- [X] T037 [US3] Controller `app/Http/Controllers/Penyelenggara/SwitchController.php` + rute `penyelenggara.switch` di `routes/web.php`
- [X] T038 [US3] Pemilih konteks di `resources/js/components/penyelenggara-sidebar.tsx` (hanya super admin) dan halaman pilih penyelenggara bila konteks kosong di `resources/js/pages/penyelenggara/pilih.tsx`

---

## Phase 6: User Story 4 - Tim & hak akses (P2)

- [X] T039 [P] [US4] Tes tim di `tests/Feature/Penyelenggara/TimTest.php`: undang pendamping, cabut undangan, nonaktifkan akun, admin terakhir tidak bisa dinonaktifkan, penyelenggara nonaktif memblokir login area, izin modul dicabut → menu & rute tertutup
- [X] T040 [US4] Controller `app/Http/Controllers/Penyelenggara/TimController.php` (index, invite, revoke, status) + Form Request
- [X] T041 [US4] Halaman `resources/js/pages/penyelenggara/tim.tsx`
- [X] T042a [US4] Catat log aktivitas (Spatie `activity()`) untuk undangan, terima undangan, perubahan peran & status akun di `app/Http/Controllers/Penyelenggara/TimController.php`, `app/Http/Controllers/UndanganController.php`, `app/Http/Controllers/Admin/PenyelenggaraController.php`; asersi entri log di `tests/Feature/Penyelenggara/TimTest.php` (FR-014)
- [X] T042 [US4] Kelompokkan modul penyelenggara di layar Hak Akses agar hanya tampil untuk peran penyelenggara di `app/Http/Controllers/Admin/RolePermissionController.php` (dan page terkait)

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T043 [P] Tes frontend (node) untuk sidebar penyelenggara & gating di `tests/Unit/penyelenggara-sidebar.test.mjs`
- [X] T044 `vendor/bin/pint --dirty --format agent`, `npm run build`, suite penuh `php artisan test --compact --parallel` (hanya kegagalan lama)
- [X] T045 Verifikasi tidak ada perubahan pada `card_*`/`attendances`: `git diff --stat database/migrations` hanya berisi 3 migrasi baru; `CardAttendanceTest`, `CardParticipantTest` tidak diubah dan hijau
- [X] T046 Jalankan langkah manual `quickstart.md` lalu commit `feat(001): tenant penyelenggara, peran, undangan, isolasi` (tanpa trailer Claude)

---

## Dependencies & Execution Order

- Setup (T001–T002) → Foundational (T003–T021) → US1 (T022–T031) → US2 (T032–T035) → US3 & US4 (boleh paralel) → Polish.
- US2 bergantung pada rute area penyelenggara dari US1 (T028).
- Di dalam story: tes dulu (harus gagal) → model → controller → rute → halaman.

### Parallel Opportunities

- T006, T007, T008 paralel; T014, T015, T016 paralel; T020 paralel dengan middleware.
- T022 ∥ T023; T029 ∥ T030; T032 ∥ T033.

## Parallel Example: User Story 1

```text
T022 tests/Feature/Penyelenggara/PenyelenggaraCrudTest.php
T023 tests/Feature/Penyelenggara/UndanganTest.php
→ lalu T024..T028 berurutan → T029 ∥ T030
```

## Implementation Strategy

### MVP First

Phase 1–2 lalu US1 + US2 (keduanya P1: tanpa isolasi, fitur tidak boleh dirilis). Validasi, commit.

### Incremental Delivery

US3 (switch konteks) dan US4 (tim) menyusul dalam commit terpisah bila waktu sempit.

## Notes

- Penegakan kuota peserta dikerjakan di spec 002; di sini kuota hanya disimpan.

- Tidak ada perubahan pada tabel `card_*`, `attendances`, `schools` (Konstitusi III & V).
- Nama index MySQL ≤ 64 karakter; query tanggal kompatibel SQLite.

## Audit 2026-10-14 — P1

Tes: `tests/Feature/Penyelenggara/AuditP1Test.php`, `tests/Unit/akses-penyelenggara.test.mjs`.

- [X] T-A10 Sidebar penyelenggara juga di halaman Settings (`auth.user.penyelenggara_id`) — `app-sidebar-layout.tsx`, `HandleInertiaRequests`
