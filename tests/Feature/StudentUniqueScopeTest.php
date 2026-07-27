<?php

use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\QueryException;

test('two schools may use the same nis and nisn', function () {
    $satu = School::factory()->create();
    $dua = School::factory()->create();

    $siswaSatu = Student::factory()->create([
        'school_id' => $satu->id,
        'classroom_id' => Classroom::factory()->create(['school_id' => $satu->id])->id,
        'nis' => '20250001',
        'nisn' => '0012345678',
    ]);

    $siswaDua = Student::factory()->create([
        'school_id' => $dua->id,
        'classroom_id' => Classroom::factory()->create(['school_id' => $dua->id])->id,
        'nis' => '20250001',
        'nisn' => '0012345678',
    ]);

    expect($siswaSatu->nis)->toBe($siswaDua->nis)
        ->and($siswaSatu->school_id)->not->toBe($siswaDua->school_id);
});

test('a soft deleted students nis can be reused in the same school', function () {
    $school = School::factory()->create();
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);

    $lama = Student::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => $classroom->id,
        'nis' => '20250009',
        'nisn' => '0099887766',
    ]);

    $lama->delete();

    $baru = Student::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => $classroom->id,
        'nis' => '20250009',
        'nisn' => '0099887766',
    ]);

    expect($baru->exists)->toBeTrue()
        ->and($lama->fresh()->trashed())->toBeTrue();
});

test('nis is nullable so publicly registered students without one can be stored', function () {
    $school = School::factory()->create();
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);

    $tanpaNis = Student::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => $classroom->id,
        'nis' => null,
        'nisn' => null,
    ]);

    expect($tanpaNis->refresh()->nis)->toBeNull();
});

test('classroom names are unique per academic year within a school', function () {
    $school = School::factory()->create();
    $kelas = Classroom::factory()->create(['school_id' => $school->id, 'name' => '7A']);

    expect(fn () => Classroom::factory()->create([
        'school_id' => $school->id,
        'academic_year_id' => $kelas->academic_year_id,
        'name' => '7A',
    ]))->toThrow(QueryException::class);
});

test('the same classroom name may exist in another school', function () {
    $satu = School::factory()->create();
    $dua = School::factory()->create();

    Classroom::factory()->create(['school_id' => $satu->id, 'name' => '7A']);
    $kembar = Classroom::factory()->create(['school_id' => $dua->id, 'name' => '7A']);

    expect($kembar->exists)->toBeTrue();
});

test('siswa store accepts a nis freed by a soft deleted student', function () {
    $user = createAdminUser();
    $classroom = Classroom::factory()->create(['school_id' => $user->school_id]);

    $lama = Student::factory()->create([
        'school_id' => $user->school_id,
        'classroom_id' => $classroom->id,
        'nis' => '20250100',
    ]);
    $lama->delete();

    $this->actingAs($user)
        ->post(route('admin.siswa.store'), [
            'full_name' => 'Siswa Baru',
            'nis' => '20250100',
            'gender' => 'LAKI_LAKI',
            'classroom_id' => $classroom->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Student::where('nis', '20250100')->count())->toBe(1);
});
