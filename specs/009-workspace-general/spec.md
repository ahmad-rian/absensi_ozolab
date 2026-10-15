# Spec 009 — Workspace "Absensi General"

**Status**: diimplementasi 2026-10-15 · **Pemilik**: super admin

## Latar
Modul penyelenggara (spec 001–005) masih menumpang di sidebar sekolah (`/admin/penyelenggara`) dan
nama "Kartu Bebas / Haji" masih tampil, padahal haji kini hanya salah satu kategori.

## Keputusan
- Workspace terpisah `/general` khusus super admin dengan layout dan sidebar berkelompok sendiri:
  Ringkasan, Penyelenggara, Pengguna, Kategori & Program, Haji (kartu lama), Desain Kartu,
  Pengaturan, Bantuan.
- **Haji lama = pindah tampilan, data tetap.** Halaman `/kartu-bebas/*` dirender di dalam shell
  general. Baris `card_*`, `card_attendances`, QR, `/p/{kode}`, `/scan-peserta` tidak berubah
  (konstitusi III). Nama permission `kartu-bebas.access` dan rute tidak diganti — hanya label.
- `/admin/penyelenggara*` dialihkan 301 ke `/general/penyelenggara*`.
- Pengaturan general (`settings` berprefiks `general_`): nama & logo, kuota bawaan penyelenggara
  baru, folder Drive kartu, gateway WhatsApp (token Fonnte terenkripsi) + uji kirim. Tidak menyentuh
  `schools.settings`.
- Branding general dipakai di `/general`, `/kartu-bebas`, dan area penyelenggara setelah logo
  general diunggah; sebelum itu tampilan lama tetap.
- Sidebar sekolah: "Kartu Bebas / Haji" + "Penyelenggara" diganti satu entri "Absensi General".
- Tombol "Masuk/Buka" memilih konteks penyelenggara; parameter `kembali` hanya menerima jalur
  `/penyelenggara/...` (anti open-redirect).

## Di luar cakupan
Migrasi data haji lama ke program/sesi (butuh amandemen konstitusi — ditolak user 2026-10-15).

## Tes
`tests/Feature/General/WorkspaceGeneralTest.php`; `KartuBebasTetapTest`, `BrandingTest` tetap lolos.
