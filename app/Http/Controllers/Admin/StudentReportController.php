<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\Student\StudentStatsBuilder;
use App\Support\SchoolTime;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan per siswa (absensi sekolah & absen sholat), CSV dan PDF.
 *
 * Semua angka datang dari StudentStatsBuilder yang sama dengan halaman detail
 * siswa, jadi isi berkas dijamin sama dengan yang tampil di layar.
 */
class StudentReportController extends Controller
{
    public function __construct(
        private readonly StudentStatsBuilder $stats,
    ) {}

    public function attendanceCsv(Request $request, Student $siswa): StreamedResponse
    {
        ['start' => $start, 'end' => $end] = $this->range($request);
        $data = $this->stats->attendanceFor($siswa, $start, $end);

        return $this->streamCsv(
            $this->filename('absensi', $siswa, $start, $end, 'csv'),
            ['Tanggal', 'Jenis', 'Status', 'Jam', 'Perangkat'],
            array_map(fn (array $row) => [
                $row['date'],
                $row['type_label'],
                $row['status_label'],
                $row['time'] ?? '-',
                $row['device_id'] ?? '-',
            ], $data['recent']),
            $this->summaryLines([
                'Hadir' => $data['summary']['hadir'],
                'Terlambat' => $data['summary']['terlambat'],
                'Izin' => $data['summary']['izin'],
                'Sakit' => $data['summary']['sakit'],
                'Alpa' => $data['summary']['alpa'],
                'Tanpa Catatan' => $data['summary']['tanpa_keterangan'],
                'Hari Efektif' => $data['summary']['effective_days'],
                '% Kehadiran' => $data['summary']['rate'].'%',
            ], $siswa, $start, $end),
        );
    }

    public function attendancePdf(Request $request, Student $siswa): HttpResponse
    {
        ['start' => $start, 'end' => $end] = $this->range($request);
        $data = $this->stats->attendanceFor($siswa, $start, $end);

        $pdf = Pdf::loadView('pdf.student-attendance', [
            'student' => $siswa->loadMissing('classroom'),
            'schoolName' => $siswa->school?->name ?? 'Sekolah',
            'startDate' => $start,
            'endDate' => $end,
            'summary' => $data['summary'],
            'recent' => $data['recent'],
            'printedAt' => SchoolTime::now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($this->filename('absensi', $siswa, $start, $end, 'pdf'));
    }

    public function prayerCsv(Request $request, Student $siswa): StreamedResponse
    {
        ['start' => $start, 'end' => $end] = $this->range($request);
        $data = $this->stats->prayerFor($siswa, $start, $end);

        return $this->streamCsv(
            $this->filename('sholat', $siswa, $start, $end, 'csv'),
            ['Tanggal', 'Status', 'Jam', 'Perangkat'],
            array_map(fn (array $row) => [
                $row['date'],
                $row['status_label'],
                $row['time'] ?? '-',
                $row['device_id'] ?? '-',
            ], $data['recent']),
            $this->summaryLines([
                'Ikut Sholat' => $data['summary']['hadir'],
                'Tidak Ikut' => $data['summary']['tidak_hadir'],
                'Hari Efektif' => $data['summary']['effective_days'],
                '% Kehadiran' => $data['summary']['rate'].'%',
            ], $siswa, $start, $end),
        );
    }

    public function prayerPdf(Request $request, Student $siswa): HttpResponse
    {
        ['start' => $start, 'end' => $end] = $this->range($request);
        $data = $this->stats->prayerFor($siswa, $start, $end);

        $pdf = Pdf::loadView('pdf.student-prayer', [
            'student' => $siswa->loadMissing('classroom'),
            'schoolName' => $siswa->school?->name ?? 'Sekolah',
            'startDate' => $start,
            'endDate' => $end,
            'window' => $data['window'],
            'summary' => $data['summary'],
            'recent' => $data['recent'],
            'printedAt' => SchoolTime::now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($this->filename('sholat', $siswa, $start, $end, 'pdf'));
    }

    /**
     * @return array{start: string, end: string}
     */
    private function range(Request $request): array
    {
        return $this->stats->resolveRange($request->query('start_date'), $request->query('end_date'));
    }

    private function filename(string $prefix, Student $siswa, string $start, string $end, string $ext): string
    {
        return sprintf('%s-%s-%s-%s.%s', $prefix, $siswa->nis ?? $siswa->id, $start, $end, $ext);
    }

    /**
     * Blok identitas + ringkasan yang ditulis sebelum tabel rincian.
     *
     * @param  array<string, mixed>  $summary
     * @return array<int, array<int, string>>
     */
    private function summaryLines(array $summary, Student $siswa, string $start, string $end): array
    {
        $lines = [
            ['Nama', $siswa->full_name],
            ['NIS', (string) ($siswa->nis ?? '-')],
            ['NISN', (string) ($siswa->nisn ?? '-')],
            ['Kelas', $siswa->classroom?->name ?? '-'],
            ['Periode', $start.' s/d '.$end],
            [],
        ];

        foreach ($summary as $label => $value) {
            $lines[] = [$label, (string) $value];
        }

        $lines[] = [];

        return $lines;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, array<int, string>>  $preamble
     */
    private function streamCsv(string $filename, array $header, array $rows, array $preamble): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows, $preamble) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM supaya Excel membaca karakter Indonesia dengan benar.
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($preamble as $line) {
                fputcsv($handle, $line);
            }

            fputcsv($handle, $header);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
