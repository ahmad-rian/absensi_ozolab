<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrayerType;
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
                'Rata-rata Jam Masuk' => $data['punctuality']['avg_check_in'] ?? '-',
                'Runtun Hadir Terpanjang' => $data['streaks']['longest_present'],
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
            'punctuality' => $data['punctuality'],
            'streaks' => $data['streaks'],
            'byWeekday' => $data['by_weekday'],
            'comparison' => $data['comparison'],
            'recent' => $data['recent'],
            'printedAt' => SchoolTime::now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($this->filename('absensi', $siswa, $start, $end, 'pdf'));
    }

    public function prayerCsv(Request $request, Student $siswa): StreamedResponse
    {
        ['start' => $start, 'end' => $end] = $this->range($request);
        $type = $this->prayerType($request);
        $data = $this->stats->prayerFor($siswa, $start, $end, $type);

        return $this->streamCsv(
            $this->filename($this->prayerPrefix($type), $siswa, $start, $end, 'csv'),
            ['Tanggal', 'Jenis', 'Status', 'Jam', 'Perangkat'],
            array_map(fn (array $row) => [
                $row['date'],
                $row['type_label'] ?? '-',
                $row['status_label'],
                $row['time'] ?? '-',
                $row['device_id'] ?? '-',
            ], $data['recent']),
            $this->summaryLines($this->prayerSummaryLines($data), $siswa, $start, $end),
        );
    }

    public function prayerPdf(Request $request, Student $siswa): HttpResponse
    {
        ['start' => $start, 'end' => $end] = $this->range($request);
        $type = $this->prayerType($request);
        $data = $this->stats->prayerFor($siswa, $start, $end, $type);

        $pdf = Pdf::loadView('pdf.student-prayer', [
            'student' => $siswa->loadMissing('classroom'),
            'schoolName' => $siswa->school?->name ?? 'Sekolah',
            'title' => $type?->label() ?? 'Sholat Berjamaah',
            'startDate' => $start,
            'endDate' => $end,
            'window' => $data['window'],
            'summary' => $data['summary'],
            'types' => $data['types'],
            'recent' => $data['recent'],
            'printedAt' => SchoolTime::now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($this->filename($this->prayerPrefix($type), $siswa, $start, $end, 'pdf'));
    }

    /**
     * `?jenis=dhuha|dzuhur` — tanpa parameter berarti seluruh jenis yang aktif.
     *
     * Sengaja query param, bukan segmen rute baru: keempat rute laporan yang
     * sudah beredar sebagai `<a href>` tetap hidup tanpa alias apa pun.
     * Nilai ngawur diperlakukan sebagai "seluruh jenis", bukan 404, supaya
     * tautan salah ketik tetap menghasilkan laporan.
     */
    private function prayerType(Request $request): ?PrayerType
    {
        // `query()` mengembalikan array untuk `?jenis[]=x`; tanpa penjaga ini
        // signature `fromSlug(?string)` melempar TypeError alias 500.
        $jenis = $request->query('jenis');

        return is_string($jenis) ? PrayerType::fromSlug($jenis) : null;
    }

    private function prayerPrefix(?PrayerType $type): string
    {
        return $type ? 'sholat-'.$type->slug() : 'sholat';
    }

    /**
     * Ringkasan gabungan tetap memakai label lama supaya laporan yang sudah
     * beredar tidak berubah bentuk; rincian per jenis ditambahkan di bawahnya.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prayerSummaryLines(array $data): array
    {
        $lines = [
            'Ikut Sholat' => $data['summary']['hadir'],
            'Tidak Ikut' => $data['summary']['tidak_hadir'],
            'Hari Efektif' => $data['summary']['effective_days'],
            '% Kehadiran' => $data['summary']['rate'].'%',
        ];

        foreach ($data['types'] as $type) {
            $lines[$type['label']] = $type['summary']['hadir'].' dari '.$type['summary']['effective_days']
                .' ('.$type['summary']['rate'].'%)';
        }

        return $lines;
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
                fputcsv($handle, array_map(fn ($cell) => $this->csvSafe($cell), $line));
            }

            fputcsv($handle, $header);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($cell) => $this->csvSafe($cell), $row));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
