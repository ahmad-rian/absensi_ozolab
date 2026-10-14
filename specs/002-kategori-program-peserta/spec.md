# Feature Specification: Kategori, Program, Grup, Peserta & Kartu

**Feature Branch**: `002-kategori-program-peserta` (dikerjakan langsung di `main`)

**Created**: 2026-10-14

**Status**: Draft

**Input**: User description: "Admin penyelenggara mengelola Kategori dari 5 template bawaan (Haji/Umroh, Pelatihan, Pengajian, Karyawan, Event) dengan skema data peserta dan saklar fitur; Program per periode; Grup bertingkat; Peserta dengan foto & impor; Kartu QR permanen memakai editor Kartu Bebas; hasil kartu di Drive akun Tyas folder khusus penyelenggara. Data haji lama dan absensi sekolah tidak berubah."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Membuat kategori Haji dari template (Priority: P1)

Admin biro memilih template "Haji/Umroh". Kategori langsung jadi dengan istilah "Jamaah" untuk
peserta, "Rombongan" untuk grup, dan kolom No Porsi, Nama, Kloter, Telepon Keluarga. Admin
menambah kolom "Embarkasi" (pilihan: SOC, SUB, JKG).

**Why this priority**: Kategori adalah titik awal semua data lain; template membuat penyelenggara
siap pakai tanpa bantuan developer (PRD: ≤ 15 menit dari nol).

**Independent Test**: Buat kategori dari tiap template; skema, istilah, dan saklar fitur terisi
sesuai template; kolom tambahan tersimpan.

**Acceptance Scenarios**:

1. **Given** admin penyelenggara di halaman Kategori, **When** memilih template Haji/Umroh dan
   menyimpan, **Then** kategori "Haji/Umroh" muncul dengan istilah Jamaah/Rombongan, kolom bawaan,
   dan saklar terlambat-pulang-izin menyala.
2. **Given** kategori tersimpan, **When** admin menambah kolom pilihan "Embarkasi" dengan 3 opsi,
   **Then** kolom tampil di form peserta dan impor.
3. **Given** kolom inti Nama dan Telepon Wali, **When** admin mencoba menghapusnya, **Then** ditolak
   dengan penjelasan bahwa kolom inti wajib ada.
4. **Given** kategori sudah punya peserta, **When** admin menghapus kolom non-inti, **Then** kolom
   disembunyikan dari form, data lama tetap tersimpan dan bisa dimunculkan lagi.

---

### User Story 2 - Program dan grup bertingkat (Priority: P1)

Admin membuat program "Haji 1448H" (1 Mei–20 Juni 2027) dalam kategori Haji, lalu menyusun grup
Kloter 12 → Rombongan 3 → Regu A.

**Why this priority**: Kehadiran, jadwal, dan laporan selalu dihitung per program dan grup.

**Independent Test**: Program + struktur 3 tingkat tersimpan; program yang lewat tanggal bisa
diarsip dan menjadi baca-saja.

**Acceptance Scenarios**:

1. **Given** kategori Haji, **When** admin membuat program dengan tanggal mulai-selesai, **Then**
   program aktif dengan zona waktu Asia/Jakarta kecuali diubah.
2. **Given** program aktif, **When** admin membuat grup bertingkat 3 level, **Then** pohon grup
   tampil berurutan dan bisa diurutkan ulang.
3. **Given** program diarsip, **When** siapa pun mencoba mengubah peserta/grupnya, **Then** ditolak
   dengan pesan bahwa program sudah diarsip.
4. **Given** program lama, **When** admin memilih "Salin ke program baru", **Then** struktur grup
   ikut tersalin tanpa peserta dan tanpa kehadiran.

---

### User Story 3 - Peserta, foto, dan impor (Priority: P1)

Admin mengimpor 320 jamaah dari Excel, memperbaiki 4 baris yang gagal, lalu menambahkan foto.

**Why this priority**: Tanpa peserta tidak ada kartu dan absensi.

**Independent Test**: Impor file contoh: pratinjau menampilkan baris valid & gagal beserta
alasannya; konfirmasi menyimpan yang valid; kuota ditegakkan.

**Acceptance Scenarios**:

1. **Given** file Excel dengan kolom sesuai skema, **When** diunggah, **Then** pratinjau menampilkan
   jumlah baris valid, gagal, dan alasan per baris (kolom wajib kosong, pilihan tidak dikenal,
   tanggal salah).
2. **Given** nama grup di file sama persis dengan grup yang ada, **When** dikonfirmasi, **Then**
   peserta masuk ke grup itu; nama yang tidak sama persis dilaporkan, tidak ditebak.
3. **Given** kuota penyelenggara 300 dan sudah ada 290 peserta, **When** mengimpor 20 baris valid,
   **Then** impor ditolak dengan pesan sisa kuota 10.
4. **Given** peserta tersimpan, **When** admin mengunggah foto dan menggeser area crop, **Then** foto
   tersimpan dalam rasio kartu.
5. **Given** peserta dipindah dari Rombongan 3 ke Rombongan 4, **When** riwayatnya dibuka, **Then**
   tercatat tanggal, grup asal, grup tujuan, dan siapa yang memindah.

---

### User Story 4 - Kartu peserta dengan QR permanen (Priority: P1)

Admin mendesain kartu program memakai editor kartu yang sudah dikenal, men-generate kartu per
peserta, lalu mengunduh semua kartu (ZIP) untuk dicetak.

**Why this priority**: Kartu ber-QR adalah alat absen di lapangan.

**Independent Test**: Generate kartu satu peserta dua kali; isi QR identik; kartu tersimpan di
folder Drive penyelenggara; ZIP berisi semua kartu selesai.

**Acceptance Scenarios**:

1. **Given** layout program memuat elemen QR, **When** kartu peserta digenerate, **Then** QR berisi
   token absensi peserta, bukan data pribadinya.
2. **Given** kartu sudah dicetak, **When** kartu digenerate ulang karena foto diganti, **Then** QR
   identik dengan sebelumnya.
3. **Given** saklar generate massal global mati, **When** admin mencari tombol generate semua,
   **Then** tombol tidak ada; generate per peserta tetap bisa.
4. **Given** super admin sudah mengatur folder root Drive penyelenggara, **When** kartu selesai,
   **Then** berkas tersimpan di subfolder penyelenggara → program.

---

### User Story 5 - Pendamping hanya melihat grupnya (Priority: P2)

Admin menugaskan Ustadz Hasan ke Rombongan 3 dan 4. Saat login, Ustadz Hasan hanya melihat
jamaah di dua rombongan itu.

**Why this priority**: Privasi dan fokus kerja pendamping; tidak menghalangi uji fitur inti.

**Independent Test**: Pendamping dengan 2 grup hanya melihat peserta dua grup itu (termasuk
sub-grupnya); alamat peserta grup lain → sama dengan tidak ada.

**Acceptance Scenarios**:

1. **Given** pendamping ditugaskan ke Rombongan 3, **When** membuka daftar peserta, **Then** hanya
   jamaah Rombongan 3 dan regu di bawahnya yang tampil.
2. **Given** pendamping tanpa grup, **When** membuka daftar peserta, **Then** daftar kosong dengan
   pesan "Belum ditugaskan ke grup mana pun".

---

### Edge Cases

- Dua template dipakai untuk kategori bernama sama → nama boleh sama, slug dibedakan.
- Impor berisi baris kembar (No Porsi sama) → baris kedua dilaporkan kembar, tidak dibuat dua.
- Peserta dihapus → kartu lamanya tidak lagi bisa dipakai absen; datanya bisa dipulihkan admin.
- Grup induk dihapus padahal punya sub-grup/peserta → ditolak; kosongkan dulu.
- Foto bukan gambar atau > 10 MB → ditolak dengan pesan batas.
- Folder Drive root belum diatur → kartu tetap dibuat dan bisa diunduh dari server; admin melihat
  peringatan "Drive belum diatur" (diselesaikan super admin).
- Program arsip tetap bisa dilihat dan diekspor.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem MUST menyediakan 5 template kategori: Haji/Umroh, Pelatihan, Pengajian,
  Karyawan, Event satu hari — masing-masing dengan istilah peserta/grup, kolom bawaan, dan saklar
  fitur bawaan.
- **FR-002**: Admin penyelenggara MUST bisa membuat, mengubah, dan mengarsip kategori; skema kolom
  mendukung tipe teks, angka, tanggal, pilihan, dan foto, serta urutan dan status wajib.
- **FR-003**: Kolom inti Nama dan Telepon Wali MUST selalu ada di setiap kategori.
- **FR-004**: Menghapus kolom non-inti MUST menyembunyikan kolom tanpa menghapus data peserta.
- **FR-005**: Setiap kategori MUST punya saklar fitur: terlambat, pulang, izin/sakit, notifikasi
  wali, portal wali, RFID.
- **FR-006**: Admin MUST bisa membuat program (nama, tanggal mulai-selesai, zona waktu, status);
  program arsip MUST baca-saja kecuali untuk melihat dan ekspor.
- **FR-007**: Admin MUST bisa menyalin struktur grup program lama ke program baru tanpa peserta dan
  kehadiran.
- **FR-008**: Grup MUST bisa bertingkat tanpa batas praktis (minimal 3 level diuji), punya urutan,
  dan satu peserta MUST boleh berada di lebih dari satu grup dalam program yang sama.
- **FR-009**: Setiap perpindahan/penambahan/penghapusan peserta di grup MUST tercatat (waktu, asal,
  tujuan, pelaku).
- **FR-010**: Admin MUST bisa menugaskan pendamping ke satu atau lebih grup; pendamping MUST hanya
  melihat peserta di grup tugasnya beserta sub-grupnya.
- **FR-011**: Admin MUST bisa menambah, mengubah, menghapus (lunak), dan memulihkan peserta sesuai
  skema kategori, termasuk unggah dan crop foto.
- **FR-012**: Impor Excel/CSV MUST menampilkan pratinjau (valid/gagal + alasan per baris) sebelum
  disimpan, mencocokkan nama grup dan opsi pilihan secara persis tanpa tebakan, dan menghasilkan
  laporan baris gagal yang bisa diunduh.
- **FR-013**: Penambahan peserta (manual atau impor) MUST ditolak bila melampaui kuota peserta
  penyelenggara, dengan pesan sisa kuota.
- **FR-014**: Admin MUST bisa mendesain layout kartu per program dengan editor kartu yang sudah
  ada (elemen teks, foto, QR, bingkai).
- **FR-015**: Setiap peserta MUST punya token QR absensi permanen yang tidak mengandung data
  pribadi dan tidak berubah saat kartu digenerate ulang.
- **FR-016**: Admin MUST bisa generate kartu per peserta, melihat status (antre, proses, selesai,
  gagal), dan mengunduh semua kartu selesai sebagai ZIP; generate massal MUST tetap tunduk pada
  saklar global.
- **FR-017**: Kartu selesai MUST tersimpan di Google Drive akun Tyas pada folder root penyelenggara
  yang diatur super admin, dengan subfolder per penyelenggara lalu per program.
- **FR-018**: Fitur ini MUST NOT mengubah data Kartu Bebas/haji yang sudah ada maupun absensi
  sekolah.

### Key Entities

- **Kategori**: jenis kegiatan milik penyelenggara; istilah, skema kolom, saklar fitur, template asal.
- **Program**: periode berjalan dalam kategori; tanggal, zona waktu, status.
- **Grup**: pengelompokan bertingkat dalam program; induk, urutan, pendamping yang ditugaskan.
- **Peserta**: orang yang diabsen dalam satu program; data sesuai skema, foto, token QR, status.
- **Riwayat Grup Peserta**: catatan masuk/pindah/keluar grup.
- **Layout Kartu**: desain kartu program.
- **Kartu Peserta**: hasil render per peserta dengan status dan lokasi berkas.
- **Impor Peserta**: satu sesi impor dengan ringkasan dan daftar baris gagal.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Penyelenggara baru bisa membuat kategori dari template, program, 3 grup, dan
  mengimpor 300 peserta dalam kurang dari 15 menit tanpa bantuan developer.
- **SC-002**: Impor 1.000 baris menampilkan pratinjau dalam kurang dari 30 detik.
- **SC-003**: 100% kartu yang digenerate ulang memiliki QR identik dengan kartu sebelumnya.
- **SC-004**: Pendamping tidak pernah melihat peserta di luar grup tugasnya (0 kebocoran di uji).
- **SC-005**: 0 perubahan pada data Kartu Bebas/haji lama dan 0 regresi uji absensi sekolah.

## Assumptions

- Peserta milik tepat satu program; peserta yang sama di program lain dicatat sebagai peserta baru.
- Zona waktu program disimpan sekarang; pemakaiannya di jadwal & scan (spec 003–004).
- Generate kartu memakai mesin render dan antrean kartu yang sudah ada.
- Folder root Drive = "Tyas General Apps" (Drive Saya › OTHER, akun OAuth Tyas yang sama dengan root
  "Tyas Absensi APPS"), diisi super admin lewat pengaturan; belum diatur = berkas tetap di server
  sampai Drive diatur.
- Batas foto 10 MB, format JPG/PNG/WebP, disimpan sebagai JPEG kualitas tinggi seperti foto siswa.
- Template bisa diperluas super admin di masa depan; daftar 5 template ini tetap untuk rilis awal.
