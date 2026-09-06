<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\StudentLookup;
use App\Support\StudioRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint baca untuk Tyas Studio.
 *
 * Operator memilih siswa dengan tiga cara — tembak kartu, cari nama/NIS, atau
 * telusur sekolah → kelas → siswa — dan ketiganya berakhir di sini.
 *
 * `qr_token` TIDAK PERNAH ikut dalam respons mana pun. Ia kredensial: siapa pun
 * yang memegangnya bisa mengabsenkan anak itu, dan Studio tidak membutuhkannya
 * untuk apa pun.
 */
class StudioController extends Controller
{
    /**
     * GET /api/studio/schools
     */
    public function schools(Request $request): JsonResponse
    {
        $token = StudioRequest::token($request);

        $schools = $token->batasi(School::where('is_active', true), 'id')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'city'])
            ->map(fn (School $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'city' => $s->city,
            ]);

        return response()->json(['schools' => $schools]);
    }

    /**
     * GET /api/studio/schools/{school}/classrooms
     */
    public function classrooms(Request $request, string $school): JsonResponse
    {
        $token = StudioRequest::token($request);

        // Route model binding sengaja tidak dipakai: bindingnya menilai lewat
        // global scope `school`, yang mati di jalur bertoken. Pencariannya
        // eksplisit supaya batasannya terlihat di baris yang sama.
        $sekolah = $token->batasi(School::where('is_active', true), 'id')
            ->findOrFail($school);

        $kelas = Classroom::where('school_id', $sekolah->id)
            ->withCount(['students' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get()
            ->map(fn (Classroom $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'grade_level' => $c->grade_level,
                'students_count' => $c->students_count,
            ]);

        return response()->json([
            'school' => ['id' => $sekolah->id, 'name' => $sekolah->name],
            'classrooms' => $kelas,
        ]);
    }

    /**
     * GET /api/studio/schools/{school}/students
     *
     * Seluruh siswa satu sekolah, berhalaman. Dipakai Tyas Studio untuk
     * menyalin cerminnya sendiri.
     *
     * Dua endpoint lain tidak cukup untuk itu: `students?search=` dibatasi 50
     * baris dan mengandaikan ada kata kunci, sementara
     * `classrooms/{id}/students` MELEWATKAN siswa yang belum punya kelas —
     * dan siswa baru sering berada di keadaan itu berhari-hari.
     *
     * `updated_at` ikut supaya penyalinan berikutnya bisa inkremental.
     */
    public function schoolStudents(Request $request, string $school): JsonResponse
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'sejak' => ['sometimes', 'date'],
        ]);

        $token = StudioRequest::token($request);

        $sekolah = $token->batasi(School::where('is_active', true), 'id')->findOrFail($school);

        $query = StudioRequest::students($request)
            ->where('school_id', $sekolah->id)
            ->with('classroom')
            // `orderBy('id')` supaya urutan halamannya stabil: mengurutkan
            // berdasarkan nama membuat siswa yang namanya dibetulkan di
            // tengah penyalinan bisa terlewat atau terhitung dua kali.
            ->orderBy('id');

        if ($request->filled('sejak')) {
            $query->where('updated_at', '>=', $request->date('sejak'));
        }

        $halaman = $query->paginate($request->integer('per_page', 200));

        return response()->json([
            'school' => ['id' => $sekolah->id, 'name' => $sekolah->name],
            'students' => collect($halaman->items())->map(fn (Student $s) => $this->ringkas($s) + [
                'classroom_id' => $s->classroom_id,
                'is_active' => (bool) $s->is_active,
                'updated_at' => $s->updated_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $halaman->currentPage(),
                'last_page' => $halaman->lastPage(),
                'total' => $halaman->total(),
            ],
        ]);
    }

    /**
     * GET /api/studio/classrooms/{classroom}/students
     */
    public function classroomStudents(Request $request, string $classroom): JsonResponse
    {
        $token = StudioRequest::token($request);

        $kelas = $token->batasi(Classroom::query())->findOrFail($classroom);

        $siswa = StudioRequest::students($request)
            ->where('classroom_id', $kelas->id)
            ->where('is_active', true)
            ->with('classroom')
            ->orderBy('full_name')
            ->get();

        return response()->json([
            'classroom' => ['id' => $kelas->id, 'name' => $kelas->name],
            'students' => $siswa->map(fn (Student $s) => $this->ringkas($s)),
        ]);
    }

    /**
     * GET /api/studio/students?search=
     */
    public function students(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $query = StudioRequest::students($request)
            ->where('is_active', true)
            ->with(['classroom', 'school']);

        if ($request->filled('search')) {
            $cari = $request->string('search')->toString();

            $query->where(function ($q) use ($cari) {
                $q->where('full_name', 'like', "%{$cari}%")
                    ->orWhere('nis', 'like', "%{$cari}%")
                    ->orWhere('nisn', 'like', "%{$cari}%");
            });
        }

        $siswa = $query->orderBy('full_name')->limit($request->integer('per_page', 20))->get();

        return response()->json(['students' => $siswa->map(fn (Student $s) => $this->ringkas($s))]);
    }

    /**
     * GET /api/studio/students/{student}
     */
    public function student(Request $request, string $student): JsonResponse
    {
        $siswa = StudioRequest::student($request, $student)->load(['classroom', 'school']);

        return response()->json(['student' => $this->ringkas($siswa)]);
    }

    /**
     * GET /api/studio/students/by-qr/{token}
     *
     * Untuk barcode gun. Toleransi bacaan kotor ikut dipakai — pembaca di
     * lapangan terbukti menyisipkan karakter sampah di ujung — tapi
     * pencocokannya tetap PERSIS: bacaan separuh ditolak.
     */
    public function byQr(Request $request, string $token): JsonResponse
    {
        $studio = StudioRequest::token($request);

        $siswa = $studio->batasi(Student::where('qr_token', trim($token)))
            ->where('is_active', true)
            ->with(['classroom', 'school'])
            ->first();

        if (! $siswa && ($bersih = StudentLookup::extractQrToken($token))) {
            $siswa = $studio->batasi(Student::where('qr_token', $bersih))
                ->where('is_active', true)
                ->with(['classroom', 'school'])
                ->first();
        }

        if (! $siswa) {
            return response()->json(['message' => 'Kartu tidak dikenali di sekolah ini.'], 404);
        }

        return response()->json(['student' => $this->ringkas($siswa)]);
    }

    /**
     * Bentuk siswa yang dikirim ke Studio.
     *
     * Sengaja tipis: Studio hanya perlu mengenali orangnya di layar, bukan
     * memegang datanya. Tidak ada `qr_token`, tidak ada alamat, tidak ada data
     * orang tua.
     *
     * @return array<string, mixed>
     */
    private function ringkas(Student $s): array
    {
        return [
            'id' => $s->id,
            'nis' => $s->nis,
            'nisn' => $s->nisn,
            'full_name' => $s->full_name,
            'classroom' => $s->classroom?->name,
            'grade_level' => $s->classroom?->grade_level,
            'school' => $s->school?->name,
            'school_id' => $s->school_id,
            'photo_url' => $s->photo_path ? asset('storage/'.$s->photo_path) : null,
        ];
    }
}
