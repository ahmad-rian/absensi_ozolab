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
    $hasFrame = !empty($frameUrl);
    $isPortrait = ($orientation ?? 'landscape') === 'portrait';

    // Physical card dimensions (mm) — swapped for portrait.
    $cardW = $isPortrait ? 54 : 85.6;
    $cardH = $isPortrait ? 85.6 : 54;

    $elements = $c['elements'] ?? \App\Models\SchoolCardLayout::defaultElements();

    $hGradStart = $c['header_gradient_start'] ?? ($isOsis ? '#5dc4f5' : '#c9986a');
    $hGradEnd   = $c['header_gradient_end']   ?? ($isOsis ? '#3aa8df' : '#b07b4a');
    $hTextColor = $c['header_text_color']     ?? ($isOsis ? '#06243a' : '#1a1208');
    $wmText     = $c['watermark_text'] ?? ($isOsis ? 'ORGANISASI SISWA INTRA SEKOLAH' : 'PERPUSTAKAAN WIDYA SASTRA');

    // Resolve a field element's display value from its data source.
    $resolveValue = function (string $source) use ($student) {
        return match ($source) {
            'full_name'    => strtoupper($student->full_name),
            'address'      => $student->address ?? '—',
            'ttl'          => trim(($student->birth_place ?? '').($student->birth_date ? ', '.$student->birth_date->translatedFormat('d F Y') : ''), ', ') ?: '—',
            'religion'     => $student->religion?->label() ?? '—',
            'nis'          => $student->nis ?? '—',
            'nisn'         => $student->nisn ?? '—',
            'classroom'    => $student->classroom?->name ?? '—',
            'gender'       => $student->gender?->label() ?? '—',
            'parent_phone' => $student->parent_phone ?? '—',
            default        => '—',
        };
    };
@endphp

:root { --mm: {{ $exportMm ?? '9.5' }}px; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    width: calc({{ $cardW }} * var(--mm));
    height: calc({{ $cardH }} * var(--mm));
    overflow: hidden;
    font-family: 'Manrope', sans-serif;
    position: relative;
    @if($hasFrame)
    background: url('{{ $frameUrl }}') center/cover no-repeat;
    @elseif($isOsis)
    background: radial-gradient(ellipse at 70% 30%, rgba(255,255,255,0.5) 0%, transparent 60%), linear-gradient(135deg, #e0f3ff 0%, #c7e9fb 50%, #b8e1f7 100%);
    @else
    background: radial-gradient(ellipse at 30% 20%, rgba(255,255,255,0.4) 0%, transparent 50%), linear-gradient(135deg, #d6b88a 0%, #e8d2a8 40%, #d4b380 100%);
    @endif
}

/* Frameless decorative header band */
.header-band {
    position: relative; z-index: 5;
    height: calc(13 * var(--mm));
    background: linear-gradient(180deg, {{ $hGradStart }} 0%, {{ $hGradEnd }} 100%);
    display: flex; align-items: center;
    padding: 0 calc(2 * var(--mm));
    gap: calc(1.2 * var(--mm));
}
.logo-circle {
    width: calc(8.5 * var(--mm)); height: calc(8.5 * var(--mm));
    border-radius: 50%; flex-shrink: 0; overflow: hidden;
    background: rgba(255,255,255,0.25);
    display: flex; align-items: center; justify-content: center;
}
.logo-circle img { width:100%; height:100%; object-fit:contain; border-radius:50%; }
.header-text { flex: 1; text-align: center; font-family: 'Inter Tight', sans-serif; color: {{ $hTextColor }}; line-height: 1.05; min-width: 0; }
.header-text .line-tiny { font-size: calc(1.0 * var(--mm)); font-weight: 700; }
.header-text .line-small { font-size: calc(1.1 * var(--mm)); font-weight: 700; letter-spacing: 0.02em; }
.header-text .line-big { font-size: calc(1.7 * var(--mm)); font-weight: 800; letter-spacing: 0.01em; margin-top: 2px; }
.header-text .line-addr { font-size: calc(0.85 * var(--mm)); font-weight: 500; font-family: 'Manrope', sans-serif; margin-top: 3px; line-height: 1.2; }
.watermark { position: absolute; inset: calc(13 * var(--mm)) 0 0 0; z-index: 2; overflow: hidden; pointer-events: none; opacity: 0.35; }
.watermark-row { display: flex; white-space: nowrap; font-family: 'Inter Tight', sans-serif; font-weight: 800; font-size: calc(1.4 * var(--mm)); letter-spacing: -0.02em; line-height: 1.6; @if($isOsis) color: rgba(255,255,255,0.85); @else color: rgba(255,255,255,0.5); @endif }
.watermark-row span { padding-right: calc(1.2 * var(--mm)); }
.watermark-row:nth-child(even) { transform: translateX(calc(-3 * var(--mm))); }

/* === Absolute element positioning (shared with editor) === */
.el { position: absolute; z-index: 6; }
.el-field {
    display: flex; align-items: baseline;
    font-family: 'Inter Tight', sans-serif; font-weight: 800;
    line-height: 1.25; letter-spacing: -0.01em; color: #0c0c14;
    white-space: nowrap; overflow: hidden;
}
.el-field .lbl { flex-shrink: 0; }
.el-field .sep { flex-shrink: 0; padding: 0 calc(0.6 * var(--mm)); }
.el-field .val { font-weight: 700; overflow: hidden; text-overflow: ellipsis; }
.el-photo { border-radius: calc(0.4 * var(--mm)); overflow: hidden; background: transparent; }
.el-photo img { width:100%; height:100%; object-fit:cover; object-position:center; display:block; }
.el-photo .ph { width:100%; height:100%; display:flex; align-items:center; justify-content:center; color: rgba(0,0,0,0.3); background: rgba(255,255,255,0.35); border: 1.5px dashed rgba(0,0,0,0.3); }
.el-qr { border-radius: calc(0.4 * var(--mm)); padding: calc(0.3 * var(--mm)); background: transparent; }
.el-qr svg { width:100%; height:100%; display:block; }
</style>
</head>
<body>
    @unless($hasFrame)
    <div class="header-band">
        <div class="logo-circle">@if($logoUrl)<img src="{{ $logoUrl }}" alt="Logo">@endif</div>
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
        <div class="logo-circle"></div>
    </div>
    <div class="watermark">
        @for($i = 0; $i < 14; $i++)
            <div class="watermark-row">@for($j = 0; $j < 4; $j++)<span>{{ $wmText }}</span>@endfor</div>
        @endfor
    </div>
    @endunless

    @foreach($elements as $el)
        @continue(empty($el['enabled']))
        @php $type = $el['type'] ?? 'field'; @endphp

        @if($type === 'field')
            <div class="el el-field" style="left: calc({{ $el['x'] }} * var(--mm)); top: calc({{ $el['y'] }} * var(--mm)); width: calc({{ $el['width'] ?? 55 }} * var(--mm)); font-size: calc({{ $el['fontSize'] ?? 2.0 }} * var(--mm));">
                <span class="lbl" style="width: calc({{ $el['labelWidth'] ?? 18 }} * var(--mm));">{{ $el['label'] ?? '' }}</span>
                <span class="sep">:</span>
                <span class="val">{{ $resolveValue($el['source'] ?? '') }}</span>
            </div>
        @elseif($type === 'photo')
            <div class="el el-photo" style="left: calc({{ $el['x'] }} * var(--mm)); top: calc({{ $el['y'] }} * var(--mm)); width: calc({{ $el['w'] ?? 16 }} * var(--mm)); height: calc({{ $el['h'] ?? 21 }} * var(--mm));">
                @if($photoUrl)
                    <img src="{{ $photoUrl }}" alt="{{ $student->full_name }}">
                @else
                    <div class="ph"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                @endif
            </div>
        @elseif($type === 'qr')
            <div class="el el-qr" style="left: calc({{ $el['x'] }} * var(--mm)); top: calc({{ $el['y'] }} * var(--mm)); width: calc({{ $el['size'] ?? 15 }} * var(--mm)); height: calc({{ $el['size'] ?? 15 }} * var(--mm));">{!! $qrSvg !!}</div>
        @endif
    @endforeach
</body>
</html>
