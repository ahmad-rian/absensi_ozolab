# Tasks: Notifikasi Wali (006) — direncanakan

- [ ] T001 Tes: scan Hadir → 1 pesan antre ke nomor wali; saklar kategori mati → tidak ada; duplikat scan → tidak kirim ulang; kuota habis → log "melebihi kuota"; antrean sekolah tidak tertunda
- [ ] T002 Migrasi `penyelenggara_notification_logs` (tenant, kanal, tujuan, status, alasan)
- [ ] T003 Event `KehadiranTercatat` di `KehadiranRecorder` & `penyelenggara:tandai-alpa`
- [ ] T004 Job `KirimNotifikasiWali` di antrean `notif-penyelenggara` + worker supervisor terpisah
- [ ] T005 Kuota harian per penyelenggara + template pesan (variabel nama, sesi, jam, status) di pengaturan penyelenggara
- [ ] T006 Halaman log notifikasi penyelenggara
- [ ] T007 Pint, build, suite, commit
