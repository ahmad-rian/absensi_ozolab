<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>Laporan absensi peserta</title><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
th, td { border: 1px solid #bbb; padding: 5px; text-align: left; word-break: break-word; }
th { background: #eee; } tr { page-break-inside: avoid; } thead { display: table-header-group; }
section + section { page-break-before: always; }
</style></head>
<body>
<h1>Laporan absensi peserta</h1><p>{{ $filters['start_date'] }} sampai {{ $filters['end_date'] }}</p>
<p>Peserta yang belum dicatat tidak dihitung sebagai alpa.</p>
@forelse ($sections as $section)
<section><h2>{{ $section['name'] }}</h2><table><thead><tr><th>Tanggal</th><th>Peserta</th><th>Status</th><th>Masuk</th><th>Pulang</th><th>Catatan</th></tr></thead><tbody>
@foreach ($section['rows'] as $row)
<tr><td>{{ $row['date'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['status'] }}</td><td>{{ $row['check_in'] ?? '—' }}</td><td>{{ $row['check_out'] ?? '—' }}</td><td>{{ $row['note'] ?? '—' }}</td></tr>
@endforeach
</tbody></table></section>
@empty
<p>Belum ada catatan absensi pada periode ini.</p>
@endforelse
</body></html>
