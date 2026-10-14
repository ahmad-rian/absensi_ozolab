# Data Model 003 (ULID, BelongsToPenyelenggara)

| Tabel | Kolom | Aturan |
|---|---|---|
| sesi_polas | program_id, grup_id?, name, pengulangan (mingguan/rentang/sekali), hari json (1–7), tanggal_mulai?, tanggal_selesai?, tanggal?, datang_mulai, datang_selesai, batas_terlambat?, pulang_mulai?, pulang_selesai? | datang_mulai < datang_selesai; batas_terlambat di dalam jendela datang; pulang wajib bila fitur pulang kategori menyala; pulang_mulai ≥ datang_selesai |
| sesis | program_id, sesi_pola_id?, grup_id?, name, tanggal, salinan jendela waktu | unique (sesi_pola_id, tanggal) |
| liburs | program_id, tanggal, keterangan | unique (program_id, tanggal) |
| kehadirans | program_id, sesi_id, card_form_submission_id, status (AttendanceStatus), datang_at?, pulang_at?, sumber, catatan?, dicatat_oleh? | unique (sesi_id, card_form_submission_id) — diisi di 004 |
