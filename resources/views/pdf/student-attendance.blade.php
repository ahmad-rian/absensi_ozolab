<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Absensi Siswa</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 18px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { font-size: 18px; margin-bottom: 2px; }
        .header h2 { font-size: 14px; font-weight: normal; margin-bottom: 4px; }
        .header p { font-size: 11px; color: #555; }
        .identity { width: 100%; margin-bottom: 14px; }
        .identity td { padding: 3px 0; font-size: 11px; border: 0; }
        .identity td:first-child { width: 130px; color: #555; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .summary th, .summary td { border: 1px solid #999; padding: 6px 8px; text-align: center; }
        .summary th { background-color: #f0f0f0; font-size: 10px; text-transform: uppercase; }
        .summary td { font-size: 13px; font-weight: bold; }
        table.detail { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.detail th, table.detail td { border: 1px solid #999; padding: 5px 8px; text-align: left; }
        table.detail th { background-color: #f0f0f0; font-weight: bold; font-size: 10px; text-transform: uppercase; }
        table.detail td { font-size: 11px; }
        h3 { font-size: 12px; margin-bottom: 4px; }
        .footer { margin-top: 22px; font-size: 10px; color: #777; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $schoolName }}</h1>
        <h2>Laporan Absensi Siswa</h2>
        <p>Periode: {{ $startDate }} s/d {{ $endDate }}</p>
    </div>

    <table class="identity">
        <tr><td>Nama</td><td>: {{ $student->full_name }}</td></tr>
        <tr><td>NIS / NISN</td><td>: {{ $student->nis ?? '-' }} / {{ $student->nisn ?? '-' }}</td></tr>
        <tr><td>Kelas</td><td>: {{ $student->classroom?->name ?? '-' }}</td></tr>
        <tr><td>Agama</td><td>: {{ $student->religion?->label() ?? '-' }}</td></tr>
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
                    <td>{{ $row['status_label'] }}</td>
                    <td>{{ $row['time'] ?? '-' }}</td>
                    <td>{{ $row['device_id'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align: center">Tidak ada catatan pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">Dicetak pada: {{ $printedAt }}</p>
</body>
</html>
