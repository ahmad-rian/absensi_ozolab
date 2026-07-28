import { Bullets, Menu, Note, Para, Pitfall, Steps } from '@/components/panduan/guide-section';

export function ApaIni() {
    return (
        <div className="space-y-4">
            <Para>
                Aplikasi ini mencatat kehadiran siswa lewat scan QR di gerbang, lalu mengabari orang tua
                lewat WhatsApp, Telegram, atau email. Selain itu ia juga mengurus data induk siswa,
                absen sholat berjamaah, pencetakan kartu, dan laporan kehadiran.
            </Para>
            <Para>Satu aplikasi melayani banyak sekolah sekaligus. Data tiap sekolah terpisah penuh.</Para>

            <Bullets>
                <li>
                    <b>Super Admin</b> — pemilik platform. Membuat sekolah, mengatur role, dan mengisi
                    kredensial notifikasi. Bisa berpindah antar sekolah.
                </li>
                <li>
                    <b>Admin Sekolah</b> — mengelola seluruh data sekolahnya sendiri: siswa, kelas,
                    absensi, kartu, laporan, dan pengaturan.
                </li>
                <li>
                    <b>Guru</b> — melihat siswa, mencatat absensi, membuka laporan dan notifikasi.
                    Tidak bisa mengubah kelas, pengguna, atau pengaturan.
                </li>
                <li>
                    <b>Orang Tua</b> — tidak masuk ke aplikasi. Mereka menerima notifikasi dan memakai
                    halaman publik untuk mendaftar atau menghubungkan Telegram.
                </li>
            </Bullets>

            <Note>
                Menu yang kamu lihat di sebelah kiri menyesuaikan role dan fitur yang aktif di sekolahmu.
                Kalau sebuah menu tidak ada, kemungkinan besar rolemu memang tidak diberi aksesnya, atau
                fiturnya dimatikan di <Menu>Pengaturan → Fitur</Menu>.
            </Note>
        </div>
    );
}

export function LangkahPertama() {
    return (
        <div className="space-y-4">
            <Para>Sebelum siswa bisa scan di gerbang, tiga hal ini harus ada lebih dulu.</Para>

            <Steps>
                <li>
                    <b>Tahun ajaran aktif.</b> Buka <Menu>Pengaturan → Umum</Menu>, pilih tahun ajaran
                    berjalan, lalu simpan. Tanpa ini, kelas tidak punya induk dan kenaikan kelas tidak
                    bisa dijalankan.
                </li>
                <li>
                    <b>Kelas.</b> Buka <Menu>Kelas</Menu> dan buat rombel. Bisa sekaligus satu rentang,
                    misalnya tingkat 7 paralel A sampai F.
                </li>
                <li>
                    <b>Jadwal absensi.</b> Buka <Menu>Jadwal Absensi</Menu>, tentukan hari aktif dan jam
                    masuk/pulang. Hari yang tidak punya jadwal aktif akan menolak semua scan.
                </li>
            </Steps>

            <Para>
                Setelah itu masukkan siswa — satu per satu lewat <Menu>Siswa → Tambah</Menu>, atau
                sekaligus lewat <Menu>Impor Siswa</Menu>.
            </Para>

            <Pitfall>
                Urutannya tidak bisa dibalik. Siswa wajib punya kelas, dan kelas wajib punya tahun ajaran.
                Kalau kelas dibuat sebelum tahun ajaran diaktifkan, kelas itu akan menempel ke tahun
                ajaran yang salah dan tidak akan muncul saat kenaikan kelas.
            </Pitfall>
        </div>
    );
}
