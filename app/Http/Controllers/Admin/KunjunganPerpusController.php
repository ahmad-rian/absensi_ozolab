<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\LibraryVisit;
use App\Support\SchoolTime;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catatan kunjungan perpustakaan. Hanya menampilkan — pencatatannya lewat
 * halaman scan, dan tidak ada alasan mengubahnya dari sini.
 */
class KunjunganPerpusController extends Controller
{
    public function index(Request $request): Response
    {
        $range = $this->resolveRange($request);

        $visits = LibraryVisit::forSchool()
            ->with(['student:id,full_name,nis,nisn,classroom_id', 'student.classroom:id,name'])
            ->whereBetween('visit_date', [$range['start'], $range['end']])
            ->when($request->query('search'), function ($query, $search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            })
            ->when($request->query('classroom_id'), function ($query, $classroomId) {
                $query->whereHas('student', fn ($q) => $q->where('classroom_id', $classroomId));
            })
            ->orderByDesc('entered_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (LibraryVisit $visit) => [
                'id' => $visit->id,
                'student' => $visit->student?->full_name,
                'nis' => $visit->student?->nis,
                'classroom' => $visit->student?->classroom?->name,
                'date' => $visit->visit_date->format('d M Y'),
                'entered_at' => $visit->entered_at->format('H:i'),
                'exited_at' => $visit->exited_at?->format('H:i'),
                'duration_minutes' => $visit->durationMinutes(),
            ]);

        return Inertia::render('admin/kunjungan-perpus/index', [
            'visits' => $visits,
            'classrooms' => Classroom::forSchool()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $request->query('search', ''),
                'classroom_id' => $request->query('classroom_id', ''),
                ...$range,
            ],
            'summary' => $this->summary($range),
        ]);
    }

    /**
     * @param  array{start: string, end: string}  $range
     * @return array<string, int>
     */
    private function summary(array $range): array
    {
        $inRange = fn () => LibraryVisit::forSchool()->whereBetween('visit_date', [$range['start'], $range['end']]);

        return [
            'visits' => $inRange()->count(),
            'students' => $inRange()->distinct('student_id')->count('student_id'),
            // Yang masih terbuka HARI INI; kunjungan hari lalu yang lupa discan
            // keluar bukan "sedang di dalam", cuma data menggantung.
            'inside' => LibraryVisit::forSchool()
                ->whereDate('visit_date', SchoolTime::now()->toDateString())
                ->stillInside()
                ->count(),
        ];
    }

    /**
     * @return array{start: string, end: string}
     */
    private function resolveRange(Request $request): array
    {
        $now = SchoolTime::now();

        return [
            'start' => $this->validDate($request->query('start')) ?? $now->copy()->startOfMonth()->toDateString(),
            'end' => $this->validDate($request->query('end')) ?? $now->copy()->endOfMonth()->toDateString(),
        ];
    }

    private function validDate(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
