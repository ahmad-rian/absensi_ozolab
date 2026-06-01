<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter+Tight:wght@500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
@php
    $isOsis = ($layout->type ?? 'osis') === 'osis';
    $c = $config;

    // Header colors
    $hGradStart = $c['header_gradient_start'] ?? ($isOsis ? '#5dc4f5' : '#c9986a');
    $hGradEnd   = $c['header_gradient_end']   ?? ($isOsis ? '#3aa8df' : '#b07b4a');
    $hTextColor = $c['header_text_color']      ?? ($isOsis ? '#06243a' : '#1a1208');

    // Watermark
    $wmText       = $c['watermark_text'] ?? ($isOsis ? 'ORGANISASI SISWA INTRA SEKOLAH' : 'PERPUSTAKAAN WIDYA SASTRA');
    $showEmblem   = $c['show_emblem'] ?? $isOsis;
    $showValidity = $c['show_validity'] ?? $isOsis;
    $validityText = $c['validity_text'] ?? 'BERLAKU S/D TAMAT BELAJAR';
    $showQr       = $c['show_qr'] ?? true;
@endphp

:root {
    --mm: 9.5px;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    width: calc(85.6 * var(--mm));
    height: calc(54 * var(--mm));
    overflow: hidden;
    font-family: 'Manrope', sans-serif;
    position: relative;
    @if($isOsis)
    background:
        radial-gradient(ellipse at 70% 30%, rgba(255,255,255,0.5) 0%, transparent 60%),
        linear-gradient(135deg, #e0f3ff 0%, #c7e9fb 50%, #b8e1f7 100%);
    @else
    background:
        radial-gradient(ellipse at 30% 20%, rgba(255,255,255,0.4) 0%, transparent 50%),
        linear-gradient(135deg, #d6b88a 0%, #e8d2a8 40%, #d4b380 100%);
    @endif
}

/* Perpus texture overlays */
@unless($isOsis)
body::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse at 10% 80%, rgba(139,90,43,0.25) 0%, transparent 40%),
        radial-gradient(ellipse at 90% 60%, rgba(139,90,43,0.18) 0%, transparent 50%);
    z-index: 1;
}
body::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        repeating-linear-gradient(95deg, transparent 0, transparent 7px, rgba(139,90,43,0.06) 7px, rgba(139,90,43,0.06) 8px);
    z-index: 1;
}
@endunless

/* Header band */
.header-band {
    position: relative;
    z-index: 5;
    height: calc(13 * var(--mm));
    background: linear-gradient(180deg, {{ $hGradStart }} 0%, {{ $hGradEnd }} 100%);
    display: flex;
    align-items: center;
    padding: 0 calc(2 * var(--mm));
    gap: calc(1.2 * var(--mm));
}
.logo-circle {
    width: calc(8.5 * var(--mm));
    height: calc(8.5 * var(--mm));
    border-radius: 50%;
    flex-shrink: 0;
    overflow: hidden;
    background: rgba(255,255,255,0.25);
    display: flex;
    align-items: center;
    justify-content: center;
}
.logo-circle img { width: 100%; height: 100%; object-fit: contain; border-radius: 50%; }
.header-text {
    flex: 1;
    text-align: center;
    font-family: 'Inter Tight', sans-serif;
    color: {{ $hTextColor }};
    line-height: 1.05;
    min-width: 0;
}
.header-text .line-tiny { font-size: calc(1.0 * var(--mm)); font-weight: 700; }
.header-text .line-small { font-size: calc(1.1 * var(--mm)); font-weight: 700; letter-spacing: 0.02em; }
.header-text .line-big { font-size: calc(1.7 * var(--mm)); font-weight: 800; letter-spacing: 0.01em; margin-top: 2px; }
.header-text .line-addr { font-size: calc(0.85 * var(--mm)); font-weight: 500; font-family: 'Manrope', sans-serif; margin-top: 3px; line-height: 1.2; }

/* Watermark */
.watermark {
    position: absolute;
    inset: calc(13 * var(--mm)) 0 0 0;
    z-index: 2;
    overflow: hidden;
    pointer-events: none;
    opacity: 0.35;
}
.watermark-row {
    display: flex;
    white-space: nowrap;
    font-family: 'Inter Tight', sans-serif;
    font-weight: 800;
    font-size: calc(1.4 * var(--mm));
    letter-spacing: -0.02em;
    line-height: 1.6;
    @if($isOsis)
    color: rgba(255,255,255,0.85);
    @else
    color: rgba(255,255,255,0.5);
    @endif
}
.watermark-row span { padding-right: calc(1.2 * var(--mm)); }
.watermark-row:nth-child(even) { transform: translateX(calc(-3 * var(--mm))); }

/* OSIS emblem */
.osis-emblem {
    position: absolute;
    left: 50%;
    top: calc(32 * var(--mm));
    transform: translate(-50%, -50%);
    width: calc(22 * var(--mm));
    height: calc(22 * var(--mm));
    z-index: 2;
    opacity: 0.18;
    pointer-events: none;
}
.osis-emblem svg { width: 100%; height: 100%; }

/* Body fields */
.body-area {
    position: relative;
    z-index: 5;
    padding: calc(1.5 * var(--mm)) calc(2.5 * var(--mm)) 0;
}
.field-row {
    display: flex;
    font-family: 'Inter Tight', sans-serif;
    font-size: calc(1.1 * var(--mm));
    font-weight: 700;
    line-height: 1.45;
    letter-spacing: 0.005em;
    color: #0c0c14;
}
.field-label {
    width: calc(21 * var(--mm));
    flex-shrink: 0;
}
.field-sep {
    width: calc(2 * var(--mm));
    text-align: center;
    flex-shrink: 0;
}
.field-value {
    flex: 1;
    font-weight: 600;
    font-family: 'Manrope', sans-serif;
    font-size: calc(1.1 * var(--mm));
    letter-spacing: -0.005em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Validity text */
.validity-text {
    position: absolute;
    left: 50%;
    top: calc(28 * var(--mm));
    transform: translateX(-50%);
    font-family: 'Inter Tight', sans-serif;
    font-weight: 800;
    font-size: calc(1.4 * var(--mm));
    letter-spacing: 0.02em;
    z-index: 6;
    color: #0c0c14;
}

/* Bottom row */
.bottom-row {
    position: absolute;
    left: calc(2.5 * var(--mm));
    right: calc(2 * var(--mm));
    top: calc(31 * var(--mm));
    bottom: calc(2 * var(--mm));
    z-index: 6;
    display: grid;
    grid-template-columns: auto auto 1fr;
    gap: calc(1.5 * var(--mm));
    align-items: center;
}
.photo-slot {
    width: calc(16 * var(--mm));
    height: calc(21 * var(--mm));
    border-radius: calc(0.4 * var(--mm));
    overflow: hidden;
    background: rgba(255,255,255,0.35);
    border: 1.5px dashed rgba(0,0,0,0.35);
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
    font-size: calc(3 * var(--mm));
    color: rgba(0,0,0,0.3);
}
.qr-slot {
    width: calc(15 * var(--mm));
    height: calc(15 * var(--mm));
    background: #fff;
    border-radius: calc(0.4 * var(--mm));
    padding: calc(0.35 * var(--mm));
    box-shadow: 0 1px 2px rgba(0,0,0,0.08);
    align-self: center;
}
.qr-slot svg { width: 100%; height: 100%; display: block; }
.signature-area {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: calc(0.3 * var(--mm));
    border: 1.5px dashed rgba(0,0,0,0.3);
    background: rgba(255,255,255,0.18);
}
.signature-label {
    font-family: 'Inter Tight', sans-serif;
    font-size: calc(1.15 * var(--mm));
    font-weight: 800;
    letter-spacing: 0.01em;
    color: rgba(0,0,0,0.5);
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
            {{-- Tut Wuri Handayani / second logo --}}
        </div>
    </div>

    <!-- Watermark -->
    <div class="watermark">
        @for($i = 0; $i < 14; $i++)
            <div class="watermark-row">
                @for($j = 0; $j < 4; $j++)
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
            <div class="signature-label">KEPALA SEKOLAH</div>
        </div>
    </div>
</body>
</html>
