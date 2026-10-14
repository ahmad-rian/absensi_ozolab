# Kontrak Rute 002 (grup /penyelenggara, middleware area + permission modul)

| Metode | Path | Nama | Izin |
|---|---|---|---|
| GET/POST | /penyelenggara/kategori | penyelenggara.kategori.index/store | penyelenggara-kategori.access |
| GET/PUT | /penyelenggara/kategori/{kategori} | .edit/.update | idem |
| GET/POST | /penyelenggara/program | penyelenggara.program.index/store | penyelenggara-program.access |
| GET/PUT | /penyelenggara/program/{program} | .show/.update | idem |
| POST | /penyelenggara/program/{program}/arsip, /salin | .arsip/.salin | idem |
| POST/PUT/DELETE | /penyelenggara/program/{program}/grup[/{grup}] | penyelenggara.grup.* | idem |
| PUT | /penyelenggara/grup/{grup}/pendamping | penyelenggara.grup.pendamping | idem |
| GET | /penyelenggara/program/{program}/peserta | penyelenggara.peserta.index | penyelenggara-peserta.access |
| GET/POST | /penyelenggara/program/{program}/peserta/create | .create/.store | idem |
| GET/PUT/DELETE | /penyelenggara/peserta/{peserta} | .edit/.update/.destroy | idem |
| POST | /penyelenggara/peserta/{peserta}/pulihkan, /grup, /generate | .restore/.grup/.generate | idem |
| GET | /penyelenggara/peserta/{peserta}/kartu | .kartu (unduh/preview) | idem |
| POST | /penyelenggara/program/{program}/impor (pratinjau), /impor/{kunci}/simpan | penyelenggara.impor.* | idem |
| GET | /penyelenggara/program/{program}/layout | penyelenggara.layout.edit (editor) | penyelenggara-kartu.access |
| PUT | /penyelenggara/program/{program}/layout | penyelenggara.layout.update | idem |
| GET | /penyelenggara/program/{program}/kartu.zip | penyelenggara.kartu.zip | idem |
| PUT | /admin/pengaturan-drive-penyelenggara | admin.penyelenggara.drive | super-admin |

Model binding semua parameter memakai trait tenant → milik penyelenggara lain = 404.
Pendamping: peserta di luar grupnya = 404.
