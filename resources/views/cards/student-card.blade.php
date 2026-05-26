<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter+Tight:wght@600;700;800&display=swap" rel="stylesheet">
<style>
@php
    $isOsis = ($layout->type ?? 'osis') === 'osis';
    $c = $config;

    // Dimensions
    $cardW = $c['card_width'] ?? 813;
    $cardH = $c['card_height'] ?? 513;

    // Header colors
    $hGradStart = $c['header_gradient_start'] ?? ($isOsis ? '#5dc4f5' : '#c9986a');
    $hGradEnd   = $c['header_gradient_end']   ?? ($isOsis ? '#3aa8df' : '#b07b4a');
    $hTextColor = $c['header_text_color']      ?? ($isOsis ? '#06243a' : '#1a1208');

    // Background
    $bgGradStart = $isOsis ? '#e0f3ff' : '#d6b88a';
    $bgGradEnd   = $isOsis ? '#b8e1f7' : '#d4b380';

    // Watermark
    $wmText     = $c['watermark_text'] ?? ($isOsis ? 'ORGANISASI SISWA INTRA SEKOLAH' : 'PERPUSTAKAAN WIDYA SASTRA');
    $showEmblem = $c['show_emblem'] ?? $isOsis;
    $showValidity = $c['show_validity'] ?? $isOsis;
    $validityText = $c['validity_text'] ?? 'BERLAKU S/D TAMAT BELAJAR';

    // Photo
    $photoW = ($c['photo_width_mm'] ?? 16) * 9.5;
    $photoH = ($c['photo_height_mm'] ?? 21) * 9.5;

    // QR
    $qrSize = ($c['qr_size_mm'] ?? 15) * 9.5;
    $showQr = $c['show_qr'] ?? true;

    // Fonts
    $fontFamily = $c['font_family'] ?? 'Manrope';
    $fontSchool = $c['font_school'] ?? 16;
    $fontField  = $c['font_field'] ?? 15;
@endphp

* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    width: {{ $cardW }}px;
    height: {{ $cardH }}px;
    overflow: hidden;
    font-family: '{{ $fontFamily }}', 'Inter Tight', sans-serif;
    position: relative;
    background: linear-gradient(135deg, {{ $bgGradStart }} 0%, {{ $bgGradEnd }} 100%);
}

/* Header band */
.header-band {
    height: {{ $cardH * 0.24 }}px;
    background: linear-gradient(180deg, {{ $hGradStart }} 0%, {{ $hGradEnd }} 100%);
    display: flex;
    align-items: center;
    padding: 0 {{ $cardW * 0.025 }}px;
    gap: {{ $cardW * 0.015 }}px;
    position: relative;
    z-index: 5;
}
.logo-circle {
    width: {{ $cardH * 0.16 }}px;
    height: {{ $cardH * 0.16 }}px;
    border-radius: 50%;
    flex-shrink: 0;
    overflow: hidden;
    background: rgba(255,255,255,0.25);
}
.logo-circle img { width: 100%; height: 100%; object-fit: contain; }
.header-text {
    flex: 1;
    text-align: center;
    color: {{ $hTextColor }};
    line-height: 1.05;
}
.header-text .line-tiny { font-size: {{ $fontSchool * 0.625 }}px; font-weight: 700; }
.header-text .line-small { font-size: {{ $fontSchool * 0.625 }}px; font-weight: 700; letter-spacing: 0.02em; }
.header-text .line-big { font-size: {{ $fontSchool }}px; font-weight: 800; letter-spacing: 0.01em; margin-top: 2px; }
.header-text .line-addr { font-size: {{ $fontSchool * 0.53 }}px; font-weight: 500; margin-top: 3px; line-height: 1.2; }

/* Watermark */
.watermark {
    position: absolute;
    top: {{ $cardH * 0.24 }}px;
    left: 0; right: 0; bottom: 0;
    z-index: 2;
    overflow: hidden;
    pointer-events: none;
    opacity: {{ $isOsis ? '0.12' : '0.08' }};
}
.watermark-row {
    display: flex;
    white-space: nowrap;
    font-family: 'Inter Tight', sans-serif;
    font-weight: 800;
    font-size: {{ $cardW * 0.017 }}px;
    letter-spacing: -0.02em;
    line-height: 1.8;
    color: {{ $isOsis ? 'rgba(0,80,160,0.9)' : 'rgba(100,60,20,0.7)' }};
}
.watermark-row span { padding-right: {{ $cardW * 0.015 }}px; }
.watermark-row:nth-child(even) { transform: translateX(-{{ $cardW * 0.04 }}px); }

/* OSIS emblem */
.osis-emblem {
    position: absolute;
    left: 50%;
    top: {{ $cardH * 0.62 }}px;
    transform: translate(-50%, -50%);
    width: {{ $cardW * 0.27 }}px;
    height: {{ $cardW * 0.27 }}px;
    z-index: 2;
    opacity: 0.1;
    pointer-events: none;
}
.osis-emblem svg { width: 100%; height: 100%; }

/* Body fields */
.body-area {
    position: relative;
    z-index: 5;
    padding: {{ $cardH * 0.02 }}px {{ $cardW * 0.03 }}px 0;
}
.field-row {
    display: flex;
    font-size: {{ $fontField }}px;
    font-weight: 800;
    line-height: 1.28;
    letter-spacing: -0.005em;
    color: #0c0c14;
    margin-bottom: {{ $cardH * 0.002 }}px;
}
.field-label { width: {{ $cardW * 0.3 }}px; flex-shrink: 0; }
.field-sep { width: {{ $cardW * 0.02 }}px; text-align: left; flex-shrink: 0; }
.field-value {
    flex: 1;
    font-weight: 700;
    font-size: {{ $fontField - 0.5 }}px;
    letter-spacing: -0.012em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Validity text */
.validity-text {
    position: absolute;
    left: 50%;
    top: {{ $cardH * 0.545 }}px;
    transform: translateX(-50%);
    font-family: 'Inter Tight', sans-serif;
    font-weight: 800;
    font-size: {{ $cardW * 0.017 }}px;
    letter-spacing: 0.02em;
    z-index: 6;
    color: #0c0c14;
}

/* Bottom row */
.bottom-row {
    position: absolute;
    left: {{ $cardW * 0.03 }}px;
    right: {{ $cardW * 0.025 }}px;
    top: {{ $cardH * 0.6 }}px;
    bottom: {{ $cardH * 0.04 }}px;
    z-index: 6;
    display: grid;
    grid-template-columns: auto auto 1fr;
    gap: {{ $cardW * 0.018 }}px;
    align-items: center;
}
.photo-slot {
    width: {{ $photoW }}px;
    height: {{ $photoH }}px;
    border-radius: 3px;
    overflow: hidden;
    background: rgba(255,255,255,0.35);
    border: 1.5px dashed rgba(0,0,0,0.3);
}
.photo-slot.filled {
    background: #0a0a0f;
    border: none;
}
.photo-slot img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center top;
    display: block;
}
.photo-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    color: rgba(0,0,0,0.3);
}
.qr-slot {
    width: {{ $qrSize }}px;
    height: {{ $qrSize }}px;
    background: #fff;
    border-radius: 3px;
    padding: 3px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.08);
}
.qr-slot svg { width: 100%; height: 100%; display: block; }
.signature-area {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 3px;
    font-family: 'Inter Tight', sans-serif;
    font-weight: 800;
    font-size: {{ $cardW * 0.014 }}px;
    color: rgba(0,0,0,0.4);
    letter-spacing: 0.01em;
}
</style>
</head>
<body>
    <!-- Header Band -->
    <div class="header-band">
        <div class="logo-circle">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="Logo">
            @endif
        </div>
        <div class="header-text">
            @if($isOsis)
                <div class="line-tiny">{{ $school->getSetting('header_line1', 'PEMERINTAH KABUPATEN') }}</div>
                <div class="line-small">{{ $school->getSetting('header_line2', 'DINAS PENDIDIKAN') }}</div>
            @else
                <div class="line-tiny">{{ $school->getSetting('perpus_line1', 'PERPUSTAKAAN') }}</div>
            @endif
            <div class="line-big">{{ strtoupper($school->name) }}</div>
            <div class="line-addr">{{ $school->address ?? '' }}</div>
        </div>
        <div class="logo-circle">
            {{-- Tut Wuri Handayani / second logo placeholder --}}
        </div>
    </div>

    <!-- Watermark -->
    <div class="watermark">
        @for($i = 0; $i < 12; $i++)
            <div class="watermark-row">
                @for($j = 0; $j < 5; $j++)
                    <span>{{ $wmText }}</span>
                @endfor
            </div>
        @endfor
    </div>

    <!-- OSIS Emblem -->
    @if($showEmblem)
        <div class="osis-emblem">
            <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <circle cx="50" cy="50" r="48" fill="none" stroke="#1565a0" stroke-width="1"/>
                <path d="M50 8 L42 30 L18 30 L37 44 L30 68 L50 54 L70 68 L63 44 L82 30 L58 30 Z" fill="none" stroke="#1565a0" stroke-width="1"/>
                <text x="50" y="92" font-family="Inter Tight" font-weight="800" font-size="10" text-anchor="middle" fill="#1565a0">OSIS</text>
            </svg>
        </div>
    @endif

    <!-- Body Fields -->
    <div class="body-area">
        <div class="field-row">
            <span class="field-label">NAMA</span>
            <span class="field-sep">:</span>
            <span class="field-value">{{ strtoupper($student->full_name) }}</span>
        </div>
        <div class="field-row">
            <span class="field-label">ALAMAT</span>
            <span class="field-sep">:</span>
            <span class="field-value">{{ $student->address ?? '—' }}</span>
        </div>
        <div class="field-row">
            <span class="field-label">TEMPAT TGL.LAHIR</span>
            <span class="field-sep">:</span>
            <span class="field-value">{{ ($student->birth_place ?? '') }}{{ $student->birth_date ? ', ' . $student->birth_date->translatedFormat('d F Y') : '' }}</span>
        </div>
        <div class="field-row">
            <span class="field-label">AGAMA</span>
            <span class="field-sep">:</span>
            <span class="field-value">{{ $student->religion?->label() ?? '—' }}</span>
        </div>
        <div class="field-row">
            <span class="field-label">NO.INDUK</span>
            <span class="field-sep">:</span>
            <span class="field-value">{{ $student->nis ?? '—' }}</span>
        </div>
    </div>

    <!-- Validity Text (OSIS only) -->
    @if($showValidity)
        <div class="validity-text">{{ $validityText }}</div>
    @endif

    <!-- Bottom Row: Photo + QR + Signature -->
    <div class="bottom-row">
        <div class="photo-slot {{ $photoUrl ? 'filled' : '' }}">
            @if($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $student->full_name }}">
            @else
                <div class="photo-placeholder">👤</div>
            @endif
        </div>

        @if($showQr)
            <div class="qr-slot">{!! $qrSvg !!}</div>
        @endif

        <div class="signature-area">
            KEPALA SEKOLAH
        </div>
    </div>
</body>
</html>
