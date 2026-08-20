<?php

use App\Http\Controllers\Admin\ClassPromotionController;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;

/**
 * Route kenaikan kelas belum ada di routes/web.php (didaftarkan terpisah).
 * Shim ini memasang URI + middleware yang sama seperti rencana produksi supaya
 * test tetap menguji jalur HTTP sungguhan, dan otomatis mundur begitu route
 * aslinya terdaftar.
 */
function registerClassPromotionRoutes(): void
{
    if (Route::has('admin.kelas.kenaikan')) {
        return;
    }

    Route::middleware(['web', 'auth', 'verified', 'permission:kelas.access', 'feature:master_siswa'])
        ->prefix('admin')
        ->group(function (): void {
            Route::get('kelas/kenaikan', [ClassPromotionController::class, 'index'])->name('admin.kelas.kenaikan');
            Route::post('kelas/kenaikan', [ClassPromotionController::class, 'upload'])->name('admin.kelas.kenaikan.upload');
            Route::get('kelas/kenaikan/{key}', [ClassPromotionController::class, 'review'])->name('admin.kelas.kenaikan.review');
            Route::post('kelas/kenaikan/{key}/apply', [ClassPromotionController::class, 'apply'])->name('admin.kelas.kenaikan.apply');
        });

    // Nama route dipasang setelah route masuk koleksi, jadi peta namanya harus
    // disegarkan manual — di aplikasi sungguhan RouteServiceProvider yang
    // melakukannya seusai memuat routes/web.php.
    Route::getRoutes()->refreshNameLookups();
}

/**
 * @param  list<list<string>>  $rows
 */
function writePromotionCsv(array $rows): string
{
    $dir = base_path('tests/fixtures/imports');

    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $path = $dir.'/kenaikan-kelas.csv';
    $handle = fopen($path, 'w');

    foreach ($rows as $row) {
        fputcsv($handle, $row, ',', '"', '\\');
    }

    fclose($handle);

    return $path;
}

/**
 * Sekolah dengan tahun ajaran lama + aktif, kelas 7A (lama) dan 8A (aktif).
 *
 * @return array{user: User, last_year: AcademicYear, active_year: AcademicYear, old_class: Classroom, new_class: Classroom}
 */
function makePromotionSchool(): array
{
    $user = createAdminUser();

    $lastYear = AcademicYear::factory()->create([
        'school_id' => $user->school_id,
        'name' => '2024/2025',
        'start_date' => '2024-07-15',
        'end_date' => '2025-06-30',
        'is_active' => false,
    ]);

    $activeYear = AcademicYear::factory()->active()->create([
        'school_id' => $user->school_id,
        'name' => '2025/2026',
        'start_date' => '2025-07-15',
        'end_date' => '2026-06-30',
    ]);

    return [
        'user' => $user,
        'last_year' => $lastYear,
        'active_year' => $activeYear,
        'old_class' => Classroom::factory()->create([
            'school_id' => $user->school_id,
            'academic_year_id' => $lastYear->id,
            'name' => '7A',
            'grade_level' => 7,
        ]),
        'new_class' => Classroom::factory()->create([
            'school_id' => $user->school_id,
            'academic_year_id' => $activeYear->id,
            'name' => '8A',
            'grade_level' => 8,
        ]),
    ];
}

/**
 * Riwayat awal seperti yang diisi migrasi backfill.
 */
function makeCurrentHistory(Student $student, Classroom $classroom, AcademicYear $academicYear): StudentClassHistory
{
    return StudentClassHistory::create([
        'school_id' => $student->school_id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'academic_year_id' => $academicYear->id,
        'is_current' => true,
    ]);
}

/**
 * Unggah berkas lalu kembalikan kunci staging dari URL redirect.
 *
 * @param  list<list<string>>  $rows
 */
function uploadPromotionFile(User $user, array $rows): string
{
    $path = writePromotionCsv($rows);

    $response = test()->actingAs($user)->post(route('admin.kelas.kenaikan.upload'), [
        'file' => new UploadedFile($path, 'kenaikan-kelas.csv', 'text/csv', null, true),
    ]);

    $response->assertRedirect();

    return basename((string) parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
}

beforeEach(function () {
    registerClassPromotionRoutes();
});

it('menutup riwayat tahun lama dan membuka riwayat tahun aktif', function () {
    ['user' => $user, 'last_year' => $lastYear, 'active_year' => $activeYear, 'old_class' => $oldClass, 'new_class' => $newClass] = makePromotionSchool();

    $student = Student::factory()->create([
        'school_id' => $user->school_id,
        'classroom_id' => $oldClass->id,
        'nisn' => '1234567890',
    ]);

    $oldHistory = makeCurrentHistory($student, $oldClass, $lastYear);

    $key = uploadPromotionFile($user, [
        ['nisn', 'kelas baru'],
        ['1234567890', '8A'],
    ]);

    $this->actingAs($user)
        ->post(route('admin.kelas.kenaikan.apply', ['key' => $key]))
        ->assertRedirect();

    expect($oldHistory->fresh()->is_current)->toBeFalse();

    $newHistory = StudentClassHistory::withoutGlobalScope('school')
        ->where('student_id', $student->id)
        ->where('academic_year_id', $activeYear->id)
        ->first();

    expect($newHistory)->not->toBeNull()
        ->and($newHistory->is_current)->toBeTrue()
        ->and($newHistory->classroom_id)->toBe($newClass->id)
        ->and($newHistory->school_id)->toBe($user->school_id);
});

it('memindahkan penunjuk kelas berjalan di tabel students', function () {
    ['user' => $user, 'last_year' => $lastYear, 'old_class' => $oldClass, 'new_class' => $newClass] = makePromotionSchool();

    $student = Student::factory()->create([
        'school_id' => $user->school_id,
        'classroom_id' => $oldClass->id,
        'nisn' => '2222222222',
    ]);

    makeCurrentHistory($student, $oldClass, $lastYear);

    $key = uploadPromotionFile($user, [
        ['nisn', 'kelas baru'],
        ['2222222222', '8A'],
    ]);

    $this->actingAs($user)
        ->post(route('admin.kelas.kenaikan.apply', ['key' => $key]))
        ->assertRedirect();

    expect($student->fresh()->classroom_id)->toBe($newClass->id);
});

it('tidak mengubah siswa yang tidak ada di berkas dan menampilkannya terpisah', function () {
    ['user' => $user, 'last_year' => $lastYear, 'old_class' => $oldClass] = makePromotionSchool();

    $promoted = Student::factory()->create([
        'school_id' => $user->school_id,
        'classroom_id' => $oldClass->id,
        'nisn' => '3333333333',
    ]);

    $untouched = Student::factory()->create([
        'school_id' => $user->school_id,
        'classroom_id' => $oldClass->id,
        'nisn' => '4444444444',
        'full_name' => 'Siswa Terlewat',
    ]);

    makeCurrentHistory($promoted, $oldClass, $lastYear);
    $untouchedHistory = makeCurrentHistory($untouched, $oldClass, $lastYear);

    $key = uploadPromotionFile($user, [
        ['nisn', 'kelas baru'],
        ['3333333333', '8A'],
    ]);

    $this->actingAs($user)
        ->get(route('admin.kelas.kenaikan.review', ['key' => $key]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/kelas/kenaikan')
            ->where('preview.summary.promote', 1)
            ->where('preview.summary.missing', 1)
            ->where('preview.missing.0.nisn', '4444444444')
            ->where('preview.missing.0.full_name', 'SISWA TERLEWAT')
        );

    $this->actingAs($user)
        ->post(route('admin.kelas.kenaikan.apply', ['key' => $key]))
        ->assertRedirect();

    expect($untouched->fresh()->classroom_id)->toBe($oldClass->id)
        ->and($untouchedHistory->fresh()->is_current)->toBeTrue()
        ->and(StudentClassHistory::withoutGlobalScope('school')->where('student_id', $untouched->id)->count())->toBe(1);
});

it('menolak kelas tujuan milik tahun ajaran lain', function () {
    ['user' => $user, 'last_year' => $lastYear, 'old_class' => $oldClass] = makePromotionSchool();

    $student = Student::factory()->create([
        'school_id' => $user->school_id,
        'classroom_id' => $oldClass->id,
        'nisn' => '5555555555',
    ]);

    makeCurrentHistory($student, $oldClass, $lastYear);

    // 7A hanya ada di tahun ajaran lama.
    $key = uploadPromotionFile($user, [
        ['nisn', 'kelas baru'],
        ['5555555555', '7A'],
    ]);

    $this->actingAs($user)
        ->get(route('admin.kelas.kenaikan.review', ['key' => $key]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('preview.summary.promote', 0)
            ->where('preview.summary.reject', 1)
            ->where('preview.rows.0.action', 'reject')
            ->where('preview.rows.0.reason', fn (string $reason) => str_contains($reason, 'tahun ajaran aktif'))
        );

    $this->actingAs($user)
        ->post(route('admin.kelas.kenaikan.apply', ['key' => $key]))
        ->assertRedirect();

    expect($student->fresh()->classroom_id)->toBe($oldClass->id)
        ->and(StudentClassHistory::withoutGlobalScope('school')->where('student_id', $student->id)->count())->toBe(1);
});

it('tidak menggandakan riwayat saat diterapkan dua kali', function () {
    ['user' => $user, 'last_year' => $lastYear, 'active_year' => $activeYear, 'old_class' => $oldClass, 'new_class' => $newClass] = makePromotionSchool();

    $student = Student::factory()->create([
        'school_id' => $user->school_id,
        'classroom_id' => $oldClass->id,
        'nisn' => '6666666666',
    ]);

    makeCurrentHistory($student, $oldClass, $lastYear);

    $key = uploadPromotionFile($user, [
        ['nisn', 'kelas baru'],
        ['6666666666', '8A'],
    ]);

    $this->actingAs($user)
        ->post(route('admin.kelas.kenaikan.apply', ['key' => $key]))
        ->assertRedirect();

    // Berkas yang sama diunggah dan diterapkan ulang — persis yang dilakukan
    // admin kalau ragu apakah kenaikan tadi berhasil.
    $repeatKey = uploadPromotionFile($user, [
        ['nisn', 'kelas baru'],
        ['6666666666', '8A'],
    ]);

    $this->actingAs($user)
        ->post(route('admin.kelas.kenaikan.apply', ['key' => $repeatKey]))
        ->assertRedirect();

    $histories = StudentClassHistory::withoutGlobalScope('school')
        ->where('student_id', $student->id)
        ->get();

    expect($histories)->toHaveCount(2)
        ->and($histories->where('academic_year_id', $activeYear->id)->count())->toBe(1)
        ->and($histories->where('is_current', true)->count())->toBe(1)
        ->and($student->fresh()->classroom_id)->toBe($newClass->id);
});

it('tidak pernah menyentuh siswa sekolah lain walau NISN-nya sama', function () {
    ['user' => $user, 'last_year' => $lastYear, 'old_class' => $oldClass, 'new_class' => $newClass] = makePromotionSchool();

    $student = Student::factory()->create([
        'school_id' => $user->school_id,
        'classroom_id' => $oldClass->id,
        'nisn' => '7777777777',
    ]);

    makeCurrentHistory($student, $oldClass, $lastYear);

    $other = makePromotionSchool();

    $otherStudent = Student::factory()->create([
        'school_id' => $other['user']->school_id,
        'classroom_id' => $other['old_class']->id,
        'nisn' => '7777777777',
    ]);

    $key = uploadPromotionFile($user, [
        ['nisn', 'kelas baru'],
        ['7777777777', '8A'],
    ]);

    $this->actingAs($user)
        ->post(route('admin.kelas.kenaikan.apply', ['key' => $key]))
        ->assertRedirect();

    expect($student->fresh()->classroom_id)->toBe($newClass->id)
        ->and($otherStudent->fresh()->classroom_id)->toBe($other['old_class']->id)
        ->and(StudentClassHistory::withoutGlobalScope('school')->where('student_id', $otherStudent->id)->count())->toBe(0);
});

it('memakai kolom kelas baru walau berkas juga memuat kolom kelas asal', function () {
    ['user' => $user, 'last_year' => $lastYear, 'old_class' => $oldClass, 'new_class' => $newClass] = makePromotionSchool();

    $student = Student::factory()->create([
        'school_id' => $user->school_id,
        'classroom_id' => $oldClass->id,
        'nisn' => '9999999999',
    ]);

    makeCurrentHistory($student, $oldClass, $lastYear);

    $key = uploadPromotionFile($user, [
        ['nisn', 'kelas', 'kelas baru'],
        ['9999999999', '7A', '8A'],
    ]);

    $this->actingAs($user)
        ->post(route('admin.kelas.kenaikan.apply', ['key' => $key]))
        ->assertRedirect();

    expect($student->fresh()->classroom_id)->toBe($newClass->id);
});

it('menolak kelas tujuan yang tidak ada dan menyebut namanya', function () {
    ['user' => $user, 'last_year' => $lastYear, 'old_class' => $oldClass] = makePromotionSchool();

    $student = Student::factory()->create([
        'school_id' => $user->school_id,
        'classroom_id' => $oldClass->id,
        'nisn' => '1010101010',
    ]);

    makeCurrentHistory($student, $oldClass, $lastYear);

    $key = uploadPromotionFile($user, [
        ['nisn', 'kelas tujuan'],
        ['1010101010', '9Z'],
    ]);

    $this->actingAs($user)
        ->get(route('admin.kelas.kenaikan.review', ['key' => $key]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('preview.summary.reject', 1)
            ->where('preview.rows.0.reason', fn (string $reason) => str_contains($reason, '9Z'))
        );

    expect($student->fresh()->classroom_id)->toBe($oldClass->id);
});

it('menolak kunci staging milik sekolah lain', function () {
    ['user' => $user, 'last_year' => $lastYear, 'old_class' => $oldClass] = makePromotionSchool();

    $student = Student::factory()->create([
        'school_id' => $user->school_id,
        'classroom_id' => $oldClass->id,
        'nisn' => '8888888888',
    ]);

    makeCurrentHistory($student, $oldClass, $lastYear);

    $key = uploadPromotionFile($user, [
        ['nisn', 'kelas baru'],
        ['8888888888', '8A'],
    ]);

    $intruder = makePromotionSchool()['user'];

    $this->actingAs($intruder)
        ->get(route('admin.kelas.kenaikan.review', ['key' => $key]))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->post(route('admin.kelas.kenaikan.apply', ['key' => $key]))
        ->assertForbidden();

    expect($student->fresh()->classroom_id)->toBe($oldClass->id);
});
