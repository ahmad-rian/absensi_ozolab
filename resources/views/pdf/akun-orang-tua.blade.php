<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Akun Orang Tua</title>
    @include('pdf.report-style')
    <style>
        .catatan { border: 1px solid #e5e7eb; border-left: 3px solid #1d4ed8; padding: 10px 12px; margin-bottom: 18px; }
        .catatan strong { color: #111827; }
        .sandi { font-family: DejaVu Sans Mono, monospace; background: #f3f4f6; padding: 1px 5px; }
        td.email { font-family: DejaVu Sans Mono, monospace; font-size: 9px; }
        .belum { color: #b91c1c; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Daftar Akun Orang Tua</h2>
        <h1>{{ $schoolName }}</h1>
        <p>{{ $classroomName ? 'Kelas '.$classroomName : 'Seluruh kelas' }} &middot; {{ count($rows) }} siswa</p>
    </div>

    <div class="catatan">
        Gunakan sandi yang diberikan sekolah. Jika sekolah telah menyetel sandi awal
        <span class="sandi">password</span>, ganti sandi saat diminta setelah masuk.
        Akun yang sudah mengganti sandi memakai sandi pribadinya.
        Alamat berakhiran <strong>@tyas.app</strong> adalah nama pengguna untuk login,
        bukan kotak surat. Untuk memulihkan sandi akun tersebut, hubungi operator sekolah.
        @if ($belumSiap > 0)
            <br><span class="belum">{{ $belumSiap }} baris masih memakai alamat login lama</span>
            dan ditandai pada tabel; jalankan pembaruan alamat sebelum lembar ini dibagikan.
        @endif
    </div>

    <table class="detail">
        <thead>
            <tr>
                <th style="width:22%">Nama Anak</th>
                <th style="width:8%">Kelas</th>
                <th style="width:10%">NIS</th>
                <th style="width:22%">Nama Orang Tua</th>
                <th style="width:24%">Email (untuk login)</th>
                <th style="width:14%">WhatsApp</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['anak'] }}</td>
                    <td>{{ $row['kelas'] }}</td>
                    <td>{{ $row['nis'] }}</td>
                    <td>{{ $row['wali'] }}</td>
                    <td class="email {{ $row['siap'] ? '' : 'belum' }}">{{ $row['email'] }}</td>
                    <td>{{ $row['wa'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center">Belum ada orang tua yang tertaut ke siswa.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Dicetak {{ $printedAt }} &middot; halaman <span class="page-number"></span>
    </div>
</body>
</html>
