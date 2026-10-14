# Tasks: RFID Peserta (008) — direncanakan

- [ ] T001 Tes: daftar UID, UID kembar ditolak dengan nama pemilik, desimal/heks dinormalkan, scan UID = scan QR, saklar mati menolak
- [ ] T002 Migrasi kolom `rfid_uid` nullable + indeks pada `card_form_submissions` (baris lama NULL)
- [ ] T003 Normalisasi UID + form daftar/lepas kartu di halaman peserta
- [ ] T004 `KehadiranRecorder` menerima UID bila fitur RFID kategori menyala
- [ ] T005 Pint, build, suite, commit
