<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\Student\StudentPhotoIntake;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar siswa aktif yang belum punya pas foto, untuk sekolah yang sedang dibuka.
 *
 * Dulu daftar ini hanya ada di layar Generate Kartu massal, yang khusus super
 * admin dan kini tersembunyi selama saklar CARDS_GENERATE_MASSAL mati. Staf
 * sekolah akhirnya memeriksa siswa satu per satu. Halaman ini memisahkan
 * "siapa yang belum difoto" dari urusan generate.
 *
 * "Belum ada foto" berarti `photo_path` DAN `photo_drive_file_id` kosong:
 * siswa yang fotonya sudah tersimpan di Drive dihitung punya foto, sama
 * seperti `AuditStudentDriveFilesCommand::jenisWajibSiswa()`.
 */
class SiswaTanpaFotoController extends Controller
{
    private const PER_HALAMAN = 25;

    public function index(Request $request): Response
    {
        $kelas = $this->kelasValid((string) $request->query('kelas', ''));
        $cari = trim((string) $request->query('q', ''));

        $siswa = $this->tanpaFoto($this->siswaAktif($kelas))
            ->when($cari !== '', fn (Builder $query) => $query->where(
                fn (Builder $q) => $q->where('full_name', 'like', "%{$cari}%")
                    ->orWhere('nis', 'like', "%{$cari}%")
                    ->orWhere('nisn', 'like', "%{$cari}%"),
            ))
            ->with('classroom:id,name')
            ->orderBy(Classroom::select('name')->whereColumn('classrooms.id', 'students.classroom_id'))
            ->orderBy('no_absen')
            ->orderBy('full_name')
            ->paginate(self::PER_HALAMAN, ['id', 'full_name', 'nis', 'no_absen', 'classroom_id'])
            ->withQueryString()
            ->through(fn (Student $s) => [
                'id' => $s->id,
                'full_name' => $s->full_name,
                'nis' => $s->nis,
                'no_absen' => $s->no_absen,
                'classroom' => $s->classroom?->name,
            ]);

        return Inertia::render('admin/siswa-tanpa-foto/index', [
            'siswa' => $siswa,
            'ringkasan' => $this->ringkasan($kelas),
            'classrooms' => Classroom::query()->orderBy('name')->get(['id', 'name']),
            'filters' => ['kelas' => $kelas, 'q' => $cari],
            'schoolId' => auth()->user()->school_id,
        ]);
    }

    /**
     * Pasang pas foto langsung dari daftar.
     *
     * Logikanya milik `StudentPhotoIntake`, sama dengan tombol Ganti Foto di
     * detail siswa; bedanya hanya tujuan kembali. `back()` menjaga modal antrean
     * tetap terbuka untuk siswa berikutnya. Binding `{siswa}` tersaring global
     * scope sekolah, jadi siswa sekolah lain berhenti di 404.
     */
    public function foto(Request $request, Student $siswa, StudentPhotoIntake $intake): RedirectResponse
    {
        $aturan = StudentPhotoIntake::aturanUnggah();
        $request->validate($aturan['rules'], $aturan['messages'], $aturan['attributes']);

        $intake->store($siswa, $request->file('photo')->getRealPath());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pas foto '.$siswa->full_name.' tersimpan.']);

        return back();
    }

    /**
     * @return array{total: int, berfoto: int, tanpa_foto: int, persen_berfoto: float}
     */
    private function ringkasan(string $kelas): array
    {
        $total = $this->siswaAktif($kelas)->count();
        $tanpaFoto = $this->tanpaFoto($this->siswaAktif($kelas))->count();
        $berfoto = $total - $tanpaFoto;

        return [
            'total' => $total,
            'berfoto' => $berfoto,
            'tanpa_foto' => $tanpaFoto,
            'persen_berfoto' => $total > 0 ? round($berfoto / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Siswa aktif sekolah yang sedang dibuka; global scope `school` yang
     * membatasi sekolahnya.
     *
     * @return Builder<Student>
     */
    private function siswaAktif(string $kelas): Builder
    {
        return Student::query()
            ->where('is_active', true)
            ->when($kelas !== '', fn (Builder $query) => $query->where('classroom_id', $kelas));
    }

    /**
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    private function tanpaFoto(Builder $query): Builder
    {
        return $query->whereNull('photo_path')->whereNull('photo_drive_file_id');
    }

    /**
     * Kelas dari sekolah lain diabaikan, bukan ditolak: itu terjadi biasa
     * saat operator berpindah sekolah sementara `?kelas=` lama masih menempel.
     */
    private function kelasValid(string $kelas): string
    {
        return $kelas !== '' && Classroom::query()->whereKey($kelas)->exists() ? $kelas : '';
    }
}
