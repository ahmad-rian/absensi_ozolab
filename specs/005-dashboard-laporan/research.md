# Research: Dashboard & Laporan (005)

## R1. Satu sumber angka
- **Decision**: `RingkasanKehadiran` menghitung target peserta per sesi (grup sesi + turunan, tidak diarsip,
  cakupan pendamping) dan status dari `kehadirans` (tanpa baris = BELUM). Dashboard dan semua ekspor memakai
  layanan yang sama → FR-005 konsisten secara konstruksi.
## R2. Dashboard
- Inertia `defer` untuk ringkasan & daftar belum hadir (pola DashboardController sekolah) dengan skeleton.
## R3. Ekspor
- XLSX lewat `XlsxDownload::sheets` (nama sheet aman via `namaSheet`); PDF DomPDF memakai `pdf.report-style`.
- Mode: grup (sheet per grup, kolom per sesi), sesi (satu sheet), peserta (riwayat semua sesi).
- Rentang maksimum 366 hari.
## R4. Peringkat & tren (P3)
- Peringkat = rata-rata menit datang setelah `datang_mulai` (lebih kecil lebih cepat), minimal 3 kehadiran;
  tampil bila program sudah berjalan ≥ 7 hari. Tren = % (HADIR+TERLAMBAT) per minggu 1/2/3 bulan terakhir.
