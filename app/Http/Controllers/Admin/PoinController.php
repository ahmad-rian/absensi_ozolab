<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\PoinSiswa;
use App\Models\School;
use App\Models\Student;
use App\Services\Poin\RekapPoin;
use App\Support\PoinSettings;
use App\Support\SchoolTime;
use App\Support\StudentPhotoStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Poin pelanggaran siswa. Melihat cukup `poin.access` (Guru), mencatat,
 * menghapus, dan mengatur butuh `poin-kelola.access` (Admin / guru BK) — dijaga
 * di rute. Binding `{poin}` tersaring scope sekolah, jadi milik sekolah lain 404.
 */
class PoinController extends Controller
{
    public function index(Request $request, RekapPoin $rekap): Response
    {
        $school = $this->sekolah();
        $setup = PoinSettings::for($school);
        $level = in_array($request->level, [...PoinSettings::LEVEL, 'aman'], true) ? $request->level : '';

        $siswa = $rekap->perSiswa($setup, $request->kelas, $request->q, $level)
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Student $s) => [
                'id' => $s->id,
                'nama' => $s->full_name,
                'nis' => $s->nis,
                'kelas' => $s->classroom?->name,
                'foto' => StudentPhotoStorage::displayUrl($s->photo_path),
                'total' => (int) $s->poin_total,
                'terlambat' => (int) $s->poin_terlambat,
                'alpa' => (int) $s->poin_alpa,
                'manual' => (int) $s->poin_manual,
                'level' => $setup->levelUntuk((int) $s->poin_total),
            ]);

        return Inertia::render('admin/poin/index', [
            'siswa' => $siswa,
            'ringkasan' => $rekap->ringkasanLevel($setup),
            'setup' => $setup->toArray(),
            'classrooms' => Classroom::query()->orderBy('name')->get(['id', 'name']),
            'filters' => ['kelas' => $request->kelas ?? '', 'q' => $request->q ?? '', 'level' => $level],
            'bolehKelola' => $this->bolehKelola($request),
        ]);
    }

    public function riwayat(Request $request): Response
    {
        $sumber = in_array($request->sumber, [PoinSiswa::TERLAMBAT, PoinSiswa::ALPA, PoinSiswa::MANUAL], true) ? $request->sumber : '';
        $dari = $this->tanggalAtauNull($request->dari);
        $sampai = $this->tanggalAtauNull($request->sampai);

        $poin = PoinSiswa::query()
            ->with(['student:id,full_name,nis,classroom_id', 'student.classroom:id,name', 'pencatat:id,name'])
            ->when($sumber, fn ($q) => $q->where('sumber', $sumber))
            ->when($dari, fn ($q) => $q->where('tanggal', '>=', $dari))
            ->when($sampai, fn ($q) => $q->where('tanggal', '<=', $sampai))
            ->when($request->kelas, fn ($q, $kelas) => $q->whereHas('student', fn ($s) => $s->where('classroom_id', $kelas)))
            ->when($request->q, fn ($q, $cari) => $q->whereHas('student', fn ($s) => $s->where('full_name', 'like', '%'.addcslashes($cari, '%_\\').'%')))
            ->orderByDesc('tanggal')
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (PoinSiswa $p) => $this->baris($p));

        return Inertia::render('admin/poin/riwayat', [
            'poin' => $poin,
            'classrooms' => Classroom::query()->orderBy('name')->get(['id', 'name']),
            'filters' => ['sumber' => $sumber, 'kelas' => $request->kelas ?? '', 'q' => $request->q ?? '', 'dari' => $dari ?? '', 'sampai' => $sampai ?? ''],
            'bolehKelola' => $this->bolehKelola($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Exists lewat model ber-scope: siswa sekolah lain tidak ditemukan.
            'student_id' => ['required', 'string', function (string $attr, mixed $value, \Closure $fail) {
                if (! Student::query()->whereKey($value)->exists()) {
                    $fail('Siswa tidak ditemukan di sekolah ini.');
                }
            }],
            'kategori' => ['required', 'string', 'max:100'],
            'poin' => ['required', 'integer', 'min:1', 'max:1000'],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.SchoolTime::todayString()],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'tanggal.before_or_equal' => 'Tanggal pelanggaran tidak boleh di masa depan.',
            'alasan.required' => 'Tuliskan alasan / kronologi pelanggarannya.',
        ]);

        PoinSiswa::create([
            ...$data,
            'sumber' => PoinSiswa::MANUAL,
            'dicatat_oleh' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pelanggaran dicatat: +{$data['poin']} poin."]);

        return back();
    }

    /**
     * Pilihan siswa untuk dialog "Catat Pelanggaran" (maks 10, sekolah sendiri).
     */
    public function cariSiswa(Request $request): JsonResponse
    {
        $cari = trim((string) $request->query('q', ''));

        if (mb_strlen($cari) < 2) {
            return response()->json([]);
        }

        $siswa = Student::query()
            ->with('classroom:id,name')
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->where('full_name', 'like', '%'.addcslashes($cari, '%_\\').'%')
                ->orWhere('nis', $cari))
            ->orderBy('full_name')
            ->limit(10)
            ->get(['id', 'full_name', 'nis', 'classroom_id'])
            ->map(fn (Student $s) => ['id' => $s->id, 'nama' => $s->full_name, 'nis' => $s->nis, 'kelas' => $s->classroom?->name]);

        return response()->json($siswa);
    }

    public function destroy(PoinSiswa $poin): RedirectResponse
    {
        // Poin otomatis mengikuti absensi; hapusnya lewat koreksi absensi.
        abort_unless($poin->isManual(), 422, 'Poin otomatis hilang sendiri bila status absensinya dikoreksi.');

        $poin->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Catatan poin dihapus.']);

        return back();
    }

    public function pengaturan(): Response
    {
        return Inertia::render('admin/poin/pengaturan', [
            'setup' => PoinSettings::for($this->sekolah())->toArray(),
        ]);
    }

    public function simpanPengaturan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'terlambat' => ['required', 'integer', 'min:0', 'max:1000'],
            'alpa' => ['required', 'integer', 'min:0', 'max:1000'],
            'kategori' => ['present', 'array', 'max:50'],
            'kategori.*.nama' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'kategori.*.poin' => ['required', 'integer', 'min:1', 'max:1000'],
            'level.kuning' => ['required', 'integer', 'min:1', 'max:10000'],
            'level.orange' => ['required', 'integer', 'gt:level.kuning', 'max:10000'],
            'level.merah' => ['required', 'integer', 'gt:level.orange', 'max:10000'],
            'semester_mulai' => ['required', 'date_format:Y-m-d'],
        ], [
            'kategori.*.nama.distinct' => 'Nama kategori tidak boleh kembar.',
            'level.orange.gt' => 'Ambang orange harus lebih besar dari kuning.',
            'level.merah.gt' => 'Ambang merah harus lebih besar dari orange.',
        ]);

        $school = $this->sekolah();

        $school->settings = [
            ...($school->settings ?? []),
            'poin_terlambat' => (int) $data['terlambat'],
            'poin_alpa' => (int) $data['alpa'],
            'poin_kategori' => array_map(fn (array $k) => ['nama' => trim($k['nama']), 'poin' => (int) $k['poin']], $data['kategori']),
            'poin_level' => array_map('intval', $data['level']),
            'poin_semester_mulai' => $data['semester_mulai'],
        ];
        $school->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengaturan poin disimpan. Berlaku untuk catatan berikutnya.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function baris(PoinSiswa $p): array
    {
        return [
            'id' => $p->id,
            'tanggal' => $p->tanggal,
            'sumber' => $p->sumber,
            'label' => $p->label(),
            'poin' => $p->poin,
            'alasan' => $p->alasan,
            'pencatat' => $p->pencatat?->name,
            'siswa' => $p->relationLoaded('student') ? [
                'id' => $p->student?->id,
                'nama' => $p->student?->full_name,
                'nis' => $p->student?->nis,
                'kelas' => $p->student?->classroom?->name,
            ] : null,
        ];
    }

    private function bolehKelola(Request $request): bool
    {
        $user = $request->user();

        return $user->isSuperAdmin() || $user->can('poin-kelola.access');
    }

    private function sekolah(): School
    {
        abort_unless(app()->bound('currentSchool'), 404, 'Pilih sekolah lebih dulu.');

        return app('currentSchool');
    }

    private function tanggalAtauNull(mixed $nilai): ?string
    {
        return is_string($nilai) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) ? $nilai : null;
    }
}
