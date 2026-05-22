<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @php
            $paperSizes = [
                'A4' => ['portrait' => [794, 1123], 'landscape' => [1123, 794]],
                'A3' => ['portrait' => [1123, 1587], 'landscape' => [1587, 1123]],
                'Letter' => ['portrait' => [816, 1056], 'landscape' => [1056, 816]],
            ];
            $size = $paperSizes[$layout->paper_size][$layout->orientation] ?? $paperSizes['A4']['portrait'];
        @endphp
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            width: {{ $size[0] }}px;
            height: {{ $size[1] }}px;
            background: {{ $config['bg_color'] ?? '#ffffff' }};
            padding: {{ $config['page_padding'] ?? '30px' }};
            overflow: hidden;
        }
        .header {
            text-align: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid {{ $config['header_border_color'] ?? '#3b82f6' }};
        }
        .header h1 {
            font-size: {{ $config['header_size'] ?? 18 }}px;
            color: {{ $config['header_color'] ?? '#1a1a2e' }};
            font-weight: 700;
        }
        .header p {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat({{ $layout->columns }}, 1fr);
            gap: {{ $config['grid_gap'] ?? '12' }}px;
        }
        .student-cell {
            text-align: center;
            padding: {{ $config['cell_padding'] ?? '8px' }};
            border: 1px solid {{ $config['cell_border_color'] ?? '#e5e7eb' }};
            border-radius: {{ $config['cell_radius'] ?? 8 }}px;
            background: {{ $config['cell_bg'] ?? '#fafafa' }};
        }
        .student-photo {
            width: {{ $config['photo_size'] ?? 80 }}px;
            height: {{ ($config['photo_size'] ?? 80) * 1.3 }}px;
            border-radius: {{ $config['photo_radius'] ?? 6 }}px;
            object-fit: cover;
            margin: 0 auto 6px;
            border: 2px solid {{ $config['photo_border_color'] ?? '#cbd5e1' }};
            display: block;
        }
        .photo-placeholder {
            width: {{ $config['photo_size'] ?? 80 }}px;
            height: {{ ($config['photo_size'] ?? 80) * 1.3 }}px;
            border-radius: {{ $config['photo_radius'] ?? 6 }}px;
            margin: 0 auto 6px;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 24px;
        }
        .student-name {
            font-size: {{ $config['name_size'] ?? 11 }}px;
            font-weight: 600;
            color: #1f2937;
            line-height: 1.3;
        }
        .student-info {
            font-size: {{ $config['info_size'] ?? 9 }}px;
            color: #6b7280;
            margin-top: 2px;
        }
        .footer {
            position: absolute;
            bottom: 15px;
            left: 30px;
            right: 30px;
            text-align: center;
            font-size: 9px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $config['title'] ?? $school->name }}</h1>
        <p>{{ $config['subtitle'] ?? 'Album Foto Siswa' }} — Halaman {{ $pageNumber }} dari {{ $totalPages }}</p>
    </div>

    <div class="grid">
        @foreach($students as $student)
            <div class="student-cell">
                @if($student->photo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($student->photo_path) }}"
                         alt="{{ $student->full_name }}"
                         class="student-photo">
                @else
                    <div class="photo-placeholder">👤</div>
                @endif
                <div class="student-name">{{ $student->full_name }}</div>
                <div class="student-info">
                    {{ $student->nis ?? '-' }}
                    @if($student->classroom)
                        · {{ $student->classroom->name }}
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="footer">{{ $school->name }} — Dicetak {{ now()->format('d M Y') }}</div>
</body>
</html>
