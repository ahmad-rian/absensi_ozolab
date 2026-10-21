<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>Laporan absensi peserta</title><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
th, td { border: 1px solid #bbb; padding: 5px; text-align: left; word-break: break-word; }
th { background: #eee; } tr { page-break-inside: avoid; } thead { display: table-header-group; }
.angka { text-align: center; }
section + section { page-break-before: always; }
</style></head>
<body>
<h1>Laporan absensi peserta</h1><p>{{ $filters['start_date'] }} sampai {{ $filters['end_date'] }}@if (! empty($filters['kecamatan'])) · Kecamatan {{ $filters['kecamatan'] }}@endif</p>
<p>Peserta yang belum dicatat tidak dihitung sebagai alpa.</p>
@if (! empty($ringkasan))
<h2>Ringkasan per kecamatan</h2>
<table><thead><tr><th>Kecamatan</th><th class="angka">Peserta</th><th class="angka">Hadir</th><th class="angka">Izin</th><th class="angka">Sakit</th><th class="angka">Alpa</th><th class="angka">Total catatan</th></tr></thead><tbody>
@foreach ($ringkasan as $r)
<tr><td>{{ $r['kecamatan'] }}</td><td class="angka">{{ $r['peserta'] }}</td><td class="angka">{{ $r['hadir'] }}</td><td class="angka">{{ $r['izin'] }}</td><td class="angka">{{ $r['sakit'] }}</td><td class="angka">{{ $r['alpa'] }}</td><td class="angka">{{ $r['total'] }}</td></tr>
@endforeach
</tbody></table>
@endif
@forelse ($sections as $section)
<section><h2>{{ $section['name'] }}</h2><table><thead><tr><th>Tanggal</th><th>Peserta</th><th>Layout</th><th>Status</th><th>Masuk</th><th>Pulang</th><th>Catatan</th></tr></thead><tbody>
@foreach ($section['rows'] as $row)
<tr><td>{{ $row['date'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['layout'] }}</td><td>{{ $row['status'] }}</td><td>{{ $row['check_in'] ?? '—' }}</td><td>{{ $row['check_out'] ?? '—' }}</td><td>{{ $row['note'] ?? '—' }}</td></tr>
@endforeach
</tbody></table></section>
@empty
<p>Belum ada catatan absensi pada periode ini.</p>
@endforelse
</body></html>
