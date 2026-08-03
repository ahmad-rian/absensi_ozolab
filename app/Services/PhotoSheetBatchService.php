<?php

namespace App\Services;

use App\Models\PhotoSheetBatch;
use App\Models\Student;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

/**
 * Menyusun satu berkas PDF berisi beberapa lembar pas foto, di mana satu lembar
 * bisa dibagi ke beberapa siswa dengan jumlah cetak berbeda.
 *
 * Ukuran kertas dan kapasitas slot tidak didefinisikan ulang di sini — semuanya
 * dibaca dari PhotoSheetGeneratorService::TEMPLATES supaya lembar per-siswa dan
 * lembar batch tidak pernah berbeda ukuran.
 */
class PhotoSheetBatchService
{
    /** Jumlah slot dalam satu lembar untuk template tertentu. */
    public static function capacity(string $template): int
    {
        $config = self::config($template);

        return $config['cols'] * $config['rows'];
    }

    /**
     * @return array{label: string, sheet: array{0: int, 1: int}, cols: int, rows: int, slot: array{0: int, 1: int}, gap: int}
     */
    public static function config(string $template): array
    {
        return PhotoSheetGeneratorService::TEMPLATES[$template]
            ?? PhotoSheetGeneratorService::TEMPLATES['4r_3x4'];
    }

    /**
     * Ratakan pesanan jadi deret slot, lalu potong per lembar.
     *
     * Slot satu siswa sengaja berurutan, tidak diselang-seling: operator studio
     * memotong dan menyerahkan per orang, jadi foto satu anak harus berdekatan.
     * Lembar terakhir diisi null sampai penuh supaya grid tetap utuh.
     *
     * @param  array<int, array{student_id: string, quantity: int}>  $items
     * @return array<int, array<int, string|null>> daftar lembar, tiap lembar berisi kunci siswa atau null
     */
    public static function paginate(array $items, int $capacity): array
    {
        if ($capacity < 1) {
            return [];
        }

        $slots = [];

        foreach ($items as $item) {
            $quantity = max(0, (int) ($item['quantity'] ?? 0));

            for ($i = 0; $i < $quantity; $i++) {
                $slots[] = $item['student_id'];
            }
        }

        if (! $slots) {
            return [];
        }

        return array_map(
            fn (array $page) => array_pad($page, $capacity, null),
            array_chunk($slots, $capacity),
        );
    }

    /** Total slot yang dipesan, dipakai untuk ringkasan dan validasi. */
    public static function totalSlots(array $items): int
    {
        return array_sum(array_map(fn (array $item) => max(0, (int) ($item['quantity'] ?? 0)), $items));
    }

    public static function pageCount(array $items, int $capacity): int
    {
        $total = self::totalSlots($items);

        return $capacity > 0 ? (int) ceil($total / $capacity) : 0;
    }

    /**
     * Render batch jadi PDF dan kembalikan path relatif di disk `public`.
     */
    public function render(PhotoSheetBatch $batch): string
    {
        $config = self::config($batch->template);
        $capacity = self::capacity($batch->template);

        $items = $batch->items ?? [];
        $pages = self::paginate($items, $capacity);

        // Satu data URI per siswa, dipakai ulang di semua slotnya — foto studio
        // berukuran ratusan KB, dan satu batch bisa memuat puluhan slot.
        $photos = $this->photoDataUris($batch, $items);

        $pagesWithPhotos = array_map(
            fn (array $page) => array_map(
                fn (?string $studentId) => $studentId === null ? null : ($photos[$studentId] ?? null),
                $page,
            ),
            $pages,
        );

        $html = View::make('cards.photo-sheet-batch', [
            'pages' => $pagesWithPhotos,
            'sheetW' => $config['sheet'][0],
            'sheetH' => $config['sheet'][1],
            'cols' => $config['cols'],
            'rows' => $config['rows'],
            'slotW' => $config['slot'][0],
            'slotH' => $config['slot'][1],
            'gap' => $config['gap'],
        ])->render();

        $path = sprintf('sheets/batches/%s/%s.pdf', $batch->school_id, $batch->id);
        $fullPath = Storage::disk('public')->path($path);

        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->renderPdf($html, $fullPath, $config['sheet'][0], $config['sheet'][1]);

        return $path;
    }

    /**
     * @param  array<int, array{student_id: string, quantity: int}>  $items
     * @return array<string, string>
     */
    private function photoDataUris(PhotoSheetBatch $batch, array $items): array
    {
        $studentIds = array_values(array_unique(array_column($items, 'student_id')));

        return Student::forSchool($batch->school_id)
            ->whereIn('id', $studentIds)
            ->get(['id', 'photo_path'])
            ->mapWithKeys(fn (Student $student) => [
                $student->id => $this->toBase64DataUri($student->photo_path),
            ])
            ->filter()
            ->all();
    }

    private function toBase64DataUri(?string $storagePath): ?string
    {
        if (! $storagePath) {
            return null;
        }

        $fullPath = Storage::disk('public')->path($storagePath);

        if (! file_exists($fullPath)) {
            return null;
        }

        $mime = function_exists('mime_content_type') ? (mime_content_type($fullPath) ?: 'image/png') : 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($fullPath));
    }

    private function renderPdf(string $html, string $outputPath, int $sheetW, int $sheetH): void
    {
        $browsershot = Browsershot::html($html)
            ->paperSize($sheetW, $sheetH, 'mm')
            ->margins(0, 0, 0, 0)
            ->showBackground()
            ->timeout(240)
            ->waitUntilNetworkIdle()
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox']);

        $chromePath = config('services.chrome.path');
        if ($chromePath && file_exists($chromePath)) {
            $browsershot->setChromePath($chromePath);
        }

        $browsershot->savePdf($outputPath);
    }
}
