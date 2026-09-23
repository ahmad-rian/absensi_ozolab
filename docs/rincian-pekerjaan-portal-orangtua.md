# Rincian Pekerjaan — Portal Orang Tua & Pembenahan Hak Akses

Aplikasi: **tyasphoto.ozolab.id** (Absensi Ozolab / Tyas Photo)
Periode: **22 September 2026**

Dibagi dua tahap. Tahap 1 sudah tayang di produksi (`2785e9d`). Tahap 2 selesai dikerjakan dan lulus pengujian, menunggu jadwal deploy.

---

## TAHAP 1 — Portal Orang Tua & Hak Akses (sudah tayang)

### 1. Portal Orang Tua (fitur baru)

Ruang kerja terpisah untuk orang tua siswa, di luar panel admin: alamat, tampilan, menu, dan warna sendiri.

- Halaman ringkasan anak: identitas, kelas, pas foto, kehadiran hari ini
- Rincian absensi sekolah, Sholat Dhuha, dan Sholat Dzuhur dengan penyaring periode
- Unduh laporan PDF per jenis (absensi / Dhuha / Dzuhur) atau gabungan ketiganya
- Galeri pas foto dan kartu OSIS, bisa dilihat dan diunduh
- Panel sholat otomatis menyesuaikan: sekolah yang tidak memakai fitur sholat tidak melihat menunya

**Pengamanan data anak.** Satu lapis pemeriksaan kepemilikan yang berlaku untuk semua halaman dan semua berkas unduhan — orang tua tidak bisa membuka data anak keluarga lain dengan menukar alamat di peramban. Diuji khusus untuk setiap titik akses.

### 2. Sistem masuk untuk orang tua

- Role `ORANG_TUA` diberi akses; sebelumnya role-nya ada tapi tidak punya izin apa pun sehingga login selalu berakhir di layar 403
- Pengalihan otomatis sesuai peran: orang tua ke portalnya, staf ke panel admin
- Perintah penyetelan sandi awal massal, per sekolah, dengan mode simulasi dan konfirmasi sebelum eksekusi (menolak jalan tanpa persetujuan eksplisit karena sandi lama tidak bisa dikembalikan)
- Akun admin, guru, dan superadmin dikecualikan — termasuk akun yang kebetulan juga memegang peran orang tua

### 3. Wajib ganti sandi saat login pertama

- Kolom penanda baru pada data pengguna
- Halaman ganti sandi khusus, tanpa jalan keluar selama penandanya aktif
- Penjagaan berlaku di seluruh aplikasi — panel admin, portal orang tua, halaman setelan, dan ruang kerja Kartu Bebas
- Sandi baru tunduk pada aturan kekuatan sandi produksi dan tidak boleh sama dengan sandi awal

### 4. Hak akses berbahasa Indonesia

- 16 nama izin diterjemahkan (`card-generation.access` → `generate-kartu.access`, `users.access` → `pengguna.access`, dan seterusnya), akhiran `.access` dipertahankan
- Migrasi dilakukan dengan mengganti nama, bukan hapus-lalu-buat — sehingga role khusus buatan admin dan izin tambahan per pengguna tidak hilang. Ini titik paling berisiko di seluruh pekerjaan dan diuji tersendiri
- Alamat URL panel admin sengaja tidak ikut berubah agar tautan lama tetap hidup

### 5. Laporan admin

- Jenis laporan baru: Absensi Sekolah, Sholat Dhuha, Sholat Dzuhur, atau semuanya
- Ekspor Excel satu berkas **banyak sheet** (Ringkasan, Absensi, Dhuha, Dzuhur); sheet sholat hanya dibuat bila fiturnya aktif
- Penanganan nama sheet: batas 31 karakter, karakter terlarang ditolak, nama ganda dicegah

### 6. Perancangan ulang berkas PDF

Seluruh laporan PDF dirancang ulang dengan satu berkas gaya bersama: kop rata kiri, ringkasan berupa angka besar berlabel, tabel tanpa garis vertikal dengan baris berselang, nomor halaman, dan satu warna aksen. Diterapkan ke laporan per siswa, laporan admin, dan laporan gabungan yang baru.

### 7. Jadwal sholat dipindah

Pengaturan jam sholat pindah dari halaman Pengaturan ke halaman Jadwal Absensi, muncul hanya bila fiturnya aktif. Tab Sholat di Pengaturan tinggal berisi sakelar aktif/tidak dan cakupan peserta. Validasi jendela waktu yang tumpang-tindih dipertahankan dan dipakai bersama oleh kedua halaman.

### 8. Generate kartu mengikuti sekolah aktif

Halaman generate kartu massal tidak lagi punya pemilih sekolah sendiri; ia mengikuti sekolah yang dipilih di sidebar. Menghapus dua pemilih yang saling tidak tahu — sumber kebingungan dan penyebab galat 404 lintas sekolah sebelumnya.

### 9. Impor email orang tua

Berkas impor siswa menerima kolom email orang tua (`email ortu`, `email orangtua`, `email wali`), sehingga alamat login bisa diisi massal alih-alih satu per satu.

---

## TAHAP 2 — Penyempurnaan Portal & Identitas Login (selesai, belum deploy)

### 10. Perancangan ulang halaman masuk

Halaman masuk sebelumnya menyatakan "khusus admin" di tiga tempat, padahal orang tua kini memakai pintu yang sama. Judul, keterangan, dan catatan kaki diganti menjadi netral; ditambah petunjuk khusus orang tua tentang alamat mana yang dipakai dan ke siapa menghubungi bila belum menerima akun. Blok verifikasi captcha dibingkai agar terbaca sebagai satu langkah.

### 11. Restrukturisasi navigasi portal

Sebelumnya setiap menu berada di bawah halaman anak, sehingga orang tua harus membuka anaknya dulu sebelum bisa melihat apa pun.

- Menu berdiri sendiri: Beranda, Absensi Sekolah, Absen Sholat, Laporan, Foto & Kartu
- Anak menjadi konteks yang dipilih sekali dan terbawa antar menu
- Pemilih anak hanya muncul untuk keluarga dengan lebih dari satu anak
- Tautan lama yang sudah beredar tetap hidup, dialihkan ke bentuk baru

### 12. Dashboard dengan grafik

- Kartu angka: persentase kehadiran, hadir, terlambat, alpa
- Grafik donat sebaran status
- Grafik batang pola per hari — menunjukkan hari mana anak paling sering terlambat
- Status hari ini ditampilkan paling atas
- Warna status konsisten di seluruh kartu, grafik, dan tabel; status ditandai warna **dan** kata agar tetap terbaca saat dicetak atau oleh pengguna buta warna

### 13. Perbaikan keterbacaan antarmuka

Warna utama portal terlalu terang untuk teks putih di atasnya sehingga tombol terbaca pucat dan tidak dikenali sebagai tombol. Palet diturunkan hingga melewati ambang kontras WCAG AA, dan seluruh tombol portal dirapikan. Seluruh halaman portal dipastikan responsif dari ponsel sampai layar lebar.

### 14. Alamat login yang bisa diucapkan

Alamat lama berbentuk `parent-01M1WFWXKFMEEKWKZ0AXPF4YK7@internal.app` — 26 karakter acak yang mustahil didiktekan lewat telepon.

- Penghasil alamat baru berbasis slug nama + domain pendek (`ibu-rahma@tyas.app`)
- Penanganan nama ganda dengan akhiran angka
- Nama yang tidak layak jadi alamat (nomor telepon, satu-dua huruf, tanda baca) dialihkan ke nama anak
- Perintah pembaruan massal dengan mode simulasi; email sungguhan yang sudah diisi admin tidak pernah ditimpa

### 15. Ekspor daftar akun orang tua (PDF)

Untuk mempermudah sekolah membagikan akun.

- Unduhan PDF per sekolah atau **per kelas**
- Berisi nama anak, kelas, NIS, nama orang tua, alamat login, dan nomor WhatsApp
- Menandai akun yang alamatnya belum diperbarui
- Sandi tidak dicetak di lembar ini

---

## Pengujian & mutu

- **1.070 pengujian otomatis, 1.063 lulus, 7 dilewati, 0 gagal** (4.236 asersi)
- Berkas pengujian khusus untuk pekerjaan ini mencakup: kepemilikan data anak di setiap titik akses, penjagaan wajib ganti sandi, keamanan perintah sandi massal, keutuhan hak akses saat migrasi nama izin, penghasil alamat login, isi ekspor PDF, dan pembatasan data antar sekolah
- Pemeriksaan tipe TypeScript dan pemformat kode PHP bersih untuk seluruh berkas yang disentuh

## Dokumentasi

- `docs/rencana-portal-orang-tua.md` — rancangan lengkap beserta peta kode
- `docs/lanjutan-portal-orangtua.md` — panduan lanjutan, urutan deploy, dan catatan risiko

---

## Catatan untuk pelanggan

**Portal orang tua baru dapat digunakan setelah alamat login terisi.** Saat ini 6.509 akun masih memakai alamat bawaan sistem. Perintah pembaruan massal sudah disediakan dan berjalan per sekolah.

**Sandi awal seragam adalah keputusan yang diambil sadar.** Risikonya ditutup oleh kewajiban ganti sandi pada login pertama; keduanya dirancang untuk tayang bersamaan.
