# Blueprint Sistem Absensi Siswa Berbasis QR Code

**Stack:** Laravel 13 · Inertia.js · React 19 · Tailwind CSS 4 · shadcn/ui · Recharts
**Lingkup MVP:** Modul Autentikasi (Login) + Modul Dashboard
**Catatan:** Dokumen ini hanya memuat infrastruktur, arsitektur, dan rancangan komponen. Tidak ada contoh kode.

---

## 1. Ringkasan Sistem

### 1.1 Tujuan
Membangun sistem absensi sekolah yang memanfaatkan QR Code unik per siswa. Setiap kali siswa melakukan pemindaian QR pada pintu masuk/keluar sekolah, sistem mencatat kehadiran dan secara otomatis mengirim notifikasi WhatsApp ke orang tua melalui API eksternal.

### 1.2 Aktor Utama
| Peran | Akses | Tanggung Jawab |
|-------|-------|----------------|
| **Admin Sekolah** | Penuh | Mengelola data master (kelas, tahun ajaran, jadwal, pengaturan), memantau dashboard, mengekspor laporan. |
| **Guru / Operator Absensi** | Terbatas | Operator scanner QR, validasi absensi, melihat rekap kelas. |
| **Orang Tua / Wali** | Registrasi mandiri | Mendaftarkan siswa, melihat riwayat absensi anak, menerima notifikasi WA. |
| **Siswa** | Tanpa login | Subjek absensi; identifikasi via kartu QR. |

### 1.3 Alur Inti
1. Orang tua mendaftar lalu mengisi data siswa → sistem membuat token QR unik per siswa.
2. Admin menerbitkan kartu QR (cetak) untuk siswa.
3. Siswa men-scan QR di scanner sekolah → endpoint validasi mencatat kehadiran.
4. Background job memicu pengiriman pesan WhatsApp ke nomor orang tua via API eksternal.
5. Dashboard memvisualisasikan tren kehadiran (Recharts).

---

## 2. Tech Stack & Versi Library

### 2.1 Backend
| Komponen | Pilihan | Keterangan |
|----------|---------|------------|
| Framework | Laravel 13 | PHP 8.3+ |
| ORM | Eloquent | Native |
| Queue | Laravel Queue (database driver dev, Redis prod) | Untuk pengiriman WA async |
| QR Generator | `simplesoftwareio/simple-qrcode` atau `bacon/bacon-qr-code` | Server-side rendering PNG/SVG |
| HTTP Client | `Illuminate\Support\Facades\Http` | Untuk panggil API WA |
| Auth | Laravel Breeze (Inertia + React stack) | Sudah include scaffolding login |
| Validation | Form Request classes | Centralized validation |
| Authorization | Policies + Gates | RBAC sederhana |
| Activity Log | `spatie/laravel-activitylog` | Audit trail |
| Permission | `spatie/laravel-permission` | Role & permission |

### 2.2 Frontend
| Komponen | Pilihan | Keterangan |
|----------|---------|------------|
| Renderer | Inertia.js (React adapter) | SSR opsional |
| UI Library | React 19 | Functional components + hooks |
| Styling | Tailwind CSS 4 | Konfigurasi `@theme inline` |
| Component Kit | shadcn/ui | Copy-paste model (bukan dependency) |
| Charting | Recharts | Pie, Bar, Line, Area |
| Icons | `lucide-react` | Konsisten dengan shadcn |
| Form | `react-hook-form` + `zod` | Validasi sisi klien |
| Date | `date-fns` + `dayjs` (opsional) | Format & locale id-ID |
| Tabel | `@tanstack/react-table` | Sorting, filtering, pagination |
| Toast | `sonner` | Notifikasi non-blocking |
| QR Scanner (web) | `html5-qrcode` atau `@yudiel/react-qr-scanner` | Untuk scanner berbasis browser |

### 2.3 Build & Dev Tooling
- Vite (default Laravel 13)
- TypeScript (disarankan)
- ESLint + Prettier
- Pest atau PHPUnit
- Laravel Pint (formatter PHP)

---

## 3. Struktur Direktori (High-Level)

```
sistem-absensi/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── GenerateDailyAttendanceReport.php
│   │       ├── RotateQrTokensCommand.php
│   │       └── SyncAttendanceStatsCommand.php
│   ├── Enums/
│   │   ├── AttendanceStatus.php          (HADIR, TERLAMBAT, ALPA, IZIN, SAKIT)
│   │   ├── AttendanceType.php            (CHECK_IN, CHECK_OUT)
│   │   ├── Gender.php
│   │   ├── ParentRelation.php            (AYAH, IBU, WALI)
│   │   ├── NotificationChannel.php       (WHATSAPP, EMAIL)
│   │   └── UserRole.php                  (ADMIN, GURU, ORANG_TUA)
│   ├── Events/
│   │   ├── StudentCheckedIn.php
│   │   ├── StudentCheckedOut.php
│   │   └── StudentRegistered.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/                     (Login, Logout, Register, Password)
│   │   │   ├── DashboardController.php
│   │   │   └── ProfileController.php
│   │   ├── Middleware/
│   │   │   ├── EnsureUserHasRole.php
│   │   │   ├── HandleInertiaRequests.php
│   │   │   └── ForceHttps.php
│   │   ├── Requests/
│   │   │   ├── Auth/LoginRequest.php
│   │   │   └── Auth/RegisterRequest.php
│   │   └── Resources/                    (untuk shape data ke Inertia)
│   │       ├── DashboardStatsResource.php
│   │       └── UserResource.php
│   ├── Jobs/
│   │   ├── SendWhatsAppAttendanceNotification.php
│   │   └── GenerateStudentQrToken.php
│   ├── Listeners/
│   │   ├── DispatchAttendanceWhatsAppNotification.php
│   │   └── LogAttendanceActivity.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Student.php
│   │   ├── ParentProfile.php
│   │   ├── Classroom.php
│   │   ├── AcademicYear.php
│   │   ├── Attendance.php
│   │   ├── AttendanceSchedule.php
│   │   ├── NotificationLog.php
│   │   └── Setting.php
│   ├── Notifications/
│   │   └── WhatsAppAttendanceNotification.php
│   ├── Policies/
│   │   ├── StudentPolicy.php
│   │   └── AttendancePolicy.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── WhatsAppServiceProvider.php
│   └── Services/
│       ├── Attendance/
│       │   ├── AttendanceRecorder.php      (Domain logic mencatat absen)
│       │   ├── QrTokenGenerator.php        (HMAC + rotation)
│       │   └── ScheduleResolver.php        (Tentukan jadwal aktif)
│       ├── Notification/
│       │   ├── WhatsAppGateway.php         (Interface)
│       │   ├── DefaultWhatsAppGateway.php  (Implementasi konkret API user)
│       │   └── MessageTemplateRenderer.php
│       └── Dashboard/
│           └── StatsAggregator.php
│
├── bootstrap/
├── config/
│   ├── attendance.php                    (Jam masuk default, toleransi terlambat)
│   ├── whatsapp.php                      (Endpoint, token, template id)
│   └── ...
├── database/
│   ├── factories/
│   │   ├── UserFactory.php
│   │   ├── StudentFactory.php
│   │   ├── ParentProfileFactory.php
│   │   ├── ClassroomFactory.php
│   │   ├── AttendanceFactory.php
│   │   └── NotificationLogFactory.php
│   ├── migrations/
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── RolePermissionSeeder.php
│       ├── AcademicYearSeeder.php
│       ├── ClassroomSeeder.php
│       ├── AdminUserSeeder.php
│       ├── ParentAndStudentSeeder.php
│       ├── AttendanceScheduleSeeder.php
│       ├── AttendanceHistorySeeder.php   (data 30–90 hari ke belakang)
│       ├── SettingSeeder.php
│       └── NotificationLogSeeder.php
│
├── resources/
│   ├── css/
│   │   └── app.css                       (Tailwind 4 + tema shadcn dari user)
│   ├── js/
│   │   ├── app.tsx                       (Entry Inertia)
│   │   ├── ssr.tsx                       (opsional)
│   │   ├── components/
│   │   │   ├── ui/                       (shadcn primitives)
│   │   │   │   ├── alert.tsx
│   │   │   │   ├── alert-dialog.tsx
│   │   │   │   ├── avatar.tsx
│   │   │   │   ├── badge.tsx
│   │   │   │   ├── breadcrumb.tsx
│   │   │   │   ├── button.tsx
│   │   │   │   ├── calendar.tsx
│   │   │   │   ├── card.tsx
│   │   │   │   ├── chart.tsx             (wrapper Recharts shadcn-style)
│   │   │   │   ├── checkbox.tsx
│   │   │   │   ├── command.tsx
│   │   │   │   ├── dialog.tsx
│   │   │   │   ├── dropdown-menu.tsx
│   │   │   │   ├── form.tsx
│   │   │   │   ├── input.tsx
│   │   │   │   ├── label.tsx
│   │   │   │   ├── popover.tsx
│   │   │   │   ├── select.tsx
│   │   │   │   ├── separator.tsx
│   │   │   │   ├── sheet.tsx
│   │   │   │   ├── sidebar.tsx
│   │   │   │   ├── skeleton.tsx
│   │   │   │   ├── sonner.tsx
│   │   │   │   ├── switch.tsx
│   │   │   │   ├── table.tsx
│   │   │   │   ├── tabs.tsx
│   │   │   │   ├── textarea.tsx
│   │   │   │   ├── toggle.tsx
│   │   │   │   └── tooltip.tsx
│   │   │   ├── dashboard/
│   │   │   │   ├── stat-card.tsx
│   │   │   │   ├── attendance-trend-chart.tsx    (LineChart)
│   │   │   │   ├── attendance-status-pie.tsx     (PieChart)
│   │   │   │   ├── class-comparison-bar.tsx      (BarChart)
│   │   │   │   ├── weekly-area-chart.tsx         (AreaChart)
│   │   │   │   ├── latest-checkins-table.tsx
│   │   │   │   ├── upcoming-events-card.tsx
│   │   │   │   └── live-activity-feed.tsx
│   │   │   ├── layouts/
│   │   │   │   ├── app-sidebar.tsx
│   │   │   │   ├── app-header.tsx
│   │   │   │   ├── app-shell.tsx
│   │   │   │   └── auth-layout.tsx
│   │   │   └── shared/
│   │   │       ├── theme-toggle.tsx
│   │   │       ├── confirm-dialog.tsx
│   │   │       ├── empty-state.tsx
│   │   │       ├── page-header.tsx
│   │   │       ├── data-table.tsx
│   │   │       └── role-badge.tsx
│   │   ├── hooks/
│   │   │   ├── use-theme.ts
│   │   │   ├── use-mobile.ts
│   │   │   ├── use-debounced.ts
│   │   │   └── use-confirm.tsx
│   │   ├── lib/
│   │   │   ├── utils.ts                  (cn helper)
│   │   │   ├── formatters.ts             (rupiah, tanggal id-ID, NIS)
│   │   │   └── constants.ts
│   │   ├── pages/
│   │   │   ├── auth/
│   │   │   │   ├── login.tsx
│   │   │   │   ├── register.tsx
│   │   │   │   ├── forgot-password.tsx
│   │   │   │   └── reset-password.tsx
│   │   │   ├── dashboard/
│   │   │   │   └── index.tsx
│   │   │   └── errors/
│   │   │       ├── 403.tsx
│   │   │       ├── 404.tsx
│   │   │       └── 500.tsx
│   │   └── types/
│   │       ├── inertia.d.ts              (PageProps global)
│   │       ├── models.ts                 (Type definition Student, Attendance, dst.)
│   │       └── shared.ts
│   └── views/
│       └── app.blade.php                 (Root template Inertia)
│
├── routes/
│   ├── web.php
│   ├── auth.php
│   ├── api.php                           (Endpoint scanner & webhook WA)
│   └── console.php
│
├── storage/
│   └── app/
│       └── qr-codes/                     (Cache PNG QR)
└── tests/
    ├── Feature/
    │   ├── Auth/LoginTest.php
    │   └── Dashboard/DashboardAccessTest.php
    └── Unit/
        └── Services/QrTokenGeneratorTest.php
```

---

## 4. Skema Database

### 4.1 Daftar Tabel

| Tabel | Fungsi |
|-------|--------|
| `users` | Akun login (admin, guru, orang tua) |
| `model_has_roles` / `roles` / `permissions` | spatie/laravel-permission |
| `parent_profiles` | Detail profil orang tua (relasi 1-1 ke `users` role ORANG_TUA) |
| `students` | Data siswa, relasi ke `parent_profiles` dan `classrooms` |
| `classrooms` | Kelas (mis. "7A", "8B") |
| `academic_years` | Tahun ajaran (mis. "2025/2026"), flag aktif |
| `attendances` | Catatan absen siswa (check-in / check-out) |
| `attendance_schedules` | Jadwal jam masuk/pulang per hari per kelas |
| `notification_logs` | Riwayat pengiriman WA (status, payload, response) |
| `settings` | Konfigurasi runtime (jam toleransi, template pesan, dsb.) |
| `password_reset_tokens` | Default Laravel |
| `sessions` | Default Laravel |
| `failed_jobs` | Default Laravel |
| `jobs` | Default Laravel (database queue) |
| `activity_log` | spatie/laravel-activitylog |

### 4.2 Detail Kolom (Ringkas)

**users**
- `id`, `name`, `email` (unique), `email_verified_at`, `password`, `phone`, `avatar_path`, `is_active` (bool), `last_login_at`, timestamps, soft delete.

**parent_profiles**
- `id`, `user_id` (FK unique), `nik` (nullable), `whatsapp_number` (E.164, indexed), `relation` (enum: AYAH/IBU/WALI), `occupation`, `address`, `city`, timestamps.

**academic_years**
- `id`, `name` (mis. "2025/2026"), `start_date`, `end_date`, `is_active` (unique partial index), timestamps.

**classrooms**
- `id`, `academic_year_id` (FK), `name` (mis. "7A"), `grade_level` (int 7–12), `homeroom_teacher_id` (FK users nullable), `capacity`, timestamps.

**students**
- `id`, `parent_profile_id` (FK), `classroom_id` (FK nullable), `nis` (unique), `nisn` (unique nullable), `full_name`, `gender` (enum), `birth_place`, `birth_date`, `address`, `photo_path`, `qr_token` (string 64 char, unique, indexed), `qr_issued_at`, `qr_rotated_at`, `is_active`, timestamps, soft delete.

**attendance_schedules**
- `id`, `classroom_id` (FK nullable; null = global default), `day_of_week` (0–6), `check_in_start`, `check_in_end`, `late_threshold` (time), `check_out_start`, `check_out_end`, `is_active`, timestamps.

**attendances**
- `id`, `student_id` (FK), `attendance_date` (date, indexed), `type` (enum: CHECK_IN/CHECK_OUT), `status` (enum: HADIR/TERLAMBAT/ALPA/IZIN/SAKIT), `recorded_at` (datetime), `recorded_by` (FK users nullable, untuk scanner manual), `device_id` (string nullable), `notes`, timestamps.
- **Unique composite index:** (`student_id`, `attendance_date`, `type`) untuk mencegah double-scan.

**notification_logs**
- `id`, `student_id` (FK), `attendance_id` (FK nullable), `parent_profile_id` (FK), `channel` (enum), `whatsapp_number` (snapshot), `template_key`, `payload` (JSON), `response_body` (JSON), `status` (PENDING/SENT/FAILED), `error_message`, `attempt_count`, `sent_at`, timestamps.

**settings**
- `id`, `key` (unique), `value` (JSON), `description`, timestamps.

### 4.3 Diagram Relasi (Tekstual)

```
users (1) ──── (1) parent_profiles ──── (∞) students ──── (∞) attendances
                                              │
                                              └── (∞) notification_logs

academic_years (1) ──── (∞) classrooms ──── (∞) students
                                              │
                                              └── (∞) attendance_schedules

users (ADMIN/GURU) ──── (∞ via recorded_by) attendances
```

---

## 5. Migrations — Urutan Eksekusi

1. `create_users_table` (modifikasi default Breeze + tambahan kolom)
2. `create_password_reset_tokens_table`
3. `create_sessions_table`
4. `create_jobs_table`, `create_failed_jobs_table`
5. `create_permission_tables` (spatie publish)
6. `create_activity_log_table` (spatie publish)
7. `create_academic_years_table`
8. `create_classrooms_table`
9. `create_parent_profiles_table`
10. `create_students_table`
11. `create_attendance_schedules_table`
12. `create_attendances_table` (dengan composite unique)
13. `create_notification_logs_table`
14. `create_settings_table`

---

## 6. Seeders — Strategi Data Dummy

### 6.1 Urutan & Volume Default
| Seeder | Volume | Catatan |
|--------|--------|---------|
| `RolePermissionSeeder` | 3 role × ~12 permission | ADMIN, GURU, ORANG_TUA |
| `SettingSeeder` | ~10 key | Jam masuk default, template WA, jam toleransi, dsb. |
| `AcademicYearSeeder` | 2 (1 aktif) | "2024/2025" arsip, "2025/2026" aktif |
| `AdminUserSeeder` | 1 admin + 3 guru | Credential default: `admin@sekolah.test` / `password` |
| `ClassroomSeeder` | 18 kelas | Kelas 7A–7F, 8A–8F, 9A–9F (3 grade × 6 paralel) |
| `AttendanceScheduleSeeder` | 5 hari × 18 kelas | Senin–Jumat, 07:00–07:15 check-in, 14:30 check-out |
| `ParentAndStudentSeeder` | 200 orang tua → 300 siswa | Rata-rata 1.5 anak/keluarga, sebar ke kelas |
| `AttendanceHistorySeeder` | 90 hari ke belakang | Distribusi realistis: 88% HADIR, 6% TERLAMBAT, 3% IZIN, 2% SAKIT, 1% ALPA |
| `NotificationLogSeeder` | Mirror attendance | 95% SENT, 4% FAILED, 1% PENDING |

### 6.2 Pertimbangan Realisme Data
- Nama siswa & orang tua: kombinasi factory dengan kamus nama Indonesia (Bagus, Citra, Dewi, dsb.) — bukan `fake()->name()` murni.
- Nomor WhatsApp: format `62812xxxxxxxx`, valid E.164.
- Tanggal absensi: skip hari libur nasional + Sabtu/Minggu via array `holidays` di seeder.
- Distribusi terlambat lebih tinggi pada Senin pagi (boolean weighted random).
- NIS: pola `{tahun_masuk}{kelas_grade}{urut3digit}`.

### 6.3 Faker Locale
Gunakan `fake('id_ID')` di semua factory untuk nama tempat, alamat, kota.

---

## 7. Routing

### 7.1 `routes/web.php`
| Method | URI | Name | Controller | Middleware |
|--------|-----|------|------------|------------|
| GET | `/` | `home` | Redirect ke `/dashboard` jika auth, ke `/login` jika tidak | `guest|auth` |
| GET | `/dashboard` | `dashboard` | `DashboardController@index` | `auth`, `verified` |

### 7.2 `routes/auth.php` (otomatis Breeze)
| Method | URI | Name |
|--------|-----|------|
| GET | `/login` | `login` |
| POST | `/login` | `login.store` |
| GET | `/register` | `register` |
| POST | `/register` | `register.store` |
| GET | `/forgot-password` | `password.request` |
| POST | `/forgot-password` | `password.email` |
| GET | `/reset-password/{token}` | `password.reset` |
| POST | `/reset-password` | `password.update` |
| POST | `/logout` | `logout` |

### 7.3 `routes/api.php` (disiapkan untuk fase berikutnya)
| Method | URI | Fungsi |
|--------|-----|--------|
| POST | `/api/scanner/scan` | Endpoint scanner QR (auth via API token device) |
| POST | `/api/webhooks/whatsapp/status` | Callback status delivery WA |

> Pada MVP, hanya rute web yang aktif. Rute API didefinisikan namun di-comment / disabled hingga modul scanner dibangun.

---

## 8. Modul Autentikasi (Login)

### 8.1 Konfigurasi
- Provider: Laravel Breeze (Inertia + React, dengan TypeScript).
- Halaman `register` **dimodifikasi**: form bertingkat 2 langkah (Step 1: akun orang tua, Step 2: data siswa).
- Saat MVP, `register` boleh disederhanakan jadi 1 langkah (akun orang tua saja); pendaftaran siswa dilakukan setelah login pertama. **Catatan: registrasi UI tetap dibuat lengkap, namun field siswa boleh disembunyikan/opsional di MVP.**

### 8.2 Form Login (Halaman `/login`)
**Layout:** `AuthLayout` — split screen 50/50 (kiri ilustrasi/branding, kanan form). Pada mobile, ilustrasi disembunyikan.

**Field:**
- Email (input email)
- Password (input password, toggle visibility)
- Remember me (checkbox)
- Link "Lupa kata sandi?"
- Link "Belum punya akun? Daftar"

**Komponen shadcn yang dipakai:** `Card`, `Input`, `Label`, `Button`, `Checkbox`, `Form` (react-hook-form), `Alert` (untuk error global), `Sonner` (untuk toast success).

**State error:**
- Validasi inline (react-hook-form + zod schema).
- Error backend ditampilkan via `Alert` variant `destructive` di atas form.
- Rate limiting: setelah 5 kali gagal, tampilkan `AlertDialog` informatif "Akun terkunci sementara, coba lagi dalam X menit".

### 8.3 Form Registrasi (Halaman `/register`)
**Layout:** `AuthLayout` versi wide, dengan progress stepper.

**Step 1 — Data Akun Orang Tua:**
- Nama lengkap, Email, Nomor WhatsApp (input dengan prefix `+62`), Password, Konfirmasi password, Hubungan dengan siswa (Select: Ayah/Ibu/Wali).

**Step 2 — Data Siswa (opsional di MVP):**
- Nama lengkap siswa, NIS (atau "Belum tahu"), Tanggal lahir (Calendar popover), Jenis kelamin (RadioGroup), Kelas (Combobox via `Command`), Alamat (Textarea), Foto (opsional, dropzone).
- Tombol "Tambah siswa lain" (array fields, useFieldArray).

**Komponen shadcn yang dipakai:** semua di atas + `Tabs` atau `Stepper` custom, `Popover` + `Calendar`, `Command` + `Popover` untuk combobox, `Textarea`.

**Konfirmasi sebelum submit:** `AlertDialog` ringkasan data sebelum POST.

### 8.4 Lupa & Reset Password
- Halaman `/forgot-password`: input email → trigger Laravel Notification (email default Breeze, atau bisa di-extend kirim WA via gateway yang sama untuk konsistensi).
- Halaman `/reset-password/{token}`: form set password baru + konfirmasi.

### 8.5 Middleware Akses
- Setelah login, default redirect berdasarkan role:
  - ADMIN / GURU → `/dashboard`
  - ORANG_TUA → `/dashboard` (versi parent-view, di MVP tampil sama tapi data difilter)
- Middleware `EnsureUserHasRole` untuk membatasi route administratif (disiapkan untuk fase 2).

---

## 9. Modul Dashboard

### 9.1 Layout
**Komponen:** `AppShell` membungkus `AppSidebar` + `AppHeader` + konten.

**Sidebar (`AppSidebar`):**
- Header: logo sekolah + nama sekolah (ambil dari `settings`).
- Menu utama (MVP hanya item Dashboard aktif, sisanya disabled/coming soon):
  - 📊 Dashboard (aktif)
  - 👥 Siswa (disabled)
  - 👨‍👩‍👧 Orang Tua (disabled)
  - 🏫 Kelas (disabled)
  - ✅ Absensi (disabled)
  - 📱 Scanner (disabled)
  - 📈 Laporan (disabled)
  - 💬 Notifikasi (disabled)
  - ⚙️ Pengaturan (disabled)
- Footer sidebar: profile user + dropdown menu (Profile, Logout).
- Collapsible: ikon-only mode di layar sempit.
- Komponen shadcn: `Sidebar`, `SidebarMenu`, `SidebarMenuItem`, `Tooltip` (untuk mode collapsed).

**Header (`AppHeader`):**
- Breadcrumb di kiri (gunakan komponen `Breadcrumb`).
- Search global (Cmd+K, gunakan `Command` dialog) — placeholder di MVP.
- Theme toggle (light/dark) — gunakan `DropdownMenu` dengan tiga opsi: Terang, Gelap, Sistem.
- Notification bell dengan badge unread count.
- Avatar + nama user.

### 9.2 Konten Halaman Dashboard

**Section 1 — Page Header:**
- Judul "Dashboard"
- Subjudul tanggal hari ini (format: "Senin, 19 Mei 2026")
- Action di kanan: `Select` filter rentang waktu (Hari ini, 7 hari, 30 hari, Bulan ini, Semester ini).

**Section 2 — Stat Cards (Grid 4 kolom desktop, 2 kolom tablet, 1 kolom mobile):**

| Card | Metrik | Trend |
|------|--------|-------|
| Total Siswa | Hitung `students.is_active` | Δ vs minggu lalu |
| Hadir Hari Ini | Hitung attendances CHECK_IN HARI INI | % vs kemarin |
| Terlambat Hari Ini | Hitung status TERLAMBAT hari ini | Indikator warna |
| Tingkat Kehadiran | % HADIR/(HADIR+ALPA) rentang aktif | Δ poin |

Setiap card menggunakan komponen `Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`. Trend ditampilkan dengan `Badge` (variant default/destructive/secondary) + ikon panah dari `lucide-react`.

**Section 3 — Grafik Utama (Grid 2 kolom desktop, 1 kolom mobile):**

- **Tren Kehadiran 30 Hari Terakhir** (LineChart Recharts)
  - X-axis: tanggal
  - Y-axis: jumlah siswa
  - 2 garis: Hadir vs Terlambat (warna `--chart-1` dan `--chart-3`)
  - Tooltip custom mengikuti tema shadcn.

- **Distribusi Status Hari Ini** (PieChart Recharts)
  - Slices: HADIR, TERLAMBAT, IZIN, SAKIT, ALPA
  - Warna pakai `--chart-1` sampai `--chart-5`
  - Legend di bawah.

**Section 4 — Grafik Sekunder (Grid 2 kolom):**

- **Perbandingan Antar Kelas** (BarChart horizontal)
  - X-axis: % kehadiran
  - Y-axis: nama kelas
  - Bar warna gradient berdasar % (rendah merah, tinggi hijau via `--chart-4`).

- **Pola Mingguan** (AreaChart)
  - X-axis: Senin–Jumat
  - Stacked area: Hadir, Terlambat, Tidak Hadir
  - Insight ringkas di bawah ("Hari paling sering terlambat: Senin").

**Section 5 — Aktivitas Terkini (Grid 3 kolom: 2 kol tabel + 1 kol feed):**

- **Tabel Check-in Terbaru** (2 kolom):
  - Kolom: Foto+Nama, Kelas, Waktu, Status (Badge berwarna), Notifikasi (✓/✗ status WA).
  - 10 baris terbaru, ada link "Lihat semua".
  - Komponen: `Table`, `Badge`, `Avatar`.

- **Live Activity Feed** (1 kolom):
  - List vertikal scrollable berisi event terbaru (siswa A check-in jam 07:02, dst.)
  - Auto-refresh tiap 30 detik (polling) atau via Echo (fase 2).
  - Komponen: list custom dengan `Avatar`, garis vertikal timeline.

### 9.3 State Loading
- Initial: gunakan `Skeleton` untuk setiap card dan chart sambil menunggu data.
- Refresh: spinner kecil di pojok card, data lama tetap tampil (stale-while-revalidate).

### 9.4 Empty State
- Jika belum ada data absensi: tampilkan `EmptyState` shared component dengan ilustrasi + CTA "Mulai catat absensi pertama".

---

## 10. Integrasi Tema shadcn (CSS yang Diberikan)

### 10.1 Penempatan
File CSS pada `resources/css/app.css`. Karena memakai Tailwind 4 dengan `@import "tailwindcss"`, semua token CSS variable di `:root` dan `.dark` akan langsung dipakai oleh utility classes shadcn (bg-background, text-foreground, dsb.).

### 10.2 Identitas Visual yang Dihasilkan
- **Warna utama:** biru cerah (oklch 0.67 0.16 245) — bernuansa Twitter blue.
- **Border radius:** 1.3rem (sangat rounded — gaya modern friendly).
- **Font sans:** Open Sans.
- **Shadow:** sangat tipis hingga nyaris invisible (opacity 0) → tampilan flat, minimalis.
- **Dark mode:** background hitam pekat (oklch 0 0 0), card sedikit lebih terang.

### 10.3 Konsekuensi Desain
- Karena radius besar, semua tombol, card, dialog, dan input akan tampak "pillow soft". Pastikan komponen `Badge`, `Avatar`, `Input` mengikuti radius via `radius-sm`, `radius-md`, `radius-lg`.
- Shadow nyaris tidak ada → gunakan **border** dan **background contrast** (mis. `bg-card` di atas `bg-background`) untuk separasi visual, bukan shadow.
- Aksen biru kuat → pakai variant `accent` untuk hover ringan, `primary` untuk CTA utama saja.

### 10.4 Font
Tambah Google Fonts Open Sans di `resources/views/app.blade.php` head (preconnect + link), atau import via `@font-face` di CSS. Pastikan weight 400, 500, 600, 700 ter-load.

### 10.5 Theme Provider (Light/Dark)
- Komponen `ThemeProvider` (context React) menyimpan preferensi di `localStorage` dengan key `theme` (`light` | `dark` | `system`).
- Pada mount, terapkan class `dark` ke `<html>` jika perlu.
- Hook `useTheme()` expose `theme`, `setTheme`, `resolvedTheme`.
- Toggle pakai `DropdownMenu` di header.

---

## 11. Komponen UI — Pola Pemakaian

### 11.1 Alert (Inline, Non-blocking)
**Lokasi penggunaan:**
- Banner di atas dashboard untuk informasi sistem (mis. "Sistem akan maintenance Sabtu malam").
- Pesan error di form login/register.
- Notifikasi sukses ringkas setelah aksi.

**Varian:**
- `default` — info umum
- `destructive` — error / peringatan keras
- (Custom) `success` — buat via override (border hijau + bg `--chart-2/10`)
- (Custom) `warning` — kuning (mis. `--chart-3`)

**Anatomi:** `<Alert>` mengandung `<AlertTitle>` (opsional) dan `<AlertDescription>`. Tambah ikon dari `lucide-react` (Info, AlertCircle, CheckCircle2, AlertTriangle) di kiri.

**Behaviour:**
- Tidak dismissable secara default, kecuali ditambahkan tombol X.
- Untuk pesan transient (mis. "Data berhasil disimpan"), gunakan **Sonner toast** alih-alih Alert.

### 11.2 Alert Dialog (Modal Konfirmasi)
**Lokasi penggunaan:**
- Konfirmasi logout.
- Konfirmasi sebelum aksi destruktif (hapus, reset QR token).
- Konfirmasi sebelum submit data registrasi siswa.
- Peringatan akun terkunci karena terlalu banyak gagal login.
- Pemberitahuan kritikal (mis. "API WhatsApp tidak tersedia, notifikasi akan ditunda").

**Anatomi:** `AlertDialog`, `AlertDialogTrigger`, `AlertDialogContent`, `AlertDialogHeader`, `AlertDialogTitle`, `AlertDialogDescription`, `AlertDialogFooter`, `AlertDialogAction`, `AlertDialogCancel`.

**Variant kustom yang perlu disiapkan (via prop):**
- `destructive` — tombol Action merah, ikon AlertTriangle.
- `warning` — tombol Action kuning, ikon AlertCircle.
- `info` — tombol Action biru (primary), ikon Info.
- `success` — tombol Action hijau, ikon CheckCircle2.

**Hook `useConfirm`:** wrapper untuk memanggil AlertDialog secara imperatif tanpa harus mount manual. Mengembalikan promise `<boolean>`.

### 11.3 Sonner Toast
- Posisi: top-right pada desktop, top-center pada mobile.
- Durasi default: 4 detik.
- Varian: `success`, `error`, `warning`, `info`, `loading`, `promise`.
- Pakai untuk: feedback aksi cepat, notifikasi inbound (WA terkirim), error background sync.

### 11.4 Dialog vs Alert Dialog
- **Dialog** — untuk konten interaktif (form, detail, edit cepat). Bisa ditutup via Esc dan klik luar.
- **Alert Dialog** — hanya untuk konfirmasi keputusan; tidak bisa ditutup via klik luar (paksa pengguna memilih).

### 11.5 Tabel Data
- Pakai `@tanstack/react-table` v8 sebagai engine, render dengan `Table` shadcn.
- Fitur MVP: sorting kolom, search global, pagination (server-side via Inertia partial reload).

---

## 12. Charts dengan Recharts

### 12.1 Wrapper Chart shadcn-style
Buat `components/ui/chart.tsx` mengikuti pola shadcn:
- `ChartContainer` — provider CSS variable untuk warna chart, mengaplikasikan `--chart-1` … `--chart-5` ke `--color-{key}` lokal.
- `ChartTooltip` & `ChartTooltipContent` — custom tooltip Recharts dengan styling tema.
- `ChartLegend` & `ChartLegendContent`.

### 12.2 Konfigurasi Warna per Chart
Setiap chart menerima objek `config`:
```
{
  hadir:     { label: "Hadir",     color: "var(--chart-1)" },
  terlambat: { label: "Terlambat", color: "var(--chart-3)" },
  alpa:      { label: "Alpa",      color: "var(--chart-5)" },
  izin:      { label: "Izin",      color: "var(--chart-2)" },
  sakit:     { label: "Sakit",     color: "var(--chart-4)" }
}
```

### 12.3 Daftar Chart yang Dibangun di MVP
| Komponen | Tipe Recharts | Sumber Data |
|----------|---------------|-------------|
| `AttendanceTrendChart` | `LineChart` | 30 hari terakhir, agregat harian |
| `AttendanceStatusPie` | `PieChart` | Distribusi status hari ini |
| `ClassComparisonBar` | `BarChart` (horizontal) | % kehadiran per kelas |
| `WeeklyAreaChart` | `AreaChart` stacked | Pola Senin–Jumat 4 minggu terakhir |

### 12.4 Responsif
- Bungkus tiap chart di `ResponsiveContainer` Recharts.
- Sembunyikan `axisTick`, `axisLabel` pada layar <768px untuk menjaga keterbacaan.

### 12.5 Aksesibilitas
- Sertakan tabel fallback (toggle "Lihat sebagai tabel") untuk pengguna screen reader.
- Tooltip dapat dipicu via keyboard.

---

## 13. Sistem QR Code

### 13.1 Generasi Token
- Saat siswa terdaftar (event `StudentRegistered`), dispatch job `GenerateStudentQrToken`.
- Token: kombinasi `student_id` + timestamp + random + HMAC SHA-256 menggunakan secret di `.env` (`QR_TOKEN_SECRET`).
- Hasil: string 64 karakter base64url, disimpan di `students.qr_token`, ditandai `qr_issued_at`.
- Format payload QR yang dicetak: URI scheme `attendance://scan?token={token}` (untuk app native) atau URL fallback `https://{domain}/scan?token={token}` (untuk web scanner).

### 13.2 Rotasi Token
- Command `RotateQrTokensCommand` dapat dijadwalkan (mis. per semester) untuk regenerate token siswa tertentu (mis. setelah kartu hilang).
- Lama dan baru: token lama langsung di-invalidasi (tidak ada grace period untuk keamanan).

### 13.3 Penyimpanan & Pengiriman
- PNG QR di-generate on-demand via service `QrTokenGenerator->renderPng()`, di-cache di `storage/app/qr-codes/{student_id}.png` selama 30 hari.
- Endpoint download cetak (PDF kartu siswa) — disiapkan untuk fase 2.

### 13.4 Validasi Scan (Fase 2, infrastruktur disiapkan)
- Endpoint `POST /api/scanner/scan`:
  - Auth: device API token (header `X-Device-Token`).
  - Body: `token`, `device_id`, `client_timestamp`.
  - Service `AttendanceRecorder` mengurai token, verify HMAC, cek aktif, resolve siswa, panggil `ScheduleResolver` untuk tentukan status (HADIR/TERLAMBAT), insert attendance.
  - Dispatch event `StudentCheckedIn` → trigger listener WA.

---

## 14. Integrasi WhatsApp

### 14.1 Abstraksi Gateway
- Interface `WhatsAppGateway` mendefinisikan method `sendTemplate(to, templateKey, variables)` dan `sendText(to, message)`.
- Implementasi konkret `DefaultWhatsAppGateway` menerima URL endpoint, API key, dan ID template dari `config/whatsapp.php`.

### 14.2 Konfigurasi Environment
```
WHATSAPP_BASE_URL=
WHATSAPP_API_KEY=
WHATSAPP_SENDER_ID=
WHATSAPP_TIMEOUT=10
WHATSAPP_ATTENDANCE_TEMPLATE=attendance_notify_v1
WHATSAPP_QUEUE=whatsapp
```

### 14.3 Alur Pengiriman
```
[Scan QR]
   │
   ▼
AttendanceRecorder.record()
   │
   ├─ insert ke attendances
   ├─ fire event StudentCheckedIn(attendance)
   │
   ▼
Listener DispatchAttendanceWhatsAppNotification
   │
   ▼
dispatch(SendWhatsAppAttendanceNotification job) ke queue 'whatsapp'
   │
   ▼
Worker queue
   │
   ├─ render template via MessageTemplateRenderer
   ├─ panggil WhatsAppGateway->sendTemplate()
   ├─ simpan ke notification_logs
   └─ jika gagal, retry exponential backoff (3x: 30s, 2m, 10m)
```

### 14.4 Template Pesan (Default)
Disimpan di `settings` dengan key `whatsapp_template_attendance`. Variabel placeholder:
- `{nama_siswa}`
- `{kelas}`
- `{waktu}` (format `HH:mm`)
- `{tanggal}` (format `d MMMM yyyy`)
- `{status}` (HADIR/TERLAMBAT)
- `{nama_sekolah}`

**Contoh isi default (di seeder):**
> Halo Bapak/Ibu Wali, ananda {nama_siswa} ({kelas}) telah {status} di {nama_sekolah} pada {tanggal} pukul {waktu}. Terima kasih.

### 14.5 Idempotency & Throttle
- Composite unique index attendance mencegah double-insert → otomatis mencegah double-notify.
- Job punya `uniqueId` berdasar `attendance_id` (Laravel `ShouldBeUnique` interface).

### 14.6 Webhook Status (Fase 2)
- Endpoint `POST /api/webhooks/whatsapp/status` untuk update status delivery (DELIVERED, READ, FAILED) dari provider WA.
- Update `notification_logs.status` sesuai callback.

### 14.7 Monitoring
- Dashboard internal: card "WA Terkirim Hari Ini" + "WA Gagal".
- Command `whatsapp:report` untuk laporan harian per email admin.

---

## 15. Properti Inertia Shared

`HandleInertiaRequests::share()` mengirim ke setiap response:

| Key | Isi |
|-----|-----|
| `auth.user` | User aktif: id, name, email, avatar, role, permissions |
| `app.name` | Nama aplikasi dari `config('app.name')` |
| `app.school_name` | Dari `settings.school_name` |
| `app.school_logo` | Dari `settings.school_logo` |
| `flash.success` / `flash.error` / `flash.info` / `flash.warning` | Session flash → di-render via Sonner |
| `errors` | Error bag (default Inertia) |
| `breadcrumbs` | Array breadcrumb per halaman |
| `ziggy` | Routes (jika pakai package `tightenco/ziggy`) |

---

## 16. Keamanan

- **CSRF:** default Laravel.
- **Rate limiting:** login 5 percobaan/menit per IP+email; scanner 60 req/menit per device.
- **Password policy:** min 8 karakter, harus mengandung huruf dan angka (validator custom).
- **Email verification:** wajib sebelum akses dashboard penuh (middleware `verified`).
- **HTTPS:** force di production via `ForceHttps` middleware.
- **Sanitize:** semua input lewat Form Request.
- **Authorization:** Policy `StudentPolicy` memastikan orang tua hanya bisa lihat data siswa miliknya.
- **Activity log:** semua aksi CRUD oleh admin tercatat (spatie/laravel-activitylog).
- **QR token:** HMAC verification + rotasi.
- **Secret rotation:** `APP_KEY`, `QR_TOKEN_SECRET`, `WHATSAPP_API_KEY` masuk daftar rotation checklist.
- **Dependency audit:** `composer audit` dan `npm audit` di CI.

---

## 17. Pengaturan (Settings) — Key Default

| Key | Tipe | Default | Deskripsi |
|-----|------|---------|-----------|
| `school_name` | string | "SMP Nusantara" | Nama tampilan |
| `school_logo` | string (path) | `null` | Path logo |
| `timezone` | string | `Asia/Jakarta` | TZ aplikasi |
| `default_check_in_time` | time | `07:00` | Jam mulai check-in |
| `late_threshold_time` | time | `07:15` | Setelah jam ini → TERLAMBAT |
| `default_check_out_time` | time | `14:30` | Jam pulang |
| `whatsapp_template_attendance` | string | (lihat 14.4) | Template pesan WA |
| `whatsapp_enabled` | bool | `true` | Master switch |
| `notify_on_check_in` | bool | `true` | Kirim WA saat masuk |
| `notify_on_check_out` | bool | `false` | Kirim WA saat pulang |
| `holiday_calendar` | json | `[]` | Array tanggal libur |

---

## 18. Internasionalisasi

- Bahasa default: `id` (Indonesia).
- File lang: `lang/id/*.php` untuk validation, auth, passwords.
- Frontend: format tanggal & angka pakai `Intl.DateTimeFormat('id-ID')` dan `Intl.NumberFormat('id-ID')`.
- Tidak perlu i18n multi-bahasa di MVP, namun struktur file sudah disiapkan.

---

## 19. Testing

### 19.1 Backend
- `Auth\LoginTest`: redirect setelah login, error credentials salah, throttling.
- `Dashboard\DashboardAccessTest`: guest → redirect, authed → 200.
- `Services\QrTokenGeneratorTest`: token unik, HMAC valid, expired token ditolak.
- `Services\AttendanceRecorderTest`: status terlambat saat melebihi threshold, double-scan ditolak.
- `Jobs\SendWhatsAppAttendanceNotificationTest`: retry logic, log tersimpan.

### 19.2 Frontend
- Vitest + React Testing Library untuk komponen UI utama (StatCard, ConfirmDialog, AlertDialog wrapper).
- Playwright smoke test: login → dashboard → logout.

---

## 20. Logging & Monitoring

- Laravel Telescope (dev only).
- Log channels:
  - `daily` — default
  - `whatsapp` — khusus WA gateway, retensi 30 hari
  - `attendance` — khusus event absen, retensi 90 hari
- Sentry (atau alternatif) di production via package `sentry/sentry-laravel`.

---

## 21. Konvensi Penamaan

- **Migration:** `snake_case`, deskriptif (`add_qr_token_to_students_table`).
- **Model:** `PascalCase` singular (`Student`, `Attendance`).
- **Controller:** `PascalCase` + suffix `Controller`.
- **Komponen React:** `kebab-case` filename, `PascalCase` export.
- **Halaman Inertia:** filename = slug URL, lowercase (`dashboard/index.tsx`).
- **Variabel:** `camelCase` di JS/TS, `snake_case` di PHP.
- **Konstanta enum:** `UPPER_SNAKE`.

---

## 22. Variabel Environment Penting

```
APP_NAME="Sistem Absensi"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=absensi
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_DRIVER=database

QR_TOKEN_SECRET=

WHATSAPP_BASE_URL=
WHATSAPP_API_KEY=
WHATSAPP_SENDER_ID=
WHATSAPP_ATTENDANCE_TEMPLATE=attendance_notify_v1
WHATSAPP_QUEUE=whatsapp

VITE_APP_NAME="${APP_NAME}"
```

---

## 23. Workflow Pengembangan

### 23.1 Setup Awal
1. `composer create-project laravel/laravel sistem-absensi`
2. `php artisan install:api` (opsional untuk Sanctum)
3. `composer require laravel/breeze --dev`
4. `php artisan breeze:install react --typescript --ssr` (atau tanpa ssr)
5. `composer require spatie/laravel-permission spatie/laravel-activitylog simplesoftwareio/simple-qrcode`
6. `npm install` lalu `npm install lucide-react recharts react-hook-form @hookform/resolvers zod sonner date-fns @tanstack/react-table cmdk`
7. Tambah komponen shadcn satu per satu (`npx shadcn@latest add ...`).
8. Salin CSS tema yang diberikan ke `resources/css/app.css`.
9. Buat migrations sesuai bagian 5, lalu seeders sesuai bagian 6.
10. `php artisan migrate --seed`.

### 23.2 Perintah Harian
- `composer dev` — script gabungan menjalankan `php artisan serve`, `php artisan queue:listen`, `php artisan pail`, dan `npm run dev`.
- `php artisan test` — jalankan test suite.
- `php artisan migrate:fresh --seed` — reset database + isi ulang dummy.

### 23.3 Git Workflow
- Branching: `main` (production), `develop` (integrasi), `feature/*`, `fix/*`.
- Commit: Conventional Commits (`feat:`, `fix:`, `chore:`, `docs:`).

---

## 24. Roadmap Pasca-MVP

| Fase | Modul |
|------|-------|
| 2 | CRUD Siswa, Orang Tua, Kelas, Tahun Ajaran |
| 3 | Scanner web + scanner mobile companion |
| 4 | Pengaturan jadwal absensi per kelas, hari libur |
| 5 | Laporan PDF/Excel, ekspor rekap bulanan |
| 6 | Portal Orang Tua (riwayat anak, izin online) |
| 7 | Real-time dashboard via Laravel Reverb / Echo |
| 8 | Notifikasi multi-channel (Email, Telegram) |
| 9 | Aplikasi mobile native (React Native) |

---

## 25. Catatan Final

- **MVP scope** secara sengaja dibatasi ke halaman Login & Dashboard. Semua skema database, seeder, service, dan event listener tetap dibangun penuh agar fase berikutnya tinggal menyambungkan UI tanpa migrasi struktural.
- **Tema shadcn** dengan radius besar dan shadow flat akan memberikan kesan modern dan ramah. Konsisten gunakan border + bg-contrast sebagai separator.
- **Data dummy** harus realistis (distribusi statistik wajar) agar chart Recharts di dashboard langsung terlihat hidup saat demo.
- **Notifikasi WhatsApp** diabstraksi melalui interface, sehingga pergantian provider API ke depan tidak menyentuh logic domain.
- **Keamanan QR** memakai HMAC + rotasi → token tidak bisa di-clone atau brute-force.