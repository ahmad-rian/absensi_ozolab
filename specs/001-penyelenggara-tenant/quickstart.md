# Quickstart Validasi: Penyelenggara & Tenancy (001)

Prasyarat: `composer install`, `npm install`, DB tes SQLite.

1. `php artisan migrate` — muncul tabel `penyelenggaras`, `penyelenggara_invitations`, kolom
   `users.penyelenggara_id`; tidak ada perubahan pada `card_*`/`attendances` (cek `git diff database/`).
2. `php artisan db:seed --class=RolePermissionSeeder` — role ADMIN_PENYELENGGARA & PENDAMPING ada.
3. Tes: `php artisan test --compact --filter=Penyelenggara` — mencakup:
   - CRUD super admin + slug unik (US1)
   - undangan: kirim, terima, kedaluwarsa, dipakai ulang (US1, FR-002)
   - isolasi A↔B → 404 identik; penyelenggara → /admin/siswa ditolak; sekolah → /penyelenggara ditolak;
     tanpa konteks → 0 baris (US2, FR-004..006)
   - switch konteks super admin + saklar fitur tidak ditembus (US3, FR-007..008)
   - nonaktif memblokir; admin terakhir tidak bisa dinonaktifkan (FR-011..012)
4. Regresi: `php artisan test --compact --filter="CardAttendance|CardParticipant|SchoolIsolation|TenantContext|PermissionGate"` hijau tanpa perubahan tes.
5. Manual: login super admin → Admin › Penyelenggara → buat "Al-Hijrah Travel" → undang email →
   buka tautan dari log mail (`storage/logs/laravel.log` bila MAIL_MAILER=log) → buat sandi → mendarat
   di /penyelenggara.
