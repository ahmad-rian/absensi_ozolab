<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\Attendance\ScheduleProvisioner;
use App\Services\Import\ClassPromotionParser;
use App\Services\Student\RiwayatKelas;
use App\Support\XlsxDownload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
 *
 * Kolom Kelas Baru boleh berisi `LULUS`: siswanya dinonaktifkan, kelas
 * terakhirnya tetap tercatat di riwayat.
 */
class ClassPromotionController extends Controller
{
    private const STAGING_PREFIX = 'class-promotion-staging:';

    /** Hasil penerapan, disimpan supaya baris gagal masih bisa diunduh. */
    private const RESULT_PREFIX = 'class-promotion-result:';

    private const TEMPLATE_HEADER = ['NISN', 'NIS', 'Nama', 'Kelas Sekarang', 'Kelas Baru'];

    public function index(): Response
    {
        return Inertia::render('admin/kelas/kenaikan', [
            'academic_year' => $this->activeAcademicYear($this->currentSchoolId())?->only(['id', 'name']),
            'preview' => null,
            'hasil' => null,
        ]);
    }

    /**
     * Template terisi: seluruh siswa aktif berikut kelasnya sekarang, tinggal
     * mengisi kolom Kelas Baru. Sheet kedua memuat nama kelas tujuan yang sah.
     */
    public function template(): BinaryFileResponse
    {
        $schoolId = $this->currentSchoolId();
        $academicYear = $this->activeAcademicYear($schoolId);

        $siswa = Student::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->with(['classroom' => fn ($q) => $q->withoutGlobalScope('school')->select(['id', 'name'])])
            ->get(['id', 'nisn', 'nis', 'full_name', 'classroom_id'])
            ->sort(fn (Student $a, Student $b): int => strnatcasecmp((string) $a->classroom?->name, (string) $b->classroom?->name)
                ?: strcasecmp((string) $a->full_name, (string) $b->full_name))
            ->map(fn (Student $s): array => [(string) $s->nisn, (string) $s->nis, (string) $s->full_name, (string) $s->classroom?->name, ''])
            ->values()
            ->all();

        $kelas = $academicYear
            ? Classroom::query()
                ->withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $academicYear->id)
                ->pluck('name')
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->map(fn (string $nama): array => [$nama, 'Kelas tahun ajaran '.$academicYear->name])
                ->values()
                ->all()
            : [];

        $kelas[] = ['LULUS', 'Untuk siswa kelas akhir. Siswanya dinonaktifkan, riwayat absensinya tetap ada.'];

        return XlsxDownload::sheets('template-kenaikan-kelas.xlsx', [
            'Kenaikan Kelas' => ['header' => self::TEMPLATE_HEADER, 'rows' => $siswa],
            'Kelas Tujuan' => ['header' => ['Isi kolom Kelas Baru dengan', 'Keterangan'], 'rows' => $kelas],
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
            return back()->withErrors(['file' => 'Belum ada tahun ajaran aktif. Aktifkan dulu di Pengaturan → tab Umum, lalu unggah lagi.']);
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
            'hasil' => null,
        ]);
    }

    /**
     * Ringkasan setelah penerapan, berikut siswa yang gagal dan alasannya.
     */
    public function hasil(string $key): Response
    {
        $hasil = $this->cached(self::RESULT_PREFIX, $key);

        return Inertia::render('admin/kelas/kenaikan', [
            'academic_year' => $this->activeAcademicYear($hasil['school_id'])?->only(['id', 'name']),
            'preview' => null,
            'hasil' => [
                'key' => $key,
                'filename' => $hasil['filename'],
                'summary' => $hasil['summary'],
                'failures' => $hasil['failures'],
            ],
        ]);
    }

    /**
     * Unduh baris bermasalah supaya bisa diperbaiki lalu diunggah lagi -
     * judul kolomnya sama dengan template.
     */
    public function unduh(string $key, string $jenis): BinaryFileResponse
    {
        abort_unless(in_array($jenis, ['ditolak', 'tidak-ada'], true), 404);

        $data = Cache::has(self::STAGING_PREFIX.$key)
            ? $this->cached(self::STAGING_PREFIX, $key)
            : $this->cached(self::RESULT_PREFIX, $key);

        if ($jenis === 'tidak-ada') {
            return XlsxDownload::make(
                'kenaikan-kelas-tidak-ada-di-berkas.xlsx',
                self::TEMPLATE_HEADER,
                array_map(fn (array $m): array => [(string) $m['nisn'], (string) ($m['nis'] ?? ''), (string) $m['full_name'], (string) ($m['classroom'] ?? ''), ''], $data['missing']),
            );
        }

        $ditolak = [
            ...array_values(array_filter($data['rows'] ?? [], fn (array $row): bool => $row['action'] === ClassPromotionParser::ACTION_REJECT)),
            ...($data['failures'] ?? []),
        ];

        return XlsxDownload::make(
            'kenaikan-kelas-ditolak.xlsx',
            [...self::TEMPLATE_HEADER, 'Alasan', 'Baris di berkas asal'],
            array_map(fn (array $row): array => [
                (string) $row['nisn'], (string) ($row['nis'] ?? ''), (string) ($row['student_name'] ?? ''),
                (string) ($row['from_classroom'] ?? ''), (string) $row['to_classroom'], (string) $row['reason'], (int) $row['row_number'],
            ], $ditolak),
        );
    }

    public function apply(string $key, RiwayatKelas $riwayat, ScheduleProvisioner $jadwal): RedirectResponse
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
        $graduated = 0;
        $failures = [];
        $kelasTujuan = [];

        foreach ($staging['rows'] as $row) {
            if (! in_array($row['action'], [ClassPromotionParser::ACTION_PROMOTE, ClassPromotionParser::ACTION_GRADUATE], true)) {
                continue;
            }

            try {
                if ($row['action'] === ClassPromotionParser::ACTION_GRADUATE) {
                    $this->graduateStudent($row, $schoolId, $riwayat);
                    $graduated++;
                } else {
                    $this->promoteStudent($row, $schoolId, $academicYear->id, $riwayat);
                    $kelasTujuan[$row['classroom_id']] = true;
                    $promoted++;
                }
            } catch (Throwable $e) {
                // Alasan yang kita tulis sendiri aman ditampilkan; galat lain
                // dicatat ke log dan diganti kalimat yang bisa ditindaklanjuti.
                if (! $e instanceof RuntimeException) {
                    report($e);
                }

                $failures[] = [
                    ...$row,
                    'action' => ClassPromotionParser::ACTION_REJECT,
                    'reason' => $e instanceof RuntimeException
                        ? $e->getMessage()
                        : 'Gagal diproses karena gangguan sistem. Unduh baris ini lalu unggah ulang.',
                ];
            }
        }

        $jadwalDisalin = $this->salinJadwal(array_keys($kelasTujuan), $schoolId, $jadwal);

        $summary = [
            'promoted' => $promoted,
            'graduated' => $graduated,
            'failed' => count($failures),
            'rejected' => (int) ($staging['summary'][ClassPromotionParser::ACTION_REJECT] ?? 0),
            'missing' => (int) ($staging['summary']['missing'] ?? 0),
            'schedules_copied' => $jadwalDisalin,
        ];

        activity()
            ->performedOn($academicYear)
            ->causedBy(auth()->user())
            ->withProperties(['berkas' => $staging['filename'], ...$summary])
            ->log('kenaikan kelas');

        Cache::forget(self::STAGING_PREFIX.$key);
        Cache::put(self::RESULT_PREFIX.$key, [
            'school_id' => $schoolId,
            'filename' => $staging['filename'],
            'summary' => $summary,
            'rows' => array_values(array_filter($staging['rows'], fn (array $row): bool => $row['action'] === ClassPromotionParser::ACTION_REJECT)),
            'failures' => $failures,
            'missing' => $staging['missing'],
        ], now()->addHours(6));

        $message = "{$promoted} siswa dinaikkan ke tahun ajaran {$academicYear->name}.";

        if ($graduated > 0) {
            $message .= " {$graduated} siswa diluluskan.";
        }

        if ($failures !== []) {
            $message .= ' '.count($failures).' siswa gagal diproses.';
        }

        if ($summary['missing'] > 0) {
            $message .= " {$summary['missing']} siswa tidak ada di berkas dan kelasnya tidak diubah.";
        }

        return to_route('admin.kelas.kenaikan.hasil', ['key' => $key])->with('success', $message);
    }

    /**
     * Pindahkan satu siswa: catat riwayat (menutup tahun lama, membuka tahun
     * berjalan), lalu geser penunjuk kelas berjalan di tabel students.
     *
     * Satu transaksi PER SISWA, bukan satu transaksi untuk seluruh berkas:
     * kalau siswa ke-300 gagal, 299 siswa sebelumnya tetap naik dan admin cukup
     * mengunggah ulang berkas yang sama - riwayatnya idempoten, jadi pengulangan
     * itu tidak menggandakan apa pun.
     *
     * @param  array{student_id: ?string, classroom_id: ?string}  $row
     *
     * @throws RuntimeException Bila siswa atau kelas tujuan sudah tidak valid.
     */
    private function promoteStudent(array $row, string $schoolId, string $academicYearId, RiwayatKelas $riwayat): void
    {
        DB::transaction(function () use ($row, $schoolId, $academicYearId, $riwayat): void {
            $student = $this->student($row, $schoolId);

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

            $riwayat->catat($student, $row['classroom_id'], $student->classroom_id);

            $student->classroom_id = $row['classroom_id'];
            $student->save();
        });
    }

    /**
     * Siswa kelas akhir: dinonaktifkan, kelas terakhirnya tetap di riwayat.
     * Kelasnya tidak diubah supaya rekap kelas terakhir tetap utuh, dan admin
     * bisa mengaktifkannya lagi dari menu Siswa kalau ternyata salah.
     *
     * @param  array{student_id: ?string}  $row
     *
     * @throws RuntimeException Bila siswanya sudah tidak ada.
     */
    private function graduateStudent(array $row, string $schoolId, RiwayatKelas $riwayat): void
    {
        DB::transaction(function () use ($row, $schoolId, $riwayat): void {
            $student = $this->student($row, $schoolId);

            $riwayat->tutup($student);

            $student->is_active = false;
            $student->save();
        });
    }

    /**
     * @param  array{student_id: ?string}  $row
     *
     * @throws RuntimeException
     */
    private function student(array $row, string $schoolId): Student
    {
        $student = Student::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->whereKey($row['student_id'])
            ->first();

        if (! $student) {
            throw new RuntimeException('Siswa ini sudah tidak ada di data sekolah.');
        }

        return $student;
    }

    /**
     * Kelas tujuan yang belum punya jadwal sendiri mewarisi jadwal khusus dari
     * kelas bernama sama di tahun sebelumnya. Gagal menyalin tidak membatalkan
     * kenaikan kelas - siswanya tetap terlayani jadwal umum.
     *
     * @param  list<string>  $classroomIds
     */
    private function salinJadwal(array $classroomIds, string $schoolId, ScheduleProvisioner $jadwal): int
    {
        $disalin = 0;

        $kelas = Classroom::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->whereKey($classroomIds)
            ->get();

        foreach ($kelas as $tujuan) {
            try {
                $disalin += $jadwal->salinDariKelas($tujuan);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $disalin;
    }

    /**
     * @return array{school_id: string, academic_year_id: string, filename: string, rows: list<array<string, mixed>>, missing: list<array<string, mixed>>, summary: array<string, int>}
     */
    private function staging(string $key): array
    {
        return $this->cached(self::STAGING_PREFIX, $key);
    }

    /**
     * @return array<string, mixed>
     */
    private function cached(string $prefix, string $key): array
    {
        // Kunci masuk mentah dari URL dan dipakai sebagai cache key; batasi
        // bentuknya supaya tidak bisa menyentuh entri cache lain.
        abort_unless((bool) preg_match('/^[A-Za-z0-9]{32}$/', $key), 404);

        $data = Cache::get($prefix.$key);

        abort_if(! is_array($data), 404, 'Data kenaikan kelas ini sudah kedaluwarsa. Unggah ulang berkasnya.');

        // Tidak lewat model, jadi global scope sekolah tidak menolongnya:
        // pemilik kunci diperiksa manual.
        abort_unless($data['school_id'] === $this->currentSchoolId(), 403);

        return $data;
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
