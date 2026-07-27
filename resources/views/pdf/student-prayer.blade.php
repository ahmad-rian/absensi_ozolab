<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Absen Sholat</title>
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
        <h2>Laporan Absen {{ $title ?? 'Sholat Dzuhur' }}</h2>
        <p>Periode: {{ $startDate }} s/d {{ $endDate }}@if ($window) · Jendela absen {{ $window }}@endif</p>
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
                <th>Ikut Sholat</th>
                <th>Tidak Ikut</th>
                <th>Hari Efektif</th>
                <th>% Kehadiran</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $summary['hadir'] }}</td>
                <td>{{ $summary['tidak_hadir'] }}</td>
                <td>{{ $summary['effective_days'] }}</td>
                <td>{{ $summary['rate'] }}%</td>
            </tr>
        </tbody>
    </table>

    @if (!empty($types) && count($types) > 1)
        <h3>Rincian per Jenis Sholat</h3>
        <table class="detail" style="margin-bottom:14px">
            <thead>
                <tr>
                    <th>Jenis</th>
                    <th>Jendela</th>
                    <th>Ikut</th>
                    <th>Tidak Ikut</th>
                    <th>% Kehadiran</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($types as $type)
                    <tr>
                        <td>{{ $type['label'] }}</td>
                        <td>{{ $type['window'] }}</td>
                        <td>{{ $type['summary']['hadir'] }}</td>
                        <td>{{ $type['summary']['tidak_hadir'] }}</td>
                        <td>{{ $type['summary']['rate'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
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
                    <td>{{ $row['type_label'] ?? '-' }}</td>
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
