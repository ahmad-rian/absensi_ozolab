<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $judul }}</title>
    @include('pdf.report-style')
    <style>
        .bagian-baru { page-break-before: always; }
        .judul-bagian { font-size: 14px; margin: 0 0 10px; }
    </style>
</head>
<body>
    @if ($mode === 'grup')
        @if (! empty($ringkasan))
            <div class="header">
                <h1>{{ $penyelenggara }}</h1>
                <h2>{{ $judul }} · {{ $program }}</h2>
                @if ($dari)<p>Periode: {{ \Carbon\Carbon::parse($dari)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($sampai)->format('d/m/Y') }}</p>@endif
            </div>
            <h3 class="judul-bagian">Ringkasan per kecamatan</h3>
            <table>
                <thead>
                    <tr>
                        <th>Kecamatan</th>
                        <th class="text-center">Peserta</th>
                        @foreach ($status as $s)<th class="text-center">{{ $s === 'BELUM' ? 'Belum' : ucfirst(strtolower($s)) }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ringkasan as $r)
                        <tr>
                            <td>{{ $r['kecamatan'] }}</td>
                            <td class="text-center">{{ $r['peserta'] }}</td>
                            @foreach ($status as $s)<td class="text-center">{{ $r['hitung'][$s] }}</td>@endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @foreach ($bagian as $grup => $rows)
            <div class="{{ $loop->first && empty($ringkasan) ? '' : 'bagian-baru' }}">
                <div class="header">
                    <h1>{{ $penyelenggara }}</h1>
                    <h2>{{ $judul }} · {{ $program }}</h2>
                    @if ($dari)<p>Periode: {{ \Carbon\Carbon::parse($dari)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($sampai)->format('d/m/Y') }}</p>@endif
                </div>
                <h3 class="judul-bagian">{{ $grup }} · {{ $rows->count() }} peserta</h3>
                <table>
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 30px;">No</th>
                            <th>Nama</th>
                            @foreach ($kolom ?? [] as $k)<th>{{ $k['label'] }}</th>@endforeach
                            @foreach ($status as $s)<th class="text-center">{{ $s === 'BELUM' ? 'Belum' : ucfirst(strtolower($s)) }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $i => $row)
                            <tr>
                                <td class="text-center">{{ $i + 1 }}</td>
                                <td>{{ $row['nama'] }}</td>
                                @foreach ($row['identitas'] ?? [] as $nilai)<td>{{ $nilai }}</td>@endforeach
                                @foreach ($status as $s)<td class="text-center">{{ $row['hitung'][$s] }}</td>@endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @else
        <div class="header">
            <h1>{{ $penyelenggara }}</h1>
            <h2>{{ $judul }} · {{ $program }}</h2>
            @if ($dari)<p>Periode: {{ \Carbon\Carbon::parse($dari)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($sampai)->format('d/m/Y') }}</p>@endif
        </div>
        <table>
            <thead><tr>@foreach ($header as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
