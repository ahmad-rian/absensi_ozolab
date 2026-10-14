# Feature Specification: Notifikasi Wali

**Feature Branch**: `006-notifikasi-wali` (langsung di `main`) · **Created**: 2026-10-14 · **Status**: Draft — implementasi setelah spec 001–005

**Input**: User description: "WA/Email/Telegram ke wali saat hadir, terlambat, atau alpa; kuota per penyelenggara dan antrean terpisah supaya notifikasi sekolah tidak tertunda."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Keluarga tahu jamaah sudah hadir (Priority: P1)

Saat kartu Bu Siti di-scan, anaknya menerima WA "Bu Siti hadir di Manasik Pagi pukul 08.10".

**Independent Test**: Kategori dengan saklar notifikasi menyala; scan → satu pesan antre ke nomor
wali; saklar mati → tidak ada pesan.

**Acceptance Scenarios**:

1. **Given** saklar notifikasi wali kategori menyala dan nomor wali valid, **When** peserta
   tercatat Hadir/Terlambat, **Then** satu pesan terkirim memakai template penyelenggara.
2. **Given** alpa otomatis, **When** sapuan selesai, **Then** wali menerima satu pesan ringkas per
   peserta per sesi.
3. **Given** kuota WA penyelenggara habis hari itu, **When** ada pesan baru, **Then** pesan dicatat
   "melebihi kuota" dan tidak dikirim; Email/Telegram tetap berjalan bila aktif.

### User Story 2 - Notifikasi sekolah tidak terganggu (Priority: P1)

**Acceptance Scenarios**:

1. **Given** antrean notifikasi penyelenggara menumpuk 1.000 pesan, **When** sekolah mengirim
   notifikasi terlambat, **Then** pesan sekolah tidak menunggu antrean penyelenggara.
2. **Given** kuota WA sekolah 50/hari, **When** penyelenggara mengirim pesan, **Then** kuota sekolah
   tidak berkurang.

### Edge Cases

- Nomor wali kosong/tidak valid → dilewati dan dicatat.
- Scan ulang (duplikat) → tidak mengirim pesan kedua.
- Status manual diubah → tidak mengirim ulang kecuali Alpa → Hadir (opsional, dimatikan bawaan).

## Requirements *(mandatory)*

- **FR-001**: Notifikasi MUST hanya terkirim bila saklar kategori dan kanal penyelenggara aktif.
- **FR-002**: Template pesan per penyelenggara MUST bisa diubah dengan variabel nama, sesi, jam,
  status.
- **FR-003**: Kuota WA harian per penyelenggara MUST ditegakkan dan terpisah dari kuota sekolah.
- **FR-004**: Pengiriman MUST lewat antrean terpisah dari antrean notifikasi sekolah.
- **FR-005**: Setiap pengiriman MUST tercatat (kanal, tujuan, status, alasan gagal).

### Key Entities

- **Template Notifikasi Penyelenggara**, **Log Notifikasi Peserta**.

## Success Criteria *(mandatory)*

- **SC-001**: 95% pesan hadir sampai ke gateway dalam 2 menit setelah scan.
- **SC-002**: 0 penundaan notifikasi sekolah akibat beban penyelenggara pada uji beban.

## Assumptions

- Memakai gateway WA (Fonnte), Email, Telegram yang sudah ada.
- Nomor wali diambil dari kolom inti Telepon Wali (spec 002).
