<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kehadiran</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 18px;
            margin-bottom: 2px;
        }
        .header h2 {
            font-size: 14px;
            font-weight: normal;
            margin-bottom: 4px;
        }
        .header p {
            font-size: 11px;
            color: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #999;
            padding: 5px 8px;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }
        td {
            font-size: 11px;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .summary-row td {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 15px;
            font-size: 9px;
            color: #777;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $schoolName }}</h1>
        <h2>Laporan Kehadiran</h2>
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
                <th class="text-center">Alpa</th>
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

    <div class="footer">
        Dicetak pada: {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
