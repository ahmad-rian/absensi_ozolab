<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kehadiran</title>
    @include('pdf.report-style')
    <style>
        .kelas-baru { page-break-before: always; }
        .kelas-judul { font-size: 14px; margin: 0 0 10px; }
    </style>
</head>
<body>
    @php($sholat = in_array($reportKind ?? '', ['dhuha', 'dzuhur']))

    {{-- Satu bagian per kelas, tiap kelas mulai di halaman baru: wali kelas mencetak dan membagikannya per kelas. --}}
    @foreach ($kelompok as $kelas => $rows)
        <div class="{{ $loop->first ? '' : 'kelas-baru' }}">
            <div class="header">
                <h1>{{ $schoolName }}</h1>
                <h2>Laporan {{ ($reportKind ?? 'absensi') === 'semuanya' ? 'Kehadiran & Sholat' : ($kinds[$reportKind ?? 'absensi'] ?? 'Kehadiran') }}</h2>
                <p>Periode: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</p>
            </div>

            <h3 class="kelas-judul">{{ $kelas === \App\Support\KelompokKelas::TANPA_KELAS ? $kelas : 'Kelas '.$kelas }} · {{ $rows->count() }} siswa</h3>

            <table>
                <thead>
                    <tr>
                        <th class="text-center" style="width: 30px;">No</th>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th class="text-center">Hadir</th>
                        <th class="text-center">Terlambat</th>
                        <th class="text-center">Izin</th>
                        <th class="text-center">Sakit</th>
                        <th class="text-center">{{ $sholat ? 'Tidak Ikut' : 'Alpa' }}</th>
                        <th class="text-center">% Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $index => $row)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>{{ $row['nis'] }}</td>
                            <td>{{ $row['full_name'] }}</td>
                            <td class="text-center">{{ $row['hadir'] }}</td>
                            <td class="text-center">{{ $row['terlambat'] }}</td>
                            <td class="text-center">{{ $row['izin'] }}</td>
                            <td class="text-center">{{ $row['sakit'] }}</td>
                            <td class="text-center">{{ $row['alpa'] }}</td>
                            <td class="text-center">{{ $row['attendance_rate'] }}%</td>
                        </tr>
                    @endforeach
                    <tr class="summary-row">
                        <td colspan="3" class="text-right">Total kelas</td>
                        <td class="text-center">{{ $rows->sum('hadir') }}</td>
                        <td class="text-center">{{ $rows->sum('terlambat') }}</td>
                        <td class="text-center">{{ $rows->sum('izin') }}</td>
                        <td class="text-center">{{ $rows->sum('sakit') }}</td>
                        <td class="text-center">{{ $rows->sum('alpa') }}</td>
                        <td class="text-center">-</td>
                    </tr>
                </tbody>
            </table>

            @if (($reportKind ?? '') === 'semuanya')
                @foreach ($kinds as $slug => $label)
                    @if ($slug !== 'absensi')
                        <h3>{{ $label }}</h3>
                        <table>
                            <thead><tr><th>NIS</th><th>Nama</th><th>Ikut</th><th>Tidak ikut</th><th>Hari efektif</th><th>Kehadiran</th></tr></thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr><td>{{ $row['nis'] }}</td><td>{{ $row['full_name'] }}</td><td>{{ $row['prayers'][$slug]['hadir'] }}</td><td>{{ $row['prayers'][$slug]['tidak_hadir'] }}</td><td>{{ $row['prayers'][$slug]['effective_days'] }}</td><td>{{ $row['prayers'][$slug]['rate'] }}%</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                @endforeach
            @endif
        </div>
    @endforeach

    <div class="{{ $kelompok->isEmpty() ? '' : 'kelas-baru' }}">
        @if ($kelompok->isEmpty())
            <div class="header">
                <h1>{{ $schoolName }}</h1>
                <p>Periode: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</p>
            </div>
            <p>Belum ada siswa pada filter ini.</p>
        @else
            <h3 class="kelas-judul">Ringkasan seluruh kelas</h3>
            <table class="summary">
                <tr><th>Hadir</th><th>Terlambat</th><th>Izin</th><th>Sakit</th><th>{{ $sholat ? 'Tidak Ikut' : 'Alpa' }}</th></tr>
                <tr>
                    <td>{{ $summary['total_hadir'] }}</td>
                    <td>{{ $summary['total_terlambat'] }}</td>
                    <td>{{ $summary['total_izin'] }}</td>
                    <td>{{ $summary['total_sakit'] }}</td>
                    <td>{{ $summary['total_alpa'] }}</td>
                </tr>
            </table>
        @endif
    </div>

<div class="footer">Dicetak: {{ $printedAt ?? \App\Support\SchoolTime::now()->format('d/m/Y H:i') }} · Halaman <span class="page-number"></span></div>
</body>
</html>
