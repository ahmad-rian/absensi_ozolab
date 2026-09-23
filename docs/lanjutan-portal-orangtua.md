# Lanjutan: Portal Orang Tua v2

Status per 22 September 2026. **Pekerjaan ini BELUM di-commit.** Semua perubahan ada di working tree.

Rilis sebelumnya (`2785e9d`) sudah tayang di produksi. Dokumen ini hanya tentang pekerjaan sesudahnya.

---

## Yang sudah dikerjakan

| Permintaan | Keadaan |
|---|---|
| Login jangan khusus admin | **Selesai.** `resources/js/pages/auth/login.tsx` — judul "Selamat datang", tidak ada lagi kata "Panel Admin" di mana pun, placeholder `nama@email.com`, catatan untuk orang tua di bawah form |
| Portal: menu terpisah, tidak klik anak dulu | **Selesai.** Anak jadi konteks `?anak=`, bukan segmen rute |
| Dashboard pakai grafik | **Selesai.** recharts: donat sebaran status + batang pola per hari |
| Tombol tidak kelihatan tombol (kontras) | **Selesai.** `--primary` portal diturunkan dari `oklch(0.6907 …)` ke `oklch(0.52 0.15 242)` |
| Email orang tua terlalu sulit | **Selesai.** Slug nama + `@tyas.app`, ada perintah backfill |
| Ekspor PDF akun orang tua per sekolah | **Selesai.** `/admin/orang-tua/export-pdf` |

**Tes portal: 30/30 lulus** (`php artisan test --filter=PortalOrangTua`).

### Berkas yang berubah / baru

```
app/Http/Controllers/OrangTua/PortalController.php      dipecah: index, absensi, sholat, laporan, galeri, download
app/Http/Middleware/EnsureAnakSendiri.php               baca ?anak=, default anak pertama, 403 kalau bukan anaknya
app/Support/AlamatLoginOrangTua.php                     BARU — slug nama + @tyas.app, unik, fallback nama anak
app/Console/Commands/SlugEmailOrangTua.php              BARU — ortu:email-slug
app/Console/Commands/SetelPasswordOrangTua.php          + konfirmasi/--force sebelum menimpa hash
app/Services/ParentProfileService.php                   pakai AlamatLoginOrangTua
app/Http/Controllers/Admin/OrangTuaController.php       + exportPdf()
resources/views/pdf/akun-orang-tua.blade.php            BARU
routes/web.php                                          rute portal baru + redirect bentuk lama + export-pdf
resources/js/components/orangtua-sidebar.tsx            menu per bagian + pemilih anak
resources/js/components/orangtua/portal-page.tsx        BARU — kerangka + penyaring periode
resources/js/components/orangtua/panel-absensi.tsx      BARU — kartu angka, donat, batang, tabel, lencana
resources/js/pages/orangtua/{beranda,absensi,sholat,laporan,galeri}.tsx
resources/js/pages/orangtua/{index,anak}.tsx            DIHAPUS
resources/js/layouts/orangtua-layout.tsx                warna primer diperbaiki
resources/js/pages/admin/orang-tua/index.tsx            + tombol Unduh Daftar Akun
tests/Feature/PortalOrangTuaTest.php                    30 tes
```

---

## Yang HARUS dicek sebelum commit

1. **Suite penuh: HIJAU.** Dijalankan atas seluruh perubahan di dokumen ini —
   **1070 tes, 1063 lulus, 7 skip, 0 gagal** (4.236 asersi, ±6 menit).
   Acuan sebelum pekerjaan ini 1061 tes; sembilan tambahannya dari
   `PortalOrangTuaTest`.

   Tidak perlu diulang kecuali ada perubahan kode baru. Kalau ada, ulangi:
   ```bash
   php artisan test --compact
   ```

2. **Prettier/ESLint berkas TSX baru.** Repo ini TIDAK prettier-clean secara keseluruhan — jangan jalankan `prettier --write` ke seluruh proyek, diff-nya akan meledak dan mengubur perubahan nyata. Periksa berkas baru saja:
   ```bash
   npx prettier --check resources/js/components/orangtua/*.tsx resources/js/pages/orangtua/*.tsx
   npx eslint resources/js/components/orangtua resources/js/pages/orangtua
   ```

3. **`npx tsc --noEmit`** — sudah bersih untuk berkas portal saat terakhir dicek. 45 error yang tersisa semuanya di 6 berkas lama yang tidak disentuh (`card-layouts/editor.tsx` 24, `album-layouts/index.tsx` 15, sisanya). Jangan dikira regresi.

4. **Sisa prop lama.** Prop `children` diganti `daftarAnak`. Pastikan tidak ada sisa:
   ```bash
   grep -rn "orangtua/index\|orangtua/anak\|props.children" resources/js app tests
   ```

---

## Commit & deploy

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add -A && git commit    # pesan TANPA trailer Co-Authored-By / Claude-Session
git push origin main
```

**Urutan deploy WAJIB seperti ini** — `optimize:clear` sebelum build, bukan sesudah:

```bash
cd /www/wwwroot/tyasphoto.ozolab.id
git pull origin main
/www/server/php/84/bin/php artisan optimize:clear     # WAJIB sebelum build
npm run build                                          # berhenti di sini kalau ada ✗
/www/server/php/84/bin/php artisan optimize
/www/server/php/84/bin/php artisan queue:restart
chown -R www:www /www/wwwroot/tyasphoto.ozolab.id 2>/dev/null; true
```

Alasannya: wayfinder membuat `resources/js/routes/**` dengan membaca tabel rute lewat artisan, dan berkas itu `.gitignore`-kan. Kalau cache rute masih lama, build gagal `UNLOADABLE_DEPENDENCY` pada modul rute baru — persis yang terjadi pada deploy sebelumnya. Rute baru di rilis ini: `orangtua.absensi`, `orangtua.sholat`, `admin.orang-tua.export-pdf`.

Tidak ada migrasi baru di rilis ini.

---

## Setelah tayang: backfill 6.509 email

```bash
# Lihat dulu, tidak mengubah apa pun
/www/server/php/84/bin/php artisan ortu:email-slug --dry-run

# Per sekolah
/www/server/php/84/bin/php artisan ortu:email-slug --sekolah=<id>
```

Hanya menyentuh alamat `@internal.app`. Email sungguhan yang sudah diisi admin tidak pernah ditimpa.

Perhatikan keluaran tabel contoh sebelum/sesudah pada lima baris pertama. Nama yang tidak layak jadi slug (nomor telepon, satu-dua huruf) jatuh ke `wali@tyas.app`, `wali2@tyas.app`, dan seterusnya — **periksa berapa banyak yang begitu**, karena alamat itu tidak membedakan siapa pun:

```bash
/www/server/php/84/bin/php artisan tinker --execute 'echo App\Models\User::where("email","like","wali%@tyas.app")->count();'
```

Kalau jumlahnya besar, pertimbangkan mengubah `AlamatLoginOrangTua::untuk()` supaya memakai nama ANAK lebih dulu ketika nama orang tua tidak layak — kodenya sudah menerima `?Student $anak` sebagai parameter kedua dan perintah backfill sudah mengirimkannya; yang perlu diubah hanya urutan prioritas di baris `$dasar = …`.

---

## Yang belum dikerjakan

1. **Ekspor PDF akun belum punya penyaring kelas di UI.** Endpoint-nya sudah menerima `?classroom_id=`, tombolnya belum mengirimkannya. Untuk sekolah dengan ribuan siswa, satu PDF berisi semuanya terlalu tebal untuk dibagikan per kelas.

2. **Captcha login murni sisi klien.** `resources/js/components/simple-captcha.tsx` membuat dan mencocokkan kode di JavaScript; tidak ada verifikasi apa pun di server — `grep -rn "captcha" app/ config/ routes/` nol hasil. Artinya `POST /login` langsung lewat curl melewatinya sepenuhnya. Yang benar-benar menahan brute force adalah rate limiter Fortify (5/menit per email+IP, `FortifyServiceProvider:109`). Jadi bukan lubang menganga, tapi captcha itu **tidak menambah keamanan apa pun** — ia hanya menyulitkan pengguna sah dan pengujian otomatis. Putuskan: buang, atau pindahkan pencocokannya ke server (simpan kode di session, validasi saat login).

3. **Halaman `/orangtua/sholat` untuk sekolah tanpa fitur sholat** menampilkan pesan kosong, dan menunya memang disembunyikan lewat prop `fiturSholat`. Tapi URL-nya tetap bisa dibuka langsung. Itu disengaja (tidak ada data sensitif), sebutkan saja kalau dianggap mengganggu.

4. **Berkas kredensial di server belum dihapus:** `rm -f ~/.my-backup.cnf` (berisi sandi database dalam teks biasa).

---

## Cara mencoba portal secara lokal

Skrip penyemai data demo ada di scratchpad sesi ini dan akan hilang. Intinya: satu `ParentProfile` dengan dua `Student`, lalu 40 hari `Attendance` + `PrayerAttendance` campur status, dan sekolah dengan `prayer_enabled` + `prayer_dhuha_enabled` bernilai true.

Captcha menghalangi login lewat skrip. Untuk Puppeteer, isi email dan sandi lalu:

```js
await page.evaluate(() => document.querySelector('form').requestSubmit());
```

Captcha hanya mengunci atribut `disabled` pada tombol, tidak ikut di `onSubmit`, jadi `requestSubmit()` lolos. (Ini bukti poin 2 di atas.)
