# Data Model: Penyelenggara & Tenancy (001)

Semua tabel baru ber-ULID. Tidak ada tabel `card_*`, `attendances`, atau `schools` yang diubah.

## penyelenggaras (baru)
| Kolom | Tipe | Aturan |
|---|---|---|
| id | ulid PK | |
| name | string(150) | wajib |
| slug | string(160) unique | dari name; akhiran `-2`, `-3` bila bentrok |
| is_active | boolean default true | nonaktif = semua akun diblokir (FR-011) |
| participant_quota | unsigned int nullable | null = tanpa batas |
| wa_daily_quota | unsigned int default 50 | |
| settings | json nullable | saklar fitur `feature_<key>` |
| timestamps, softDeletes | | |

## users (kolom baru)
| Kolom | Tipe | Aturan |
|---|---|---|
| penyelenggara_id | ulid nullable, index, FK → penyelenggaras | terisi ⇒ `school_id` harus null |

Invarian: user penyelenggara punya tepat satu role dari {ADMIN_PENYELENGGARA, PENDAMPING} dan
`school_id = null`. Penyelenggara aktif harus punya ≥ 1 ADMIN_PENYELENGGARA aktif (FR-012).

## penyelenggara_invitations (baru, BelongsToPenyelenggara)
| Kolom | Tipe | Aturan |
|---|---|---|
| id | ulid PK | |
| penyelenggara_id | ulid FK | scope |
| email | string | unik di antara undangan "menunggu" per penyelenggara; ditolak bila email sudah dipakai user lain |
| role | string | ADMIN_PENYELENGGARA atau PENDAMPING |
| token_hash | string(64) unique | SHA-256 token mentah (mentah hanya di email) |
| invited_by | ulid FK users | |
| expires_at | timestamp | +7 hari |
| accepted_at, revoked_at | timestamp nullable | |

Status turunan: dipakai (`accepted_at`), dibatalkan (`revoked_at`), kedaluwarsa (`expires_at < now`),
selain itu menunggu.

## Enum & konstanta
- `UserRole`: + `AdminPenyelenggara = 'ADMIN_PENYELENGGARA'`, `Pendamping = 'PENDAMPING'`.
- `AppModule`: + `PenyelenggaraBeranda`, `PenyelenggaraTim`, `PenyelenggaraHakAkses` (grup "Penyelenggara").
- `PenyelenggaraFeature`: `notifikasi_wali` (contoh; diperluas di spec berikutnya).

## Log aktivitas (FR-014)
`Penyelenggara` dan `PenyelenggaraInvitation` memakai `LogsActivity` (Spatie, sudah dipakai `User`).
