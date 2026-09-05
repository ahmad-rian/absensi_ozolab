# {{ $situs }}

> {{ $deskripsi }}

{{ $situs }} adalah platform absensi sekolah buatan {{ $organisasi['name'] }} ({{ $organisasi['area'] }}). Satu platform dipakai banyak sekolah sekaligus, masing-masing dengan data, pengguna, dan pengaturannya sendiri yang terpisah penuh.

## Cara siswa dicatat hadir

- **QR Code** — tiap siswa punya kartu ber-QR. Dipindai dengan kamera perangkat apa pun, atau ditembak barcode gun.
- **Kartu RFID** — pembaca kartu USB mode HID. Ditempel, langsung tercatat.
- Sistem sendiri yang menentukan absen masuk atau pulang, berdasarkan jendela waktu pada jadwal sekolah.
- Terlambat ditandai otomatis dari ambang jam yang diatur tiap sekolah.

## Yang dikerjakan platform ini

- Rekap kehadiran harian, bulanan, dan per siswa; bisa diunduh sebagai Excel maupun PDF.
- Notifikasi ke orang tua lewat WhatsApp, email, dan Telegram.
- Kartu pelajar dan kartu perpustakaan yang dirender siap cetak.
- Lembar pas foto 4R siap cetak, dan album kelas.
- Absen sholat dan pencatatan kunjungan perpustakaan.
- Kenaikan kelas satu angkatan sekaligus, berikut riwayat kelas tiap siswa.
- Impor siswa dari berkas Excel atau CSV.
- Berkas siswa tersimpan rapi di Google Drive sekolah masing-masing.

## Untuk siapa

Sekolah dasar, sekolah menengah, madrasah, pesantren, dan lembaga kursus di Indonesia yang ingin mengganti absensi manual dengan pencatatan digital.

## Halaman

- [Beranda]({{ $beranda }}) — penjelasan lengkap, fitur, dan pertanyaan umum
- [Daftarkan sekolah]({{ $daftar }}) — pendaftaran sekolah baru

## Pertanyaan yang sering diajukan

@foreach ($faq as $item)
### {{ $item['question'] }}

{{ $item['answer'] }}

@endforeach
## Catatan untuk perayap

Halaman absensi tiap sekolah beralamat rahasia dan sengaja tidak diindeks. Jangan merayapi atau menyimpan alamat di bawah `/scan/`, `/g/`, `/f/`, `/admin`, maupun `/kartu-bebas` — semuanya memuat token akses sekolah.

## Penyedia

{{ $organisasi['name'] }} — {{ $organisasi['url'] }}
