<?php

use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Services\Import\StudentImportApplier;

/**
 * Nama siswa disimpan huruf besar.
 *
 * Kapitalisasinya masuk campur dari impor tiap sekolah dan ikut terbawa ke
 * album serta PDF laporan. Diseragamkan lewat mutator di `Student`, bukan di
 * tiap controller: ada delapan jalur tulis nama dan semuanya lewat
 * mass-assignment atau `fill()`, jadi satu mutator menangkap semuanya —
 * termasuk jalur baru yang belum ada.
 */
beforeEach(function () {
    $this->admin = createAdminUser();
    $this->kelas = Classroom::factory()->create(['school_id' => $this->admin->school_id]);
});

test('a name typed in lowercase is stored uppercase', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.siswa.store'), [
            'full_name' => 'afrisya aulia sari',
            'nis' => '20250501',
            'gender' => 'PEREMPUAN',
            'classroom_id' => $this->kelas->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Student::where('nis', '20250501')->first()->full_name)->toBe('AFRISYA AULIA SARI');
});

test('editing a student uppercases the name too', function () {
    $siswa = Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'classroom_id' => $this->kelas->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.siswa.update', $siswa), [
            'full_name' => 'budi santoso',
            'gender' => 'LAKI_LAKI',
            'classroom_id' => $this->kelas->id,
        ])
        ->assertSessionHasNoErrors();

    expect($siswa->fresh()->full_name)->toBe('BUDI SANTOSO');
});

test('public registration uppercases both the student and the parent name', function () {
    $sekolah = School::factory()->create(['is_active' => true]);
    $kelas = Classroom::factory()->create(['school_id' => $sekolah->id]);

    $this->post('/daftar', [
        'school_id' => $sekolah->id,
        'full_name' => 'ahmad fauzi',
        'gender' => 'LAKI_LAKI',
        'classroom_id' => $kelas->id,
        'nisn' => '0011223399',
        'no_absen' => '7',
        'religion' => 'ISLAM',
        'birth_place' => 'Jakarta',
        'birth_date' => '2013-04-01',
        'address' => 'Jl. Melati No. 1',
        'parent_name' => 'siti rahmawati',
        'parent_phone' => '081234567890',
        'parent_relation' => 'WALI',
    ])->assertOk();

    $siswa = Student::withoutGlobalScope('school')->where('nisn', '0011223399')->first();

    expect($siswa->full_name)->toBe('AHMAD FAUZI')
        // Tempat lahir dan alamat sengaja dibiarkan: alamat huruf besar semua
        // justru lebih sulit dibaca.
        ->and($siswa->parent_name)->toBe('SITI RAHMAWATI')
        ->and($siswa->address)->toBe('Jl. Melati No. 1');
});

test('imported names are uppercased on the way in', function () {
    $sekolah = School::factory()->create();
    $kelas = Classroom::factory()->create(['school_id' => $sekolah->id, 'name' => '7A']);

    app(StudentImportApplier::class)->apply([
        [
            'row_number' => 2,
            'action' => 'create',
            'reason' => null,
            'student_id' => null,
            'data' => [
                'nisn' => '3000009001',
                'nis' => '2025901',
                'full_name' => 'hana pratiwi',
                'parent_name' => 'joko susilo',
                'gender' => 'PEREMPUAN',
                'classroom_id' => $kelas->id,
            ],
        ],
    ], $sekolah->id);

    $siswa = Student::withoutGlobalScope('school')->where('nisn', '3000009001')->first();

    expect($siswa->full_name)->toBe('HANA PRATIWI')
        ->and($siswa->parent_name)->toBe('JOKO SUSILO');
});

test('an empty parent name stays null instead of becoming an empty string', function () {
    $siswa = Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'parent_name' => null,
    ]);

    expect($siswa->fresh()->parent_name)->toBeNull();
});

test('surrounding whitespace is trimmed along the way', function () {
    $siswa = Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'full_name' => '  imam sutrisno  ',
    ]);

    expect($siswa->fresh()->full_name)->toBe('IMAM SUTRISNO');
});

test('searching in lowercase still finds a student stored uppercase', function () {
    Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'full_name' => 'siti rahmawati',
    ]);

    // Yang dijaga di sini: kotak cari tidak boleh diam-diam rusak gara-gara
    // nilai tersimpannya berubah bentuk.
    $this->actingAs($this->admin)
        ->get(route('admin.siswa.index', ['search' => 'siti']))
        ->assertInertia(fn ($page) => $page
            ->has('students.data', 1)
            ->where('students.data.0.full_name', 'SITI RAHMAWATI')
        );
});

// ------------------------------------------------------- perintah backfill

test('the backfill command changes nothing on a dry run', function () {
    $siswa = Student::factory()->create([
        'school_id' => $this->admin->school_id,
    ]);
    $siswa->newQuery()->withoutGlobalScope('school')
        ->where('id', $siswa->id)->update(['full_name' => 'nama lama']);

    $this->artisan('students:uppercase-names', ['--dry-run' => true])->assertSuccessful();

    expect($siswa->fresh()->full_name)->toBe('nama lama');
});

test('the backfill command uppercases names already in the database', function () {
    $siswa = Student::factory()->create(['school_id' => $this->admin->school_id]);
    $siswa->newQuery()->withoutGlobalScope('school')
        ->where('id', $siswa->id)->update(['full_name' => 'nama lama', 'parent_name' => 'ortu lama']);

    $this->artisan('students:uppercase-names')->assertSuccessful();

    expect($siswa->fresh()->full_name)->toBe('NAMA LAMA')
        ->and($siswa->fresh()->parent_name)->toBe('ORTU LAMA');
});

test('running the backfill twice is safe', function () {
    $siswa = Student::factory()->create(['school_id' => $this->admin->school_id]);
    $siswa->newQuery()->withoutGlobalScope('school')
        ->where('id', $siswa->id)->update(['full_name' => 'nama lama']);

    $this->artisan('students:uppercase-names')->assertSuccessful();
    $sesudahSekali = $siswa->fresh()->updated_at;

    $this->artisan('students:uppercase-names')->assertSuccessful();

    expect($siswa->fresh()->full_name)->toBe('NAMA LAMA')
        // Baris yang sudah rapi tidak ditulis ulang.
        ->and($siswa->fresh()->updated_at->eq($sesudahSekali))->toBeTrue();
});

test('the backfill reaches every school, not just the one the runner belongs to', function () {
    $lain = School::factory()->create();
    $siswaLain = Student::factory()->create(['school_id' => $lain->id]);
    $siswaLain->newQuery()->withoutGlobalScope('school')
        ->where('id', $siswaLain->id)->update(['full_name' => 'siswa sekolah lain']);

    // actingAs() memasang global scope sekolah; command harus melucutinya,
    // kalau tidak hanya satu sekolah yang tersentuh.
    $this->actingAs($this->admin)->artisan('students:uppercase-names')->assertSuccessful();

    expect($siswaLain->fresh()->full_name)->toBe('SISWA SEKOLAH LAIN');
});

test('a soft-deleted student is not left behind', function () {
    $siswa = Student::factory()->create(['school_id' => $this->admin->school_id]);
    $siswa->newQuery()->withoutGlobalScope('school')
        ->where('id', $siswa->id)->update(['full_name' => 'siswa terhapus']);
    $siswa->delete();

    $this->artisan('students:uppercase-names')->assertSuccessful();

    $dipulihkan = Student::withoutGlobalScope('school')->withTrashed()->find($siswa->id);

    expect($dipulihkan->full_name)->toBe('SISWA TERHAPUS');
});
