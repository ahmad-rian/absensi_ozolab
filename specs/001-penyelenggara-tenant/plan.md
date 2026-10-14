# Implementation Plan: Penyelenggara & Tenancy

**Branch**: `001-penyelenggara-tenant` (langsung di `main`) | **Date**: 2026-10-14 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-penyelenggara-tenant/spec.md`

## Summary

Menambah tenant baru **Penyelenggara** yang terpisah dari sekolah: tabel + CRUD super admin,
undangan email, dua peran baru, scope data fail-closed lewat trait `BelongsToPenyelenggara`,
area aplikasi `/penyelenggara` dengan layout/sidebar sendiri, pemilih konteks super admin, saklar
fitur per penyelenggara, dan penutupan akses role penyelenggara ke grup rute `/admin` sekolah.
Detail keputusan di [research.md](./research.md).

## Technical Context

**Language/Version**: PHP 8.4, TypeScript (React 19)

**Primary Dependencies**: Laravel 13, Inertia v3, Fortify, spatie/laravel-permission, spatie/laravel-activitylog, Wayfinder, Tailwind v4 (tanpa dependency baru)

**Storage**: MySQL (prod), SQLite (tes)

**Testing**: Pest 4 feature tests; node test runner untuk `tests/Unit/*.test.mjs`

**Target Platform**: VPS Linux (aaPanel), PHP-FPM + supervisor queue

**Project Type**: Web application monolit (Laravel + Inertia)

**Performance Goals**: Scope tenant menambah ≤ 1 kondisi `where` per query; halaman area penyelenggara < 300 ms p95 di data uji

**Constraints**: Tidak menyentuh tabel `card_*`, `attendances`, `schools`; tidak mengubah perilaku login/redirect user lama

**Scale/Scope**: puluhan penyelenggara, ≤ 20 akun per penyelenggara; 6 halaman baru

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Prinsip | Status | Catatan |
|---|---|---|
| I. Konvensi Laravel Boost | ✅ | Model/migrasi/middleware/controller via `php artisan make:*`; Pest per user story |
| II. Tenancy fail-closed | ✅ | `BelongsToPenyelenggara` → `whereRaw('1 = 0')` tanpa konteks; 404 seragam; guard `bukan-penyelenggara` di `/admin` |
| III. Data haji tidak berubah | ✅ | Tidak ada migrasi/perintah pada `card_*` |
| IV. Perubahan data aditif | ✅ | Hanya tabel baru + kolom nullable `users.penyelenggara_id` |
| V. Absensi sekolah tidak disentuh | ✅ | Satu middleware tambahan di grup `/admin` yang no-op untuk user tanpa `penyelenggara_id`; dibuktikan tes regresi |
| VI. Batas operasional | ✅ | Tidak ada render/antrean berat; email undangan lewat antrean `default` |
| VII. Bahasa pengguna | ✅ | Semua copy Indonesia, pesan undangan tidak berlaku menyebut cara minta ulang |

Re-check pasca desain: ✅ (tidak ada pelanggaran; Complexity Tracking kosong).

## Project Structure

### Documentation (this feature)

```text
specs/001-penyelenggara-tenant/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/routes.md
└── tasks.md            # /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Enums/UserRole.php, AppModule.php, PenyelenggaraFeature.php (baru)
├── Models/Penyelenggara.php, PenyelenggaraInvitation.php (baru), User.php (penyelenggara_id, homeRoute)
├── Models/Concerns/BelongsToPenyelenggara.php (baru)
├── Http/Middleware/SetCurrentPenyelenggara.php, EnsurePenyelenggaraUser.php,
│   EnsureBukanPenyelenggara.php, EnsurePenyelenggaraFeatureEnabled.php (baru)
├── Http/Controllers/Admin/PenyelenggaraController.php (baru)
├── Http/Controllers/Penyelenggara/{DashboardController,TimController,SwitchController}.php (baru)
├── Http/Controllers/UndanganController.php (baru)
├── Notifications/UndanganPenyelenggara.php (baru)
└── Support/PenyelenggaraFeatures.php (baru)
bootstrap/app.php            # alias + priority list
routes/web.php               # grup /admin/penyelenggara, /penyelenggara, /undangan
database/migrations/         # 3 migrasi baru
database/factories/          # PenyelenggaraFactory, PenyelenggaraInvitationFactory
database/seeders/RolePermissionSeeder.php
resources/js/layouts/penyelenggara-layout.tsx, components/penyelenggara-sidebar.tsx (baru)
resources/js/pages/admin/penyelenggara/{index,show}.tsx, pages/penyelenggara/{dashboard,tim}.tsx,
resources/js/pages/undangan/show.tsx (baru)
tests/Feature/Penyelenggara/*.php (baru)
```

**Structure Decision**: Monolit yang ada; kode baru mengikuti pola tetangga (`Admin/*` untuk
super admin, folder fitur `Penyelenggara/*` meniru `KartuBebas/*`, layout & sidebar meniru
`kartu-bebas-layout.tsx`/`kartu-bebas-sidebar.tsx`).

## Complexity Tracking

Tidak ada pelanggaran konstitusi.
