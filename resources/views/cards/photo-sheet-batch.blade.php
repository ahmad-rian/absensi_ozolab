<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        /*
         * Ukuran kertas ditetapkan di sini, bukan diserahkan ke Chrome. Tanpa
         * @page, PDF-nya keluar A4 dengan lembar 4R mengambang di tengah.
         */
        @page {
            size: {{ $sheetW }}mm {{ $sheetH }}mm;
            margin: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #ffffff;
        }
        .page {
            width: {{ $sheetW }}mm;
            height: {{ $sheetH }}mm;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3mm;
            overflow: hidden;
            page-break-after: always;
            break-after: page;
        }
        /* Halaman terakhir tanpa page-break, kalau tidak PDF dapat halaman kosong. */
        .page:last-child {
            page-break-after: auto;
            break-after: auto;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat({{ $cols }}, {{ $slotW }}mm);
            grid-template-rows: repeat({{ $rows }}, {{ $slotH }}mm);
            gap: {{ $gap }}mm;
            justify-content: center;
            align-content: center;
        }
        .slot {
            width: {{ $slotW }}mm;
            height: {{ $slotH }}mm;
            overflow: hidden;
            border: 0.2mm solid #d1d5db;
        }
        .slot img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        /* Slot sisa dibiarkan putih — operator memotongnya sebagai kertas kosong. */
        .slot.empty {
            border-color: #e5e7eb;
        }
    </style>
</head>
<body>
    @foreach($pages as $page)
        <div class="page">
            <div class="grid">
                @foreach($page as $photoUrl)
                    <div class="slot{{ $photoUrl ? '' : ' empty' }}">
                        @if($photoUrl)
                            <img src="{{ $photoUrl }}" alt="">
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</body>
</html>
