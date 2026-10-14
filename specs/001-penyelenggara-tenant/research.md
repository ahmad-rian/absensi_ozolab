# Research: Penyelenggara & Tenancy (001)

## R1. Mekanisme scope tenant
- **Decision**: Trait baru `BelongsToPenyelenggara` meniru `app/Models/Concerns/BelongsToSchool.php`
  (isi otomatis `penyelenggara_id` saat `creating`, global scope `penyelenggara`, `acrossPenyelenggara()`),
  tetapi **fail-closed**: bila konteks kosong, scope menambahkan `whereRaw('1 = 0')`. Konteks diambil
  dari container (`app('currentPenyelenggara')`), bukan langsung dari user, supaya super admin bisa
  berpindah. Console/queue/rute publik memakai `withoutGlobalScope('penyelenggara')` + filter eksplisit.
- **Rationale**: Konstitusi Prinsip II. `BelongsToSchool` (baris 31-35) hanya memfilter bila
  `school_id` ada — user penyelenggara (school_id null) akan melihat semua baris sekolah.
- **Alternatives**: Memakai `school_id` untuk penyelenggara (ditolak user); paket multitenancy
  (dependency baru, ditolak Prinsip I).

## R2. Menutup akses role penyelenggara ke rute sekolah
- **Decision**: Middleware `bukan-penyelenggara` (alias baru) ditambahkan ke grup
  `Route::middleware(['auth','verified'])->prefix('admin')` (routes/web.php:216) dan rute `admin/switch-school`;
  user dengan `penyelenggara_id` terisi diarahkan ke `penyelenggara.dashboard`. Kebalikannya,
  grup `/penyelenggara` memakai `penyelenggara` (hanya super admin atau user ber-`penyelenggara_id`).
- **Rationale**: Grup `/admin` hanya bergantung pada permission per modul; rute tanpa permission
  (mis. Panduan, dashboard) tetap terbuka bagi user mana pun yang login.
- **Alternatives**: Memperbaiki `BelongsToSchool` menjadi fail-closed (berisiko memutus jalur sekolah
  yang ada; ditunda, dicatat sebagai temuan).

## R3. Peran & izin
- **Decision**: `UserRole` + `ADMIN_PENYELENGGARA`, `PENDAMPING`. `AppModule` + modul penyelenggara
  (`penyelenggara-beranda`, `penyelenggara-tim`, `penyelenggara-hak-akses`; modul data ditambah di spec
  berikutnya) dengan permission `<value>.access`, disinkronkan `RolePermissionSeeder`; default per peran
  lewat `AppModule::defaultsFor()`. `Gate::before` super admin tidak diubah.
- **Rationale**: Layar Hak Akses (`RolePermissionController`) sudah membaca `AppModule` — cukup
  dikelompokkan agar modul penyelenggara hanya tampil untuk peran penyelenggara.
- **Alternatives**: Sistem izin terpisah (ditolak: dua sumber kebenaran).

## R4. Konteks super admin
- **Decision**: Session `current_penyelenggara_id` + rute `penyelenggara/switch` (pola
  `admin/switch-school`, routes/web.php:190). Middleware `SetCurrentPenyelenggara` membinding
  `currentPenyelenggara` hanya di grup `/penyelenggara`, dan harus didaftarkan di priority list sebelum
  `SubstituteBindings` (pola bootstrap/app.php:62).
- **Rationale**: Pola terbukti; binding rute tidak tersaring konteks basi.

## R5. Undangan email
- **Decision**: Tabel `penyelenggara_invitations` (token di-hash SHA-256, `expires_at` 7 hari,
  `accepted_at`, `revoked_at`) + Mailable/Notification on-demand ke email tujuan. Halaman publik
  `undangan/{token}` membuat user + kata sandi lalu login. Tidak ada mekanisme undangan sebelumnya
  (user dibuat langsung dengan sandi di `UserManagementController::store`).
- **Rationale**: Spec FR-002 meminta status undangan dan sekali pakai; reset-password broker tidak
  menyimpan status.

## R6. Saklar fitur penyelenggara
- **Decision**: `penyelenggaras.settings` json + enum `PenyelenggaraFeature` dan middleware
  `fitur-penyelenggara:` meniru `EnsureFeatureEnabled`/`SchoolFeatures`; super admin tidak menembus.
  Di spec ini saklar disiapkan dengan 1 fitur contoh (`notifikasi_wali`) supaya mekanismenya teruji.

## R7. Redirect login
- **Decision**: `User::homeRoute()` mengembalikan `penyelenggara.dashboard` bila user punya
  `penyelenggara_id`; `RoleLoginResponse` tidak berubah.
