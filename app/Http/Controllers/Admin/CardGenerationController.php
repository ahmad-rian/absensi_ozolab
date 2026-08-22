<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateStudentCardJob;
use App\Models\CardGenerationLog;
use App\Models\SchoolCardLayout;
use App\Models\Student;
use App\Services\CardGeneratorService;
use App\Support\SchoolTime;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CardGenerationController extends Controller
{
    /** Rentang siap pakai untuk `?range=`; `custom` memakai start_date/end_date. */
    private const RANGES = ['today', 'week', 'month', 'all', 'custom'];

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        [$start, $end] = $this->window($filters);

        $logs = CardGenerationLog::forSchool()
            ->with(['student:id,full_name,nis', 'cardLayout:id,name'])
            ->when($start, fn ($q) => $q->where('created_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('created_at', '<=', $end))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['type'], fn ($q, $type) => $q->where('type', $type))
            ->when($filters['search'], fn ($q, $search) => $q->whereHas(
                'student',
                fn ($s) => $s->where('full_name', 'like', "%{$search}%")->orWhere('nis', 'like', "%{$search}%"),
            ))
            ->latest()
            ->paginate(50)
            ->withQueryString()
            ->through(fn (CardGenerationLog $log) => [
                'id' => $log->id,
                'type' => $log->type,
                'student_name' => $log->student?->full_name ?? '-',
                'student_nis' => $log->student?->nis ?? '-',
                'layout_name' => $log->cardLayout?->name ?? '-',
                'status' => $log->status,
                'file_url' => $log->file_path ? Storage::disk('public')->url($log->file_path) : null,
                'drive_url' => $log->drive_url,
                'generated_by' => $log->generated_by,
                'error_message' => $log->error_message,
                'created_at' => SchoolTime::display($log->created_at),
            ]);

        return Inertia::render('admin/card-generation/index', [
            'logs' => $logs,
            'filters' => $filters,
        ]);
    }

    /**
     * @return array{range: string, start_date: string, end_date: string, status: string, type: string, search: string}
     */
    private function filters(Request $request): array
    {
        $range = (string) $request->query('range', 'all');

        return [
            'range' => in_array($range, self::RANGES, true) ? $range : 'all',
            'start_date' => (string) $request->query('start_date', ''),
            'end_date' => (string) $request->query('end_date', ''),
            'status' => (string) $request->query('status', ''),
            'type' => (string) $request->query('type', ''),
            'search' => (string) $request->query('search', ''),
        ];
    }

    /**
     * Batas rentang sebagai waktu UTC, karena `created_at` diisi Eloquent pada
     * `config('app.timezone')` yang di sini UTC.
     *
     * Batas harinya sendiri dihitung di jam sekolah: "hari ini" bagi operator
     * berakhir tengah malam WIB, bukan tengah malam UTC yang jatuh pukul 07.00
     * pagi. `whereDate()` akan melakukan persis kesalahan itu — ia membandingkan
     * tanggal UTC yang tersimpan, sehingga generate sore hari terhitung besok.
     *
     * @param  array{range: string, start_date: string, end_date: string, ...}  $filters
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function window(array $filters): array
    {
        $tz = SchoolTime::timezone();
        $today = CarbonImmutable::now($tz)->startOfDay();

        return match ($filters['range']) {
            'today' => [$today->utc(), $today->endOfDay()->utc()],
            'week' => [$today->startOfWeek()->utc(), $today->endOfWeek()->utc()],
            'month' => [$today->startOfMonth()->utc(), $today->endOfMonth()->utc()],
            'custom' => [
                $this->boundary($filters['start_date'], $tz, false),
                $this->boundary($filters['end_date'], $tz, true),
            ],
            default => [null, null],
        };
    }

    /** Tanggal yang diketik operator, atau null bila kosong / tidak terbaca. */
    private function boundary(string $date, string $tz, bool $endOfDay): ?CarbonImmutable
    {
        if ($date === '') {
            return null;
        }

        try {
            $parsed = CarbonImmutable::parse($date, $tz);
        } catch (InvalidFormatException) {
            return null;
        }

        return ($endOfDay ? $parsed->endOfDay() : $parsed->startOfDay())->utc();
    }

    public function preview(Request $request): Response
    {
        $request->validate([
            'layout_id' => ['required', 'exists:school_card_layouts,id'],
            'student_id' => ['required', $this->belongsToSchool('students')],
        ]);

        $layout = SchoolCardLayout::forSchool()->findOrFail($request->layout_id);
        $student = Student::forSchool()->with('classroom')->findOrFail($request->student_id);

        $service = new CardGeneratorService;
        $result = $service->generateCard($student, $layout);

        return Inertia::render('admin/card-generation/preview', [
            'html' => $result['html'],
            'imageUrl' => Storage::disk('public')->url($result['path']),
            'student' => [
                'full_name' => $student->full_name,
                'nis' => $student->nis,
            ],
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'layout_id' => ['required', 'exists:school_card_layouts,id'],
            'student_ids' => ['nullable', 'array'],
            'classroom_id' => ['nullable', $this->belongsToSchool('classrooms')],
        ]);

        $layout = SchoolCardLayout::forSchool()->findOrFail($validated['layout_id']);

        // Resolve student list — always scoped by school
        $query = Student::forSchool();

        $studentIds = $validated['student_ids'] ?? [];
        $isAll = empty($studentIds) || in_array('all', $studentIds, true);

        if (! $isAll) {
            $query->whereIn('id', $studentIds);
        }

        if (! empty($validated['classroom_id'])) {
            $query->where('classroom_id', $validated['classroom_id']);
        }

        $students = $query->get();

        if ($students->isEmpty()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Tidak ada siswa yang ditemukan.']);

            return to_route('admin.card-generation');
        }

        // Queue one render job per student so the request returns immediately and
        // heavy Browsershot work runs on the `cards` worker.
        foreach ($students as $student) {
            $log = CardGenerationLog::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'school_card_layout_id' => $layout->id,
                'type' => 'card',
                'status' => 'processing',
                'generated_by' => 'admin',
            ]);

            GenerateStudentCardJob::dispatch($log->id);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$students->count()} kartu sedang diproses. Status akan diperbarui otomatis.",
        ]);

        return to_route('admin.card-generation');
    }
}
