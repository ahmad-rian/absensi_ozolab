<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanController extends Controller
{
    public function index(Request $request): Response
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $classroomId = $request->input('classroom_id');

        $schoolId = auth()->user()->school_id;

        $effectiveDays = Attendance::whereHas('student', fn ($q) => $q->where('school_id', $schoolId))
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->distinct('attendance_date')
            ->count('attendance_date');

        $reportData = $this->getReportData($startDate, $endDate, $classroomId, $schoolId);

        $summary = [
            'effective_days' => $effectiveDays,
            'total_hadir' => $reportData->sum('hadir'),
            'total_terlambat' => $reportData->sum('terlambat'),
            'total_tidak_hadir' => $reportData->sum('izin') + $reportData->sum('sakit') + $reportData->sum('alpa'),
        ];

        $classrooms = Classroom::forSchool()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('admin/laporan/index', [
            'reportData' => $reportData->values(),
            'summary' => $summary,
            'classrooms' => $classrooms,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'classroom_id' => $classroomId ?? '',
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $classroomId = $request->input('classroom_id');
        $schoolId = auth()->user()->school_id;

        $reportData = $this->getReportData($startDate, $endDate, $classroomId, $schoolId);

        $filename = 'laporan-kehadiran-'.Carbon::now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($reportData) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'NIS',
                'Nama Siswa',
                'Kelas',
                'Hadir',
                'Terlambat',
                'Izin',
                'Sakit',
                'Alpa',
                '% Kehadiran',
            ]);

            foreach ($reportData as $row) {
                fputcsv($handle, [
                    $row['nis'],
                    $row['full_name'],
                    $row['classroom_name'],
                    $row['hadir'],
                    $row['terlambat'],
                    $row['izin'],
                    $row['sakit'],
                    $row['alpa'],
                    $row['attendance_rate'].'%',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request): HttpResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $classroomId = $request->input('classroom_id');
        $schoolId = auth()->user()->school_id;

        $reportData = $this->getReportData($startDate, $endDate, $classroomId, $schoolId);

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
        ]);

        $pdf->setPaper('a4', 'landscape');

        $filename = 'laporan-kehadiran-'.Carbon::now()->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * @return Collection<int, array{student_id: int, nis: string, full_name: string, classroom_name: string, hadir: int, terlambat: int, izin: int, sakit: int, alpa: int, attendance_rate: float}>
     */
    private function getReportData(string $startDate, string $endDate, ?string $classroomId, ?string $schoolId): Collection
    {
        $query = Attendance::when($schoolId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('school_id', $schoolId)))
            ->select(
                'student_id',
                DB::raw('COUNT(CASE WHEN status = \''.AttendanceStatus::Hadir->value.'\' THEN 1 END) as hadir_count'),
                DB::raw('COUNT(CASE WHEN status = \''.AttendanceStatus::Terlambat->value.'\' THEN 1 END) as terlambat_count'),
                DB::raw('COUNT(CASE WHEN status = \''.AttendanceStatus::Izin->value.'\' THEN 1 END) as izin_count'),
                DB::raw('COUNT(CASE WHEN status = \''.AttendanceStatus::Sakit->value.'\' THEN 1 END) as sakit_count'),
                DB::raw('COUNT(CASE WHEN status = \''.AttendanceStatus::Alpa->value.'\' THEN 1 END) as alpa_count'),
                DB::raw('COUNT(*) as total_records'),
            )
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->when($classroomId, function ($q) use ($classroomId) {
                $q->whereHas('student', fn ($sq) => $sq->where('classroom_id', $classroomId));
            })
            ->groupBy('student_id')
            ->with('student:id,nis,full_name,classroom_id', 'student.classroom:id,name');

        return $query->get()->map(function ($row) {
            $totalPresent = $row->hadir_count + $row->terlambat_count;
            $attendanceRate = $row->total_records > 0
                ? round(($totalPresent / $row->total_records) * 100, 1)
                : 0;

            return [
                'student_id' => $row->student_id,
                'nis' => $row->student?->nis ?? '-',
                'full_name' => $row->student?->full_name ?? '-',
                'classroom_name' => $row->student?->classroom?->name ?? '-',
                'hadir' => $row->hadir_count,
                'terlambat' => $row->terlambat_count,
                'izin' => $row->izin_count,
                'sakit' => $row->sakit_count,
                'alpa' => $row->alpa_count,
                'attendance_rate' => $attendanceRate,
            ];
        });
    }
}
