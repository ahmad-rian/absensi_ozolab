<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --mm: {{ $exportMm }}px;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            width: calc({{ $sheetW }} * var(--mm));
            height: calc({{ $sheetH }} * var(--mm));
            background: #ffffff;
            overflow: hidden;
        }
        .sheet {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: calc(3 * var(--mm));
        }
        .grid {
            display: grid;
            grid-template-columns: repeat({{ $cols }}, calc({{ $slotW }} * var(--mm)));
            grid-template-rows: repeat({{ $rows }}, calc({{ $slotH }} * var(--mm)));
            gap: calc({{ $gap }} * var(--mm));
            justify-content: center;
            align-content: center;
        }
        .slot {
            width: calc({{ $slotW }} * var(--mm));
            height: calc({{ $slotH }} * var(--mm));
            overflow: hidden;
            border: 0.5px solid #d1d5db;
            background: #f3f4f6;
        }
        .slot img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .caption {
            margin-top: calc(2 * var(--mm));
            text-align: center;
            font-size: calc(3 * var(--mm));
            color: #111827;
            font-weight: 600;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="grid">
            @for($i = 0; $i < $count; $i++)
                <div class="slot">
                    @if($photoUrl)
                        <img src="{{ $photoUrl }}" alt="pas foto">
                    @endif
                </div>
            @endfor
        </div>
        @if($caption !== '')
            <div class="caption">{{ $caption }}</div>
        @endif
    </div>
</body>
</html>
