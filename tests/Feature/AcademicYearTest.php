<?php

use App\Models\AcademicYear;
use App\Models\School;

test('activating an academic year deactivates the others in the same school', function () {
    $school = School::factory()->create();

    $lama = AcademicYear::factory()->active()->create(['school_id' => $school->id, 'name' => '2024/2025']);
    $lain = AcademicYear::factory()->active()->create(['school_id' => $school->id, 'name' => '2025/2026']);
    $baru = AcademicYear::factory()->create(['school_id' => $school->id, 'name' => '2026/2027']);

    $baru->activate();

    expect($baru->refresh()->is_active)->toBeTrue()
        ->and($lama->refresh()->is_active)->toBeFalse()
        ->and($lain->refresh()->is_active)->toBeFalse();
});

test('activating an academic year leaves other schools untouched', function () {
    $satu = School::factory()->create();
    $dua = School::factory()->create();

    $aktifDiSekolahLain = AcademicYear::factory()->active()->create(['school_id' => $dua->id]);
    $target = AcademicYear::factory()->create(['school_id' => $satu->id]);

    $target->activate();

    expect($aktifDiSekolahLain->refresh()->is_active)->toBeTrue();
});

test('activation stays scoped when running without a logged in user', function () {
    // Job dan command berjalan tanpa global scope `school`; aktivasi tidak
    // boleh menyapu tenant lain hanya karena scope-nya diam.
    $satu = School::factory()->create();
    $dua = School::factory()->create();

    $aktifDiSekolahLain = AcademicYear::factory()->active()->create(['school_id' => $dua->id]);
    $target = AcademicYear::factory()->create(['school_id' => $satu->id]);

    expect(auth()->hasUser())->toBeFalse();

    $target->activate();

    expect($target->refresh()->is_active)->toBeTrue()
        ->and($aktifDiSekolahLain->refresh()->is_active)->toBeTrue();
});

test('pengaturan umum can switch the active academic year', function () {
    $user = createAdminUser();

    $lama = AcademicYear::factory()->active()->create(['school_id' => $user->school_id, 'name' => '2025/2026']);
    $baru = AcademicYear::factory()->create(['school_id' => $user->school_id, 'name' => '2026/2027']);

    $this->actingAs($user)
        ->put(route('admin.pengaturan.update'), [
            'section' => 'umum',
            'school_name' => 'SD Negeri 1',
            'timezone' => 'Asia/Jakarta',
            'academic_year_id' => $baru->id,
        ])
        ->assertRedirect(route('admin.pengaturan', ['tab' => 'umum']));

    expect($baru->refresh()->is_active)->toBeTrue()
        ->and($lama->refresh()->is_active)->toBeFalse();

    // Tahun ajaran hidup di tabelnya sendiri, bukan di schools.settings.
    expect(School::find($user->school_id)->getSetting('academic_year_id'))->toBeNull();
});

test('pengaturan umum rejects an academic year from another school', function () {
    $user = createAdminUser();
    $milikSekolahLain = AcademicYear::factory()->create(['school_id' => School::factory()->create()->id]);

    $this->actingAs($user)
        ->put(route('admin.pengaturan.update'), [
            'section' => 'umum',
            'timezone' => 'Asia/Jakarta',
            'academic_year_id' => $milikSekolahLain->id,
        ])
        ->assertSessionHasErrors('academic_year_id');

    expect($milikSekolahLain->refresh()->is_active)->toBeFalse();
});

test('pengaturan page exposes the schools own academic years only', function () {
    $user = createAdminUser();
    AcademicYear::factory()->active()->create(['school_id' => $user->school_id, 'name' => '2025/2026']);
    AcademicYear::factory()->create(['school_id' => School::factory()->create()->id, 'name' => '2030/2031']);

    $this->actingAs($user)
        ->get(route('admin.pengaturan'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('academicYears', 1)
            ->where('academicYears.0.name', '2025/2026')
            ->where('activeAcademicYearId', AcademicYear::where('school_id', $user->school_id)->value('id'))
        );
});
