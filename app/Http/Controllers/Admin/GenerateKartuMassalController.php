<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateStudentCardJob;
use App\Models\CardGenerationBatch;
use App\Models\CardGenerationLog;
use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolCardLayout;
use App\Models\Student;
use App\Services\Student\StudentPhotoIntake;
use App\Support\SchoolTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Membuat kartu satu sekolah — atau satu kelas — sekaligus.
 *
 * Sampai sekarang kartu hanya bisa dibuat satu siswa pada satu waktu dari
 * halaman siswanya. Untuk angkatan baru berisi ratusan anak itu berarti ratusan
 * kali membuka halaman, dan tidak ada yang bisa melihat sudah sampai mana.
 *
 * Dua hal yang membedakannya dari sekadar perulangan:
 *
 *   1. **Foto wajib lengkap.** Kartu tanpa pas foto tetap jadi — dengan kotak
 *      kosong bergaris putus-putus di tempat wajahnya — dan itu baru ketahuan
 *      setelah ratusan berkas terunggah ke Drive. Jadi layar ini menolak jalan
 *      selama masih ada satu siswa yang fotonya belum ada, dan menyebut siapa.
 *   2. **Kemajuannya tersimpan di server.** Satu batch berisi ratusan render
 *      headless Chrome berjalan jauh lebih lama daripada kesabaran siapa pun
 *      menatap layar; operator harus bisa menutup halamannya.
 *
 * Lintas sekolah, jadi seluruh query siswa memakai `acrossSchools()` berikut
 * `where('school_id', ...)` eksplisit. Global scope `school` tidak bisa dipakai
 * di sini: sekolah yang dituju super admin belum tentu sekolah aktif di
 * sesinya, dan mengandalkannya berarti layar ini diam-diam memproses sekolah
 * yang salah.
 */
class GenerateKartuMassalController extends Controller
{
    /** Jenis layout yang ikut dibuat: sisi depan dan sisi belakang kartu OSIS. */
    private const JENIS_LAYOUT = ['osis', 'perpustakaan'];

    public function index(Request $request): Response
    {
        $schoolId = (string) $request->query('school_id', '');
        $classroomId = (string) $request->query('classroom_id', '');

        // Kelas milik sekolah lain diabaikan diam-diam, bukan ditolak: itu
        // keadaan biasa saat operator berpindah sekolah sementara `?kelas=`
        // lama masih menempel di URL.
        if ($schoolId !== '' && $classroomId !== '' && ! $this->classroomBelongsTo($classroomId, $schoolId)) {
            $classroomId = '';
        }

        return Inertia::render('admin/generate-kartu/index', [
            'filters' => ['school_id' => $schoolId, 'classroom_id' => $classroomId],
            'schools' => School::where('is_active', true)->orderBy('name')->get(['id', 'name'])->all(),
            'classrooms' => $schoolId !== '' ? $this->classroomOptions($schoolId) : [],
            'ringkasan' => $schoolId !== '' ? $this->ringkasan($schoolId, $classroomId) : null,
            'batchBerjalan' => $schoolId !== '' ? $this->batchTerakhir($schoolId) : null,
        ]);
    }

    /**
     * Berapa siswa yang siap, dan siapa saja yang belum.
     *
     * @return array{total: int, berfoto: int, tanpa_foto: array<int, array<string, mixed>>}
     */
    private function ringkasan(string $schoolId, string $classroomId): array
    {
        $query = Student::acrossSchools()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->when($classroomId !== '', fn ($q) => $q->where('classroom_id', $classroomId));

        $total = (clone $query)->count();
        $berfoto = (clone $query)->whereNotNull('photo_path')->count();

        $tanpaFoto = (clone $query)
            ->whereNull('photo_path')
            ->with('classroom:id,name')
            ->orderBy('full_name')
            // Dibatasi supaya sekolah yang belum satu pun difoto tidak
            // mengirim dua ribu baris ke peramban; jumlah sebenarnya sudah
            // terbaca dari `total - berfoto`.
            ->limit(200)
            ->get(['id', 'full_name', 'nis', 'classroom_id'])
            ->map(fn (Student $s) => [
                'id' => $s->id,
                'full_name' => $s->full_name,
                'nis' => $s->nis,
                'classroom' => $s->classroom?->name,
            ])
            ->all();

        return ['total' => $total, 'berfoto' => $berfoto, 'tanpa_foto' => $tanpaFoto];
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'classroom_id' => ['nullable', 'string'],
        ]);

        $schoolId = $validated['school_id'];
        $classroomId = (string) ($validated['classroom_id'] ?? '');

        if ($classroomId !== '' && ! $this->classroomBelongsTo($classroomId, $schoolId)) {
            throw ValidationException::withMessages([
                'classroom_id' => 'Kelas ini bukan milik sekolah yang dipilih.',
            ]);
        }

        $students = Student::acrossSchools()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->when($classroomId !== '', fn ($q) => $q->where('classroom_id', $classroomId))
            ->get(['id', 'school_id', 'photo_path']);

        if ($students->isEmpty()) {
            throw ValidationException::withMessages([
                'school_id' => 'Tidak ada siswa aktif pada pilihan ini.',
            ]);
        }

        // Diperiksa ulang di sini meski tombolnya sudah dimatikan di klien.
        // Tombol yang mati bukan penjagaan — ia hanya menjelaskan.
        $tanpaFoto = $students->whereNull('photo_path')->count();

        if ($tanpaFoto > 0) {
            throw ValidationException::withMessages([
                'school_id' => "Masih ada {$tanpaFoto} siswa tanpa pas foto. Lengkapi dulu sebelum generate.",
            ]);
        }

        $layouts = SchoolCardLayout::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->whereIn('type', self::JENIS_LAYOUT)
            ->get();

        if ($layouts->isEmpty()) {
            throw ValidationException::withMessages([
                'school_id' => 'Sekolah ini belum punya layout kartu OSIS yang aktif.',
            ]);
        }

        $batch = DB::transaction(function () use ($schoolId, $classroomId, $students, $layouts) {
            $batch = CardGenerationBatch::create([
                'school_id' => $schoolId,
                'classroom_id' => $classroomId !== '' ? $classroomId : null,
                'created_by' => auth()->id(),
                'total' => $students->count() * ($layouts->count() + 1),
                'status' => 'processing',
            ]);

            foreach ($students as $student) {
                $sheetLog = CardGenerationLog::create([
                    'school_id' => $student->school_id,
                    'student_id' => $student->id,
                    'card_generation_batch_id' => $batch->id,
                    'type' => 'photo_sheet',
                    'status' => 'processing',
                    'generated_by' => 'admin',
                ]);

                GenerateStudentCardJob::dispatch($sheetLog->id)->afterCommit();

                foreach ($layouts as $layout) {
                    $log = CardGenerationLog::create([
                        'school_id' => $student->school_id,
                        'student_id' => $student->id,
                        'school_card_layout_id' => $layout->id,
                        'card_generation_batch_id' => $batch->id,
                        'type' => 'card',
                        'status' => 'processing',
                        'generated_by' => 'admin',
                    ]);

                    GenerateStudentCardJob::dispatch($log->id)->afterCommit();
                }
            }

            return $batch;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$batch->total} berkas pas foto 4R dan kartu OSIS diantrekan untuk {$students->count()} siswa. Halaman ini boleh ditinggal.",
        ]);

        return to_route('admin.generate-kartu', array_filter([
            'school_id' => $schoolId,
            'classroom_id' => $classroomId !== '' ? $classroomId : null,
        ]));
    }

    /**
     * Pasang pas foto seorang siswa tanpa meninggalkan layar ini.
     *
     * Parameternya `string $siswa`, BUKAN `Student $siswa`, dan itu bukan
     * kelalaian. `Student` memakai global scope `school` yang menyaring
     * `school_id = auth()->user()->school_id` — nilai yang untuk super admin
     * diambil `SetCurrentSchool` dari `session('current_school_id')`. Layar ini
     * memilih sekolah lewat query string dan tidak pernah menyentuh sesi, jadi
     * begitu keduanya berbeda — keadaan normal di sini — route model binding
     * tidak menemukan apa pun dan tombolnya 404. Itulah cacat yang diperbaiki
     * endpoint ini; mengetik modelnya akan menghidupkannya kembali.
     *
     * Polanya sama dengan `StudentQuickOpenController`, yang juga menerima id
     * mentah demi alasan yang persis sama. Bedanya di sini konteks sesi TIDAK
     * dipindahkan: operator sedang mengurus satu layar lintas sekolah, dan
     * menggeser sekolah aktifnya di tengah antrean unggah justru mengubah
     * konteks yang tidak ia lihat.
     *
     * Karena scope tenant memang dilepas, penjagaannya ditulis eksplisit:
     * `super-admin` di grup rutenya, dan `school_id` yang wajib cocok dengan
     * siswanya sehingga id nyasar dari sekolah lain ditolak alih-alih
     * diam-diam diproses.
     */
    public function unggahFoto(Request $request, string $siswa, StudentPhotoIntake $intake): RedirectResponse
    {
        $aturan = StudentPhotoIntake::aturanUnggah();

        $validated = $request->validate(
            [...$aturan['rules'], 'school_id' => ['required', 'exists:schools,id']],
            $aturan['messages'],
            $aturan['attributes'],
        );

        $student = Student::acrossSchools()->findOrFail($siswa);

        if ($student->school_id !== $validated['school_id']) {
            throw ValidationException::withMessages([
                'photo' => 'Siswa ini bukan milik sekolah yang sedang dibuka.',
            ]);
        }

        $intake->store($student, $request->file('photo')->getRealPath(), 'admin-massal');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pas foto '.$student->full_name.' tersimpan.',
        ]);

        // `back()`, bukan ke halaman detail siswa: modalnya harus tetap di
        // tempatnya supaya antrean siswa berikutnya tidak terputus.
        return back();
    }

    /**
     * Kemajuan satu batch, untuk polling.
     *
     * Tidak di-scope ke sekolah aktif: super admin membuat batch untuk sekolah
     * mana pun dari daftar, dan rutenya sendiri sudah dikunci `super-admin`.
     */
    public function progres(CardGenerationBatch $batch): JsonResponse
    {
        return response()->json($batch->progres());
    }

    /**
     * Batch terakhir milik sekolah ini, kalau ada.
     *
     * Dikirim apa pun statusnya, bukan hanya yang masih berjalan: operator yang
     * membuka halaman ini lagi keesokan harinya berhak melihat berapa yang
     * gagal semalam.
     *
     * @return array<string, mixed>|null
     */
    private function batchTerakhir(string $schoolId): ?array
    {
        $batch = CardGenerationBatch::where('school_id', $schoolId)
            ->with('classroom:id,name')
            ->latest()
            ->first();

        if (! $batch) {
            return null;
        }

        return [
            'id' => $batch->id,
            'kelas' => $batch->classroom?->name,
            'dibuat' => SchoolTime::display($batch->created_at),
            'progres' => $batch->progres(),
        ];
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function classroomOptions(string $schoolId): array
    {
        return Classroom::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Classroom $c) => ['id' => $c->id, 'name' => $c->name])
            ->all();
    }

    private function classroomBelongsTo(string $classroomId, string $schoolId): bool
    {
        return Classroom::withoutGlobalScope('school')
            ->where('id', $classroomId)
            ->where('school_id', $schoolId)
            ->exists();
    }
}
