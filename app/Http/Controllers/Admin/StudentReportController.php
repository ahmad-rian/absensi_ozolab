<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\Student\StudentReportBuilder;
use App\Services\Student\StudentStatsBuilder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentReportController extends Controller
{
    public function __construct(private readonly StudentReportBuilder $reports) {}

    public function attendanceXlsx(Request $request, Student $siswa): Response
    {
        return $this->reports->attendanceXlsx($request, $siswa);
    }

    public function attendancePdf(Request $request, Student $siswa): Response
    {
        return $this->reports->attendancePdf($request, $siswa);
    }

    public function prayerXlsx(Request $request, Student $siswa): Response
    {
        return $this->reports->prayerXlsx($request, $siswa);
    }

    public function prayerPdf(Request $request, Student $siswa): Response
    {
        return $this->reports->prayerPdf($request, $siswa);
    }

    public function combinedPdf(Request $request, Student $siswa): Response
    {
        $request->validate(['start_date' => ['nullable', 'date_format:Y-m-d'], 'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date']]);
        $range = app(StudentStatsBuilder::class)->resolveRange($request->input('start_date'), $request->input('end_date'));

        return $this->reports->combinedPdf($siswa, $range['start'], $range['end']);
    }
}
