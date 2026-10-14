# Feature Specification: Jadwal & Sesi

**Feature Branch**: `003-jadwal-sesi` (dikerjakan langsung di `main`)

**Created**: 2026-10-14

**Status**: Draft

**Input**: User description: "Sesi berulang (harian/mingguan) atau sekali, per program atau per grup; jendela datang, batas terlambat, jendela pulang; banyak sesi bernama per hari (manasik, ziarah, sholat); hari libur per program."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Jadwal harian berulang (Priority: P1)

Admin pelatihan membuat sesi "Kelas Pagi" Senin–Jumat: datang 07.00–08.30, terlambat setelah
07.30, pulang 15.00–17.00, berlaku sepanjang program.

**Why this priority**: Tanpa sesi, scan tidak tahu status hadir/terlambat (inti PRD M6).

**Independent Test**: Simpan pola; daftar sesi per tanggal muncul untuk setiap hari kerja dalam
rentang program dan tidak muncul pada Sabtu/Minggu.

**Acceptance Scenarios**:

1. **Given** program 1–30 Juni, **When** admin membuat pola Senin–Jumat, **Then** sesi muncul di
   kalender pada 21 hari kerja tersebut dengan jendela waktu yang benar.
2. **Given** jendela datang 07.00–08.30 dan batas terlambat 07.30, **When** admin menyimpan, **Then**
   sistem menerima; bila batas terlambat di luar jendela datang, ditolak dengan penjelasan.
3. **Given** saklar "pulang" kategori mati, **When** admin membuat sesi, **Then** isian jendela
   pulang tidak ditampilkan dan tidak wajib.

---

### User Story 2 - Banyak sesi bernama dalam sehari (Priority: P1)

Biro haji membuat 3 sesi pada hari manasik: "Manasik Pagi" 08.00–11.00, "Sholat Dzuhur"
11.45–12.30, "Manasik Siang" 13.00–15.00.

**Why this priority**: Haji dan event butuh lebih dari satu absensi per hari.

**Independent Test**: Tiga sesi tidak tumpang-tindih tersimpan; sesi keempat yang bertabrakan
ditolak dengan nama sesi yang ditabrak.

**Acceptance Scenarios**:

1. **Given** 3 sesi pada tanggal sama untuk target yang sama, **When** disimpan, **Then** ketiganya
   tampil berurutan menurut jam.
2. **Given** sesi "Manasik Pagi" 08.00–11.00, **When** admin menambah sesi 10.30–12.00 untuk target
   yang sama, **Then** ditolak dengan pesan "bertabrakan dengan Manasik Pagi (08.00–11.00)".
3. **Given** dua sesi bersamaan tetapi untuk grup berbeda (Rombongan 3 dan Rombongan 4), **When**
   disimpan, **Then** diterima.

---

### User Story 3 - Sesi sekali dan sesi khusus grup (Priority: P2)

Panitia event membuat satu sesi "Registrasi" pada 12 Juli 07.00–10.00 untuk seluruh peserta, dan
biro membuat "Ziarah Madinah" khusus Kloter 12.

**Why this priority**: Melengkapi pola berulang; bukan syarat uji pertama.

**Acceptance Scenarios**:

1. **Given** program event 1 hari, **When** admin membuat sesi sekali, **Then** sesi hanya ada pada
   tanggal itu.
2. **Given** sesi khusus Kloter 12, **When** peserta Rombongan 3 (anak Kloter 12) dicek, **Then**
   sesi berlaku; peserta kloter lain tidak.

---

### User Story 4 - Hari libur (Priority: P2)

Admin menandai 17 Juni sebagai libur ("Idul Adha"). Tidak ada sesi aktif hari itu dan tidak ada
yang ditandai alpa.

**Acceptance Scenarios**:

1. **Given** pola harian, **When** 17 Juni ditandai libur, **Then** kalender menampilkan libur dan
   sesi hari itu tidak aktif.
2. **Given** libur dihapus, **When** kalender dibuka, **Then** sesi hari itu kembali aktif.

---

### User Story 5 - Mengubah jadwal tidak mengubah sejarah (Priority: P2)

Admin menggeser jam datang mulai besok. Kehadiran hari-hari sebelumnya tetap dinilai dengan jadwal
lama.

**Acceptance Scenarios**:

1. **Given** sesi sudah lewat dengan kehadiran tercatat, **When** pola diubah, **Then** sesi yang
   sudah lewat tidak berubah; hanya sesi mendatang mengikuti pola baru.
2. **Given** sesi hari ini sudah punya kehadiran, **When** admin mengubah polanya, **Then** sistem
   meminta konfirmasi apakah sesi hari ini ikut diubah.

---

### Edge Cases

- Sesi melewati tengah malam (22.00–02.00) → tidak didukung di rilis ini; ditolak dengan pesan.
- Program diperpanjang → sesi untuk tanggal baru ikut dibuat dari pola.
- Program diperpendek → sesi di luar rentang tanpa kehadiran dihapus; yang punya kehadiran dipertahankan dan ditandai "di luar program".
- Zona waktu program Asia/Riyadh → semua jam sesi dibaca dalam zona program.
- Pola tanpa hari terpilih → ditolak.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Admin MUST bisa membuat pola sesi dengan nama, target (seluruh program atau grup
  tertentu beserta sub-grupnya), dan pengulangan: hari tertentu dalam seminggu sepanjang program,
  rentang tanggal, atau satu tanggal.
- **FR-002**: Setiap sesi MUST punya jendela datang (mulai–selesai) dan batas terlambat di dalam
  jendela datang; jendela pulang wajib hanya bila saklar "pulang" kategori menyala.
- **FR-003**: Sesi untuk target yang sama pada tanggal yang sama MUST NOT bertabrakan; penolakan
  MUST menyebut sesi yang ditabrak.
- **FR-004**: Sistem MUST menurunkan sesi per tanggal dari pola untuk seluruh rentang program dan
  memperbaruinya saat program atau pola berubah, tanpa mengubah sesi yang sudah lewat.
- **FR-005**: Admin MUST bisa menandai dan menghapus hari libur per program dengan keterangan;
  hari libur menonaktifkan semua sesi pada tanggal itu.
- **FR-006**: Admin MUST bisa melihat kalender program: sesi per tanggal, libur, dan jendela waktu.
- **FR-007**: Untuk satu peserta dan satu waktu, sistem MUST bisa menentukan sesi yang sedang
  berlaku (atau menyatakan tidak ada sesi + sesi berikutnya) menurut zona waktu program.
- **FR-008**: Mengubah pola MUST NOT mengubah penilaian kehadiran pada sesi yang sudah lewat.
- **FR-009**: Fitur ini MUST NOT mengubah jadwal absensi sekolah maupun data Kartu Bebas.

### Key Entities

- **Pola Sesi**: aturan pengulangan + jendela waktu + target.
- **Sesi**: kejadian sesi pada satu tanggal dengan salinan jendela waktunya.
- **Hari Libur**: tanggal tanpa sesi dalam program.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admin menyusun jadwal program 40 hari dengan 3 sesi/hari dalam kurang dari 5 menit.
- **SC-002**: 100% sesi yang sudah lewat tetap memakai jadwal saat itu setelah pola diubah.
- **SC-003**: 0 sesi bertabrakan tersimpan untuk target yang sama.
- **SC-004**: Penentuan sesi aktif untuk satu scan terasa instan bagi petugas (tanpa jeda terlihat).

## Assumptions

- Granularitas waktu menit; tidak ada sesi lintas tengah malam di rilis awal.
- Jadwal hanya berlaku dalam rentang tanggal program.
- Libur berlaku untuk seluruh program (bukan per grup) di rilis awal.
