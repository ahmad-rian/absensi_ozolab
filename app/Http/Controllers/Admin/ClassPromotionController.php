<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Services\Import\ClassPromotionParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

/**
 * Kenaikan kelas massal lewat berkas NISN + kelas baru.
 *
 * Alurnya sama dengan impor siswa: unggah -> review -> terapkan, dengan hasil
 * parse dititipkan di cache selama satu jam. Barisnya tidak dibawa pulang-pergi
 * lewat form tersembunyi supaya berkas ribuan baris tidak membengkakkan request
 * dan isi pratinjau tidak bisa dikarang ulang sebelum "terapkan" ditekan.
 *
 * Berbeda dari impor siswa, penerapan dikerjakan langsung (bukan lewat queue):
 * satu berkas kenaikan kelas paling banyak sejumlah siswa satu sekolah dan
 * admin butuh tahu hasilnya seketika sebelum tahun ajaran berjalan dipakai.
 */
class ClassPromotionController extends Controller
{
    private const STAGING_PREFIX = 'class-promotion-staging:';

    public function index(): Response
    {
        return Inertia::render('admin/kelas/kenaikan', [
            'academic_year' => $this->activeAcademicYear($this->currentSchoolId())?->only(['id', 'name']),
            'preview' => null,
        ]);
    }

    public function upload(Request $request, ClassPromotionParser $parser): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ], [], ['file' => 'berkas']);

        $schoolId = $this->currentSchoolId();
        $academicYear = $this->activeAcademicYear($schoolId);

        if (! $academicYear) {
            return back()->withErrors(['file' => 'Belum ada tahun ajaran aktif. Aktifkan dulu tahun ajaran berjalan sebelum menaikkan kelas.']);
        }

        $file = $request->file('file');
        $filename = Str::limit($file->getClientOriginalName(), 120, '');
        $extension = $file->getClientOriginalExtension();

        // Berkas mentah tidak pernah jadi arsip: hanya numpang di disk privat
        // supaya OpenSpout punya path yang bisa dibaca streaming.
        $storedPath = $file->store('class-promotions', 'local');

        try {
            $parsed = $parser->parse(Storage::disk('local')->path($storedPath), $extension, $schoolId, $academicYear->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($storedPath);
        }

        $key = Str::random(32);

        Cache::put(self::STAGING_PREFIX.$key, [
            'school_id' => $schoolId,
            'academic_year_id' => $academicYear->id,
            'filename' => $filename,
            'rows' => $parsed['rows'],
            'missing' => $parsed['missing'],
            'summary' => $parsed['summary'],
        ], now()->addHour());

        return to_route('admin.kelas.kenaikan.review', ['key' => $key]);
    }

    public function review(string $key): Response
    {
        $staging = $this->staging($key);

        return Inertia::render('admin/kelas/kenaikan', [
            'academic_year' => $this->activeAcademicYear($staging['school_id'])?->only(['id', 'name']),
            'preview' => [
                'key' => $key,
                'filename' => $staging['filename'],
                'summary' => $staging['summary'],
                'rows' => $staging['rows'],
                'missing' => $staging['missing'],
            ],
        ]);
    }

    public function apply(string $key): RedirectResponse
    {
        $staging = $this->staging($key);
        $schoolId = $staging['school_id'];
        $academicYear = $this->activeAcademicYear($schoolId);

        // Tahun ajaran aktif bisa berganti di antara pratinjau dan penerapan;
        // menaikkan siswa ke tahun ajaran yang sudah bukan berjalan meninggalkan
        // riwayat `is_current` yang tidak pernah bisa ditutup.
        if (! $academicYear || $academicYear->id !== $staging['academic_year_id']) {
            return back()->with('error', 'Tahun ajaran aktif berubah sejak berkas diunggah. Unggah ulang berkasnya.');
        }

        $promoted = 0;
        $failed = 0;

        foreach ($staging['rows'] as $row) {
            if ($row['action'] !== ClassPromotionParser::ACTION_PROMOTE) {
                continue;
            }

            try {
                $this->promoteStudent($row, $schoolId, $academicYear->id);
                $promoted++;
            } catch (Throwable) {
                $failed++;
            }
        }

        Cache::forget(self::STAGING_PREFIX.$key);

        $message = "{$promoted} siswa dinaikkan ke tahun ajaran {$academicYear->name}.";

        if ($failed > 0) {
            $message .= " {$failed} siswa gagal diproses.";
        }

        if (($staging['summary']['missing'] ?? 0) > 0) {
            $message .= " {$staging['summary']['missing']} siswa tidak ada di berkas dan kelasnya tidak diubah.";
        }

        return to_route('kelas.index')->with('success', $message);
    }

    /**
     * Pindahkan satu siswa: tutup riwayat tahun lama, catat riwayat tahun
     * berjalan, lalu geser penunjuk kelas berjalan di tabel students.
     *
     * Satu transaksi PER SISWA, bukan satu transaksi untuk seluruh berkas:
     * kalau siswa ke-300 gagal, 299 siswa sebelumnya tetap naik dan admin cukup
     * mengunggah ulang berkas yang sama — `updateOrCreate` membuat pengulangan
     * itu tidak menggandakan riwayat.
     *
     * @param  array{student_id: ?string, classroom_id: ?string}  $row
     *
     * @throws RuntimeException Bila siswa atau kelas tujuan sudah tidak valid.
     */
    private function promoteStudent(array $row, string $schoolId, string $academicYearId): void
    {
        DB::transaction(function () use ($row, $schoolId, $academicYearId): void {
            $student = Student::query()
                ->withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->whereKey($row['student_id'])
                ->first();

            if (! $student) {
                throw new RuntimeException('Siswa yang akan dinaikkan sudah tidak ada.');
            }

            // Kelas bisa saja dihapus atau dipindah tahun ajaran setelah
            // pratinjau dibuat; tanpa pemeriksaan ini siswa mendarat di kelas
            // tahun lalu dan riwayatnya ikut salah.
            $classroomExists = Classroom::query()
                ->withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $academicYearId)
                ->whereKey($row['classroom_id'])
                ->exists();

            if (! $classroomExists) {
                throw new RuntimeException('Kelas tujuan sudah tidak ada di tahun ajaran aktif.');
            }

            StudentClassHistory::query()
                ->withoutGlobalScope('school')
                ->where('student_id', $student->id)
                ->where('academic_year_id', '!=', $academicYearId)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            StudentClassHistory::query()
                ->withoutGlobalScope('school')
                ->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'academic_year_id' => $academicYearId,
                    ],
                    [
                        'school_id' => $schoolId,
                        'classroom_id' => $row['classroom_id'],
                        'is_current' => true,
                    ],
                );

            $student->classroom_id = $row['classroom_id'];
            $student->save();
        });
    }

    /**
     * @return array{school_id: string, academic_year_id: string, filename: string, rows: list<array<string, mixed>>, missing: list<array<string, mixed>>, summary: array<string, int>}
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
     * Tidak ada jaminan hanya satu tahun ajaran ber-`is_active` per sekolah,
     * jadi yang termuda yang menang supaya hasilnya tidak bergantung pada
     * urutan baris di tabel.
     */
    private function activeAcademicYear(string $schoolId): ?AcademicYear
    {
        return AcademicYear::forSchool($schoolId)
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();
    }
}
