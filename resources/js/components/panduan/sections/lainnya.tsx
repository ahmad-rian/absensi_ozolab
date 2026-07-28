import { Bullets, Menu, Note, Para, Pitfall, Tip } from '@/components/panduan/guide-section';

export function DaftarFitur() {
    return (
        <div className="space-y-4">
            <Para>
                Saklar di <Menu>Pengaturan → Fitur</Menu> berlaku untuk sekolahmu saja. Fitur yang
                dimatikan menghilangkan menunya dari sidebar <b>dan</b> menolak halamannya kalau
                alamatnya diketik langsung.
            </Para>

            <Bullets>
                <li>
                    <b>Master Data Siswa</b> — menutup Siswa, Orang Tua, Kelas, Impor, Kenaikan Kelas.
                </li>
                <li>
                    <b>Absensi Sekolah</b> — menutup Absensi dan Jadwal Absensi, serta mematikan halaman
                    scan gerbang.
                </li>
                <li>
                    <b>Absen Sholat Dzuhur</b> dan <b>Dhuha</b> — mematikan jenis sholatnya di halaman
                    scan sholat.
                </li>
                <li>
                    <b>Laporan</b> — menutup menu Laporan beserta ekspornya.
                </li>
                <li>
                    <b>Notifikasi Absensi</b> — menghentikan pesan ke orang tua saat siswa scan.
                </li>
                <li>
                    <b>Notifikasi Alpa Sholat</b> — peringatan saat siswa berturut-turut tidak sholat.
                </li>
                <li>
                    <b>Inbox Notifikasi</b> — menutup menu riwayat pesan.
                </li>
                <li>
                    <b>Kartu &amp; Album</b> — menutup lima menu sekaligus.
                </li>
                <li>
                    <b>Pendaftaran Publik</b> dan <b>Pendaftaran Telegram</b> — menyembunyikan sekolahmu
                    dari halaman publik.
                </li>
                <li>
                    <b>Manajemen Pengguna</b>, <b>Integrasi Google Drive</b>, <b>Integrasi WhatsApp</b> —
                    menutup menunya masing-masing.
                </li>
            </Bullets>

            <Note>
                Dashboard dan Pengaturan tidak bisa dimatikan — kalau bisa, tidak ada lagi jalan untuk
                menyalakannya kembali. Modul sistem juga tidak, karena berlaku lintas sekolah.
            </Note>

            <Pitfall>
                Mematikan Absensi Sekolah <b>tidak</b> menghapus data absensi yang sudah ada. Ia hanya
                menutup menunya dan menolak scan baru. Menyalakannya kembali memunculkan semuanya utuh.
            </Pitfall>
        </div>
    );
}

export function KeluhanUmum() {
    return (
        <div className="space-y-5">
            <div className="space-y-1.5">
                <Para>
                    <b>&quot;Belum waktunya absen&quot; padahal siswa sudah datang</b>
                </Para>
                <Para>
                    Jamnya di luar jendela yang diatur. Periksa <Menu>Jadwal Absensi</Menu> untuk hari itu
                    — bawaan 06:00–08:00 masuk dan 13:00–18:00 pulang.
                </Para>
            </div>

            <div className="space-y-1.5">
                <Para>
                    <b>&quot;Tidak ada jadwal aktif untuk hari ini&quot;</b>
                </Para>
                <Para>
                    Hari itu tidak punya jadwal aktif. Paling sering terjadi di hari Sabtu yang lupa
                    diaktifkan.
                </Para>
            </div>

            <div className="space-y-1.5">
                <Para>
                    <b>&quot;QR Code tidak dikenali&quot;</b>
                </Para>
                <Para>
                    QR-nya milik sekolah lain, siswanya nonaktif, atau QR-nya sudah diperbarui. Halaman
                    scan <b>hanya</b> menerima QR — mengetik NIS tidak lagi bisa. Catat lewat menu
                    Absensi.
                </Para>
            </div>

            <div className="space-y-1.5">
                <Para>
                    <b>Orang tua tidak menerima notifikasi</b>
                </Para>
                <Bullets>
                    <li>Siswa belum punya wali, atau walinya tidak punya nomor/email sama sekali.</li>
                    <li>
                        Notifikasi Absensi dimatikan di <Menu>Pengaturan → Fitur</Menu>.
                    </li>
                    <li>
                        Gateway belum diisi Super Admin. Cek statusnya di <Menu>WhatsApp</Menu>.
                    </li>
                    <li>
                        Cek <Menu>Notifikasi</Menu> — kalau statusnya Gagal, masalahnya di gateway atau
                        nomornya.
                    </li>
                </Bullets>
            </div>

            <div className="space-y-1.5">
                <Para>
                    <b>Menu tiba-tiba hilang</b>
                </Para>
                <Para>
                    Fiturnya dimatikan di <Menu>Pengaturan → Fitur</Menu>, atau rolemu memang tidak punya
                    aksesnya. Datanya tidak hilang.
                </Para>
            </div>

            <div className="space-y-1.5">
                <Para>
                    <b>Kenaikan kelas menolak jalan</b>
                </Para>
                <Para>
                    Belum ada tahun ajaran aktif. Pilih dulu di <Menu>Pengaturan → Umum</Menu>. Kalau
                    baris tertentu ditolak, biasanya kelas tujuannya belum dibuat atau masih milik tahun
                    ajaran lama.
                </Para>
            </div>

            <div className="space-y-1.5">
                <Para>
                    <b>Impor menolak baris</b>
                </Para>
                <Bullets>
                    <li>NISN atau NIS-nya sudah dipakai siswa lain.</li>
                    <li>Kelas yang ditulis tidak ada di sekolah ini.</li>
                    <li>Isi kolom agama atau jenis kelamin tidak dikenali.</li>
                    <li>Siswa baru tapi nama, jenis kelamin, atau kelasnya kosong.</li>
                </Bullets>
                <Para>Alasannya selalu ditulis lengkap dengan nomor barisnya di halaman review.</Para>
            </div>

            <div className="space-y-1.5">
                <Para>
                    <b>Foto siswa tidak muncul di kartu</b>
                </Para>
                <Para>
                    Nama berkas di Google Drive harus persis, termasuk huruf besar-kecil dan ekstensinya.
                    Periksa juga folder Drive sekolah sudah benar di menu Google Drive.
                </Para>
            </div>

            <Tip>
                Kalau masalahnya tetap tidak jelas, catat pesan galat persisnya dan jam kejadiannya —
                dua hal itu yang paling cepat mengarahkan ke penyebabnya.
            </Tip>
        </div>
    );
}

export function TeksOrangTua() {
    return (
        <div className="space-y-4">
            <Para>Teks di bawah bisa disalin apa adanya ke grup WhatsApp wali murid.</Para>

            <div className="space-y-2">
                <Para>
                    <b>Menghubungkan Telegram</b>
                </Para>
                <pre className="bg-muted overflow-x-auto rounded-lg p-3 text-xs leading-relaxed whitespace-pre-wrap">
{`Bapak/Ibu wali murid,

Agar menerima notifikasi kehadiran ananda lewat Telegram:
1. Buka halaman: <alamat-sekolah>/daftar-telegram
2. Pilih nama sekolah dan nama ananda
3. Masukkan nomor WhatsApp yang terdaftar di sekolah
4. Ikuti langkah menghubungkan bot Telegram

Terima kasih.`}
                </pre>
            </div>

            <div className="space-y-2">
                <Para>
                    <b>Arti notifikasi kehadiran</b>
                </Para>
                <pre className="bg-muted overflow-x-auto rounded-lg p-3 text-xs leading-relaxed whitespace-pre-wrap">
{`Bapak/Ibu akan menerima pesan otomatis saat ananda:
- Tiba di sekolah (scan masuk)
- Pulang dari sekolah (scan pulang)

Pesan berisi nama, kelas, status kehadiran, dan jamnya.
Pesan ini otomatis — mohon tidak dibalas.
Bila ada pertanyaan, hubungi wali kelas.`}
                </pre>
            </div>

            <div className="space-y-2">
                <Para>
                    <b>Pendaftaran siswa baru</b>
                </Para>
                <pre className="bg-muted overflow-x-auto rounded-lg p-3 text-xs leading-relaxed whitespace-pre-wrap">
{`Pendaftaran siswa baru dapat dilakukan mandiri:
1. Buka halaman: <alamat-sekolah>/daftar
2. Pilih sekolah
3. Isi data siswa dan orang tua sesuai dokumen resmi
4. Ikuti panduan mengatur posisi foto agar seragam

Pastikan NISN diisi benar — nomor ini dipakai seterusnya.`}
                </pre>
            </div>

            <Note>Ganti &lt;alamat-sekolah&gt; dengan alamat aplikasi yang dipakai sekolahmu.</Note>
        </div>
    );
}
