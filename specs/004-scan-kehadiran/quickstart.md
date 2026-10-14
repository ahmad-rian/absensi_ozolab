# Quickstart 004
1. Program dengan sesi 08.00–09.00 (terlambat 08.15) → buka `/h/{kode}` → tembak QR kartu 08.10 → "Hadir".
2. Scan ulang → "Sudah tercatat 08.10". 08.40 → peserta lain "Terlambat".
3. Pendamping catat Sakit → scan tidak menimpa. Jalankan `php artisan penyelenggara:tandai-alpa` setelah 09.00 → sisa jadi Alpa.
4. Regresi: `php artisan test --compact --filter=CardAttendance` (30 tes haji) hijau tanpa perubahan.
