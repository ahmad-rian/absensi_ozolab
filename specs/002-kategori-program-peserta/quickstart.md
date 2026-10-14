# Quickstart 002
1. `php artisan migrate` — tabel 002 dibuat; `git diff` tidak menunjukkan perubahan data card_* lama.
2. `php artisan test --compact --filter="Penyelenggara|CardAttendance|CardParticipant|KartuBebas"` hijau
   (tes Kartu Bebas tidak diubah).
3. Manual: area penyelenggara → Kategori → template Haji/Umroh → Program "Haji 1448H" → grup 3 tingkat →
   impor `tests/fixtures/peserta-haji.xlsx` → desain layout → generate satu kartu → unduh ZIP.
