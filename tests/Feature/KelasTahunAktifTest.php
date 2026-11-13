<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Nama kelas ("7A") dibuat ulang tiap tahun ajaran. Daftar pilihan kelas hanya
 * boleh berisi kelas tahun ajaran aktif, kecuali sekolahnya belum punya tahun
 * aktif sama sekali.
 */
beforeEach(fn () => $this->withoutVite());

/**
 * Sekolah dengan kelas "7A" di tahun lama dan tahun aktif.
 *
 * @return array{0: User, 1: Classroom, 2: Classroom}
 */
function sekolahDuaTahun(bool $adaTahunAktif = true): array
{
    $admin = createAdminUser();
    $lama = AcademicYear::factory()->create(['school_id' => $admin->school_id, 'name' => '2025/2026']);
    $baru = AcademicYear::factory()->create(['school_id' => $admin->school_id, 'name' => '2026/2027', 'is_active' => $adaTahunAktif]);

    return [
        $admin,
        Classroom::factory()->create(['school_id' => $admin->school_id, 'academic_year_id' => $lama->id, 'name' => '7A', 'grade_level' => 7]),
        Classroom::factory()->create(['school_id' => $admin->school_id, 'academic_year_id' => $baru->id, 'name' => '7A', 'grade_level' => 7]),
    ];
}

test('scope tahunAktif hanya memuat kelas tahun ajaran aktif', function () {
    [$admin, $kelasLama, $kelasBaru] = sekolahDuaTahun();

    $ids = Classroom::withoutGlobalScope('school')->where('school_id', $admin->school_id)->tahunAktif()->pluck('id')->all();

    expect($ids)->toBe([$kelasBaru->id]);
    expect(Classroom::withoutGlobalScope('school')->where('school_id', $admin->school_id)->tahunAktif($kelasLama->id)->count())->toBe(2);
});

test('sekolah tanpa tahun ajaran aktif tetap melihat semua kelasnya', function () {
    [$admin] = sekolahDuaTahun(adaTahunAktif: false);

    $this->actingAs($admin)->get(route('admin.siswa.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('classrooms', 2));
});

test('tahun aktif dinilai per sekolah, bukan dari sekolah lain', function () {
    [$admin, , $kelasBaru] = sekolahDuaTahun();
    $lain = School::factory()->create(['is_active' => true]);
    $kelasLain = Classroom::factory()->create(['school_id' => $lain->id, 'name' => '8B']);

    $ids = Classroom::withoutGlobalScope('school')->tahunAktif()->whereIn('school_id', [$admin->school_id, $lain->id])->pluck('id')->all();

    // Sekolah lain belum punya tahun aktif → kelasnya tetap ikut.
    expect($ids)->toEqualCanonicalizing([$kelasBaru->id, $kelasLain->id]);
});

test('dropdown kelas admin hanya berisi kelas tahun aktif', function (string $rute, string $prop) {
    [$admin, , $kelasBaru] = sekolahDuaTahun();

    $this->actingAs($admin)->get(route($rute))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has($prop, 1)
            ->where($prop.'.0.id', $kelasBaru->id));
})->with([
    ['admin.siswa.index', 'classrooms'],
    ['admin.siswa.create', 'classrooms'],
    ['admin.absensi', 'classrooms'],
    ['admin.laporan', 'classrooms'],
]);

test('filter kelas tahun lama dari url tetap tampil di dropdown', function () {
    [$admin, $kelasLama] = sekolahDuaTahun();

    $this->actingAs($admin)->get(route('admin.siswa.index', ['classroom_id' => $kelasLama->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('classrooms', 2));
});

test('siswa yang masih di kelas tahun lama tetap bisa diedit dengan kelasnya tampil', function () {
    [$admin, $kelasLama] = sekolahDuaTahun();
    $siswa = Student::factory()->create(['school_id' => $admin->school_id, 'classroom_id' => $kelasLama->id]);

    $this->actingAs($admin)->get(route('admin.siswa.edit', $siswa))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('classrooms', 2)
            ->where('classrooms', fn ($kelas) => collect($kelas)->pluck('id')->contains($kelasLama->id)));
});

test('halaman absen kelas tahun lama tetap terbuka dan kelasnya ada di pilihan', function () {
    [$admin, $kelasLama] = sekolahDuaTahun();

    $this->actingAs($admin)->get(route('admin.absensi.kelas', $kelasLama))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kelas.id', $kelasLama->id)
            ->has('daftarKelas', 2));
});

test('form daftar publik hanya menawarkan kelas tahun aktif', function (string $jalur) {
    [, $kelasLama, $kelasBaru] = sekolahDuaTahun();

    $this->get($jalur)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('classrooms', fn ($kelas) => collect($kelas)->pluck('id')->contains($kelasBaru->id)
                && ! collect($kelas)->pluck('id')->contains($kelasLama->id)));
})->with(['/daftar', '/quick-regis']);

test('data kelas tetap memuat kelas semua tahun untuk dikelompokkan', function () {
    [$admin] = sekolahDuaTahun();

    $this->actingAs($admin)->get(route('kelas.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('classrooms', 2)
            ->where('academic_years.0.is_active', true));
});

test('impor siswa tidak menolak nama kelas yang kembar antar tahun ajaran', function () {
    [$admin, , $kelasBaru] = sekolahDuaTahun();
    $csv = "NISN,NIS,Nama,Kelas,JK,Agama\n3100000091,2026091,Siswa Baru,7A,L,Islam";

    $lokasi = $this->actingAs($admin)
        ->post(route('admin.siswa.import.upload'), ['file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv)])
        ->assertRedirect()
        ->headers->get('Location');

    $this->actingAs($admin)->get($lokasi)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('preview.summary.reject', 0)
            ->where('preview.summary.create', 1));

    expect($kelasBaru->fresh())->not->toBeNull();
});
