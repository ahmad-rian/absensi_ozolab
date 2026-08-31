<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ApplyStudentImportJob;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentImportJob;
use App\Services\Import\StudentImportParser;
use App\Support\SchoolTime;
use App\Support\XlsxDownload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentImportController extends Controller
{
    private const STAGING_PREFIX = 'student-import-staging:';

    /**
     * Kunci baris yang dibaca ApplyStudentImportJob. Prefix-nya sengaja beda
     * dari staging: staging dibuang begitu admin menekan "Terapkan", sementara
     * kunci ini harus bertahan sampai worker sempat menjalankannya.
     */
    private const ROWS_PREFIX = 'student-import:';

    /** @var list<string> */
    private const TEMPLATE_HEADER = [
        'NISN', 'NIS', 'Nama', 'Kelas', 'JK', 'Agama', 'No Absen',
        'Tempat Lahir', 'Tanggal Lahir', 'Alamat', 'Nama Orang Tua', 'No HP',
    ];

    /** @var list<list<string>> */
    private const TEMPLATE_SAMPLE_ROWS = [
        ['0071234567', '2025001', 'Ahmad Fauzi', '7A', 'L', 'Islam', '1', 'Bandung', '12/03/2012', 'Jl. Merdeka No. 10', 'Budi Santoso', '081234567890'],
        ['0071234568', '2025002', 'Siti Aminah', '7A', 'P', 'Islam', '2', 'Cimahi', '25/07/2012', 'Jl. Melati No. 4', 'Rahmat Hidayat', '081298765432'],
    ];

    public function index(): Response
    {
        return Inertia::render('admin/siswa/import', [
            'jobs' => $this->recentJobs(),
            'preview' => null,
        ]);
    }

    /**
     * Template Excel: header persis sinonim yang dikenali parser, plus dua baris
     * contoh supaya format tanggal dan kode jenis kelamin tidak perlu ditebak.
     *
     * XLSX, bukan CSV, karena seluruh isinya ditulis sebagai teks: NISN
     * `0071234567` dan tanggal `12/03/2012` bertahan apa adanya, sementara CSV
     * menyerahkannya pada tebakan Excel.
     */
    public function template(): BinaryFileResponse
    {
        return XlsxDownload::make(
            'template-impor-siswa.xlsx',
            self::TEMPLATE_HEADER,
            self::TEMPLATE_SAMPLE_ROWS,
        );
    }

    public function upload(Request $request, StudentImportParser $parser): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ], [], ['file' => 'berkas']);

        $schoolId = $this->currentSchoolId();

        $file = $request->file('file');
        $filename = Str::limit($file->getClientOriginalName(), 120, '');
        $extension = $file->getClientOriginalExtension();

        // Berkas mentah tidak pernah jadi arsip: hanya numpang di disk privat
        // supaya OpenSpout punya path yang bisa dibaca streaming.
        $storedPath = $file->store('student-imports', 'local');

        try {
            $parsed = $parser->parse(Storage::disk('local')->path($storedPath), $extension, $schoolId);
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($storedPath);
        }

        $key = Str::random(32);

        Cache::put(self::STAGING_PREFIX.$key, [
            'school_id' => $schoolId,
            'filename' => $filename,
            'rows' => $parsed['rows'],
            'summary' => $parsed['summary'],
        ], now()->addHour());

        return to_route('admin.siswa.import.review', ['key' => $key]);
    }

    public function review(string $key): Response
    {
        $staging = $this->staging($key);

        return Inertia::render('admin/siswa/import', [
            'jobs' => $this->recentJobs(),
            'preview' => [
                'key' => $key,
                'filename' => $staging['filename'],
                'summary' => $staging['summary'],
                'groups' => $this->groupRows($staging['rows']),
            ],
        ]);
    }

    public function apply(string $key): RedirectResponse
    {
        $staging = $this->staging($key);

        $importJob = StudentImportJob::query()->create([
            'school_id' => $staging['school_id'],
            'created_by' => auth()->id(),
            'filename' => $staging['filename'],
            'status' => StudentImportJob::STATUS_PENDING,
            'total_rows' => count($staging['rows']),
        ]);

        // Baris dititipkan lewat cache, bukan payload queue: 5.000 baris terlalu
        // besar untuk disimpan di tabel jobs.
        Cache::put(self::ROWS_PREFIX.$importJob->id, $staging['rows'], now()->addHour());
        Cache::forget(self::STAGING_PREFIX.$key);

        ApplyStudentImportJob::dispatch($importJob->id, self::ROWS_PREFIX.$importJob->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Impor sedang diproses. Status akan diperbarui otomatis.',
        ]);

        return to_route('admin.siswa.import');
    }

    /**
     * @return array{school_id: string, filename: string, rows: list<array{row_number: int, action: string, reason: ?string, student_id: ?string, data: array<string, mixed>}>, summary: array<string, int>}
     */
    private function staging(string $key): array
    {
        // Kunci masuk mentah dari URL dan dipakai sebagai cache key; batasi
        // bentuknya supaya tidak bisa menyentuh entri cache lain.
        abort_unless((bool) preg_match('/^[A-Za-z0-9]{32}$/', $key), 404);

        $staging = Cache::get(self::STAGING_PREFIX.$key);

        abort_if(! is_array($staging), 404, 'Data pratinjau sudah kedaluwarsa. Unggah ulang berkasnya.');

        // Staging tidak lewat model, jadi global scope sekolah tidak menolongnya:
        // pemilik kunci diperiksa manual.
        abort_unless($staging['school_id'] === $this->currentSchoolId(), 403);

        return $staging;
    }

    private function currentSchoolId(): string
    {
        $schoolId = auth()->user()?->school_id;

        abort_if(blank($schoolId), 403, 'Akun ini belum terhubung ke sekolah mana pun.');

        return (string) $schoolId;
    }

    /**
     * @param  list<array{row_number: int, action: string, reason: ?string, student_id: ?string, data: array<string, mixed>}>  $rows
     * @return array{create: list<array<string, mixed>>, update: list<array<string, mixed>>, reject: list<array<string, mixed>>}
     */
    private function groupRows(array $rows): array
    {
        $classroomNames = Classroom::forSchool()->pluck('name', 'id');

        $studentIds = array_values(array_filter(array_column($rows, 'student_id')));
        $studentNames = $studentIds === []
            ? collect()
            : Student::forSchool()->whereIn('id', $studentIds)->pluck('full_name', 'id');

        $groups = ['create' => [], 'update' => [], 'reject' => []];

        foreach ($rows as $row) {
            $data = $row['data'];
            $classroomId = $data['classroom_id'] ?? null;

            $groups[$row['action']][] = [
                'row_number' => $row['row_number'],
                'full_name' => $data['full_name'] ?? null,
                'nis' => $data['nis'] ?? null,
                'nisn' => $data['nisn'] ?? null,
                'classroom_name' => $classroomId ? ($classroomNames[$classroomId] ?? null) : null,
                'existing_name' => $row['student_id'] ? ($studentNames[$row['student_id']] ?? null) : null,
                'reason' => $row['reason'],
            ];
        }

        return $groups;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentJobs(): array
    {
        return StudentImportJob::forSchool()
            ->with('creator:id,name')
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (StudentImportJob $job): array => [
                'id' => $job->id,
                'filename' => $job->filename,
                'status' => $job->status,
                'total_rows' => $job->total_rows,
                'created_count' => $job->created_count,
                'updated_count' => $job->updated_count,
                'failed_count' => $job->failed_count,
                'errors' => array_slice($job->errors ?? [], 0, 20),
                'created_by' => $job->creator?->name ?? '-',
                'created_at' => SchoolTime::display($job->created_at),
            ])
            ->all();
    }
}
