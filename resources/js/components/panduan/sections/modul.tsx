import { Bullets, Menu, Note, Para, Pitfall, Steps, Tip } from '@/components/panduan/guide-section';

export function ModulSiswa() {
    return (
        <div className="space-y-4">
            <Para>
                Data induk siswa. Dari halaman detail siswa kamu bisa melihat QR, mencetak pas foto,
                membuka statistik kehadiran dan sholat, serta menuju kartu yang sudah tergenerate di
                Google Drive.
            </Para>
            <Bullets>
                <li>
                    <b>NIS</b> nomor induk sekolah, <b>NISN</b> nomor nasional. NISN yang dipakai sebagai
                    kunci saat impor dan kenaikan kelas karena ia tidak berubah.
                </li>
                <li>Tab statistik menampilkan ketepatan waktu, pola per hari, runtun, dan tren 12 bulan.</li>
                <li>Tab sholat punya saklar kepesertaan per siswa.</li>
            </Bullets>
            <Note>NIS dan NISN unik per sekolah, jadi dua sekolah boleh memakai nomor yang sama.</Note>
        </div>
    );
}

export function ModulOrangTua() {
    return (
        <div className="space-y-4">
            <Para>
                Satu wali bisa memiliki beberapa siswa. Wali inilah tujuan seluruh notifikasi kehadiran.
            </Para>
            <Pitfall>
                Siswa tanpa wali, atau wali tanpa satu pun tujuan kirim (WhatsApp, email, Telegram),
                <b> tidak akan pernah menerima notifikasi</b> — dan tidak ada peringatan apa pun soal itu
                di halaman absensi. Periksa di sini kalau ada orang tua yang mengaku tidak dapat kabar.
            </Pitfall>
        </div>
    );
}

export function ModulKelas() {
    return (
        <div className="space-y-4">
            <Para>
                Kelas terikat ke tahun ajaran. Membuatnya bisa sekaligus satu rentang paralel, misalnya
                tingkat 7 dari A sampai F.
            </Para>
            <Bullets>
                <li>Wali kelas diambil dari pengguna ber-role Guru.</li>
                <li>Kapasitas hanya penanda, tidak memblokir penambahan siswa.</li>
                <li>Kelas yang masih punya siswa tidak bisa dihapus.</li>
            </Bullets>
        </div>
    );
}

export function ModulJadwal() {
    return (
        <div className="space-y-4">
            <Para>
                Menentukan hari aktif dan jam masuk/pulang. Jadwal bisa dibuat global untuk seluruh
                sekolah, atau khusus satu kelas — yang khusus kelas menang.
            </Para>
            <Pitfall>
                Hari tanpa jadwal aktif menolak semua scan dengan pesan &quot;Tidak ada jadwal aktif untuk
                hari ini&quot;. Ini penyebab paling sering saat sekolah masuk hari Sabtu tapi lupa
                mengaktifkan harinya.
            </Pitfall>
        </div>
    );
}

export function ModulAbsensi() {
    return (
        <div className="space-y-4">
            <Para>
                Melihat kehadiran per tanggal dan kelas, serta mencatat manual untuk siswa yang izin,
                sakit, atau QR-nya bermasalah.
            </Para>
            <Tip>
                Ini tempat yang benar untuk mencatat siswa yang kartunya hilang — jangan mengetikkan NIS
                di halaman scan gerbang, karena memang sudah tidak menerimanya.
            </Tip>
        </div>
    );
}

export function ModulLaporan() {
    return (
        <div className="space-y-4">
            <Para>Rekap kehadiran per rentang tanggal dan kelas, bisa diekspor ke CSV atau PDF.</Para>
            <Note>
                Hari efektif dihitung dari hari yang punya catatan absensi di sekolah itu, bukan dari
                kalender. Jadi hari libur nasional tidak ikut menurunkan persentase kehadiran, selama
                memang tidak ada yang scan hari itu.
            </Note>
        </div>
    );
}

export function ModulNotifikasi() {
    return (
        <div className="space-y-4">
            <Para>
                Riwayat setiap pesan yang dikirim ke orang tua, lengkap dengan statusnya. Badge di sidebar
                menunjukkan jumlah yang belum dibaca.
            </Para>
            <Bullets>
                <li>
                    <b>Terkirim</b> — gateway menerima pesannya.
                </li>
                <li>
                    <b>Gagal</b> — periksa kredensial gateway atau nomor tujuannya.
                </li>
            </Bullets>
        </div>
    );
}

export function ModulKartuAlbum() {
    return (
        <div className="space-y-4">
            <Steps>
                <li>
                    <Menu>Frame &amp; Bingkai</Menu> — unggah bingkai kartu.
                </li>
                <li>
                    <Menu>Layout Kartu</Menu> — atur tata letak per jenis kartu: OSIS, perpustakaan,
                    identitas. Tandai satu sebagai default.
                </li>
                <li>
                    <Menu>Generate Kartu</Menu> — pilih siswa, jalankan. Prosesnya di latar belakang dan
                    statusnya diperbarui sendiri.
                </li>
                <li>
                    <Menu>Layout Album</Menu> dan <Menu>Generate Album</Menu> — untuk lembar foto massal.
                </li>
            </Steps>
            <Note>Hasil kartu diunggah ke Google Drive sekolah dan tautannya muncul di detail siswa.</Note>
        </div>
    );
}

export function ModulPengguna() {
    return (
        <div className="space-y-4">
            <Para>Menambah admin dan guru untuk sekolahmu, serta memberi hak akses tambahan per orang.</Para>
            <Pitfall>
                Kamu tidak bisa mengubah role atau hak aksesmu sendiri, dan admin sekolah tidak bisa
                memberikan hak akses modul sistem kepada siapa pun. Itu penjagaan yang disengaja.
            </Pitfall>
        </div>
    );
}

export function ModulDrive() {
    return (
        <div className="space-y-4">
            <Para>
                Menghubungkan sekolah ke folder Google Drive tempat foto siswa diambil dan hasil kartu
                disimpan.
            </Para>
            <Pitfall>
                Nama berkas foto di Drive harus <b>persis</b> saat dipakai di halaman pendaftaran publik.
                Pencarian sebagian sudah dihapus karena dulu bisa dipakai menebak nama berkas milik
                sekolah lain.
            </Pitfall>
        </div>
    );
}

export function ModulWhatsapp() {
    return (
        <div className="space-y-4">
            <Para>
                Halaman pantau saja. Kredensial gateway diisi Super Admin di menu Gateway Notifikasi.
            </Para>
            <Note>
                Notifikasi bisa lewat WhatsApp, Telegram, atau email. Email tetap terkirim walau sekolah
                belum mengatur SMTP sendiri, karena ia jatuh ke pengirim bawaan aplikasi.
            </Note>
        </div>
    );
}

export function ModulPengaturan() {
    return (
        <div className="space-y-4">
            <Bullets>
                <li>
                    <b>Umum</b> — nama situs, zona waktu, dan tahun ajaran aktif.
                </li>
                <li>
                    <b>Tampilan</b> — logo dan favicon. Tersimpan seketika, tanpa tombol simpan.
                </li>
                <li>
                    <b>Fitur</b> — empat belas saklar untuk menyalakan/mematikan bagian aplikasi.
                </li>
                <li>
                    <b>Absen Sholat</b> — jendela Dhuha dan Dzuhur, serta kepesertaan lintas agama.
                </li>
                <li>
                    <b>Notifikasi</b> — saklar notifikasi absensi, ambang alpa sholat, dan template pesan.
                </li>
            </Bullets>
            <Note>
                Tiap tab punya tombol simpan sendiri dan hanya menyimpan isinya sendiri. Berpindah tab
                dengan perubahan yang belum disimpan akan dimintai konfirmasi.
            </Note>
        </div>
    );
}

export function ModulSekolah() {
    return (
        <div className="space-y-4">
            <Para>
                Membuat dan mengelola sekolah. Tiap sekolah punya token scan sendiri yang menjadi tautan
                gerbang dan mushola.
            </Para>
            <Pitfall>
                Membuat ulang token scan <b>langsung mematikan tautan lama</b>. Perangkat gerbang yang
                masih membuka tautan lama akan berhenti bekerja sampai diperbarui.
            </Pitfall>
        </div>
    );
}

export function ModulRole() {
    return (
        <div className="space-y-4">
            <Para>
                Empat role bawaan — Super Admin, Admin Sekolah, Guru, Orang Tua — plus role custom yang
                bebas mengombinasikan modul.
            </Para>
            <Note>
                Role berlaku lintas sekolah. Mengubah satu role berdampak ke semua tenant, itu sebabnya
                menu ini hanya untuk super admin.
            </Note>
        </div>
    );
}

export function ModulGateway() {
    return (
        <div className="space-y-4">
            <Para>
                Kredensial per sekolah: token WhatsApp, bot Telegram, dan SMTP. Ada tombol uji kirim.
            </Para>
            <Pitfall>
                Mengganti host SMTP dianggap rotasi kredensial — password lama tidak dipakai ulang, jadi
                harus diisi kembali.
            </Pitfall>
        </div>
    );
}

export function ModulKartuBebas() {
    return (
        <div className="space-y-4">
            <Para>
                Untuk kartu di luar siswa, misalnya jamaah haji. Kolomnya bebas ditentukan, dan pesertanya
                mengisi lewat tautan publik terenkripsi tanpa perlu akun.
            </Para>
        </div>
    );
}
