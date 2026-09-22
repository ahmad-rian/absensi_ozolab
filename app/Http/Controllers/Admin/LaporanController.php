<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrayerType;
use App\Enums\SchoolFeature;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Setting;
use App\Models\Student;
use App\Services\Student\StudentStatsBuilder;
use App\Support\SchoolFeatures;
use App\Support\SchoolTime;
use App\Support\XlsxDownload;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LaporanController extends Controller
{
    public function index(Request $request): Response
    {
        $kind = $this->reportKind($request);
        $startDate = $request->input('start_date', SchoolTime::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', SchoolTime::now()->endOfMonth()->toDateString());
        $classroomId = $request->input('classroom_id');

        $schoolId = auth()->user()->school_id;

        $effectiveDays = Attendance::whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->distinct('attendance_date')
            ->count('attendance_date');

        $reportData = $this->dataFor($startDate, $endDate, $classroomId, $schoolId, $kind);

        $summary = [
            'effective_days' => $effectiveDays,
            'total_hadir' => $reportData->sum('hadir'),
            'total_terlambat' => $reportData->sum('terlambat'),
            'total_tidak_hadir' => $reportData->sum('izin') + $reportData->sum('sakit') + $reportData->sum('alpa'),
        ];

        $classrooms = Classroom::forSchool()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('admin/laporan/index', [
            'reportData' => $reportData->values(),
            'kinds' => $this->availableKinds(),
            'summary' => $summary,
            'classrooms' => $classrooms,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'classroom_id' => $classroomId ?? '',
                'jenis' => $kind,
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $kind = $this->reportKind($request);
        $startDate = $request->input('start_date', SchoolTime::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', SchoolTime::now()->endOfMonth()->toDateString());
        $classroomId = $request->input('classroom_id');
        $schoolId = auth()->user()->school_id;

        if ($kind !== 'absensi') {
            return XlsxDownload::sheets('laporan-'.$startDate.'.xlsx', $this->reportSheets($startDate, $endDate, $classroomId, $schoolId, $kind));
        }

        $reportData = $this->dataFor($startDate, $endDate, $classroomId, $schoolId, $kind);

        return XlsxDownload::make(
            'laporan-kehadiran-'.SchoolTime::now()->format('Y-m-d').'.xlsx',
            [
                'NIS',
                'Nama Siswa',
                'Kelas',
                'Hadir',
                'Terlambat',
                'Izin',
                'Sakit',
                'Alpa',
                '% Kehadiran',
            ],
            $reportData->map(fn (array $row): array => [
                // NIS dikirim sebagai teks: nomor induk berawalan nol akan
                // kehilangan nol depannya kalau Excel memperlakukannya angka.
                (string) $row['nis'],
                $row['full_name'],
                $row['classroom_name'],
                (int) $row['hadir'],
                (int) $row['terlambat'],
                (int) $row['izin'],
                (int) $row['sakit'],
                (int) $row['alpa'],
                $row['attendance_rate'].'%',
            ])->all(),
        );
    }

    public function exportPdf(Request $request): HttpResponse
    {
        $kind = $this->reportKind($request);
        $startDate = $request->input('start_date', SchoolTime::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', SchoolTime::now()->endOfMonth()->toDateString());
        $classroomId = $request->input('classroom_id');
        $schoolId = auth()->user()->school_id;

        $reportData = $this->dataFor($startDate, $endDate, $classroomId, $schoolId, $kind);

        $summary = [
            'total_hadir' => $reportData->sum('hadir'),
            'total_terlambat' => $reportData->sum('terlambat'),
            'total_izin' => $reportData->sum('izin'),
            'total_sakit' => $reportData->sum('sakit'),
            'total_alpa' => $reportData->sum('alpa'),
        ];

        $school = School::find($schoolId);
        $schoolName = $school?->name ?? Setting::getValue('school_name', 'Sekolah');

        $pdf = Pdf::loadView('pdf.laporan', [
            'reportData' => $reportData,
            'summary' => $summary,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'schoolName' => $schoolName,
            'reportKind' => $kind,
            'kinds' => $this->availableKinds(),
        ]);

        $pdf->setPaper('a4', 'landscape');

        $filename = 'laporan-kehadiran-'.SchoolTime::now()->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    private function availableKinds(): array
    {
        $kinds = ['absensi' => 'Absensi Sekolah'];
        $school = app()->bound('currentSchool') ? app('currentSchool') : null;
        foreach (['dhuha' => SchoolFeature::SholatDhuha, 'dzuhur' => SchoolFeature::SholatDzuhur] as $slug => $feature) {
            if ($school && SchoolFeatures::for($school)->enabled($feature)) {
                $kinds[$slug] = ucfirst($slug);
            }
        }

        return $kinds;
    }

    private function reportKind(Request $request): string
    {
        $request->validate(['jenis' => ['nullable', 'string', 'in:absensi,dhuha,dzuhur,semuanya'], 'start_date' => ['nullable', 'date_format:Y-m-d'], 'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'], 'classroom_id' => ['nullable', 'string']]);
        $kind = $request->input('jenis', 'absensi');
        abort_unless($kind === 'semuanya' || isset($this->availableKinds()[$kind]), 403);

        return $kind;
    }

    private function dataFor(string $start, string $end, ?string $classroom, ?string $school, string $kind): Collection
    {
        $classroom = $classroom === 'all' ? null : $classroom;
        $stats = app(StudentStatsBuilder::class);

        return Student::where('school_id', $school)->when($classroom, fn ($q) => $q->where('classroom_id', $classroom))->with(['school', 'classroom'])->orderBy('full_name')->get()->map(function ($student) use ($stats, $start, $end, $kind): array {
            $attendance = $stats->attendanceFor($student, $start, $end)['summary'];
            $prayers = [];
            foreach ($this->availableKinds() as $slug => $label) {
                if ($slug !== 'absensi') {
                    $prayers[$slug] = $stats->prayerFor($student, $start, $end, PrayerType::fromSlug($slug))['summary'];
                }
            }
            $summary = in_array($kind, ['semuanya', 'absensi'], true) ? $attendance : $prayers[$kind];

            return [
                'student_id' => $student->id, 'nis' => $student->nis, 'full_name' => $student->full_name, 'classroom_name' => $student->classroom?->name ?? '-',
                'hadir' => $summary['hadir'], 'terlambat' => $summary['terlambat'] ?? 0, 'izin' => $summary['izin'] ?? 0, 'sakit' => $summary['sakit'] ?? 0,
                'alpa' => $summary['alpa'] ?? $summary['tidak_hadir'] ?? 0, 'attendance_rate' => $summary['rate'], 'effective_days' => $summary['effective_days'], 'prayers' => $prayers,
            ];
        });
    }

    private function reportSheets(string $start, string $end, ?string $classroom, ?string $school, string $kind): array
    {
        $rows = $this->dataFor($start, $end, $classroom, $school, 'semuanya');
        $sheets = [];
        $kinds = $this->availableKinds();
        if ($kind === 'semuanya') {
            $sheets['Ringkasan'] = ['header' => ['NIS', 'Nama Siswa', 'Kelas', ...array_map(fn ($label) => '% '.$label, array_values($kinds))], 'rows' => $rows->map(fn ($row) => [(string) $row['nis'], $row['full_name'], $row['classroom_name'], $row['attendance_rate'].'%', ...array_map(fn ($summary) => $summary['rate'].'%', array_values($row['prayers']))])->all()];
            $sheets['Absensi Sekolah'] = ['header' => ['NIS', 'Nama Siswa', 'Kelas', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa', '% Kehadiran'], 'rows' => $rows->map(fn ($row) => [(string) $row['nis'], $row['full_name'], $row['classroom_name'], $row['hadir'], $row['terlambat'], $row['izin'], $row['sakit'], $row['alpa'], $row['attendance_rate'].'%'])->all()];
        }
        foreach ($kinds as $slug => $label) {
            if ($slug === 'absensi' || ! in_array($kind, ['semuanya', $slug], true)) {
                continue;
            }
            $sheets[$label] = ['header' => ['NIS', 'Nama Siswa', 'Kelas', 'Ikut', 'Tidak Ikut', 'Hari Efektif', '% Kehadiran'], 'rows' => $rows->map(fn ($row) => [(string) $row['nis'], $row['full_name'], $row['classroom_name'], $row['prayers'][$slug]['hadir'], $row['prayers'][$slug]['tidak_hadir'], $row['prayers'][$slug]['effective_days'], $row['prayers'][$slug]['rate'].'%'])->all()];
        }

        return $sheets;
    }
}
