# Feature Specification: Portal Wali

**Feature Branch**: `007-portal-wali` (langsung di `main`) · **Created**: 2026-10-14 · **Status**: Draft — implementasi setelah spec 001–005

**Input**: User description: "Wali login melihat riwayat hadir anggota keluarganya; satu akun untuk banyak peserta; halaman kartu digital publik tanpa data sensitif."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Keluarga memantau jamaah (Priority: P1)

Anak Bu Siti login dan melihat riwayat kehadiran ibunya per hari dan per sesi.

**Acceptance Scenarios**:

1. **Given** saklar portal wali kategori menyala, **When** wali login, **Then** ia melihat daftar
   peserta yang tertaut ke akunnya dan riwayat kehadirannya.
2. **Given** satu wali punya 2 peserta (ayah & ibu), **When** login, **Then** keduanya tampil dalam
   satu akun.
3. **Given** wali mencoba membuka peserta lain, **Then** responsnya sama dengan data tidak ada.

### User Story 2 - Kartu digital publik (Priority: P3)

**Acceptance Scenarios**:

1. **Given** tautan kartu digital peserta, **When** dibuka tanpa login, **Then** tampil nama, foto,
   program, dan QR — tanpa telepon, alamat, atau data skema lain.

### Edge Cases

- Akun wali ditautkan ke peserta lewat nomor telepon wali yang sama persis (tanpa tebakan).
- Peserta dihapus → hilang dari portal wali.

## Requirements *(mandatory)*

- **FR-001**: Wali MUST login dengan akun sendiri (pola portal orang tua sekolah, peran terpisah).
- **FR-002**: Wali MUST hanya melihat peserta yang tertaut ke akunnya.
- **FR-003**: Kartu digital publik MUST memakai tautan tak tertebak dan bisa dicabut admin.
- **FR-004**: Portal wali MUST NOT memberi akses ke data penyelenggara lain atau ke portal orang
  tua sekolah.

### Key Entities

- **Akun Wali**, **Tautan Wali–Peserta**, **Tautan Kartu Digital**.

## Success Criteria *(mandatory)*

- **SC-001**: Wali menemukan status hadir hari ini dalam ≤ 2 klik setelah login.
- **SC-002**: 0 kebocoran data antar keluarga pada uji isolasi.

## Assumptions

- Sandi wali mengikuti kebijakan sandi orang tua yang ada (8 karakter + daftar tolak + throttle).
