<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter+Tight:wght@500;600;700;800&display=swap" rel="stylesheet">
<style>
@php
    $c = $config;
    $hasFrame = !empty($frameUrl);
    $isPortrait = ($orientation ?? 'landscape') === 'portrait';
    $cardW = $isPortrait ? 54 : 85.6;
    $cardH = $isPortrait ? 85.6 : 54;
    $elements = $c['elements'] ?? [];

    // Resolve a field element's display value from the dynamic submission map.
    $resolveValue = function (string $source) use ($values) {
        $v = $values[$source] ?? null;
        return ($v === null || $v === '') ? '—' : (string) $v;
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
    @else
    background: linear-gradient(135deg, #e6f4ea 0%, #d4ecdc 50%, #c7e6d1 100%);
    @endif
}
.el { position: absolute; z-index: 6; }
.el-field {
    display: flex; align-items: flex-start;
    font-family: 'Inter Tight', sans-serif; font-weight: 800;
    line-height: 1.25; letter-spacing: -0.01em; color: #0c1a12;
    white-space: normal;
}
.el-field .lbl { flex-shrink: 0; }
.el-field .sep { flex-shrink: 0; padding: 0 calc(0.6 * var(--mm)); }
.el-field .val { flex: 1; min-width: 0; font-weight: 700; white-space: normal; overflow-wrap: anywhere; word-break: break-word; }
.el-photo { border-radius: calc(0.4 * var(--mm)); overflow: hidden; background: transparent; }
.el-photo img { width:100%; height:100%; object-fit:cover; object-position:center; display:block; }
.el-photo .ph { width:100%; height:100%; display:flex; align-items:center; justify-content:center; color: rgba(0,0,0,0.3); background: rgba(255,255,255,0.35); border: 1.5px dashed rgba(0,0,0,0.3); }
.el-qr { border-radius: calc(0.4 * var(--mm)); padding: calc(0.3 * var(--mm)); background: transparent; }
.el-qr svg { width:100%; height:100%; display:block; }
</style>
</head>
<body>
    @foreach($elements as $el)
        @continue(empty($el['enabled']))
        @php $type = $el['type'] ?? 'field'; @endphp

        @if($type === 'field')
            <div class="el el-field" style="left: calc({{ $el['x'] }} * var(--mm)); top: calc({{ $el['y'] }} * var(--mm)); width: calc({{ $el['width'] ?? 55 }} * var(--mm)); max-width: calc({{ max(8, $cardW - $el['x'] - 1) }} * var(--mm)); font-size: calc({{ $el['fontSize'] ?? 2.0 }} * var(--mm));">
                <span class="lbl" style="width: calc({{ $el['labelWidth'] ?? 12 }} * var(--mm));">{{ $el['label'] ?? '' }}</span>
                <span class="sep">:</span>
                <span class="val">{{ $resolveValue($el['source'] ?? '') }}</span>
            </div>
        @elseif($type === 'photo')
            <div class="el el-photo" style="left: calc({{ $el['x'] }} * var(--mm)); top: calc({{ $el['y'] }} * var(--mm)); width: calc({{ $el['w'] ?? 16 }} * var(--mm)); height: calc({{ $el['h'] ?? 21 }} * var(--mm));">
                @if(!empty($photoUrl))
                    <img src="{{ $photoUrl }}" alt="foto">
                @else
                    <div class="ph"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                @endif
            </div>
        @elseif($type === 'qr' && !empty($qrSvg))
            <div class="el el-qr" style="left: calc({{ $el['x'] }} * var(--mm)); top: calc({{ $el['y'] }} * var(--mm)); width: calc({{ $el['size'] ?? 15 }} * var(--mm)); height: calc({{ $el['size'] ?? 15 }} * var(--mm));">{!! $qrSvg !!}</div>
        @endif
    @endforeach
    <script>
    (function () {
        // Reflow the field stack so a long value pushes fields below it down instead
        // of overlapping them, then shrink the value font if the stack would spill
        // into the photo/QR band. The ":" stays aligned across rows.
        var fields = Array.prototype.slice.call(document.querySelectorAll('.el-field'));
        if (fields.length) {
            var mmpx = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--mm')) || 1;
            var gap = 0.8 * mmpx;

            fields.forEach(function (row) {
                row._top = row.offsetTop;
                var val = row.querySelector('.val');
                row._base = val ? parseFloat(getComputedStyle(val).fontSize) : 0;
            });
            fields.sort(function (a, b) { return a._top - b._top; });
            var firstTop = fields[0]._top;

            function layout() {
                var limit = document.body.clientHeight;
                document.querySelectorAll('.el-photo, .el-qr').forEach(function (o) {
                    if (o.offsetTop + 2 > firstTop) { limit = Math.min(limit, o.offsetTop); }
                });
                limit -= 2;

                for (var scale = 1.0; scale >= 0.5 - 0.001; scale -= 0.05) {
                    fields.forEach(function (row) {
                        var val = row.querySelector('.val');
                        if (val && row._base) { val.style.fontSize = (row._base * scale) + 'px'; }
                    });

                    var cursor = firstTop, bottom = firstTop;
                    fields.forEach(function (row) {
                        var top = Math.max(row._top, cursor);
                        row.style.top = top + 'px';
                        var h = row.offsetHeight;
                        cursor = top + h + gap;
                        bottom = top + h;
                    });

                    if (bottom <= limit) { break; }
                }
            }

            layout();
            if (document.fonts && document.fonts.ready) { document.fonts.ready.then(layout); }
        }
    })();
    </script>
</body>
</html>
