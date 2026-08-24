<?php

use App\Models\School;

/**
 * Payload lengkap tab Notifikasi.
 *
 * Setiap saklar di tab ini `required`, dan itu disengaja: checkbox yang tidak
 * tercentang tetap harus mengirim `false`, kalau tidak kuncinya tidak pernah
 * ditulis dan `getSetting()` diam-diam kembali ke default. Konsekuensinya tes
 * tidak bisa mengirim payload separuh.
 *
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function notifikasiPayload(array $override = []): array
{
    return [
        'section' => 'notifikasi',
        'whatsapp_enabled' => true,
        'notify_on_check_in' => true,
        'notify_on_check_out' => true,
        'wa_alert_only' => true,
        'wa_alert_terlambat' => true,
        'wa_alert_alpa' => true,
        'feature_notif_alpa_sholat' => false,
        'wa_verified' => false,
        'wa_daily_limit' => 50,
        'prayer_absence_threshold' => 3,
        'prayer_absence_require_present' => true,
        ...$override,
    ];
}

test('guests are redirected from pengaturan page', function () {
    $this->get(route('admin.pengaturan'))->assertRedirect(route('login'));
});

test('authenticated users can visit pengaturan page', function () {
    $user = createAdminUser();

    $response = $this->actingAs($user)->get(route('admin.pengaturan'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/pengaturan/index')
        ->has('settings')
    );
});

test('pengaturan returns existing setting values', function () {
    $user = createAdminUser();
    $school = School::find($user->school_id);
    $school->setSetting('school_name', 'SD Negeri 1');
    $school->save();

    $response = $this->actingAs($user)->get(route('admin.pengaturan'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('settings.school_name', 'SD Negeri 1')
    );
});

test('pengaturan can be updated', function () {
    $user = createAdminUser();

    $response = $this->actingAs($user)->put(route('admin.pengaturan.update'), [
        'school_name' => 'SD Negeri 2',
        'timezone' => 'Asia/Makassar',
        'whatsapp_enabled' => true,
        'notify_on_check_in' => true,
        'notify_on_check_out' => false,
        'whatsapp_template_attendance' => 'Halo {parent_name}',
    ]);

    $response->assertRedirect(route('admin.pengaturan'));
    $school = School::find($user->school_id);
    expect($school->getSetting('school_name'))->toBe('SD Negeri 2');
    expect($school->getSetting('timezone'))->toBe('Asia/Makassar');
});

test('pengaturan no longer stores attendance times', function () {
    $user = createAdminUser();

    $this->actingAs($user)->put(route('admin.pengaturan.update'), [
        'default_check_in_time' => '07:30',
        'late_threshold_time' => '07:45',
        'default_check_out_time' => '15:30',
    ])->assertRedirect(route('admin.pengaturan'));

    $school = School::find($user->school_id);

    expect($school->getSetting('default_check_in_time'))->toBeNull()
        ->and($school->getSetting('late_threshold_time'))->toBeNull();
});

test('pengaturan validates timezone values', function () {
    $user = createAdminUser();

    $response = $this->actingAs($user)->put(route('admin.pengaturan.update'), [
        'timezone' => 'Invalid/Timezone',
    ]);

    $response->assertSessionHasErrors('timezone');
});

// ---------------------------------------------------------------- Tab

test('a payload without a section still uses the legacy ruleset', function () {
    // Katup pengaman: bundle JS lama yang masih di cache browser saat deploy
    // harus tetap bisa menyimpan tanpa 422.
    $user = createAdminUser();

    $this->actingAs($user)->put(route('admin.pengaturan.update'), [
        'school_name' => 'SD Lama',
        'timezone' => 'Asia/Jakarta',
    ])->assertSessionHasNoErrors();

    expect(School::find($user->school_id)->getSetting('school_name'))->toBe('SD Lama');
});

test('saving a section returns to that tab', function () {
    $user = createAdminUser();

    $this->actingAs($user)->put(route('admin.pengaturan.update'), [
        'section' => 'umum',
        'school_name' => 'SD Tab',
        'timezone' => 'Asia/Jakarta',
    ])->assertRedirect(route('admin.pengaturan', ['tab' => 'umum']));
});

test('the section discriminator never reaches schools.settings', function () {
    $user = createAdminUser();

    $this->actingAs($user)->put(route('admin.pengaturan.update'), [
        'section' => 'umum',
        'school_name' => 'SD Bersih',
        'timezone' => 'Asia/Jakarta',
    ]);

    expect(School::find($user->school_id)->getSetting('section'))->toBeNull();
});

test('the dhuha defaults are exposed even when the school never saved them', function () {
    $user = createAdminUser();

    $this->actingAs($user)->get(route('admin.pengaturan'))
        ->assertInertia(fn ($page) => $page
            ->where('settings.prayer_dhuha_start', '07:30')
            ->where('settings.prayer_dhuha_end', '09:00')
            ->where('settings.prayer_dhuha_enabled', false)
            ->where('settings.prayer_start', '11:00'));
});

test('dhuha settings persist without disturbing the legacy dzuhur keys', function () {
    $user = createAdminUser();
    $school = School::find($user->school_id);
    $school->setSetting('prayer_enabled', true);

    $this->actingAs($user)->put(route('admin.pengaturan.update'), [
        'section' => 'sholat',
        'prayer_dhuha_enabled' => true,
        'prayer_dhuha_start' => '07:00',
        'prayer_dhuha_end' => '08:30',
        'prayer_enabled' => true,
        'prayer_start' => '11:00',
        'prayer_end' => '13:00',
        'prayer_all_religions' => false,
    ])->assertSessionHasNoErrors();

    $school = School::find($user->school_id);

    expect($school->getSetting('prayer_dhuha_start'))->toBe('07:00')
        ->and($school->getSetting('prayer_enabled'))->toBeTrue()
        ->and($school->getSetting('prayer_start'))->toBe('11:00');
});

test('overlapping prayer windows are rejected', function () {
    $user = createAdminUser();

    $this->actingAs($user)->put(route('admin.pengaturan.update'), [
        'section' => 'sholat',
        'prayer_dhuha_enabled' => true,
        'prayer_dhuha_start' => '07:30',
        'prayer_dhuha_end' => '11:30',
        'prayer_enabled' => true,
        'prayer_start' => '11:00',
        'prayer_end' => '13:00',
        'prayer_all_religions' => false,
    ])->assertSessionHasErrors('prayer_dhuha_end');
});

test('windows that merely touch at the same minute are rejected too', function () {
    // Batas jendela inklusif di kedua ujung, jadi 11:00 selesai + 11:00 mulai
    // sudah cukup membuat deteksi-jam ambigu.
    $user = createAdminUser();

    $this->actingAs($user)->put(route('admin.pengaturan.update'), [
        'section' => 'sholat',
        'prayer_dhuha_enabled' => true,
        'prayer_dhuha_start' => '07:30',
        'prayer_dhuha_end' => '11:00',
        'prayer_enabled' => true,
        'prayer_start' => '11:00',
        'prayer_end' => '13:00',
        'prayer_all_religions' => false,
    ])->assertSessionHasErrors('prayer_dhuha_end');
});

test('the overlap check is skipped while dhuha is off', function () {
    $user = createAdminUser();

    $this->actingAs($user)->put(route('admin.pengaturan.update'), [
        'section' => 'sholat',
        'prayer_dhuha_enabled' => false,
        'prayer_dhuha_start' => '07:30',
        'prayer_dhuha_end' => '11:30',
        'prayer_enabled' => true,
        'prayer_start' => '11:00',
        'prayer_end' => '13:00',
        'prayer_all_religions' => false,
    ])->assertSessionHasNoErrors();
});

test('a switch sent as null is stored as a real boolean', function () {
    // getSetting() memakai `??`: null tersimpan tidak terbedakan dari "belum
    // diatur" dan diam-diam kembali ke default true di listener notifikasi.
    $user = createAdminUser();

    $this->actingAs($user)->put(route('admin.pengaturan.update'), notifikasiPayload([
        'whatsapp_enabled' => false,
        'notify_on_check_in' => false,
        'notify_on_check_out' => false,
    ]))->assertSessionHasNoErrors();

    expect(School::find($user->school_id)->getSetting('whatsapp_enabled'))->toBeFalse();
});

test('the whatsapp alert switches are stored as real booleans', function () {
    $user = createAdminUser();

    $this->actingAs($user)->put(route('admin.pengaturan.update'), notifikasiPayload([
        'wa_alert_only' => false,
        'wa_alert_terlambat' => false,
        'wa_alert_alpa' => true,
        'wa_verified' => true,
        'wa_daily_limit' => 120,
    ]))->assertSessionHasNoErrors();

    $school = School::find($user->school_id);

    expect($school->getSetting('wa_alert_only'))->toBeFalse()
        ->and($school->getSetting('wa_alert_terlambat'))->toBeFalse()
        ->and($school->getSetting('wa_alert_alpa'))->toBeTrue()
        ->and($school->getSetting('wa_verified'))->toBeTrue()
        ->and($school->getSetting('wa_daily_limit'))->toBe(120);
});

/**
 * Skenario alpa sholat memakai key fitur yang sudah ada. Kalau tab Notifikasi
 * menulis ke key kembar, saklar di sini dan saklar di tab Fitur akan saling
 * membatalkan tanpa ada yang tahu.
 */
test('the prayer absence scenario writes the existing feature key', function () {
    $user = createAdminUser();

    $this->actingAs($user)->put(route('admin.pengaturan.update'), notifikasiPayload([
        'feature_notif_alpa_sholat' => true,
    ]))->assertSessionHasNoErrors();

    expect(School::find($user->school_id)->getSetting('feature_notif_alpa_sholat'))->toBeTrue();
});

test('an absurd daily limit is rejected', function () {
    $user = createAdminUser();

    foreach ([0, 1001] as $limit) {
        $this->actingAs($user)
            ->put(route('admin.pengaturan.update'), notifikasiPayload(['wa_daily_limit' => $limit]))
            ->assertSessionHasErrors('wa_daily_limit');
    }
});

test('the prayer absence threshold is bounded', function () {
    $user = createAdminUser();

    foreach ([1, 11] as $threshold) {
        $this->actingAs($user)
            ->put(route('admin.pengaturan.update'), notifikasiPayload(['prayer_absence_threshold' => $threshold]))
            ->assertSessionHasErrors('prayer_absence_threshold');
    }
});

test('the feature tab saves every switch including the ones turned off', function () {
    $user = createAdminUser();

    $this->actingAs($user)->put(route('admin.pengaturan.update'), [
        'section' => 'fitur',
        'features' => [
            'kartu_album' => false,
            'sholat_dhuha' => true,
            'laporan' => true,
        ],
    ])->assertRedirect(route('admin.pengaturan', ['tab' => 'fitur']));

    $school = School::find($user->school_id);

    expect($school->getSetting('feature_kartu_album'))->toBeFalse()
        // Saklar sholat menulis ke key lama, bukan key kembar.
        ->and($school->getSetting('prayer_dhuha_enabled'))->toBeTrue()
        ->and($school->getSetting('feature_sholat_dhuha'))->toBeNull();
});
