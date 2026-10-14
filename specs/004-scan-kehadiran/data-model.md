# Data Model 004
- `programs.scanner_token` string(64) unique nullable — diisi otomatis saat program dibuat (dan backfill program lama).
- `kehadirans` (dibuat di 003): status ∈ HADIR/TERLAMBAT/IZIN/SAKIT/ALPA; sumber ∈ scan/manual/otomatis.
- Log aktivitas Spatie untuk setiap perubahan kehadiran.
