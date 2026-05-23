<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\QrTokenGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentApiController extends Controller
{
    public function __construct(
        private readonly QrTokenGenerator $qrGenerator,
    ) {}

    /**
     * GET /api/students — List students with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => ['sometimes', 'exists:schools,id'],
            'classroom_id' => ['sometimes', 'exists:classrooms,id'],
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Student::with(['classroom.academicYear', 'parentProfile.user', 'school'])
            ->where('is_active', true);

        if ($request->filled('school_id')) {
            $query->where('school_id', $request->school_id);
        }

        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        $students = $query->orderBy('full_name')
            ->paginate($request->integer('per_page', 20));

        return response()->json($students);
    }

    /**
     * GET /api/students/{student} — Single student with full data + QR SVG.
     */
    public function show(Student $student): JsonResponse
    {
        $student->load(['classroom.academicYear', 'parentProfile.user', 'school']);

        return response()->json([
            'id' => $student->id,
            'nis' => $student->nis,
            'nisn' => $student->nisn,
            'full_name' => $student->full_name,
            'gender' => $student->gender->value,
            'gender_label' => $student->gender->label(),
            'religion' => $student->religion?->value,
            'religion_label' => $student->religion?->label(),
            'birth_place' => $student->birth_place,
            'birth_date' => $student->birth_date?->format('Y-m-d'),
            'address' => $student->address,
            'photo_path' => $student->photo_path,
            'is_active' => $student->is_active,
            'qr_svg' => $this->qrGenerator->renderSvg($student),
            'classroom' => $student->classroom ? [
                'id' => $student->classroom->id,
                'name' => $student->classroom->name,
                'grade_level' => $student->classroom->grade_level,
                'academic_year' => $student->classroom->academicYear?->name,
            ] : null,
            'parent' => $student->parentProfile ? [
                'id' => $student->parentProfile->id,
                'name' => $student->parentProfile->user?->name,
                'phone' => $student->parentProfile->whatsapp_number,
                'relation' => $student->parentProfile->relation->value,
                'relation_label' => $student->parentProfile->relation->label(),
            ] : null,
            'school' => $student->school ? [
                'id' => $student->school->id,
                'name' => $student->school->name,
                'slug' => $student->school->slug,
                'logo_path' => $student->school->logo_path,
                'address' => $student->school->address,
                'city' => $student->school->city,
                'phone' => $student->school->phone,
                'email' => $student->school->email,
            ] : null,
        ]);
    }

    /**
     * GET /api/students/{student}/qr — QR Code SVG only.
     */
    public function qr(Student $student): JsonResponse
    {
        return response()->json([
            'student_id' => $student->id,
            'full_name' => $student->full_name,
            'nis' => $student->nis,
            'qr_svg' => $this->qrGenerator->renderSvg($student),
        ]);
    }

    /**
     * GET /api/students/by-nis/{nis} — Lookup by NIS.
     */
    public function byNis(string $nis): JsonResponse
    {
        $student = Student::where('nis', $nis)->where('is_active', true)->first();

        if (! $student) {
            return response()->json(['message' => 'Siswa tidak ditemukan.'], 404);
        }

        return $this->show($student);
    }

    /**
     * GET /api/students/by-qr/{token} — Lookup by QR token.
     */
    public function byQr(string $token): JsonResponse
    {
        $student = Student::where('qr_token', $token)->where('is_active', true)->first();

        if (! $student) {
            return response()->json(['message' => 'QR Code tidak dikenali.'], 404);
        }

        return $this->show($student);
    }

    /**
     * GET /api/schools — List active schools.
     */
    public function schools(): JsonResponse
    {
        $schools = School::where('is_active', true)
            ->withCount(['students', 'classrooms'])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'city', 'logo_path']);

        return response()->json($schools);
    }

    /**
     * GET /api/schools/{school}/students — All students of a school.
     */
    public function schoolStudents(School $school): JsonResponse
    {
        $students = $school->students()
            ->with(['classroom'])
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get()
            ->map(fn (Student $s) => [
                'id' => $s->id,
                'nis' => $s->nis,
                'nisn' => $s->nisn,
                'full_name' => $s->full_name,
                'gender' => $s->gender->value,
                'classroom' => $s->classroom?->name,
                'grade_level' => $s->classroom?->grade_level,
            ]);

        return response()->json([
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'slug' => $school->slug,
            ],
            'total' => $students->count(),
            'students' => $students,
        ]);
    }
}
