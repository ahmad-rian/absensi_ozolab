# Portal Orang Tua, Hak Akses Berbahasa Indonesia, dan Pembenahan Laporan

Repositori: **`absensi_ozolab`** (tyasphoto.ozolab.id).

> **Dokumen kerja.** Rencana implementasi untuk sembilan permintaan yang diajukan 22 September 2026. Belum ada satu baris kode pun yang ditulis untuk ini.
>
> **Cara memakainya.** Bagian **Urutan kerja** mengikat — beberapa langkah merusak kalau dibalik, alasannya ditulis di tempatnya. Bagian **Peta kode acuan** di akhir berisi path:line hasil penelusuran; pakai itu sebagai titik masuk daripada memetakan ulang kode dari nol, tapi **verifikasi nomor barisnya** sebelum diandalkan — repositori ini aktif dan nomor baris bergeser.
>
> **Keputusan yang sudah diambil pemilik produk**, jangan ditawar ulang: orang tua login dengan **email saja** (bukan nomor WhatsApp); password default `password` hanya untuk role **ORANG_TUA**; nama permission diterjemahkan ke bahasa Indonesia dengan akhiran `.access` **dipertahankan**.
>
> **Aturan repositori** yang berlaku untuk pekerjaan ini: setiap perubahan wajib punya tes (`CLAUDE.md`), jalankan `vendor/bin/pint --dirty --format agent` setelah menyentuh PHP, dan pesan commit **tidak boleh** memuat trailer `Co-Authored-By: Claude` atau `Claude-Session:`.

## Context

Sembilan permintaan yang saling terkait. Intinya satu: **aplikasi ini sudah punya akun orang tua, tapi orang tua belum bisa memakainya.**

Role `ORANG_TUA` sudah ada dan sudah terpasang di 200 akun. Tapi `AppModule::defaultsFor()` memberinya **array kosong** (`app/Enums/AppModule.php:131`), dan Fortify mengarahkan semua orang ke `/admin/dashboard` (`config/fortify.php:76`) yang dijaga `permission:dashboard.access`. Jadi seorang orang tua yang berhasil login akan langsung menabrak 403. Tidak ada halaman untuk mereka, tidak ada rute, tidak ada menu.

Temuan lain yang mengubah bentuk pekerjaan — banyak hal sudah ada dan tinggal dipakai ulang:

| Yang sudah ada | Di mana | Artinya |
|---|---|---|
| Laporan per siswa, XLSX + PDF, absensi **dan** sholat | `app/Http/Controllers/Admin/StudentReportController.php` | Permintaan #3 sebagian besar sudah jadi, tinggal dibuka untuk orang tua dan ditambah mode gabungan |
| `StudentStatsBuilder` (`attendanceFor`, `prayerFor`, `resolveRange`) | `app/Services/Student/StudentStatsBuilder.php:47-304` | Satu-satunya sumber angka. Portal orang tua tidak boleh menghitung sendiri |
| Permission sudah **satu per modul**, format `<modul>.access` | `app/Enums/AppModule.php:41-44` | Permintaan #9 sudah 60% terpenuhi; sisanya cuma soal nama |
| Workspace terpisah dengan sidebar & tema sendiri | `resources/js/layouts/kartu-bebas-layout.tsx` | Pola yang diikuti portal orang tua, bukan bikin arsitektur baru |
| Field email login **dan** email notifikasi di form orang tua | `app/Http/Controllers/Admin/OrangTuaController.php:67-68` | Orang tua yang dibuat manual sudah punya email login yang sah |
| Log kartu per siswa dengan `file_path` + `drive_url` | `app/Models/CardGenerationLog.php:14-25` | Sumber data galeri kartu untuk permintaan #4 |

Dan satu hambatan yang menentukan urutan kerja:

**Mayoritas akun orang tua tidak punya email yang bisa dipakai login.** `ParentProfileService:40` membuat user dengan `parent-{ulid}@internal.app` dan password `Str::random(16)` yang tidak pernah diberitahukan ke siapa pun. Itu jalur yang dipakai setiap pendaftaran siswa lewat `/daftar`. Di basis data lokal, **0 dari 201** profil orang tua punya email asli.

Anda memilih login **dengan email saja**. Konsekuensinya jujur: fiturnya bisa selesai seluruhnya, tapi orang tua baru benar-benar bisa masuk setelah sekolah mengisi email mereka. Karena itu rencana ini memasukkan tiga jalur pengisian email — satuan, massal lewat impor siswa, dan penanda siapa yang belum bisa login — sebagai bagian dari pekerjaan, bukan sebagai catatan kaki.

---

## 1 · Hak akses berbahasa Indonesia (#9)

Bentuknya sudah benar: satu permission per modul, tanpa `absen_view` gaya lama. Yang belum: **12 dari 27 nama masih Inggris**, bercampur dengan yang sudah Indonesia.

Slug diterjemahkan, akhiran `.access` dipertahankan.

| Lama | Baru |
|---|---|
| `dashboard.access` | `beranda.access` |
| `rfid-cards.access` | `kartu-rfid.access` |
| `frames.access` | `bingkai.access` |
| `card-layouts.access` | `layout-kartu.access` |
| `card-generation.access` | `generate-kartu.access` |
| `album-layouts.access` | `layout-album.access` |
| `album-generation.access` | `generate-album.access` |
| `photo-sheets.access` | `pas-foto.access` |
| `users.access` | `pengguna.access` |
| `drive-config.access` | `google-drive.access` |
| `wa-config.access` | `whatsapp.access` |
| `roles.access` | `hak-akses.access` |
| `schools.access` | `sekolah.access` |
| `notification-gateways.access` | `gateway-notifikasi.access` |
| `card-forms.access` | `form-kartu.access` |
| `impersonate.access` | `masuk-sebagai.access` |
| — baru — | `portal-orang-tua.access` |

Tidak berubah: `siswa`, `orang-tua`, `kelas`, `jadwal-absensi`, `absensi`, `kunjungan-perpus`, `laporan`, `notifikasi`, `pengaturan`, `kartu-bebas`, `semua-sekolah`.

**URL admin tidak ikut berubah.** `/admin/card-generation` dan kawan-kawan tetap seperti sekarang — menerjemahkan URL berarti mematahkan setiap tautan yang sudah beredar, dan itu bukan yang diminta.

**Migrasi harus mendahului seeder, dan ini bukan detail kecil.** `RolePermissionSeeder:30` menjalankan `Permission::whereNotIn('name', $permissions)->delete()`. Kalau `AppModule` diubah lalu seeder jalan duluan, ke-12 permission lama **terhapus** dan seluruh baris `role_has_permissions` serta `model_has_permissions` yang menunjuk ke sana ikut hilang — role custom dan hak tambahan per pengguna lenyap diam-diam.

Karena itu: satu migration yang melakukan `UPDATE permissions SET name = ?` per pasangan. Id baris tidak berubah, jadi semua relasi utuh. Migration ini harus idempoten (lewati kalau nama baru sudah ada).

Yang ikut diperbarui: 33 string `permission:` di `routes/web.php`, `routes/api.php:27`, field `permission` di `resources/js/components/app-sidebar.tsx:82-190`, `resources/js/pages/admin/siswa/show.tsx:280`, `resources/js/components/panduan/guide-registry.ts`, dan `tests/Feature/PermissionGateTest.php`.

`app/Enums/SchoolFeature.php` memakai enum case, bukan string — tidak perlu disentuh.

---

## 2 · Orang tua bisa login (#1)

**Role `ORANG_TUA` diberi satu permission:** `portal-orang-tua.access`, lewat modul baru `AppModule::PortalOrangTua`. Tidak ada satu pun permission admin yang diberikan — portal berdiri di atas rutenya sendiri.

**Redirect setelah login.** Sekarang tidak ada sama sekali; semua orang dilempar ke `/admin/dashboard`. Ditambahkan binding `LoginResponse` di `FortifyServiceProvider`: role `ORANG_TUA` → `/orangtua`, selain itu → `/admin/dashboard`. Rute `/` dan `dashboard` mengikuti aturan yang sama.

**Password default.** Perintah artisan `php artisan ortu:setel-password` yang menyetel setiap user ber-role `ORANG_TUA` ke `password` dan menyalakan penanda wajib ganti. Hanya role itu — akun admin, guru, dan superadmin tidak disentuh, sesuai pilihan Anda.

> **Peringatan keamanan, dan saya menyebutkannya sekali lalu mengerjakan sesuai keputusan Anda.** Setelah perintah ini dijalankan di produksi, setiap akun orang tua yang emailnya diketahui orang lain bisa dimasuki dengan menebak satu kata. Selama jendela itu, orang asing bisa membaca nama, kelas, foto, dan riwayat kehadiran harian seorang anak. Yang menutup jendela itu adalah wajib-ganti-password di bagian #4 — jadi keduanya harus tayang bersamaan, tidak boleh terpisah. Saran tambahan: jalankan perintahnya per sekolah (`--sekolah=`), bersamaan dengan sekolah itu membagikan akun, bukan sekaligus untuk semua.

**Mengisi email login.** Tiga jalur, karena satu jalur saja tidak akan menyelesaikan 201 baris:

1. **Satuan** — sudah ada di `/admin/orang-tua/{id}/edit` (field `email`). Tidak ada pekerjaan.
2. **Massal lewat impor siswa** — `StudentImportParser::FIELD_SYNONYMS` (`app/Services/Import/StudentImportParser.php:42-55`) mendapat kolom baru `parent_email` dengan sinonim `emailortu`, `emailorangtua`, `emailwali`. `StudentImportApplier` meneruskannya ke `ParentProfileService`.
3. **Penanda** — daftar `/admin/orang-tua` menampilkan lencana **"Belum bisa login"** untuk user yang emailnya berakhiran `@internal.app`, dengan penyaring untuk melihat hanya mereka. Tanpa ini sekolah tidak punya cara tahu siapa yang tertinggal.

`ParentProfileService::findOrCreateFromRegistration` diubah: kalau email asli diberikan dan belum dipakai user lain, itu yang jadi `users.email`; kalau tidak, tetap placeholder seperti sekarang.

**Batasan yang saya sebutkan di depan, bukan setelah ketahuan:** `parent_profiles.user_id` UNIQUE dan `users.email` unique global, jadi **satu orang tua dengan anak di dua sekolah tidak bisa memakai satu akun.** Email yang sama akan ditolak di sekolah kedua. Itu sifat skema yang sudah ada, bukan yang diperkenalkan rencana ini; memperbaikinya berarti membongkar relasi orang-tua–sekolah dan jauh melampaui permintaan ini. Yang dikerjakan: pesan galatnya dibuat jelas ("Email sudah dipakai akun orang tua di sekolah lain"), bukan pelanggaran unique yang mentah.

---

## 3 · Portal orang tua (#2, #3, #4)

Workspace terpisah, mengikuti pola `kartu-bebas` yang sudah terbukti: prefix rute sendiri, layout sendiri, sidebar sendiri, tema warna sendiri.

```
routes/web.php
Route::middleware(['auth', 'permission:portal-orang-tua.access', 'password.ganti'])
    ->prefix('orangtua')->name('orangtua.')
```

| Halaman | Isi |
|---|---|
| `/orangtua` | Kartu per anak: foto, nama, kelas, kehadiran hari ini, persentase 30 hari terakhir untuk absensi/Dhuha/Dzuhur |
| `/orangtua/anak/{siswa}` | Rincian satu anak. Tiga panel — Absensi Sekolah, Dhuha, Dzuhur — dengan penyaring periode. Panel sholat hanya tampil bila fiturnya aktif di sekolah itu |
| `/orangtua/anak/{siswa}/laporan` | Pilih jenis (absensi / Dhuha / Dzuhur / semuanya) + periode → unduh PDF |
| `/orangtua/anak/{siswa}/galeri` | Pas foto dan kartu OSIS depan-belakang, bisa dilihat dan diunduh |

**Kepemilikan ditegakkan di satu tempat.** Middleware `EnsureAnakSendiri` (atau method guard bersama) yang menuntut `siswa.parent_profile_id === auth()->user()->parentProfile->id`, dipasang di keempat rute. Bukan diperiksa ulang di tiap controller — satu tempat, satu tes.

Sumber data:
- Angka: `StudentStatsBuilder` yang sudah ada, tanpa kueri baru.
- Foto: `StudentPhotoStorage::displayUrl()` — thumbnail, bukan PNG megabita.
- Kartu: `CardGenerationLog` terbaru berstatus sukses per layout; `file_path` di disk `public`.

Fitur sholat menghormati flag sekolah: `SchoolFeatures::for($school)->enabled(SchoolFeature::SholatDhuha)` dan `SholatDzuhur`.

**PDF gabungan.** `StudentReportController` sekarang punya empat aksi terpisah. Ditambah satu template `pdf/student-combined.blade.php` untuk mode "semuanya" — satu berkas, tiga bagian, satu halaman sampul identitas. Logikanya diekstrak ke `App\Services\Student\StudentReportBuilder` supaya admin dan portal orang tua memanggil kode yang sama, bukan dua salinan yang lama-lama berbeda isi.

**Desain PDF dirapikan** (#7, bagian PDF). Yang sekarang (`resources/views/pdf/student-attendance.blade.php`) bergaris `#999` di mana-mana dan berkepala rata tengah — terbaca seperti keluaran mesin, bukan surat dari sekolah. Yang dituju:

- Kop kiri-rata: nama sekolah besar, jenis laporan sebagai eyebrow kecil huruf besar berspasi, periode di bawahnya. Garis tebal tunggal sebagai pemisah, bukan kotak.
- Identitas siswa dalam dua kolom, bukan tabel bergaris.
- Ringkasan sebagai deret angka besar berlabel kecil — bukan tabel bersel. Angka yang dicari orang tua (persen kehadiran) paling besar.
- Tabel rincian tanpa garis vertikal, hanya garis tipis antarbaris dan baris genap berlatar sangat muda. Kolom status memakai penanda bentuk, bukan hanya kata, supaya terbaca saat dicetak hitam-putih.
- Kaki halaman: tanggal cetak + nomor halaman.
- Palet: teks `#111827`, redup `#6b7280`, garis `#e5e7eb`, aksen tunggal `#1d4ed8`. Tanpa warna latar blok.

dompdf tidak mendukung webfont eksternal dengan andal, jadi tipografi tetap keluarga bawaan — yang diperbaiki hierarki dan ruangnya, bukan fontnya.

---

## 4 · Wajib ganti password saat login pertama (#5)

Belum ada apa pun untuk ini — tidak ada kolom, middleware, maupun halaman.

- Migration: `users.must_change_password` boolean default `false`.
- Middleware `EnsurePasswordChanged` (alias `password.ganti`) → redirect ke `/ganti-password` selama penandanya menyala. Dipasang di grup admin **dan** grup orang tua; dikecualikan untuk halaman ganti password itu sendiri dan logout.
- Halaman React `resources/js/pages/auth/ganti-password.tsx`, layout auth, tanpa navigasi keluar — satu form, satu tujuan.
- Aturan password memakai `Password::defaults()` yang sudah dikonfigurasi (`app/Providers/AppServiceProvider.php:115-121`): di produksi minimal 12 karakter, huruf besar-kecil, angka, simbol. Password baru tidak boleh sama dengan `password`.
- Setelah tersimpan, penanda dimatikan dan pengguna diarahkan ke beranda sesuai rolenya.

Penandanya dinyalakan oleh: perintah `ortu:setel-password`, dan pembuatan user baru lewat `/admin/users` maupun `/admin/orang-tua`.

---

## 5 · Jadwal sholat pindah ke Jadwal Absensi (#6)

Sekarang tab `?tab=sholat` di Pengaturan mencampur dua hal berbeda: **apakah fiturnya menyala** dan **jam berapa jendelanya**.

Setelah perubahan:

| Halaman | Yang tinggal / yang datang |
|---|---|
| `/admin/pengaturan?tab=sholat` | `prayer_dhuha_enabled`, `prayer_enabled`, `prayer_all_religions` — aktif/tidak dan siapa yang ikut |
| `/admin/jadwal-absensi` | Kartu baru **"Jadwal Sholat"** berisi `prayer_dhuha_start/end` dan `prayer_start/end`. Muncul hanya bila salah satu fiturnya aktif |

**Penyimpanan tidak berubah.** Jam sholat tetap di kolom JSON `schools.settings` lewat key yang sudah ada. Memindahkannya ke tabel `attendance_schedules` berarti migrasi data, perubahan `PrayerSchedule`/`PrayerSettings`/`PrayerAttendanceRecorder`, dan kemampuan per-hari/per-kelas yang tidak Anda minta. Yang diminta adalah pengaturannya ada di halaman itu — itu yang dikerjakan.

Aksi baru `AttendanceScheduleController::updatePrayer()`. Validasi overlap `assertPrayerWindowsDoNotOverlap` (`PengaturanController.php:318`) diangkat ke `App\Support\PrayerSchedule` supaya dipakai kedua pemanggil — satu jendela Dhuha yang bertabrakan dengan Dzuhur membuat penentuan jenis saat scan jadi ambigu, dan penjagaan itu tidak boleh hilang dalam perpindahan.

---

## 6 · Laporan admin: sholat masuk, Excel banyak sheet (#7)

`/admin/laporan` sekarang hanya punya satu laporan: rekap kehadiran absensi sekolah. Sholat tidak ada sama sekali.

**Penyaring baru:** jenis laporan — Absensi Sekolah / Dhuha / Dzuhur / Semuanya. Tampilan layar mengikuti pilihan itu.

**Excel satu berkas, banyak sheet.** `XlsxDownload::make()` sekarang single-sheet (`app/Support/XlsxDownload.php:28-54`). Ditambah `XlsxDownload::sheets()` yang menerima array bernama dan memakai `addNewSheetAndMakeItCurrent()` + `setName()` dari openspout:

| Sheet | Isi |
|---|---|
| **Ringkasan** | Satu baris per siswa, kolom persentase untuk ketiga jenis berdampingan |
| **Absensi Sekolah** | Rekap per siswa: hadir, terlambat, izin, sakit, alpa, % |
| **Dhuha** | Ikut, tidak ikut, hari efektif, % |
| **Dzuhur** | Sama |

Sheet sholat hanya dibuat bila fiturnya aktif di sekolah itu — berkas dengan sheet kosong lebih membingungkan daripada berkas tanpa sheet itu.

Nama sheet dibatasi 31 karakter oleh format XLSX; pembangunnya memotong dan menolak karakter `[]:*?/\`.

**PDF** memakai desain yang sama dengan bagian #3, diterapkan ke `resources/views/pdf/laporan.blade.php` (A4 landscape, tabel banyak siswa).

---

## 7 · Generate kartu mengikuti sekolah di sidebar (#8)

Halaman `/admin/generate-kartu` punya pemilih sekolah sendiri lewat `?school_id=`, terpisah total dari pemilih sekolah di sidebar. Dua pemilih untuk satu hal yang sama, dan yang satu tidak tahu yang lain — itu yang membuatnya membingungkan, dan itu juga yang dulu melahirkan 404 lintas sekolah pada tombol "Pasang foto".

Sesudahnya: sekolah datang dari `app('currentSchool')` yang sudah disetel `SetCurrentSchool`. Dropdown sekolah di halaman ini **dihapus**; yang tersisa hanya pemilih kelas.

- `GenerateKartuMassalController::index` membaca sekolah aktif, bukan query string. `?school_id=` yang masih menempel di bookmark lama diabaikan, tidak error.
- `Student::acrossSchools()` dan `Classroom::withoutGlobalScope('school')` **tetap** — halaman ini superadmin-only dan pelepasan scope itu punya alasan yang tertulis panjang di `GenerateKartuMassalController.php:207-229`. Yang berubah cuma dari mana `school_id` didapat.
- `unggahFoto` tetap menerima `string $siswa` dengan pencocokan `school_id` manual. Pencocokannya kini terhadap sekolah aktif.
- Konsekuensi yang perlu Anda tahu: untuk generate sekolah lain, superadmin harus mengganti sekolah di sidebar dulu. Itu memang yang diminta, dan konsisten dengan seluruh halaman admin lain.

---

## Urutan kerja

Urutannya mengikat — beberapa langkah merusak kalau dibalik.

1. **Rename permission** — migration UPDATE nama, `AppModule`, rute, sidebar, tes. Migration **harus** sebelum seeder jalan.
2. **`must_change_password`** — migration, middleware, halaman. Harus siap sebelum langkah 4.
3. **Modul + role portal orang tua** — `portal-orang-tua.access` ke role `ORANG_TUA`, `LoginResponse` per role.
4. **Perintah setel password + jalur pengisian email** — baru boleh dijalankan di produksi setelah 2 dan 3 tayang.
5. **Portal orang tua** — rute, layout, sidebar, empat halaman.
6. **`StudentReportBuilder` + desain PDF baru** — dipakai admin dan portal.
7. **Jadwal sholat pindah.**
8. **Laporan admin: jenis baru + Excel banyak sheet.**
9. **Generate kartu ikut sidebar.**

Langkah 1–4 satu rangkaian dan sebaiknya satu deploy. 5–9 berdiri sendiri-sendiri.

---

## Berkas yang disentuh

| Berkas | Perubahan |
|---|---|
| `app/Enums/AppModule.php` | Slug Indonesia, case `PortalOrangTua`, `defaultsFor(OrangTua)` tidak lagi kosong |
| `database/migrations/*_rename_permissions_ke_bahasa_indonesia.php` | UPDATE nama, id tetap |
| `database/migrations/*_add_must_change_password_to_users.php` | Kolom baru |
| `routes/web.php` | 33 string permission, grup `/orangtua`, alias `password.ganti` |
| `app/Http/Middleware/EnsurePasswordChanged.php` | Baru |
| `app/Http/Middleware/EnsureAnakSendiri.php` | Baru |
| `app/Providers/FortifyServiceProvider.php` | `LoginResponse` per role |
| `app/Console/Commands/SetelPasswordOrangTua.php` | Baru, `--sekolah=`, `--dry-run` |
| `app/Services/ParentProfileService.php` | Pakai email asli bila ada |
| `app/Services/Import/StudentImportParser.php` + `StudentImportApplier.php` | Kolom `parent_email` |
| `app/Http/Controllers/Admin/OrangTuaController.php` | Penanda + penyaring "belum bisa login" |
| `app/Http/Controllers/OrangTua/*` | Baru: Beranda, Anak, Laporan, Galeri |
| `resources/js/layouts/orangtua-layout.tsx`, `components/orangtua-sidebar.tsx` | Baru, pola `kartu-bebas` |
| `resources/js/pages/orangtua/*` | Baru, empat halaman |
| `resources/js/pages/auth/ganti-password.tsx` | Baru |
| `app/Services/Student/StudentReportBuilder.php` | Baru, ekstraksi dari `StudentReportController` |
| `resources/views/pdf/*.blade.php` | Desain baru + `student-combined.blade.php` |
| `app/Support/XlsxDownload.php` | `sheets()` banyak sheet |
| `app/Http/Controllers/Admin/LaporanController.php` | Jenis laporan, sholat, ekspor banyak sheet |
| `app/Http/Controllers/Admin/AttendanceScheduleController.php` + `PengaturanController.php` | Jam sholat pindah |
| `app/Support/PrayerSchedule.php` | Validasi overlap diangkat ke sini |
| `resources/js/components/pengaturan/sholat-tab.tsx`, `pages/admin/jadwal-absensi/index.tsx` | Jam pindah |
| `app/Http/Controllers/Admin/GenerateKartuMassalController.php` + halamannya | Sekolah dari sidebar |
| `resources/js/components/app-sidebar.tsx`, `pages/admin/siswa/show.tsx`, `components/panduan/guide-registry.ts` | Nama permission baru |

---

## Verifikasi

**Tes Pest baru:**

1. **Rename permission** — migration dijalankan pada basis data yang berisi role custom + hak tambahan per pengguna; sesudahnya role custom itu **masih** memegang hak yang sama. Ini tes terpenting di seluruh rencana: kegagalannya senyap dan baru ketahuan saat ada admin kehilangan akses.
2. **Penjaga sumber** — tidak ada string `.access` di `routes/web.php` yang tidak ada di `AppModule::permissions()`. Mencegah rute baru memakai nama yang tidak pernah di-seed.
3. **Orang tua login** → mendarat di `/orangtua`, bukan 403. Admin login → `/admin/dashboard`.
4. **Wajib ganti password** — user bertanda dialihkan dari halaman mana pun; setelah ganti, penanda mati dan halaman semula bisa dibuka. Password baru `password` ditolak.
5. **Kepemilikan anak** — orang tua A membuka anak orang tua B → 403, untuk keempat rute.
6. **Perintah setel password** — hanya menyentuh role `ORANG_TUA`; hash admin/guru/superadmin tidak berubah (dibandingkan sebelum-sesudah). `--sekolah=` membatasi ke satu sekolah.
7. **Portal menghormati flag sholat** — sekolah dengan Dhuha mati tidak menampilkan panel Dhuha dan menolak PDF Dhuha.
8. **Excel banyak sheet** — berkas hasil dibuka kembali dengan pembaca openspout, nama dan jumlah sheet dicocokkan; sheet sholat tidak ada saat fiturnya mati.
9. **Impor `parent_email`** — email masuk ke `users.email`, dan email yang sudah dipakai ditolak dengan pesan yang bisa dibaca, bukan galat unique.
10. **Generate kartu** — halaman memakai sekolah aktif sidebar; `?school_id=` sekolah lain diabaikan, bukan 500.

**Tes lama yang harus hijau tanpa diubah isinya** (selain penyesuaian nama permission): `PermissionGateTest`, `SchoolIsolationTest`, `OrangTuaControllerTest`, `LaporanControllerTest`, `PengaturanControllerTest`, `PrayerDhuhaTest`, `PrayerScannerTest`, `BulkCardGenerationTest`, `BulkPhotoUploadTest`, `StudentImportTest`, `SecurityRegressionTest`.

**Pemeriksaan manual setelah tayang:**

```bash
# Berapa orang tua yang belum bisa login
php artisan tinker --execute 'echo App\Models\User::role("ORANG_TUA")->where("email","like","%@internal.app")->count();'

# Uji perintah tanpa mengubah apa pun
php artisan ortu:setel-password --sekolah=<id> --dry-run
```

Lalu: login sebagai satu orang tua sungguhan, pastikan layar ganti password muncul lebih dulu, dan unduh satu PDF gabungan untuk memeriksa tata letaknya pada data nyata — bukan data seeder.

**Yang tidak bisa saya nilai dari sini:** apakah sekolah punya email orang tua sama sekali. Kalau ternyata sebagian besar tidak punya, portal ini akan selesai dan benar tapi sepi penggunanya, dan jalan keluarnya adalah login lewat nomor WhatsApp yang sudah terisi 100%. Itu bisa ditambahkan belakangan tanpa membongkar apa pun di rencana ini — kolom login tinggal menerima dua bentuk.

---

## Yang tidak dikerjakan

- **Jadwal sholat per hari atau per kelas.** Tetap satu jendela per sekolah, seperti sekarang. Yang diminta perpindahan tempat pengaturannya, bukan perubahan modelnya.
- **URL admin tidak diterjemahkan.** Hanya nama permission.
- **Orang tua tidak bisa mengubah data apa pun.** Portal ini hanya baca dan unduh — tidak ada izin, tidak ada pengajuan sakit, tidak ada koreksi absensi.
- **Satu akun untuk anak di dua sekolah.** Terhalang skema yang ada; dibatasi dengan pesan galat yang jelas, bukan diperbaiki.
- **Notifikasi ke portal.** Kanal notifikasi (WhatsApp, Email, Telegram) tidak disentuh sama sekali — kebijakan kuota WhatsApp yang berlaku tetap berlaku.

---

## Peta kode acuan

Hasil penelusuran, disertakan supaya pembangunnya tidak perlu memetakan ulang. Nomor baris per 22 September 2026.

### Hak akses

| Hal | Lokasi |
|---|---|
| Enum modul = sumber semua permission | `app/Enums/AppModule.php:11-44` |
| Default modul per role (`OrangTua => []`) | `app/Enums/AppModule.php:112-133` |
| Enum role | `app/Enums/UserRole.php:7-10` |
| Seeder, **menghapus** permission di luar daftar | `database/seeders/RolePermissionSeeder.php:26,30,35-36` |
| Alias middleware `permission`/`role`/`super-admin`/`feature` | `bootstrap/app.php:31-37` |
| 33 pemasangan `permission:` | `routes/web.php:193,196,234,238,242,248,257,267,272,277,283,292,299,308,317,331,338,343,350,357,361,368,374,381,387,394,399,407,423`; `routes/api.php:27` |
| `Gate::before` — superadmin lolos semua | `app/Providers/AppServiceProvider.php:93` |
| Share `roles` + `permissions` ke Inertia | `app/Http/Middleware/HandleInertiaRequests.php:55-56` |
| Filter menu sidebar | `resources/js/components/app-sidebar.tsx:55-66` (bentuk item), `:82-190` (daftar), `:217` (filter) |
| Hak tambahan per pengguna | `app/Http/Controllers/Admin/UserManagementController.php:67-68,89,109` |
| Policy yang **tidak pernah dipanggil** | `app/Policies/StudentPolicy.php`, `app/Policies/AttendancePolicy.php` — tidak ada `authorize()`/`can()` di seluruh aplikasi |

### Autentikasi

| Hal | Lokasi |
|---|---|
| `home` Fortify = `/admin/dashboard` | `config/fortify.php:76` |
| Username = `email` | `config/fortify.php:48` |
| Fitur aktif: reset password + verifikasi email saja | `config/fortify.php:166-171` |
| `loginView` | `app/Providers/FortifyServiceProvider.php:55-58` |
| Rate limit login 5/menit | `app/Providers/FortifyServiceProvider.php:109-113` |
| Aturan kekuatan password | `app/Providers/AppServiceProvider.php:115-121` |
| Halaman login React | `resources/js/pages/auth/login.tsx:29` |
| Ganti password sukarela | `routes/settings.php:16-18` → `app/Http/Controllers/Settings/ProfileController.php` |
| **Tidak ada** `LoginResponse`, `RouteServiceProvider`, `redirectTo`, `must_change_password` | — |

### Orang tua & siswa

| Hal | Lokasi |
|---|---|
| Pembuat akun otomatis, email `parent-{ulid}@internal.app`, password acak tak diberitahukan | `app/Services/ParentProfileService.php:38-46` |
| Form admin: `email` (login, unique users) + `notification_email` | `app/Http/Controllers/Admin/OrangTuaController.php:67-68,100,111` |
| Model, relasi `user`/`students` | `app/Models/ParentProfile.php:19-52` |
| `User->parentProfile()` HasOne | `app/Models/User.php:58-61` |
| User **keluar** dari global scope sekolah | `app/Models/User.php:48-51` |
| Kolom denormalisasi di students | `students.parent_name`, `students.parent_phone` |
| Sinonim kolom impor siswa | `app/Services/Import/StudentImportParser.php:42-55` (`parent_name`, `parent_phone` — belum ada email) |
| Skema: `parent_profiles.user_id` UNIQUE, `users.email` unique global | penyebab batasan satu-akun-satu-sekolah |

### Absensi, sholat, jadwal

| Hal | Lokasi |
|---|---|
| Sumber semua angka laporan | `app/Services/Student/StudentStatsBuilder.php:47` `resolveRange`, `:68` `attendanceFor`, `:148` `prayerFor` |
| Laporan per siswa (4 aksi) | `app/Http/Controllers/Admin/StudentReportController.php:28,58,82,102`; rute `routes/web.php:226-229` |
| Laporan admin | `app/Http/Controllers/Admin/LaporanController.php:24,60,98,136`; rute `routes/web.php:277-280` |
| Penulis XLSX, **single-sheet** | `app/Support/XlsxDownload.php:28-54` |
| Template PDF | `resources/views/pdf/laporan.blade.php`, `student-attendance.blade.php`, `student-prayer.blade.php` |
| Jadwal absensi | `app/Http/Controllers/Admin/AttendanceScheduleController.php:16,34,54,72,79`; `resources/js/pages/admin/jadwal-absensi/index.tsx:68`; model `app/Models/AttendanceSchedule.php` |
| Tab sholat di Pengaturan | `resources/js/components/pengaturan/sholat-tab.tsx:11-20`; validasi `app/Http/Controllers/Admin/PengaturanController.php:44-52`; overlap `:318` |
| Jam sholat disimpan di JSON `schools.settings` | key `prayer_*` / `prayer_dhuha_*`; pembaca `app/Support/PrayerSettings.php:30`, `app/Support/PrayerSchedule.php:26,69` |
| Penentuan jenis sholat saat scan | `app/Services/Attendance/PrayerAttendanceRecorder.php:57,65,118,132` |
| Flag fitur sholat memakai key lama | `app/Enums/SchoolFeature.php:69-70` — `prayer_enabled` / `prayer_dhuha_enabled` |
| Value object fitur | `app/Support/SchoolFeatures.php:16,51` |

### Kartu, foto, generate massal

| Hal | Lokasi |
|---|---|
| Log kartu: `file_path`, `drive_url`, `type` (`card`/`photo`/`photo_sheet`) | `app/Models/CardGenerationLog.php:14-25` |
| URL foto (thumbnail, jatuh ke asli) | `app/Support/StudentPhotoStorage::displayUrl()` |
| Generate massal | `app/Http/Controllers/Admin/GenerateKartuMassalController.php:51,107,230,266,302,312` |
| Alasan `unggahFoto` tidak pakai model binding | `GenerateKartuMassalController.php:207-229` |
| Halaman React | `resources/js/pages/admin/generate-kartu/index.tsx:47,49,55,62` |

### Konteks sekolah (tenancy)

| Hal | Lokasi |
|---|---|
| Middleware konteks sekolah | `app/Http/Middleware/SetCurrentSchool.php:24,26,38,48-52` |
| Urutan wajib sebelum route model binding | `bootstrap/app.php:49-57` |
| Global scope | `app/Models/Concerns/BelongsToSchool.php:32,55` |
| Switcher sekolah di UI | `resources/js/components/school-switcher.tsx:22,29,35`; dipasang di `app-sidebar.tsx:247` |
| Rute ganti sekolah | `routes/web.php:160-173` |

### Pola workspace terpisah (acuan portal orang tua)

| Hal | Lokasi |
|---|---|
| Layout + tema warna sendiri | `resources/js/layouts/kartu-bebas-layout.tsx` |
| Sidebar sendiri | `resources/js/components/kartu-bebas-sidebar.tsx` |
| Cara halaman memilih layout | `resources/js/pages/kartu-bebas/dashboard.tsx:5,117` (`Page.layout`) |
| Halaman publik tanpa layout admin | `resources/js/app.tsx:21-40` — ada tes yang menuntut setiap `pages/scan/*` terdaftar |
| Guard rute workspace | `routes/web.php:423` |

### Angka basis data lokal (seeder, bukan produksi)

201 profil orang tua, semuanya punya user, **0 punya email asli**; 301 siswa, semuanya punya orang tua; role terpasang: SUPER_ADMIN 1, ADMIN 2, GURU 3, ORANG_TUA 200.
