<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kehadiran</title>
    @include('pdf.report-style')
</head>
<body>
    <div class="header">
        <h1>{{ $schoolName }}</h1>
        <h2>Laporan {{ ($reportKind ?? 'absensi') === 'semuanya' ? 'Kehadiran & Sholat' : ($kinds[$reportKind ?? 'absensi'] ?? 'Kehadiran') }}</h2>
        <p>Periode: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 30px;">No</th>
                <th>NIS</th>
                <th>Nama Siswa</th>
                <th>Kelas</th>
                <th class="text-center">Hadir</th>
                <th class="text-center">Terlambat</th>
                <th class="text-center">Izin</th>
                <th class="text-center">Sakit</th>
                <th class="text-center">{{ in_array($reportKind ?? '', ['dhuha', 'dzuhur']) ? 'Tidak Ikut' : 'Alpa' }}</th>
                <th class="text-center">% Kehadiran</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reportData as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $row['nis'] }}</td>
                    <td>{{ $row['full_name'] }}</td>
                    <td>{{ $row['classroom_name'] }}</td>
                    <td class="text-center">{{ $row['hadir'] }}</td>
                    <td class="text-center">{{ $row['terlambat'] }}</td>
                    <td class="text-center">{{ $row['izin'] }}</td>
                    <td class="text-center">{{ $row['sakit'] }}</td>
                    <td class="text-center">{{ $row['alpa'] }}</td>
                    <td class="text-center">{{ $row['attendance_rate'] }}%</td>
                </tr>
            @endforeach
            <tr class="summary-row">
                <td colspan="4" class="text-right">Total</td>
                <td class="text-center">{{ $summary['total_hadir'] }}</td>
                <td class="text-center">{{ $summary['total_terlambat'] }}</td>
                <td class="text-center">{{ $summary['total_izin'] }}</td>
                <td class="text-center">{{ $summary['total_sakit'] }}</td>
                <td class="text-center">{{ $summary['total_alpa'] }}</td>
                <td class="text-center">-</td>
            </tr>
        </tbody>
    </table>

    @if (($reportKind ?? '') === 'semuanya')
    @foreach ($kinds as $slug => $label)
    @if ($slug !== 'absensi')
    <h3>{{ $label }}</h3><table><thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Ikut</th><th>Tidak ikut</th><th>Hari efektif</th><th>Kehadiran</th></tr></thead><tbody>@foreach ($reportData as $row)<tr><td>{{ $row['nis'] }}</td><td>{{ $row['full_name'] }}</td><td>{{ $row['classroom_name'] }}</td><td>{{ $row['prayers'][$slug]['hadir'] }}</td><td>{{ $row['prayers'][$slug]['tidak_hadir'] }}</td><td>{{ $row['prayers'][$slug]['effective_days'] }}</td><td>{{ $row['prayers'][$slug]['rate'] }}%</td></tr>@endforeach</tbody></table>
    @endif
    @endforeach
    @endif

<div class="footer">Dicetak: {{ $printedAt ?? \App\Support\SchoolTime::now()->format('d/m/Y H:i') }} · Halaman <span class="page-number"></span></div>
</body>
</html>
