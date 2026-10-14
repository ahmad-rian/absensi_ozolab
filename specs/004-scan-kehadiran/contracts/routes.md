# Kontrak Rute 004
Publik (tanpa login):
| Metode | Path | Nama | Catatan |
|---|---|---|---|
| GET | /hadir/{kode} | public.program-scan | halaman kamera React (kode = scanner_token) |
| POST | /hadir/{kode} | public.program-scan.scan | JSON, throttle scan-gerbang |
| GET | /h/{kode} | public.program-scan.ringan | Blade `scan.light`, tanpa session; kode = 8 karakter awal token |
| POST | /h/{kode} | public.program-scan.ringan.scan | JSON, tanpa session |
JSON sukses: `{success, message, student:{full_name, classroom, photo_url, status, type, type_label, time}}` (kontrak konsol scan yang ada).
JSON gagal: `{success:false, message, student:null}` 422/404.

Area penyelenggara (izin penyelenggara-peserta.access):
| GET | /penyelenggara/program/{program}/kehadiran?sesi=&tanggal=&grup=&status= | penyelenggara.kehadiran.index |
| PUT | /penyelenggara/kehadiran/{sesi}/{peserta} | penyelenggara.kehadiran.update (status, catatan) |
| POST | /penyelenggara/program/{program}/link-scan | penyelenggara.kehadiran.rotate (izin program) |
