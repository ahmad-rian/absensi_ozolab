<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Absen Sholat</title>
    @include('pdf.report-style')
</head>
<body>
    <div class="header">
        <h1>{{ $schoolName }}</h1>
        <h2>Laporan Absen {{ $title ?? 'Sholat Dzuhur' }}</h2>
        <p>Periode: {{ $startDate }} s/d {{ $endDate }}@if ($window) · Jendela absen {{ $window }}@endif</p>
    </div>

    <table class="identity">
        <tr><td>Nama<br><strong>{{ $student->full_name }}</strong></td><td>Kelas<br><strong>{{ $student->classroom?->name ?? '-' }}</strong></td></tr>
        <tr><td>NIS / NISN<br>{{ $student->nis ?? '-' }} / {{ $student->nisn ?? '-' }}</td><td>Agama<br>{{ $student->religion?->label() ?? '-' }}</td></tr>
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
