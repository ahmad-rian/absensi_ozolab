<?php

namespace App\Services;

use App\Models\School;
use App\Models\SchoolAlbumLayout;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

class AlbumGeneratorService
{
    /**
     * Generate album pages for a set of students.
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, array{page: int, path: string}>
     */
    public function generateAlbum(Collection $students, SchoolAlbumLayout $layout, School $school): array
    {
        $config = $layout->layout_config ?? [];
        $perPage = $layout->columns * $layout->rows;
        $chunks = $students->chunk($perPage);
        $pages = [];

        foreach ($chunks as $pageNum => $pageStudents) {
            $html = View::make('cards.album-page', [
                'students' => $pageStudents,
                'school' => $school,
                'layout' => $layout,
                'config' => $config,
                'pageNumber' => $pageNum + 1,
                'totalPages' => $chunks->count(),
            ])->render();

            $filename = sprintf(
                'albums/%d/album-%s-page-%d.png',
                $school->id,
                now()->format('Ymd-His'),
                $pageNum + 1,
            );

            $fullPath = Storage::disk('public')->path($filename);
            $dir = dirname($fullPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $this->renderPage($html, $fullPath, $layout);

            $pages[] = [
                'page' => $pageNum + 1,
                'path' => $filename,
            ];
        }

        return $pages;
    }

    private function renderPage(string $html, string $outputPath, SchoolAlbumLayout $layout): void
    {
        $paperSizes = [
            'A4' => ['portrait' => [794, 1123], 'landscape' => [1123, 794]],
            'A3' => ['portrait' => [1123, 1587], 'landscape' => [1587, 1123]],
            'Letter' => ['portrait' => [816, 1056], 'landscape' => [1056, 816]],
        ];

        $size = $paperSizes[$layout->paper_size][$layout->orientation]
            ?? $paperSizes['A4']['portrait'];

        Browsershot::html($html)
            ->windowSize($size[0], $size[1])
            ->deviceScaleFactor(2)
            ->waitUntilNetworkIdle()
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox'])
            ->save($outputPath);
    }
}
