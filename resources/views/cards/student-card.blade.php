<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            width: {{ $config['card_width'] ?? 638 }}px;
            height: {{ $config['card_height'] ?? 1011 }}px;
            overflow: hidden;
            position: relative;
            background: {{ $config['bg_color'] ?? '#ffffff' }};
        }
        .card-frame {
            position: absolute;
            inset: 0;
            z-index: 1;
            pointer-events: none;
        }
        .card-frame img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .card-content {
            position: relative;
            z-index: 2;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: {{ $config['padding'] ?? '40px 30px' }};
        }
        .school-header {
            text-align: center;
            margin-bottom: {{ $config['header_margin'] ?? '20' }}px;
        }
        .school-logo {
            width: {{ $config['logo_size'] ?? 60 }}px;
            height: {{ $config['logo_size'] ?? 60 }}px;
            object-fit: contain;
            margin-bottom: 8px;
        }
        .school-name {
            font-size: {{ $config['school_name_size'] ?? 16 }}px;
            font-weight: 700;
            color: {{ $config['school_name_color'] ?? '#1a1a2e' }};
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .card-type {
            font-size: {{ $config['card_type_size'] ?? 14 }}px;
            font-weight: 600;
            color: {{ $config['card_type_color'] ?? '#4a4a8a' }};
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .photo-container {
            width: {{ $config['photo_size'] ?? 180 }}px;
            height: {{ ($config['photo_size'] ?? 180) * 1.3 }}px;
            border-radius: {{ $config['photo_radius'] ?? 12 }}px;
            overflow: hidden;
            border: 3px solid {{ $config['photo_border_color'] ?? '#3b82f6' }};
            margin: {{ $config['photo_margin'] ?? '16px 0' }};
            background: #f0f0f0;
        }
        .photo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .photo-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            color: #94a3b8;
            font-size: 48px;
        }
        .student-name {
            font-size: {{ $config['name_size'] ?? 22 }}px;
            font-weight: 700;
            color: {{ $config['name_color'] ?? '#1a1a2e' }};
            text-align: center;
            margin-bottom: 4px;
        }
        .info-table {
            margin-top: 12px;
            width: 100%;
            max-width: 80%;
        }
        .info-row {
            display: flex;
            padding: 4px 0;
            font-size: {{ $config['info_size'] ?? 13 }}px;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-label {
            width: 40%;
            color: #6b7280;
            font-weight: 500;
        }
        .info-value {
            width: 60%;
            color: #1f2937;
            font-weight: 600;
        }
        .qr-section {
            margin-top: auto;
            text-align: center;
        }
        .qr-code {
            width: {{ $config['qr_size'] ?? 100 }}px;
            height: {{ $config['qr_size'] ?? 100 }}px;
        }
        .qr-code svg {
            width: 100%;
            height: 100%;
        }
        .watermark {
            position: absolute;
            bottom: 10px;
            right: 15px;
            font-size: 9px;
            color: #ccc;
            z-index: 3;
        }
    </style>
</head>
<body>
    @if($frameUrl)
        <div class="card-frame">
            <img src="{{ $frameUrl }}" alt="Frame">
        </div>
    @endif

    <div class="card-content">
        <div class="school-header">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $school->name }}" class="school-logo">
            @endif
            <div class="school-name">{{ $school->name }}</div>
            <div class="card-type">
                @php
                    $typeLabels = [
                        'osis' => 'Kartu OSIS',
                        'perpustakaan' => 'Kartu Perpustakaan',
                        'identitas' => 'Kartu Identitas Siswa',
                    ];
                @endphp
                {{ $typeLabels[$layout->type] ?? strtoupper($layout->type) }}
            </div>
        </div>

        <div class="photo-container">
            @if($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $student->full_name }}">
            @else
                <div class="photo-placeholder">👤</div>
            @endif
        </div>

        <div class="student-name">{{ $student->full_name }}</div>

        <div class="info-table">
            @if($student->nis)
                <div class="info-row">
                    <span class="info-label">NIS</span>
                    <span class="info-value">{{ $student->nis }}</span>
                </div>
            @endif
            @if($student->nisn)
                <div class="info-row">
                    <span class="info-label">NISN</span>
                    <span class="info-value">{{ $student->nisn }}</span>
                </div>
            @endif
            @if($student->classroom)
                <div class="info-row">
                    <span class="info-label">Kelas</span>
                    <span class="info-value">{{ $student->classroom->name }}</span>
                </div>
            @endif
            @if($student->no_absen)
                <div class="info-row">
                    <span class="info-label">No. Absen</span>
                    <span class="info-value">{{ $student->no_absen }}</span>
                </div>
            @endif
            @if(($config['show_address'] ?? false) && $student->address)
                <div class="info-row">
                    <span class="info-label">Alamat</span>
                    <span class="info-value">{{ \Illuminate\Support\Str::limit($student->address, 50) }}</span>
                </div>
            @endif
        </div>

        @if($config['show_qr'] ?? true)
            <div class="qr-section">
                <div class="qr-code">{!! $qrSvg !!}</div>
            </div>
        @endif
    </div>

    @if($config['show_watermark'] ?? false)
        <div class="watermark">{{ $config['watermark_text'] ?? $school->name }}</div>
    @endif
</body>
</html>
