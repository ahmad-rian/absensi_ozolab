# Research: Scan & Kehadiran (004)

## R1. Isolasi total dari Kartu Bebas
- **Decision**: Program punya `programs.scanner_token` sendiri dan rute scan sendiri (`/hadir/{kode}` kamera,
  `/h/{kode}` ringan). `CardAttendanceService`, `/scan-peserta/*`, `/p/{kode}`, dan `card_attendances` TIDAK
  disentuh dan tidak menerima token peserta program (berbeda layout → HMAC beda).
- **Rationale**: Konstitusi III — alur haji yang berjalan harus persis sama. Ekstraksi `CardTokenResolver`
  (rencana awal) ditinggalkan: cukup memakai `CardAttendanceService::qrToken()` yang sudah publik untuk
  memverifikasi token tanpa mengubah kelas itu.
- **Alternatives**: Percabangan di `AttendanceController::rekamScan` (ditolak: menyentuh kode haji).

## R2. Pencatatan (pola AttendanceRecorder)
- Server memutuskan datang/pulang dari jendela sesi aktif (`SesiResolver`); perangkat tidak mengirim mode.
- Datang: `TERLAMBAT` bila jam > `batas_terlambat`, selain itu `HADIR`. Scan ulang = idempoten.
- Pulang: hanya bila sudah tercatat datang; tercatat di baris kehadiran yang sama.
- Status manual (IZIN/SAKIT/ALPA oleh petugas) dan ALPA otomatis tidak ditimpa scan; petugas diberi tahu.
- Konkurensi: `lockForUpdate` + tangkap `UniqueConstraintViolationException` (unique sesi+peserta).

## R3. Alpa otomatis
- Perintah `penyelenggara:tandai-alpa` tiap jam: sesi 2 hari terakhir yang jendela datangnya sudah tutup
  (zona program), bukan hari libur → peserta target (grup sesi + turunan, tidak diarsip, terdaftar sebelum
  tanggal sesi) tanpa catatan dibuat `ALPA` sumber `otomatis`. Idempoten lewat unique index.

## R4. Audit
- `Kehadiran` memakai `LogsActivity` (nilai lama/baru, pelaku via `causer`). Riwayat ditampilkan di halaman kehadiran.
