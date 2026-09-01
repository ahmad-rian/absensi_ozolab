<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CardGenerationLog;
use App\Models\Classroom;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\SchoolTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pandangan lintas sekolah, khusus super admin, dan sengaja HANYA BACA.
 *
 * Seluruh menu lain terkunci ke satu sekolah lewat global scope `school`, dan itu
 * yang menjaga data antar tenant tidak bocor. Modul ini menembusnya dengan
 * `acrossSchools()` — jadi ia tidak menyediakan satu pun aksi ubah atau hapus.
 * Menaruh tombol ubah di sini berarti satu salah klik bisa mengenai sekolah yang
 * bahkan tidak sedang dibuka operator, tanpa jejak tenant di sesinya.
 *
 * Satu pengecualian: tombol buka cepat di tab siswa (`admin.siswa.buka`). Ia
 * navigasi, bukan mutasi — dan justru menutup celah yang dikhawatirkan di atas,
 * karena ia MEMINDAHKAN konteks sekolah di sesi secara terang-terangan sebelum
 * membuka halaman siswanya, alih-alih membiarkan operator bekerja di sekolah
 * yang tidak sedang dibukanya. Route-nya sendiri hidup di luar modul ini.
 *
 * Datanya berat, jadi hanya tab yang sedang dibuka yang dihitung.
 */
class SemuaSekolahController extends Controller
{
    private const TABS = ['ringkasan', 'siswa', 'orang-tua', 'kelas', 'absensi', 'kartu'];

    public function index(Request $request): Response
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'ringkasan';
        $range = $this->resolveRange($request);
        $schoolId = $request->query('school_id');

        return Inertia::render('admin/semua-sekolah/index', [
            'tab' => $tab,
            'filters' => [
                'search' => $request->query('search', ''),
                'school_id' => $schoolId ?? '',
                'classroom_id' => $request->query('classroom_id', ''),
                'start' => $range['start'],
                'end' => $range['end'],
            ],
            'schools' => School::orderBy('name')->get(['id', 'name'])->all(),
            // Daftar kelas hanya diisi setelah satu sekolah dipilih. Nama kelas
            // berulang di setiap sekolah — "7A" ada di dua puluh tempat — jadi
            // daftar gabungannya justru tidak bisa dipakai memilih apa pun.
            'classrooms' => $schoolId ? $this->classroomOptions($schoolId) : [],
            'totals' => $this->totals(),
            'summary' => $tab === 'ringkasan' ? $this->summary() : null,
            'students' => $tab === 'siswa' ? $this->students($request) : null,
            'parents' => $tab === 'orang-tua' ? $this->parents($request) : null,
            'classrooms_list' => $tab === 'kelas' ? $this->classrooms($request) : null,
            'attendance' => $tab === 'absensi' ? $this->attendance($range) : null,
            'cards' => $tab === 'kartu' ? $this->cards($request) : null,
        ]);
    }

    /**
     * Pilihan kelas untuk satu sekolah.
     *
     * @return array<int, array<string, string>>
     */
    private function classroomOptions(string $schoolId): array
    {
        return Classroom::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Classroom $classroom) => ['id' => $classroom->id, 'name' => $classroom->name])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function totals(): array
    {
        return [
            'schools' => School::count(),
            'students' => Student::acrossSchools()->count(),
            'active_students' => Student::acrossSchools()->where('is_active', true)->count(),
        ];
    }

    /**
     * Satu baris per sekolah: jumlah siswa, kelas, dan kehadiran hari ini.
     *
     * @return array<int, array<string, mixed>>
     */
    private function summary(): array
    {
        $today = SchoolTime::now()->toDateString();

        // whereDate, bukan `=`: kolomnya di-cast `date` dan tersimpan berikut jam
        // 00:00:00, sehingga perbandingan string biasa meleset di SQLite.
        $checkIns = Attendance::acrossSchools()
            ->whereDate('attendance_date', $today)
            ->where('type', AttendanceType::CheckIn)
            ->selectRaw('school_id, status, count(*) as jumlah')
            ->groupBy('school_id', 'status')
            ->get()
            ->groupBy('school_id');

        // Global scope `school` ikut menempel pada subquery relasi, jadi tanpa
        // dilepas setiap sekolah selain milik super admin akan terhitung nol.
        $lepasBatasSekolah = fn ($query) => $query->withoutGlobalScope('school');

        return School::withCount(['students' => $lepasBatasSekolah, 'classrooms' => $lepasBatasSekolah])
            ->orderBy('name')
            ->get()
            ->map(function (School $school) use ($checkIns) {
                $rows = $checkIns->get($school->id) ?? collect();

                return [
                    'id' => $school->id,
                    'name' => $school->name,
                    'is_active' => $school->is_active,
                    'students_count' => $school->students_count,
                    'classrooms_count' => $school->classrooms_count,
                    'hadir' => (int) $rows->firstWhere('status', AttendanceStatus::Hadir)?->jumlah,
                    'terlambat' => (int) $rows->firstWhere('status', AttendanceStatus::Terlambat)?->jumlah,
                ];
            })
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function students(Request $request)
    {
        return Student::acrossSchools()
            ->with(['classroom:id,name', 'school:id,name'])
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            })
            ->when($request->query('school_id'), fn ($query, $schoolId) => $query->where('school_id', $schoolId))
            // Kelas hanya menyaring bersama sekolahnya. Id kelas memang unik, tapi
            // pilihannya baru dikirim setelah sekolah dipilih — menerimanya sendirian
            // berarti tautan tempelan bisa menampilkan satu kelas milik sekolah lain
            // di tengah daftar yang katanya tidak difilter.
            ->when(
                $request->query('school_id') && $request->query('classroom_id'),
                fn ($query) => $query->where('classroom_id', $request->query('classroom_id')),
            )
            ->orderBy('full_name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Student $student) => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'classroom' => $student->classroom?->name,
                // Nama sekolah untuk dibaca, id-nya untuk tombol buka cepat:
                // tombol itu harus tahu ke sekolah mana konteks sesi dipindahkan.
                'school' => $student->school?->name,
                'school_id' => $student->school_id,
                'is_active' => $student->is_active,
            ]);
    }

    /**
     * Orang tua lintas sekolah, hanya-baca.
     *
     * Tidak ada tombol ubah maupun hapus di sini, dan itu disengaja lebih keras
     * daripada di tab lain: `ParentProfile` tidak memakai SoftDeletes dan
     * `students.parent_profile_id` ber-cascadeOnDelete, jadi menghapus satu baris
     * di sini akan melenyapkan seluruh anaknya secara PERMANEN — di sekolah yang
     * bahkan tidak sedang dibuka operator.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function parents(Request $request)
    {
        return ParentProfile::acrossSchools()
            ->with(['user:id,name,email', 'school:id,name'])
            ->withCount(['students' => fn ($query) => $query->withoutGlobalScope('school')])
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('whatsapp_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->withoutGlobalScope('school'));
                });
            })
            ->when($request->query('school_id'), fn ($query, $schoolId) => $query->where('school_id', $schoolId))
            // Namanya ada di tabel `users`, jadi urutannya lewat subquery. Tanpa
            // urutan yang pasti, halaman 2 bisa mengulang baris dari halaman 1 —
            // MySQL tidak menjamin urutan apa pun tanpa ORDER BY.
            ->orderBy(User::select('name')->whereColumn('users.id', 'parent_profiles.user_id'))
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ParentProfile $parent) => [
                'id' => $parent->id,
                'name' => $parent->user?->name,
                'school' => $parent->school?->name,
                'whatsapp_number' => $parent->whatsapp_number,
                'relation' => $parent->relation?->label(),
                // Alamat `@internal.app` dibuat sistem dan tidak pernah sampai ke
                // siapa pun — menampilkannya sebagai email hanya menyesatkan.
                'email' => $parent->email && ! str_contains($parent->email, '@internal.app') ? $parent->email : null,
                'students_count' => $parent->students_count,
            ]);
    }

    /**
     * Kelas lintas sekolah, hanya-baca.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function classrooms(Request $request)
    {
        return Classroom::acrossSchools()
            ->with(['school:id,name', 'academicYear:id,name', 'homeroomTeacher:id,name'])
            ->withCount(['students' => fn ($query) => $query->withoutGlobalScope('school')])
            ->when($request->query('search'), fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($request->query('school_id'), fn ($query, $schoolId) => $query->where('school_id', $schoolId))
            ->orderBy('grade_level')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Classroom $classroom) => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'school' => $classroom->school?->name,
                'grade_level' => $classroom->grade_level,
                'academic_year' => $classroom->academicYear?->name,
                'homeroom_teacher' => $classroom->homeroomTeacher?->name,
                'students_count' => $classroom->students_count,
                'capacity' => $classroom->capacity,
            ]);
    }

    /**
     * Rekap kehadiran per sekolah pada rentang tanggal.
     *
     * @param  array{start: string, end: string}  $range
     * @return array<int, array<string, mixed>>
     */
    private function attendance(array $range): array
    {
        $rows = Attendance::acrossSchools()
            ->whereBetween('attendance_date', [$range['start'], $range['end']])
            ->where('type', AttendanceType::CheckIn)
            ->selectRaw('school_id, status, count(*) as jumlah')
            ->groupBy('school_id', 'status')
            ->get()
            ->groupBy('school_id');

        return School::orderBy('name')
            ->get(['id', 'name'])
            ->map(function (School $school) use ($rows) {
                $perStatus = $rows->get($school->id) ?? collect();
                $jumlah = fn (AttendanceStatus $status) => (int) $perStatus->firstWhere('status', $status)?->jumlah;

                $hadir = $jumlah(AttendanceStatus::Hadir);
                $terlambat = $jumlah(AttendanceStatus::Terlambat);
                $izin = $jumlah(AttendanceStatus::Izin);
                $sakit = $jumlah(AttendanceStatus::Sakit);
                $alpa = $jumlah(AttendanceStatus::Alpa);

                return [
                    'id' => $school->id,
                    'name' => $school->name,
                    'hadir' => $hadir,
                    'terlambat' => $terlambat,
                    'izin' => $izin,
                    'sakit' => $sakit,
                    'alpa' => $alpa,
                    'total' => $hadir + $terlambat + $izin + $sakit + $alpa,
                ];
            })
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function cards(Request $request)
    {
        return CardGenerationLog::acrossSchools()
            ->with(['student:id,full_name', 'school:id,name'])
            ->when($request->query('school_id'), fn ($query, $schoolId) => $query->where('school_id', $schoolId))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (CardGenerationLog $log) => [
                'id' => $log->id,
                'school' => $log->school?->name,
                'student' => $log->student?->full_name,
                'type' => $log->type,
                'status' => $log->status,
                'drive_url' => $log->drive_url,
                'file_url' => $log->file_path ? Storage::disk('public')->url($log->file_path) : null,
                'created_at' => SchoolTime::display($log->created_at),
            ]);
    }

    /**
     * @return array{start: string, end: string}
     */
    private function resolveRange(Request $request): array
    {
        $now = SchoolTime::now();
        $start = $request->query('start');
        $end = $request->query('end');

        return [
            'start' => $this->validDate($start) ?? $now->copy()->startOfMonth()->toDateString(),
            'end' => $this->validDate($end) ?? $now->copy()->endOfMonth()->toDateString(),
        ];
    }

    private function validDate(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
