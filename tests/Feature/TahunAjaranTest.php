<?php

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\User;

test('admin menambah tahun ajaran dari tahun mulai', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->post(route('admin.pengaturan.tahun-ajaran.store'), ['tahun_mulai' => 2027])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $tahun = AcademicYear::withoutGlobalScopes()->where('school_id', $admin->school_id)->where('name', '2027/2028')->sole();

    expect($tahun->start_date->toDateString())->toBe('2027-07-15')
        ->and($tahun->end_date->toDateString())->toBe('2028-06-30')
        // Menambah tidak mengaktifkan: tahun aktif dipilih lalu disimpan sendiri.
        ->and($tahun->is_active)->toBeFalse();
});

test('tahun ajaran yang sudah ada di sekolah itu ditolak', function () {
    $admin = createAdminUser();
    AcademicYear::factory()->create(['school_id' => $admin->school_id, 'name' => '2027/2028']);

    $this->actingAs($admin)
        ->post(route('admin.pengaturan.tahun-ajaran.store'), ['tahun_mulai' => 2027])
        ->assertSessionHasErrors('tahun_mulai');

    expect(AcademicYear::withoutGlobalScopes()->where('school_id', $admin->school_id)->where('name', '2027/2028')->count())->toBe(1);
});

test('tahun ajaran yang sama di sekolah lain tidak menghalangi', function () {
    $admin = createAdminUser();
    AcademicYear::factory()->create(['school_id' => School::factory()->create()->id, 'name' => '2027/2028']);

    $this->actingAs($admin)
        ->post(route('admin.pengaturan.tahun-ajaran.store'), ['tahun_mulai' => 2027])
        ->assertSessionHasNoErrors();
});

test('tahun mulai harus angka tahun yang wajar', function (mixed $nilai) {
    $this->actingAs(createAdminUser())
        ->post(route('admin.pengaturan.tahun-ajaran.store'), ['tahun_mulai' => $nilai])
        ->assertSessionHasErrors('tahun_mulai');
})->with([null, 'abc', 1999, 2101, '2027/2028']);

test('pengguna tanpa izin pengaturan tidak bisa menambah tahun ajaran', function () {
    $admin = createAdminUser();
    $guru = User::factory()->create(['school_id' => $admin->school_id]);
    $guru->syncRoles(['GURU']);

    $this->actingAs($guru)
        ->post(route('admin.pengaturan.tahun-ajaran.store'), ['tahun_mulai' => 2027])
        ->assertForbidden();
});

test('menyimpan pengaturan umum tanpa memilih tahun ajaran tidak mengubah tahun aktif', function () {
    $admin = createAdminUser();
    $aktif = AcademicYear::factory()->active()->create(['school_id' => $admin->school_id, 'name' => '2027/2028']);
    AcademicYear::factory()->create(['school_id' => $admin->school_id, 'name' => '2026/2027']);

    $this->actingAs($admin)
        ->put(route('admin.pengaturan.update'), ['section' => 'umum', 'school_name' => 'SMP Uji', 'timezone' => 'Asia/Jakarta'])
        ->assertSessionHasNoErrors();

    expect($aktif->fresh()->is_active)->toBeTrue();
});

test('daftar tahun ajaran di pengaturan diurutkan dari yang paling awal', function () {
    $admin = createAdminUser();
    AcademicYear::withoutGlobalScopes()->where('school_id', $admin->school_id)->delete();
    foreach ([2030, 2026, 2028] as $mulai) {
        AcademicYear::factory()->create(['school_id' => $admin->school_id, 'name' => $mulai.'/'.($mulai + 1), 'start_date' => $mulai.'-07-15', 'end_date' => ($mulai + 1).'-06-30']);
    }

    $this->withoutVite()->actingAs($admin)->get(route('admin.pengaturan'))
        ->assertInertia(fn ($page) => $page->where('academicYears', fn ($t) => collect($t)->pluck('name')->all() === ['2026/2027', '2028/2029', '2030/2031']));
});
