<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\SchoolFeature;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\RekapBulananKelas;
use App\Services\Poin\SapuanPoin;
use App\Support\SchoolFeatures;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Absen manual satu kelas sekaligus: admin / guru memilih status masuk tiap
 * siswa (hadir, terlambat, izin, sakit, alpa) dalam satu layar.
 *
 * Kelas di-bind lewat global scope sekolah, jadi kelas sekolah lain = 404.
 */
class AbsensiKelasController extends Controller
{
    /** Tab halaman detail kelas. */
    private const TAB = ['absen', 'rekap', 'siswa'];

    public function show(Request $request, Classroom $classroom, RekapBulananKelas $rekap): Response
    {
        $tanggal = $this->tanggal($request->query('tanggal'));
        $bulan = $this->bulan($request->query('bulan'), $tanggal);
        $tab = in_array($request->query('tab'), self::TAB, true) ? $request->query('tab') : 'absen';

        $absen = Attendance::query()
            ->whereDate('attendance_date', $tanggal)
            ->where('type', AttendanceType::CheckIn->value)
            ->whereIn('student_id', Student::query()->where('classroom_id', $classroom->id)->select('id'))
            ->get(['student_id', 'status', 'notes', 'recorded_by', 'recorded_at'])
            ->keyBy('student_id');

        $siswa = Student::query()
            ->where('classroom_id', $classroom->id)
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'nis', 'nisn', 'full_name'])
            ->map(function (Student $student) use ($absen): array {
                $baris = $absen->get($student->id);

                return [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'nisn' => $student->nisn,
                    'nama' => $student->full_name,
                    'status' => $baris?->status?->value,
                    'catatan' => $baris?->notes,
                    'sumber' => $baris === null ? null : ($baris->recorded_by ? 'manual' : 'scan'),
                    'jam' => $baris?->recorded_at?->format('H:i'),
                ];
            })
            ->values();

        return Inertia::render('admin/absensi/kelas', [
            'kelas' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'grade_level' => $classroom->grade_level,
                'wali' => $classroom->homeroomTeacher?->name,
            ],
            'daftarKelas' => Classroom::query()->orderBy('name')->get(['id', 'name']),
            'tanggal' => $tanggal,
            'bulan' => $bulan,
            'tab' => $tab,
            'siswa' => $siswa,
            'rekap' => Inertia::defer(fn (): array => $rekap->untuk($classroom, $bulan), 'rekap'),
        ]);
    }

    public function store(Request $request, Classroom $classroom, SapuanPoin $sapuan): RedirectResponse
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.SchoolTime::todayString()],
            'baris' => ['required', 'array', 'min:1', 'max:200'],
            'baris.*.student_id' => ['required', 'string', 'distinct'],
            'baris.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'baris.*.catatan' => ['nullable', 'string', 'max:500'],
        ], [
            'tanggal.before_or_equal' => 'Absen manual tidak bisa untuk tanggal yang belum terjadi.',
        ]);

        $ids = collect($validated['baris'])->pluck('student_id');

        $siswa = Student::query()
            ->where('classroom_id', $classroom->id)
            ->whereIn('id', $ids)
            ->pluck('school_id', 'id');

        if ($siswa->count() !== $ids->count()) {
            return back()->withErrors(['baris' => 'Ada siswa yang bukan anggota kelas ini. Muat ulang halaman lalu coba lagi.']);
        }

        $tanggal = $validated['tanggal'];
        $berubah = DB::transaction(fn (): int => $this->simpan($validated['baris'], $siswa->all(), $tanggal, $request->user()->id));

        if ($berubah > 0) {
            $this->sinkronPoin($sapuan, $classroom, $tanggal);

            activity()->performedOn($classroom)->causedBy($request->user())
                ->withProperties(['tanggal' => $tanggal, 'jumlah' => $berubah])
                ->log('absen manual per kelas');
        }

        return back()->with('success', $berubah > 0
            ? "Absensi {$berubah} siswa tersimpan."
            : 'Tidak ada perubahan untuk disimpan.');
    }

    /**
     * Tulis hanya baris yang statusnya / catatannya berbeda dari yang ada.
     *
     * @param  array<int, array{student_id: string, status: string, catatan?: string|null}>  $baris
     * @param  array<string, string>  $sekolahSiswa  student_id => school_id
     */
    private function simpan(array $baris, array $sekolahSiswa, string $tanggal, string $userId): int
    {
        $ada = Attendance::query()
            ->whereDate('attendance_date', $tanggal)
            ->where('type', AttendanceType::CheckIn->value)
            ->whereIn('student_id', array_keys($sekolahSiswa))
            ->lockForUpdate()
            ->get()
            ->keyBy('student_id');

        $berubah = 0;

        foreach ($baris as $row) {
            $status = AttendanceStatus::from($row['status']);
            $catatan = filled($row['catatan'] ?? null) ? $row['catatan'] : null;
            $lama = $ada->get($row['student_id']);

            if ($lama === null) {
                Attendance::create([
                    'school_id' => $sekolahSiswa[$row['student_id']],
                    'student_id' => $row['student_id'],
                    'attendance_date' => $tanggal,
                    'type' => AttendanceType::CheckIn,
                    'status' => $status,
                    'recorded_at' => $this->jamCatat($tanggal),
                    'recorded_by' => $userId,
                    'notes' => $catatan,
                ]);
                $berubah++;

                continue;
            }

            if ($lama->status === $status && $lama->notes === $catatan) {
                continue;
            }

            $lama->forceFill(['status' => $status, 'notes' => $catatan, 'recorded_by' => $userId])->save();
            $berubah++;
        }

        return $berubah;
    }

    /**
     * Poin otomatis dicocokkan ulang saat itu juga supaya koreksi (mis. alpa
     * jadi sakit) tidak menunggu sapuan per jam.
     */
    private function sinkronPoin(SapuanPoin $sapuan, Classroom $classroom, string $tanggal): void
    {
        $school = School::find($classroom->school_id);

        if (! $school || ! SchoolFeatures::for($school)->enabled(SchoolFeature::PoinPelanggaran)) {
            return;
        }

        $sejak = $school->getSetting(SapuanPoin::SETTING_AKTIF_SEJAK);

        if (! is_string($sejak) || $sejak === '' || $tanggal < $sejak) {
            return;
        }

        $sapuan->sapu($school, Carbon::createFromFormat('Y-m-d', $tanggal, SchoolTime::timezone())->startOfDay());
    }

    private function jamCatat(string $tanggal): Carbon
    {
        $sekarang = SchoolTime::now();

        return $tanggal === $sekarang->toDateString()
            ? $sekarang
            : Carbon::createFromFormat('Y-m-d H:i:s', $tanggal.' '.$sekarang->format('H:i:s'), SchoolTime::timezone());
    }

    /**
     * Bulan rekap (Y-m). Tidak boleh melewati bulan ini; bawaannya bulan dari
     * tanggal yang sedang dibuka.
     */
    private function bulan(mixed $nilai, string $tanggal): string
    {
        $bulanIni = substr(SchoolTime::todayString(), 0, 7);

        if (is_string($nilai) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $nilai) === 1) {
            return min($nilai, $bulanIni);
        }

        return substr($tanggal, 0, 7);
    }

    private function tanggal(mixed $nilai): string
    {
        if (is_string($nilai) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) && strtotime($nilai) !== false) {
            return min($nilai, SchoolTime::todayString());
        }

        return SchoolTime::todayString();
    }
}
