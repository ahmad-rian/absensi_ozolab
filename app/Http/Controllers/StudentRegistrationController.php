<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentRegistrationController extends Controller
{
    public function index(): Response
    {
        $schools = School::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'logo_path']);

        $classrooms = Classroom::whereIn('school_id', $schools->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'school_id', 'name', 'grade_level']);

        return Inertia::render('student-register', [
            'schools' => $schools,
            'classrooms' => $classrooms,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'nis' => ['nullable', 'string', 'max:50'],
            'no_absen' => ['nullable', 'string', 'max:10'],
            'nisn' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'religion' => ['nullable', Rule::enum(Religion::class)],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_phone' => ['nullable', 'string', 'max:20'],
        ]);

        if (empty($validated['nis'])) {
            $validated['nis'] = now()->format('Y').str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
        }

        $qrToken = Str::random(64);

        Student::create([
            ...$validated,
            'qr_token' => $qrToken,
            'qr_issued_at' => now(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('student.register')
            ->with('success', 'Data siswa berhasil didaftarkan! Terima kasih telah mengisi formulir pendaftaran.');
    }
}
