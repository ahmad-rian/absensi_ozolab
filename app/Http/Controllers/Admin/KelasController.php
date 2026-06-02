<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class KelasController extends Controller
{
    public function index(): Response
    {
        $this->ensureAcademicYears();

        $classrooms = Classroom::forSchool()
            ->withCount('students')
            ->with(['academicYear', 'homeroomTeacher'])
            ->orderBy('name')
            ->get();

        $academicYears = AcademicYear::forSchool()
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'is_active', 'school_id']);

        return Inertia::render('admin/kelas/index', [
            'classrooms' => $classrooms,
            'academic_years' => $academicYears,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'grade_level' => ['required', 'integer', 'min:1', 'max:12'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'parallel_from' => ['required', 'string', 'size:1', 'regex:/^[A-Z]$/'],
            'parallel_to' => ['required', 'string', 'size:1', 'regex:/^[A-Z]$/'],
        ]);

        $capacity = $validated['capacity'] ?? 36;
        $from = ord($validated['parallel_from']);
        $to = ord($validated['parallel_to']);

        if ($from > $to) {
            return back()->withErrors(['parallel_from' => 'Paralel awal harus sebelum atau sama dengan paralel akhir.']);
        }

        $created = 0;
        $skipped = 0;

        for ($i = $from; $i <= $to; $i++) {
            $parallel = chr($i);
            $name = $validated['grade_level'].$parallel;

            $exists = Classroom::forSchool()
                ->where('name', $name)
                ->where('academic_year_id', $validated['academic_year_id'])
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            Classroom::create([
                'name' => $name,
                'grade_level' => $validated['grade_level'],
                'academic_year_id' => $validated['academic_year_id'],
                'capacity' => $capacity,
            ]);
            $created++;
        }

        $message = "{$created} kelas berhasil ditambahkan.";
        if ($skipped > 0) {
            $message .= " {$skipped} kelas dilewati karena sudah ada.";
        }

        return back()->with('success', $message);
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'grade_level' => ['required', 'integer', 'min:1', 'max:12'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $validated['capacity'] = $validated['capacity'] ?? 36;

        $classroom->update($validated);

        return back()->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        if ($classroom->students()->exists()) {
            return back()->with('error', 'Kelas tidak dapat dihapus karena masih memiliki siswa.');
        }

        $classroom->delete();

        return back()->with('success', 'Kelas berhasil dihapus.');
    }

    /**
     * Auto-create academic years 2025/2026 through 2040/2041 for the current school.
     */
    private function ensureAcademicYears(): void
    {
        $schoolId = auth()->user()->school_id;

        $existing = AcademicYear::where('school_id', $schoolId)
            ->pluck('name')
            ->toArray();

        $toCreate = [];

        for ($year = 2025; $year <= 2040; $year++) {
            $name = "{$year}/".($year + 1);

            if (! in_array($name, $existing)) {
                $toCreate[] = [
                    'id' => (string) Str::ulid(),
                    'school_id' => $schoolId,
                    'name' => $name,
                    'start_date' => "{$year}-07-01",
                    'end_date' => ($year + 1).'-06-30',
                    'is_active' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (! empty($toCreate)) {
            AcademicYear::insert($toCreate);
        }
    }
}
