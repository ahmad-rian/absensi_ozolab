# Kontrak Rute 003 (izin penyelenggara-program.access)
| Metode | Path | Nama |
|---|---|---|
| GET | /penyelenggara/program/{program}/jadwal | penyelenggara.jadwal.index (pola, libur, kalender) |
| POST | /penyelenggara/program/{program}/jadwal | penyelenggara.jadwal.store |
| PUT/DELETE | /penyelenggara/jadwal/{pola} | penyelenggara.jadwal.update / destroy (param `termasuk_hari_ini`) |
| POST | /penyelenggara/program/{program}/libur | penyelenggara.libur.store |
| DELETE | /penyelenggara/libur/{libur} | penyelenggara.libur.destroy |
Perintah: `php artisan penyelenggara:sesi-sinkron {--program=}` (harian, aman diulang).
