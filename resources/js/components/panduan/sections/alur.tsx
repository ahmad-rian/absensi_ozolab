import { Bullets, Menu, Note, Para, Pitfall, Steps, Tip } from '@/components/panduan/guide-section';

export function SekolahBaru() {
    return (
        <div className="space-y-4">
            <Para>Urutan yang paling sedikit menimbulkan pekerjaan ulang.</Para>
            <Steps>
                <li>
                    <Menu>Pengaturan → Umum</Menu> — isi nama sekolah dan pilih tahun ajaran aktif.
                </li>
                <li>
                    <Menu>Kelas</Menu> — buat semua rombel per tingkat.
                </li>
                <li>
                    <Menu>Jadwal Absensi</Menu> — tekan Buat Jadwal Default, lalu sesuaikan jam dan hari
                    aktifnya. Sabtu mati secara default.
                </li>
                <li>
                    <Menu>Impor Siswa</Menu> atau <Menu>Siswa → Tambah</Menu> — masukkan data siswa.
                </li>
                <li>
                    <Menu>Orang Tua</Menu> — pastikan tiap siswa punya wali dengan minimal satu tujuan
                    kirim: nomor WhatsApp, email, atau Telegram.
                </li>
                <li>
                    <Menu>Sekolah</Menu> (super admin) — salin tautan scan, buka di tablet gerbang.
                </li>
                <li>
                    <Menu>Layout Kartu</Menu> lalu <Menu>Generate Kartu</Menu> — cetak kartu ber-QR.
                </li>
            </Steps>
            <Note>
                Siswa bisa scan begitu punya QR, tanpa menunggu kartunya dicetak. QR bisa ditampilkan dari
                halaman detail siswa.
            </Note>
        </div>
    );
}

export function TahunAjaran() {
    return (
        <div className="space-y-4">
            <Para>Dikerjakan sekali setiap pergantian tahun ajaran.</Para>
            <Steps>
                <li>
                    <Menu>Pengaturan → Umum</Menu> — pindahkan tahun ajaran aktif ke yang baru.
                </li>
                <li>
                    <Menu>Kelas</Menu> — buat rombel untuk tahun ajaran baru. Kelas tahun lalu dibiarkan,
                    jangan dihapus.
                </li>
                <li>
                    <Menu>Kenaikan Kelas</Menu> — unggah berkas berisi NISN dan kelas barunya.
                </li>
            </Steps>
            <Pitfall>
                Jangan menghapus kelas lama. Laporan absensi tahun lalu masih menunjuk ke kelas itu, dan
                menghapusnya membuat rekap lama kehilangan nama kelasnya.
            </Pitfall>
        </div>
    );
}

export function ImporSiswa() {
    return (
        <div className="space-y-4">
            <Para>
                Menerima <b>.xlsx</b> dan <b>.csv</b>. Prosesnya selalu tiga tahap: unggah, periksa hasil,
                baru terapkan. Tidak ada data yang berubah sebelum kamu menekan Terapkan.
            </Para>
            <Steps>
                <li>
                    Buka <Menu>Impor Siswa</Menu> lalu unduh template. Isi mengikuti kolomnya.
                </li>
                <li>Unggah berkas. Sistem membacanya dan menampilkan hasil pemeriksaan.</li>
                <li>
                    Baca halaman review. Ada tiga kelompok: <b>akan dibuat</b>, <b>akan diperbarui</b>,
                    dan <b>ditolak</b> beserta alasan dan nomor barisnya.
                </li>
                <li>Perbaiki baris yang ditolak di berkasmu, unggah ulang bila perlu.</li>
                <li>Tekan Terapkan. Prosesnya berjalan di latar belakang dan statusnya diperbarui sendiri.</li>
            </Steps>

            <Para>Bagaimana sistem tahu ini siswa baru atau siswa lama:</Para>
            <Bullets>
                <li>NISN cocok dengan satu siswa → datanya diperbarui.</li>
                <li>NISN kosong tapi NIS cocok → datanya diperbarui.</li>
                <li>Keduanya tidak cocok → dibuat sebagai siswa baru.</li>
                <li>Nama sama tapi NISN berbeda → dianggap dua orang berbeda.</li>
            </Bullets>

            <Pitfall>
                Nama tidak pernah dipakai untuk mencocokkan. Dua siswa bernama sama di satu sekolah itu
                wajar, jadi pencocokan hanya memakai NISN atau NIS. Kalau kedua kolom itu kosong, barisnya
                ditolak.
            </Pitfall>

            <Tip>
                Coba dengan 2–3 baris dulu sebelum satu angkatan. Lihat apakah halaman review sesuai
                harapan, baru lanjut ke berkas penuh.
            </Tip>
        </div>
    );
}

export function KenaikanKelas() {
    return (
        <div className="space-y-4">
            <Para>
                Berkasnya cukup dua kolom: <b>NISN</b> dan <b>kelas baru</b>. Data siswa yang lain tidak
                disentuh sama sekali.
            </Para>
            <Steps>
                <li>Pastikan tahun ajaran baru sudah aktif dan kelas tujuannya sudah dibuat.</li>
                <li>
                    Buka <Menu>Kenaikan Kelas</Menu>, unggah berkasnya.
                </li>
                <li>
                    Periksa review: <b>akan diubah</b>, <b>tidak ada di berkas</b>, dan <b>ditolak</b>.
                </li>
                <li>Tekan Terapkan.</li>
            </Steps>

            <Note>
                Siswa yang tidak ada di berkas <b>tidak diubah</b> — kelasnya tetap. Mereka tetap
                ditampilkan supaya kamu sadar ada yang belum terdaftar, misalnya siswa yang lulus atau
                pindah.
            </Note>

            <Para>
                Kelas lama siswa disimpan sebagai riwayat per tahun ajaran. Karena itu{' '}
                <b>laporan absensi tahun lalu tetap menampilkan kelas 7</b>, bukan ikut berubah jadi 8.
            </Para>

            <Pitfall>
                Kelas tujuan harus sudah ada <b>dan</b> milik tahun ajaran aktif. Kalau belum, barisnya
                ditolak — buat kelasnya dulu di menu Kelas, jangan mengandalkan sistem membuatnya sendiri.
                Itu sengaja, supaya satu salah ketik tidak melahirkan kelas hantu seperti &quot;8 A&quot;
                atau &quot;VIII-A&quot;.
            </Pitfall>
        </div>
    );
}

export function AbsensiHarian() {
    return (
        <div className="space-y-4">
            <Para>
                Jam absensi bawaan: <b>06:00–08:00 hadir</b>, lewat 08:00 dihitung <b>terlambat</b>,{' '}
                <b>13:00–18:00 pulang</b>, dan lewat 18:00 sistem menutup sendiri absensi yang lupa
                di-scan pulang.
            </Para>
            <Steps>
                <li>
                    <Menu>Jadwal Absensi</Menu> — sesuaikan hari aktif dan jamnya per hari.
                </li>
                <li>Salin tautan scan sekolah dari menu Sekolah, buka di tablet atau laptop gerbang.</li>
                <li>Buka mode layar penuh, arahkan kamera, siswa menempelkan QR-nya.</li>
            </Steps>

            <Note>
                Masuk atau pulang <b>ditentukan server dari jam</b>, bukan dipilih petugas. Itu sebabnya
                tidak ada lagi tombol Masuk/Pulang — dulu tombol itu bisa tertinggal di posisi kemarin
                sore dan membuat scan pagi tercatat sebagai pulang.
            </Note>

            <Pitfall>
                Halaman scan hanya menerima <b>QR</b>. Ketik NIS manual sudah dihapus karena NIS mudah
                ditebak dan bisa dipakai menitipkan absen. Kalau QR siswa rusak, catat lewat menu Absensi.
            </Pitfall>
        </div>
    );
}

export function AbsenSholat() {
    return (
        <div className="space-y-4">
            <Para>
                Ada dua jenis: <b>Dhuha</b> (bawaan 07:30–09:00) dan <b>Dzuhur</b> (bawaan 11:00–13:00).
                Keduanya memakai <b>satu tautan scan yang sama</b> — jenisnya ditentukan dari jam scan,
                jadi petugas mushola tidak bisa salah mode.
            </Para>
            <Steps>
                <li>
                    <Menu>Pengaturan → Fitur</Menu> — nyalakan jenis sholat yang dipakai.
                </li>
                <li>
                    <Menu>Pengaturan → Absen Sholat</Menu> — atur jamnya, dan tentukan apakah siswa
                    non-Islam ikut.
                </li>
                <li>Salin tautan scan sholat dari menu Sekolah, buka di perangkat mushola.</li>
            </Steps>

            <Note>
                Jendela Dhuha dan Dzuhur tidak boleh beririsan, bahkan tidak boleh bersentuhan di menit
                yang sama. Kalau beririsan, sistem tidak bisa menentukan jenis sholatnya dan pengaturannya
                ditolak.
            </Note>

            <Pitfall>
                <b>Jangan menyalakan Notifikasi Alpa Sholat sebelum scan sholat benar-benar berjalan
                beberapa hari.</b> Aturannya jalan apa adanya: siswa yang hadir tiga hari sekolah tanpa
                catatan sholat akan tembus ambang. Kalau belum ada satu pun catatan sholat, itu berarti{' '}
                <b>semua siswa</b>, dan seluruh orang tua menerima peringatan sekaligus.
            </Pitfall>

            <Para>
                Kepesertaan tiap siswa bisa diatur satu per satu dari halaman detail siswa, dan
                pengaturan itu menang atas aturan sekolah.
            </Para>
        </div>
    );
}
