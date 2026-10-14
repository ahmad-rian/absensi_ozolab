# Feature Specification: Penyelenggara & Tenancy

**Feature Branch**: `001-penyelenggara-tenant` (dikerjakan langsung di `main`)

**Created**: 2026-10-14

**Status**: Draft

**Input**: User description: "Modul Absensi Multi-Kategori butuh tenant baru bernama Penyelenggara (biro haji, panitia, instansi) yang terpisah dari sekolah. Super admin membuat penyelenggara, mengundang admin penyelenggara lewat email, dan bisa berpindah konteks. Peran Admin Penyelenggara dan Pendamping. Semua data modul baru ter-scope fail-closed. User penyelenggara tidak boleh membuka rute admin sekolah. Data haji prod tidak berubah; absensi sekolah tidak terpengaruh."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Super admin mendaftarkan penyelenggara dan admin pertamanya (Priority: P1)

Tim Tyas mendaftarkan biro haji "Al-Hijrah Travel" sebagai penyelenggara, mengisi kuota peserta
dan kuota notifikasi WA per hari, lalu mengundang pemilik biro lewat email. Pemilik biro
menerima email, membuat kata sandi, dan masuk ke area penyelenggara miliknya.

**Why this priority**: Tanpa penyelenggara dan admin-nya, tidak ada satu pun fitur modul baru
yang bisa dipakai siapa pun selain super admin.

**Independent Test**: Super admin membuat penyelenggara + undangan; akun undangan login dan
melihat dashboard penyelenggara kosong bernama "Al-Hijrah Travel".

**Acceptance Scenarios**:

1. **Given** super admin di halaman Penyelenggara, **When** ia mengisi nama, kuota peserta 500,
   kuota WA 50/hari, lalu menyimpan, **Then** penyelenggara tampil di daftar dengan status Aktif
   dan slug unik yang diturunkan dari nama.
2. **Given** penyelenggara tersimpan, **When** super admin mengundang `owner@alhijrah.test`,
   **Then** email undangan terkirim berisi tautan yang berlaku terbatas, dan undangan tercatat
   sebagai "Menunggu".
3. **Given** pemilik biro membuka tautan undangan yang masih berlaku, **When** ia membuat kata
   sandi, **Then** ia masuk dengan peran Admin Penyelenggara dan langsung diarahkan ke area
   penyelenggara, bukan ke dashboard sekolah.
4. **Given** tautan undangan sudah dipakai atau kedaluwarsa, **When** dibuka lagi, **Then**
   tampil pesan bahwa undangan tidak berlaku dan cara meminta undangan baru.

---

### User Story 2 - Data penyelenggara terisolasi total (Priority: P1)

Admin biro A hanya melihat dan mengubah data biro A. Ia tidak bisa membuka data biro B maupun
halaman admin sekolah mana pun, sekalipun menebak alamatnya.

**Why this priority**: Kebocoran data jamaah antar biro atau ke data siswa adalah kegagalan
yang tidak bisa diterima; prinsip tenancy fail-closed di konstitusi.

**Independent Test**: Dua penyelenggara dengan data uji; akun admin A mencoba alamat data B dan
alamat `/admin/...` sekolah — semuanya tidak memberi akses dan tidak membedakan "ada" vs "tidak
ada".

**Acceptance Scenarios**:

1. **Given** admin penyelenggara A login, **When** ia membuka alamat detail milik penyelenggara
   B, **Then** responsnya sama persis dengan alamat yang memang tidak ada.
2. **Given** admin penyelenggara A login, **When** ia membuka alamat admin sekolah (daftar
   siswa, detail siswa, absensi sekolah), **Then** akses ditolak dan ia diarahkan kembali ke
   area penyelenggaranya.
3. **Given** proses tanpa konteks penyelenggara (misalnya user yang perannya belum diatur),
   **When** data milik penyelenggara diminta, **Then** hasilnya kosong, bukan seluruh data.
4. **Given** admin sekolah login, **When** ia membuka alamat area penyelenggara, **Then** akses
   ditolak.

---

### User Story 3 - Super admin berpindah konteks penyelenggara (Priority: P2)

Tim Tyas membantu biro yang kesulitan dengan memilih biro itu dari pemilih konteks, lalu melihat
area penyelenggara persis seperti admin biro melihatnya.

**Why this priority**: Dukungan pelanggan; tidak memblokir pemakaian biro sendiri.

**Independent Test**: Super admin memilih penyelenggara A, melihat data A; memilih B, melihat
data B; tidak pernah keduanya sekaligus.

**Acceptance Scenarios**:

1. **Given** super admin, **When** ia memilih "Al-Hijrah Travel" di pemilih konteks, **Then**
   seluruh halaman area penyelenggara menampilkan data biro itu saja.
2. **Given** penyelenggara mematikan sebuah fitur, **When** super admin berada di konteks
   penyelenggara itu, **Then** menu dan halaman fitur tersebut juga tertutup bagi super admin.

---

### User Story 4 - Admin penyelenggara mengelola tim dan hak akses (Priority: P2)

Admin biro mengundang pendamping (muthawif, ketua rombongan) dan mengatur izin per modul untuk
peran Pendamping di layar Hak Akses.

**Why this priority**: Diperlukan sebelum operasional lapangan, tetapi bukan untuk uji pertama.

**Independent Test**: Admin mengundang pendamping; pendamping login dan hanya melihat menu yang
diizinkan.

**Acceptance Scenarios**:

1. **Given** admin penyelenggara, **When** ia mengundang `ustadz@alhijrah.test` sebagai
   Pendamping, **Then** akun itu terikat ke penyelenggara yang sama dan tidak bisa diubah ke
   penyelenggara lain oleh admin penyelenggara.
2. **Given** izin modul tertentu dicabut dari peran Pendamping, **When** pendamping login,
   **Then** menu modul itu hilang dan alamatnya menolak akses.
3. **Given** penyelenggara dinonaktifkan super admin, **When** admin atau pendampingnya login,
   **Then** mereka melihat pesan bahwa akun penyelenggara sedang nonaktif dan cara menghubungi
   Tyas, tanpa akses data.

---

### Edge Cases

- Email undangan sudah dipakai akun sekolah atau penyelenggara lain → undangan ditolak dengan
  pesan jelas; satu akun hanya milik satu tenant.
- Nama penyelenggara sama dengan yang sudah ada → slug diberi akhiran unik; nama boleh sama.
- Kuota peserta diturunkan di bawah jumlah peserta yang sudah ada → disimpan, tetapi penambahan
  peserta baru ditolak sampai di bawah kuota (penegakan di spec peserta).
- Admin penyelenggara terakhir dihapus/dinonaktifkan → ditolak; minimal satu admin aktif.
- Super admin belum memilih konteks penyelenggara → area penyelenggara menampilkan pemilih,
  bukan data gabungan.
- Akun lama (sekolah, orang tua, super admin) login → alur dan tujuan login mereka tidak berubah.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Super admin MUST bisa membuat, mengubah, menonaktifkan, dan mengaktifkan kembali
  penyelenggara dengan atribut nama, slug unik, status aktif, kuota peserta, dan kuota notifikasi
  WA per hari.
- **FR-002**: Super admin dan admin penyelenggara MUST bisa mengundang akun lewat email dengan
  peran Admin Penyelenggara atau Pendamping; undangan berlaku 7 hari dan sekali pakai.
- **FR-003**: Setiap akun penyelenggara MUST terikat ke tepat satu penyelenggara; admin
  penyelenggara MUST NOT bisa memindahkan akun ke penyelenggara lain.
- **FR-004**: Semua data milik modul penyelenggara MUST otomatis dibatasi ke penyelenggara aktif;
  tanpa konteks penyelenggara, permintaan data MUST menghasilkan nol data.
- **FR-005**: Permintaan data penyelenggara lain MUST memberi respons yang tidak bisa dibedakan
  dari data yang tidak ada.
- **FR-006**: Akun Admin Penyelenggara dan Pendamping MUST NOT bisa mengakses halaman atau aksi
  admin sekolah; akun sekolah dan orang tua MUST NOT bisa mengakses area penyelenggara.
- **FR-007**: Super admin MUST bisa memilih satu penyelenggara sebagai konteks kerja dan
  menggantinya kapan saja; tanpa pilihan, area penyelenggara meminta pilihan dulu.
- **FR-008**: Super admin MUST melewati pemeriksaan izin peran, tetapi MUST tetap tunduk pada
  saklar fitur penyelenggara yang sedang dipilih.
- **FR-009**: Area penyelenggara MUST punya tata letak dan menu sendiri; menu hanya menampilkan
  modul yang diizinkan untuk peran user dan dinyalakan untuk penyelenggaranya.
- **FR-010**: Izin per modul untuk peran Admin Penyelenggara dan Pendamping MUST bisa diatur di
  layar Hak Akses yang sudah ada.
- **FR-011**: Penyelenggara nonaktif MUST memblokir akses semua akunnya dengan pesan yang
  menjelaskan status dan cara menghubungi Tyas.
- **FR-012**: Setiap penyelenggara MUST memiliki minimal satu Admin Penyelenggara aktif.
- **FR-013**: Fitur ini MUST NOT mengubah satu pun baris data kartu/haji yang sudah ada maupun
  perilaku absensi, login, dan notifikasi sekolah.
- **FR-014**: Pembuatan, perubahan status, undangan, dan perubahan peran MUST tercatat di log
  aktivitas (siapa, kapan, apa).

### Key Entities

- **Penyelenggara**: organisasi pemilik data (biro haji, panitia, instansi). Atribut: nama, slug,
  status aktif, kuota peserta, kuota WA per hari, pengaturan & saklar fitur.
- **Akun Penyelenggara**: akun login yang terikat ke satu penyelenggara dengan peran Admin
  Penyelenggara atau Pendamping.
- **Undangan**: email tujuan, peran, penyelenggara, pengundang, kedaluwarsa, status (menunggu,
  dipakai, kedaluwarsa, dibatalkan).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Super admin bisa membuat penyelenggara dan mengirim undangan admin pertamanya dalam
  kurang dari 3 menit.
- **SC-002**: Undangan diterima sampai admin penyelenggara melihat area miliknya dalam kurang dari
  2 menit.
- **SC-003**: 100% percobaan akses lintas penyelenggara dan penyelenggara→sekolah dalam uji
  isolasi gagal tanpa membocorkan keberadaan data.
- **SC-004**: 0 perubahan pada data kartu/haji yang ada dan 0 regresi pada uji absensi sekolah
  setelah fitur dirilis.

## Assumptions

- Satu akun hanya milik satu tenant (satu sekolah ATAU satu penyelenggara); akun yang melayani
  dua penyelenggara memakai dua email.
- Undangan memakai mekanisme email yang sudah ada di aplikasi; masa berlaku 7 hari.
- Saklar fitur penyelenggara mengikuti pola saklar fitur sekolah (bawaan menyala untuk modul inti).
- Penugasan Pendamping ke grup tertentu dikerjakan di spec 002 (grup); di spec ini Pendamping
  melihat semua data penyelenggaranya sesuai izin modul.
- Kuota peserta dan WA hanya disimpan di spec ini; penegakannya di spec peserta dan notifikasi.
- Data haji lama tetap milik alur Kartu Bebas super admin dan tidak dipindahkan di spec ini.
