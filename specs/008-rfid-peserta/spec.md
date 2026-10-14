# Feature Specification: RFID Peserta

**Feature Branch**: `008-rfid-peserta` (langsung di `main`) · **Created**: 2026-10-14 · **Status**: Draft — implementasi setelah spec 001–005, bergantung perangkat

**Input**: User description: "Daftar kartu RFID ke peserta, absen lewat reader RFID (HID) mengikuti rencana RFID sekolah."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Daftarkan kartu RFID (Priority: P1)

Admin menempelkan kartu RFID ke reader di halaman peserta; nomor kartu tertaut ke peserta.

**Acceptance Scenarios**:

1. **Given** saklar RFID kategori menyala, **When** kartu ditempel di form peserta, **Then** UID
   tersimpan dan unik dalam penyelenggara.
2. **Given** UID sudah dipakai peserta lain, **When** didaftarkan, **Then** ditolak dengan nama
   pemilik.

### User Story 2 - Absen dengan RFID (Priority: P1)

**Acceptance Scenarios**:

1. **Given** link scan program terbuka dengan reader HID, **When** kartu ditempel, **Then**
   perilaku sama persis dengan scan QR (sesi, status, idempoten).

### Edge Cases

- Reader mengirim UID dalam format desimal vs heksadesimal → dinormalkan.
- Kartu hilang → admin melepas tautan; UID lama ditolak.

## Requirements *(mandatory)*

- **FR-001**: UID RFID MUST unik per penyelenggara dan bisa dilepas/diganti.
- **FR-002**: Scan RFID MUST memakai alur pencatatan yang sama dengan QR (spec 004).
- **FR-003**: Fitur MUST hanya aktif bila saklar RFID kategori menyala.

### Key Entities

- **Kartu RFID Peserta**.

## Success Criteria *(mandatory)*

- **SC-001**: Tempel kartu → hasil tampil dalam kurang dari 1 detik.

## Assumptions

- Reader berperilaku sebagai keyboard (HID) seperti rencana RFID sekolah; tanpa driver khusus.
