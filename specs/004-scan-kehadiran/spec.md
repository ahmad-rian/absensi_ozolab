# Feature Specification: Scan & Pencatatan Kehadiran

**Feature Branch**: `004-scan-kehadiran` (dikerjakan langsung di `main`)

**Created**: 2026-10-14

**Status**: Draft

**Input**: User description: "Link scan per program/grup (kamera, scanner gun, link ringan STB/HP lama); status otomatis hadir/terlambat dan datang/pulang dari jadwal; input manual izin/sakit/alpa oleh admin/pendamping dengan jejak audit; sapuan otomatis menandai alpa setelah jendela sesi tutup. Kartu Bebas/haji lama dan /scan sekolah tidak berubah."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Petugas men-scan kartu di pintu (Priority: P1)

Petugas membuka link scan program di HP. Jamaah menunjukkan kartu; layar langsung menampilkan
nama, foto kecil, sesi aktif, dan status "Hadir" atau "Terlambat".

**Why this priority**: Ini pekerjaan utama modul di lapangan.

**Independent Test**: Program dengan satu sesi aktif; scan token valid → kehadiran tercatat dengan
status sesuai jam; scan kedua dalam jendela yang sama → "sudah tercatat", tidak dobel.

**Acceptance Scenarios**:

1. **Given** sesi "Manasik Pagi" datang 08.00–09.00, terlambat setelah 08.15, **When** kartu
   di-scan 08.10, **Then** tercatat Hadir dan layar menampilkan nama + "Hadir · Manasik Pagi".
2. **Given** hal yang sama, **When** di-scan 08.40, **Then** tercatat Terlambat.
3. **Given** sudah tercatat datang, **When** di-scan lagi dalam jendela datang, **Then** layar
   menampilkan "Sudah tercatat 08.10" dan data tidak berubah.
4. **Given** jendela pulang 11.00–12.00 dan saklar pulang menyala, **When** di-scan 11.20, **Then**
   jam pulang tercatat pada kehadiran yang sama.
5. **Given** tidak ada sesi yang berlaku saat itu, **When** di-scan, **Then** layar menampilkan
   "Tidak ada sesi sekarang · berikutnya Manasik Siang 13.00" dan tidak ada yang tercatat.

---

### User Story 2 - Scan di perangkat lemah dan scanner gun (Priority: P1)

Di aula, petugas memakai TV box dan scanner gun; di bus, petugas memakai HP lama.

**Why this priority**: Perangkat lapangan biro haji sering lemah; pola ini sudah terbukti untuk
sekolah dan Kartu Bebas.

**Independent Test**: Link ringan tanpa kamera menerima ketikan gun + Enter dan menampilkan hasil
dalam satu layar; respons scan tidak memuat foto besar.

**Acceptance Scenarios**:

1. **Given** link ringan program, **When** scanner gun mengirim token + Enter, **Then** hasil tampil
   tanpa memuat ulang halaman.
2. **Given** token terbaca dengan huruf besar/kecil berbeda, **When** di-scan, **Then** tetap
   dikenali.
3. **Given** link scan diganti admin, **When** link lama dibuka, **Then** tampil pesan link tidak
   berlaku; kartu peserta tetap berlaku untuk link baru.

---

### User Story 3 - Izin, sakit, alpa manual (Priority: P1)

Pendamping mencatat Bu Siti "Sakit" untuk sesi Manasik Pagi dengan catatan "demam, di hotel".

**Why this priority**: Status lengkap adalah kesenjangan utama vs absensi sekolah (PRD M7.3).

**Independent Test**: Status manual tersimpan dengan pelaku & waktu; scan berikutnya untuk sesi
itu tidak menimpa status manual.

**Acceptance Scenarios**:

1. **Given** pendamping bertugas di rombongan Bu Siti, **When** mencatat Sakit + catatan, **Then**
   kehadiran sesi itu berstatus Sakit, dan log menyimpan siapa, kapan, nilai lama, nilai baru.
2. **Given** status manual Sakit, **When** kartu Bu Siti di-scan pada sesi itu, **Then** layar
   menampilkan "Sudah dicatat Sakit oleh pendamping" dan status tidak berubah.
3. **Given** pendamping bukan pendamping rombongan Bu Siti, **When** mencoba mengubah statusnya,
   **Then** ditolak seperti data tidak ada.
4. **Given** admin mengubah Alpa menjadi Izin, **When** riwayat dibuka, **Then** kedua perubahan
   terlihat berurutan.

---

### User Story 4 - Alpa otomatis (Priority: P2)

Satu jam setelah jendela datang sesi tutup, peserta yang belum tercatat apa pun otomatis Alpa.

**Acceptance Scenarios**:

1. **Given** jendela datang 08.00–09.00, **When** sapuan berjalan setelah 09.00, **Then** peserta
   target sesi tanpa catatan menjadi Alpa (sumber: otomatis).
2. **Given** hari libur atau peserta sudah berstatus Izin/Sakit, **When** sapuan berjalan, **Then**
   tidak ada perubahan.
3. **Given** sapuan dijalankan dua kali, **When** dicek, **Then** tidak ada catatan ganda.
4. **Given** peserta Alpa otomatis lalu datang terlambat dan di-scan masih dalam jendela pulang,
   **Then** status tetap Alpa dan layar memberi tahu petugas untuk menghubungi admin.

---

### Edge Cases

- Kartu peserta yang dihapus/program arsip → ditolak dengan pesan jelas, tidak tercatat.
- Token peserta program lain milik penyelenggara yang sama → ditolak ("bukan peserta program ini").
- Token kartu Kartu Bebas/haji lama di link program baru → ditolak; link Kartu Bebas lama tetap
  bekerja seperti sekarang.
- Dua scan bersamaan untuk peserta yang sama → satu catatan.
- Jam perangkat petugas salah → jam server (zona program) yang dipakai.
- Scan sangat ramai (50/menit per program) → tetap responsif.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Setiap program MUST punya link scan bertoken (dan opsional per grup) yang bisa
  diganti admin tanpa membatalkan kartu peserta; tersedia versi kamera dan versi ringan.
- **FR-002**: Scan MUST menentukan sesi berlaku, datang vs pulang, dan status Hadir/Terlambat dari
  jadwal di server menurut zona waktu program; perangkat petugas tidak menentukan status.
- **FR-003**: Satu peserta MUST punya paling banyak satu catatan kehadiran per sesi; scan ulang
  MUST idempoten.
- **FR-004**: Scan MUST NOT menimpa status yang diisi manual (Izin, Sakit, Alpa oleh petugas).
- **FR-005**: Admin dan pendamping (hanya grup tugasnya) MUST bisa mengisi/mengubah status Hadir,
  Terlambat, Izin, Sakit, Alpa dengan catatan; setiap perubahan MUST tercatat (pelaku, waktu,
  nilai lama, nilai baru).
- **FR-006**: Sistem MUST otomatis menandai Alpa untuk peserta target sesi tanpa catatan setelah
  jendela datang tutup, kecuali hari libur; berulang aman tanpa duplikat.
- **FR-007**: Respons scan MUST menampilkan nama, sesi, status, dan pesan yang menjelaskan alasan
  bila ditolak; tanpa data pribadi lain.
- **FR-008**: Token MUST dikenali tanpa memedulikan huruf besar/kecil dan spasi tepi.
- **FR-009**: Link Kartu Bebas/haji lama, kartu haji tercetak, dan link scan sekolah MUST tetap
  bekerja tanpa perubahan perilaku.
- **FR-010**: Halaman Kehadiran admin MUST menampilkan per sesi: daftar peserta target, status,
  jam datang/pulang, sumber (scan/manual/otomatis), dengan filter grup dan status.

### Key Entities

- **Kehadiran**: satu peserta pada satu sesi; status, jam datang, jam pulang, sumber, catatan,
  pencatat.
- **Log Perubahan Kehadiran**: riwayat perubahan status manual.
- **Link Scan**: token program/grup untuk petugas.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 95% scan menampilkan hasil di layar petugas dalam waktu kurang dari 1 detik.
- **SC-002**: 0 catatan ganda per peserta per sesi pada uji 100 scan paralel.
- **SC-003**: 100% kartu haji lama dan link Kartu Bebas lama tetap terbaca setelah rilis.
- **SC-004**: Pendamping mencatat izin/sakit satu peserta dalam kurang dari 20 detik.
- **SC-005**: Sapuan alpa selesai paling lambat 1 jam 15 menit setelah jendela datang tutup.

## Assumptions

- Format token kartu baru mengikuti pola token Kartu Bebas yang sudah teruji, tetapi tidak
  diterima lintas modul.
- Sapuan berjalan berkala tiap jam (pola sapuan sekolah).
- Kartu lama (QR berisi data) tidak didukung untuk program baru; semua kartu program baru memakai
  token.
- RFID di luar lingkup (spec 008).
