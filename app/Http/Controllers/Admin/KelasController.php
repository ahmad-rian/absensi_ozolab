<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class KelasController extends Controller
{
    public function index(): Response
    {
        $classrooms = Classroom::forSchool()
            ->withCount('students')
            ->with(['academicYear', 'homeroomTeacher'])
            ->orderBy('name')
            ->get();

        $academicYears = AcademicYear::forSchool()
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'is_active', 'school_id']);

        $schoolId = auth()->user()->school_id;
        $teachers = Role::where('name', 'GURU')->exists()
            ? User::role('GURU')->where('school_id', $schoolId)->orderBy('name')->get(['id', 'name'])
            : new Collection;

        return Inertia::render('admin/kelas/index', [
            'classrooms' => $classrooms,
            'academic_years' => $academicYears,
            'teachers' => $teachers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'grade_level' => ['required', 'integer', 'min:1', 'max:12'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'homeroom_teacher_id' => ['nullable', 'exists:users,id'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $validated['capacity'] = $validated['capacity'] ?? 36;

        Classroom::create($validated);

        return back()->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'grade_level' => ['required', 'integer', 'min:1', 'max:12'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'homeroom_teacher_id' => ['nullable', 'exists:users,id'],
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
}
