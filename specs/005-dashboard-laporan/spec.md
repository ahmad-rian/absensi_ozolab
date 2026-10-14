# Feature Specification: Dashboard & Laporan Kehadiran

**Feature Branch**: `005-dashboard-laporan` (dikerjakan langsung di `main`)

**Created**: 2026-10-14

**Status**: Draft

**Input**: User description: "Dashboard program: hadir hari ini per sesi & grup, belum hadir, terlambat, peringkat tercepat hadir, tren 1/2/3 bulan. Laporan XLSX (sheet per grup) dan PDF per grup, per sesi, per peserta dengan rentang tanggal."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Pantauan hari ini (Priority: P1)

Pukul 08.20 ketua biro membuka dashboard program: Manasik Pagi 287/320 hadir, 12 terlambat,
21 belum hadir — daftar yang belum hadir bisa dibuka per rombongan.

**Why this priority**: Keputusan lapangan (menjemput jamaah yang tertinggal) bergantung pada ini.

**Independent Test**: Data uji kehadiran hari ini → angka per sesi dan per grup cocok dengan hitung
manual; daftar belum hadir hanya berisi peserta target tanpa catatan.

**Acceptance Scenarios**:

1. **Given** sesi berjalan, **When** dashboard dibuka, **Then** tampil per sesi: target, hadir,
   terlambat, izin/sakit, alpa, belum hadir.
2. **Given** filter Rombongan 3, **When** diterapkan, **Then** angka dan daftar hanya untuk grup itu
   dan sub-grupnya.
3. **Given** pendamping membuka dashboard, **When** halaman tampil, **Then** hanya grup tugasnya.
4. **Given** data masih dimuat, **When** halaman dibuka, **Then** kerangka (skeleton) tampil, bukan
   halaman kosong.

---

### User Story 2 - Laporan Excel per grup (Priority: P1)

Admin mengunduh laporan program 1–20 Juni: satu sheet per rombongan, baris per jamaah, kolom per
sesi berisi status, plus rekap jumlah.

**Why this priority**: Laporan ke klien (Kemenag, perusahaan) adalah keluaran yang ditagih.

**Independent Test**: Unduh XLSX; jumlah sheet = jumlah grup terpilih; total per status cocok
dengan dashboard.

**Acceptance Scenarios**:

1. **Given** rentang tanggal dan grup dipilih, **When** unduh XLSX, **Then** satu sheet per grup,
   nama sheet aman (≤ 31 karakter, unik), kolom identitas mengikuti skema kategori.
2. **Given** pilihan PDF, **When** unduh, **Then** PDF A4 lanskap per grup dengan kop penyelenggara.
3. **Given** laporan per peserta, **When** dipilih satu jamaah, **Then** riwayat semua sesinya
   beserta jam dan sumber pencatatan.
4. **Given** laporan per sesi, **When** dipilih satu sesi, **Then** daftar peserta target dengan
   status dan jam.

---

### User Story 3 - Peringkat & tren (Priority: P3)

Panitia pelatihan melihat 10 peserta tercepat hadir dan tren persentase kehadiran 1/2/3 bulan.

**Acceptance Scenarios**:

1. **Given** kehadiran beberapa minggu, **When** panel peringkat dibuka, **Then** urutan berdasarkan
   rata-rata jam datang dengan jumlah hari dihitung.
2. **Given** pilihan 1/2/3 bulan, **When** diganti, **Then** grafik tren mengikuti rentang.

---

### Edge Cases

- Program tanpa sesi → dashboard menjelaskan jadwal belum dibuat dan menaut ke halaman jadwal.
- Rentang laporan > 1 tahun → ditolak dengan saran mempersempit.
- Grup tanpa peserta → sheet tidak dibuat; dicatat di rekap.
- Nama grup kembar setelah dipotong 31 karakter → diberi akhiran angka.
- Program arsip → laporan tetap bisa diunduh.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Dashboard program MUST menampilkan per sesi hari ini: target, Hadir, Terlambat, Izin,
  Sakit, Alpa, Belum hadir; bisa difilter per grup (termasuk sub-grup).
- **FR-002**: Daftar "belum hadir" MUST bisa dibuka per sesi dan per grup.
- **FR-003**: Pendamping MUST hanya melihat angka dan daftar untuk grup tugasnya.
- **FR-004**: Laporan XLSX MUST mendukung mode per grup (sheet per grup), per sesi, dan per peserta,
  dengan rentang tanggal; PDF MUST tersedia untuk mode per grup dan per peserta.
- **FR-005**: Angka laporan MUST konsisten dengan dashboard untuk rentang yang sama.
- **FR-006**: Panel peringkat tercepat hadir dan tren 1/2/3 bulan MUST tersedia bila program
  berjalan ≥ 7 hari.
- **FR-007**: Laporan dan dashboard MUST hanya membaca data kehadiran modul baru; laporan sekolah
  dan Kartu Bebas MUST tidak berubah.

### Key Entities

- **Ringkasan Sesi**: hitungan status per sesi/grup (turunan, tidak disimpan permanen).
- **Ekspor Laporan**: permintaan unduh dengan mode, rentang, grup.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Dashboard program 500 peserta × 3 sesi tampil dalam kurang dari 2 detik.
- **SC-002**: Laporan XLSX 30 hari × 500 peserta terunduh dalam kurang dari 30 detik.
- **SC-003**: 100% angka laporan cocok dengan dashboard pada uji data yang sama.
- **SC-004**: Admin menemukan daftar jamaah yang belum hadir di satu rombongan dalam ≤ 3 klik.

## Assumptions

- Dashboard diperbarui saat halaman dibuka/di-refresh; tidak real-time push di rilis awal.
- Kop PDF memakai nama penyelenggara (logo menyusul).
- Peringkat & tren boleh dipangkas bila waktu 5 hari tidak cukup (P3).
