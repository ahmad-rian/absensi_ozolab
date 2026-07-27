<?php

use App\Enums\AppModule;
use App\Enums\SchoolFeature;
use App\Models\School;
use App\Models\Student;
use App\Support\SchoolFeatures;
use Symfony\Component\HttpKernel\Exception\HttpException;

// ---------------------------------------------------------------- Enum

test('every feature has a label, description, group and unique setting key', function () {
    $keys = [];

    foreach (SchoolFeature::cases() as $feature) {
        expect($feature->label())->not->toBe('')
            ->and($feature->description())->not->toBe('')
            ->and($feature->group())->not->toBe('');

        $keys[] = $feature->settingKey();
    }

    expect($keys)->toHaveCount(count(array_unique($keys)));
});

test('no system-group module is mapped to a feature', function () {
    // Modul lintas-tenant super-admin-only tidak punya arti "dimatikan untuk
    // satu sekolah"; memetakannya akan melubangi batas keamanan hasil audit.
    $mapped = collect(AppModule::cases())
        ->filter(fn (AppModule $module) => $module->group() === 'Sistem')
        ->filter(fn (AppModule $module) => SchoolFeature::forModule($module) !== null)
        ->map(fn (AppModule $module) => $module->value)
        ->values()
        ->all();

    expect($mapped)->toBe([]);
});

test('dashboard and pengaturan can never be switched off', function () {
    $alwaysOn = collect(SchoolFeature::alwaysOnModules())->map(fn (AppModule $m) => $m->value);

    expect($alwaysOn)->toContain('dashboard')
        ->and($alwaysOn)->toContain('pengaturan');
});

test('the prayer and notification toggles reuse the existing setting keys', function () {
    // Satu sumber kebenaran: PrayerSettings dan DispatchAttendanceNotifications
    // membaca key yang sama, jadi tidak ada dua nilai yang perlu disinkronkan.
    expect(SchoolFeature::SholatDzuhur->settingKey())->toBe('prayer_enabled')
        ->and(SchoolFeature::SholatDhuha->settingKey())->toBe('prayer_dhuha_enabled')
        ->and(SchoolFeature::NotifAbsensi->settingKey())->toBe('whatsapp_enabled');
});

// ---------------------------------------------------------------- Resolusi

test('pre-existing features default to on for a school with no settings', function () {
    $flags = SchoolFeatures::for(School::factory()->create(['settings' => []]));

    expect($flags->enabled(SchoolFeature::MasterSiswa))->toBeTrue()
        ->and($flags->enabled(SchoolFeature::AbsensiSekolah))->toBeTrue()
        ->and($flags->enabled(SchoolFeature::Laporan))->toBeTrue()
        ->and($flags->enabled(SchoolFeature::KartuAlbum))->toBeTrue();
});

test('brand new features default to off', function () {
    $flags = SchoolFeatures::for(School::factory()->create(['settings' => []]));

    expect($flags->enabled(SchoolFeature::SholatDhuha))->toBeFalse()
        ->and($flags->enabled(SchoolFeature::NotifAlpaSholat))->toBeFalse();
});

test('a stored false does not fall back to the default', function () {
    // getSetting() memakai `??`, jadi false yang tersimpan harus tetap false —
    // regresi untuk nuansa yang mudah terlewat.
    $flags = SchoolFeatures::for(School::factory()->create([
        'settings' => ['feature_kartu_album' => false],
    ]));

    expect($flags->enabled(SchoolFeature::KartuAlbum))->toBeFalse();
});

test('the feature map sent to inertia is always complete', function () {
    $map = SchoolFeatures::for(School::factory()->create(['settings' => []]))->toArray();

    expect(array_keys($map))->toBe(SchoolFeature::values());
});

// ---------------------------------------------------------------- Middleware

beforeEach(function () {
    $this->admin = createAdminUser();
    $this->school = School::find($this->admin->school_id);
});

test('a module route is refused when its feature is switched off', function () {
    $this->school->setSetting('feature_kartu_album', false);

    $this->actingAs($this->admin)->get(route('admin.frames'))->assertForbidden();
});

test('the same route passes while the feature is on', function () {
    $this->actingAs($this->admin)->get(route('admin.frames'))->assertOk();
});

test('a super admin is refused too when the school switched the feature off', function () {
    // Ini status tenant, bukan otorisasi: super admin yang membuka Sekolah X
    // harus melihat Sekolah X persis seperti penggunanya.
    $superAdmin = createSuperAdminUser();
    School::find($superAdmin->school_id)->setSetting('feature_kartu_album', false);

    $this->actingAs($superAdmin)->get(route('admin.frames'))->assertForbidden();
});

test('pengaturan and dashboard stay reachable with every feature switched off', function () {
    $settings = $this->school->settings ?? [];

    foreach (SchoolFeature::cases() as $feature) {
        $settings[$feature->settingKey()] = false;
    }

    $this->school->forceFill(['settings' => $settings])->save();

    $this->actingAs($this->admin)->get(route('admin.pengaturan'))->assertOk();
    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
});

test('the student api is refused when master data is switched off', function () {
    Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)->getJson('/api/students')->assertOk();

    $this->school->setSetting('feature_master_siswa', false);

    $this->actingAs($this->admin)->getJson('/api/students')->assertForbidden();
});

test('an unknown feature name fails loudly instead of opening the door', function () {
    Route::middleware(['web', 'auth', 'feature:tidak-ada'])
        ->get('/__feature-typo', fn () => 'ok');

    $this->withoutExceptionHandling()
        ->actingAs($this->admin)
        ->get('/__feature-typo');
})->throws(HttpException::class, 'Fitur tidak dikenal: tidak-ada');

// ---------------------------------------------------------------- Shared prop

test('the feature map is shared with every inertia page', function () {
    $this->school->setSetting('feature_kartu_album', false);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('features')
            ->where('features.kartu_album', false)
            ->where('features.laporan', true)
            ->where('features.sholat_dhuha', false));
});

// ---------------------------------------------------------------- Halaman publik

test('a school with public registration off disappears from /daftar', function () {
    $open = School::factory()->create(['is_active' => true, 'settings' => []]);
    $closed = School::factory()->create([
        'is_active' => true,
        'settings' => ['feature_pendaftaran_publik' => false],
    ]);

    $this->get('/daftar')->assertInertia(fn ($page) => $page
        ->where('schools', fn ($schools) => collect($schools)->pluck('id')->contains($open->id)
            && ! collect($schools)->pluck('id')->contains($closed->id)));
});

test('registration is refused for a school that switched public sign-up off', function () {
    $closed = School::factory()->create([
        'is_active' => true,
        'settings' => ['feature_pendaftaran_publik' => false],
    ]);

    // Menyaring daftar di UI saja tidak cukup — school_id masih bisa di-POST.
    $this->post('/daftar', [
        'school_id' => $closed->id,
        'full_name' => 'Budi Santoso',
        'gender' => 'LAKI_LAKI',
    ])->assertSessionHasErrors('school_id');
});

test('the public scan page stays reachable and flags a disabled feature', function () {
    $this->school->forceFill([
        'is_active' => true,
        'settings' => array_merge($this->school->settings ?? [], ['feature_absensi_sekolah' => false]),
    ])->save();

    // 200, bukan 403: tablet di dinding harus menampilkan pesan yang terbaca.
    $this->get('/scan/'.$this->school->scanner_token)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('featureEnabled', false));
});

test('the public scan endpoint answers json 403 when attendance is switched off', function () {
    $this->school->forceFill([
        'is_active' => true,
        'settings' => array_merge($this->school->settings ?? [], ['feature_absensi_sekolah' => false]),
    ])->save();

    $student = Student::factory()->create(['school_id' => $this->school->id, 'qr_token' => 'abc.def']);

    $this->postJson('/scan/'.$this->school->scanner_token, ['token' => $student->qr_token])
        ->assertStatus(403)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Absensi sekolah sedang dimatikan oleh admin.');
});
