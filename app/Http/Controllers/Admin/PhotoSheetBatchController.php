<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePhotoSheetBatchJob;
use App\Models\Classroom;
use App\Models\PhotoSheetBatch;
use App\Models\Student;
use App\Services\PhotoSheetBatchService;
use App\Services\PhotoSheetGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PhotoSheetBatchController extends Controller
{
    /** Batas atas satu batch — Chrome merender semua lembar dalam satu proses. */
    private const MAX_PAGES = 30;

    public function index(): Response
    {
        $students = Student::forSchool()
            ->with('classroom:id,name')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'nis', 'classroom_id', 'photo_path']);

        return Inertia::render('admin/photo-sheets/index', [
            'students' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'nis' => $student->nis,
                'classroom' => $student->classroom?->name,
                'classroom_id' => $student->classroom_id,
                'has_photo' => (bool) $student->photo_path,
            ]),
            'classrooms' => Classroom::forSchool()->orderBy('name')->get(['id', 'name']),
            'templates' => collect(PhotoSheetGeneratorService::TEMPLATES)
                ->map(fn (array $config, string $key) => [
                    'value' => $key,
                    'label' => $config['label'],
                    'capacity' => $config['cols'] * $config['rows'],
                ])
                ->values(),
            'maxPages' => self::MAX_PAGES,
            'batches' => $this->recentBatches(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'template' => ['required', Rule::in(array_keys(PhotoSheetGeneratorService::TEMPLATES))],
            'items' => ['required', 'array', 'min:1'],
            'items.*.student_id' => ['required', $this->belongsToSchool('students')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ], [
            'items.required' => 'Pilih minimal satu siswa.',
            'items.*.student_id.exists' => 'Ada siswa yang tidak dikenali.',
            'items.*.quantity.min' => 'Jumlah cetak minimal 1.',
        ]);

        $students = Student::forSchool()
            ->whereIn('id', array_column($validated['items'], 'student_id'))
            ->get(['id', 'full_name', 'photo_path'])
            ->keyBy('id');

        $tanpaFoto = $students->filter(fn (Student $student) => ! $student->photo_path);

        if ($tanpaFoto->isNotEmpty()) {
            return back()->withErrors([
                'items' => 'Siswa berikut belum punya foto: '.$tanpaFoto->pluck('full_name')->implode(', '),
            ]);
        }

        $capacity = PhotoSheetBatchService::capacity($validated['template']);
        $pages = PhotoSheetBatchService::pageCount($validated['items'], $capacity);

        if ($pages > self::MAX_PAGES) {
            return back()->withErrors([
                'items' => "Terlalu banyak: {$pages} lembar. Maksimal ".self::MAX_PAGES.' lembar per sekali generate.',
            ]);
        }

        $batch = PhotoSheetBatch::create([
            'school_id' => auth()->user()->school_id,
            'template' => $validated['template'],
            'status' => 'processing',
            'items' => array_map(fn (array $item) => [
                'student_id' => $item['student_id'],
                'name' => $students[$item['student_id']]->full_name,
                'quantity' => (int) $item['quantity'],
            ], $validated['items']),
            'total_slots' => PhotoSheetBatchService::totalSlots($validated['items']),
            'pages' => $pages,
            'created_by' => auth()->id(),
        ]);

        GeneratePhotoSheetBatchJob::dispatch($batch->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$pages} lembar sedang diproses. Tombol cetak muncul setelah selesai.",
        ]);

        return to_route('admin.photo-sheets');
    }

    /**
     * Sajikan PDF inline supaya bisa langsung masuk dialog cetak browser.
     */
    public function download(PhotoSheetBatch $batch): BinaryFileResponse
    {
        abort_unless($batch->school_id === auth()->user()->school_id, 403);
        abort_unless($batch->status === 'completed' && $batch->file_path, 404);

        $fullPath = Storage::disk('public')->path($batch->file_path);

        abort_unless(file_exists($fullPath), 404);

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="pas-foto-'.$batch->id.'.pdf"',
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function recentBatches(): Collection
    {
        return PhotoSheetBatch::forSchool()
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (PhotoSheetBatch $batch) => [
                'id' => $batch->id,
                'template' => $batch->template,
                'template_label' => PhotoSheetGeneratorService::TEMPLATES[$batch->template]['label'] ?? $batch->template,
                'status' => $batch->status,
                'pages' => $batch->pages,
                'total_slots' => $batch->total_slots,
                'students' => collect($batch->items ?? [])
                    ->map(fn (array $item) => ($item['name'] ?? '?').' ('.($item['quantity'] ?? 0).')')
                    ->implode(', '),
                'error_message' => $batch->error_message,
                'created_at' => $batch->created_at->format('d M Y H:i'),
            ]);
    }
}
