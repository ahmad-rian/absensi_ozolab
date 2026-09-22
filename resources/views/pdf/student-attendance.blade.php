<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Absensi Siswa</title>
    @include('pdf.report-style')
</head>
<body>
    <div class="header">
        <h1>{{ $schoolName }}</h1>
        <h2>Laporan Absensi Siswa</h2>
        <p>Periode: {{ $startDate }} s/d {{ $endDate }}</p>
    </div>

    <table class="identity">
        <tr><td>Nama<br><strong>{{ $student->full_name }}</strong></td><td>Kelas<br><strong>{{ $student->classroom?->name ?? '-' }}</strong></td></tr>
        <tr><td>NIS / NISN<br>{{ $student->nis ?? '-' }} / {{ $student->nisn ?? '-' }}</td><td>Agama<br>{{ $student->religion?->label() ?? '-' }}</td></tr>
    </table>

    <table class="summary">
        <thead>
            <tr>
                <th>Hadir</th>
                <th>Terlambat</th>
                <th>Izin</th>
                <th>Sakit</th>
                <th>Alpa</th>
                <th>Tanpa Catatan</th>
                <th>Hari Efektif</th>
                <th>% Kehadiran</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $summary['hadir'] }}</td>
                <td>{{ $summary['terlambat'] }}</td>
                <td>{{ $summary['izin'] }}</td>
                <td>{{ $summary['sakit'] }}</td>
                <td>{{ $summary['alpa'] }}</td>
                <td>{{ $summary['tanpa_keterangan'] }}</td>
                <td>{{ $summary['effective_days'] }}</td>
                <td>{{ $summary['rate'] }}%</td>
            </tr>
        </tbody>
    </table>

    @isset($punctuality)
        <h3>Ketepatan Waktu</h3>
        <table class="summary" style="margin-bottom:14px">
            <thead>
                <tr>
                    <th>Rata-rata Jam Masuk</th>
                    <th>Paling Awal</th>
                    <th>Paling Akhir</th>
                    <th>Rata-rata Telat</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $punctuality['avg_check_in'] ?? '-' }}</td>
                    <td>{{ $punctuality['earliest'] ?? '-' }}</td>
                    <td>{{ $punctuality['latest'] ?? '-' }}</td>
                    <td>{{ isset($punctuality['avg_late_minutes']) ? $punctuality['avg_late_minutes'].' menit' : '-' }}</td>
                </tr>
            </tbody>
        </table>
    @endisset

    @isset($streaks)
        <h3>Runtun &amp; Perbandingan Kelas</h3>
        <table class="summary" style="margin-bottom:14px">
            <thead>
                <tr>
                    <th>Runtun Hadir Terpanjang</th>
                    <th>Runtun Bolos Terpanjang</th>
                    <th>Terakhir Tidak Hadir</th>
                    <th>Rata-rata Kelas</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $streaks['longest_present'] }} hari</td>
                    <td>{{ $streaks['longest_absent'] }} hari</td>
                    <td>{{ $streaks['last_absent_date'] ?? '-' }}</td>
                    <td>{{ isset($comparison['class_rate']) ? $comparison['class_rate'].'%' : '-' }}</td>
                </tr>
            </tbody>
        </table>
    @endisset

    @if (!empty($byWeekday['series']))
        <h3>Pola per Hari</h3>
        <table class="detail" style="margin-bottom:14px">
            <thead>
                <tr>
                    <th>Hari</th>
                    <th>Hari Efektif</th>
                    <th>Hadir</th>
                    <th>Terlambat</th>
                    <th>Izin</th>
                    <th>Sakit</th>
                    <th>Alpa</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($byWeekday['series'] as $row)
                    <tr>
                        <td>{{ $row['weekday'] }}</td>
                        <td>{{ $row['effective'] }}</td>
                        <td>{{ $row['hadir'] }}</td>
                        <td>{{ $row['terlambat'] }}</td>
                        <td>{{ $row['izin'] }}</td>
                        <td>{{ $row['sakit'] }}</td>
                        <td>{{ $row['alpa'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (!empty($byWeekday['worst_day']))
        <p style="font-size:11px; margin-bottom:10px;">
            Hari dengan keterlambatan tertinggi: <strong>{{ $byWeekday['worst_day'] }}</strong>.
        </p>
    @endif

    <h3>Rincian Catatan</h3>
    <table class="detail">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Status</th>
                <th>Jam</th>
                <th>Perangkat</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recent as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['type_label'] }}</td>
                    <td>{{ in_array($row['status'] ?? '', ['HADIR', 'TERLAMBAT']) ? '●' : '○' }} {{ $row['status_label'] }}</td>
                    <td>{{ $row['time'] ?? '-' }}</td>
                    <td>{{ $row['device_id'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align: center">Tidak ada catatan pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

<div class="footer">Dicetak: {{ $printedAt ?? \App\Support\SchoolTime::now()->format('d/m/Y H:i') }} · Halaman <span class="page-number"></span></div>
</body>
</html>
