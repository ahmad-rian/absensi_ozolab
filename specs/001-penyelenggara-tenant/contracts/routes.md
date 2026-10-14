# Kontrak Rute: Penyelenggara & Tenancy (001)

Semua halaman Inertia; nama rute dipakai lewat Wayfinder.

## Super admin (middleware: auth, verified, super-admin)
| Metode | Path | Nama | Hasil |
|---|---|---|---|
| GET | /admin/penyelenggara | admin.penyelenggara.index | daftar + pencarian |
| POST | /admin/penyelenggara | admin.penyelenggara.store | buat → redirect detail |
| GET | /admin/penyelenggara/{penyelenggara} | admin.penyelenggara.show | detail, tim, undangan |
| PUT | /admin/penyelenggara/{penyelenggara} | admin.penyelenggara.update | ubah atribut/status |
| POST | /admin/penyelenggara/{penyelenggara}/undangan | admin.penyelenggara.invite | undang Admin Penyelenggara |
| POST | /penyelenggara/switch | penyelenggara.switch | set session konteks → redirect dashboard |

## Area penyelenggara (middleware: auth, verified, penyelenggara, SetCurrentPenyelenggara, aktif)
| Metode | Path | Nama | Izin |
|---|---|---|---|
| GET | /penyelenggara | penyelenggara.dashboard | penyelenggara-beranda.access |
| GET | /penyelenggara/tim | penyelenggara.tim.index | penyelenggara-tim.access |
| POST | /penyelenggara/tim/undangan | penyelenggara.tim.invite | penyelenggara-tim.access |
| DELETE | /penyelenggara/tim/undangan/{invitation} | penyelenggara.tim.revoke | penyelenggara-tim.access |
| PUT | /penyelenggara/tim/{user}/status | penyelenggara.tim.status | penyelenggara-tim.access |

Data lintas penyelenggara → 404 (sama dengan tidak ada). Penyelenggara nonaktif → halaman
"Akun penyelenggara nonaktif" (403) untuk non-super-admin.

## Publik (guest)
| Metode | Path | Nama | Hasil |
|---|---|---|---|
| GET | /undangan/{token} | undangan.show | form buat sandi, atau pesan tidak berlaku |
| POST | /undangan/{token} | undangan.accept | buat user + login → penyelenggara.dashboard (throttle 10/menit) |

## Penolakan silang
- User ber-`penyelenggara_id` membuka `/admin/*` (termasuk `admin/switch-school`) → redirect
  `penyelenggara.dashboard`.
- User sekolah/orang tua membuka `/penyelenggara/*` → 403.

## Shared props Inertia (tambahan)
`currentPenyelenggara: {id, name, slug} | null`, `penyelenggaras` (hanya super admin, untuk pemilih),
`penyelenggaraFeatures: Record<string, boolean>`.
