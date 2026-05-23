<?php

use App\Models\School;

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
        'default_check_in_time' => '07:30',
        'late_threshold_time' => '07:45',
        'default_check_out_time' => '15:30',
        'timezone' => 'Asia/Makassar',
        'whatsapp_enabled' => true,
        'notify_on_check_in' => true,
        'notify_on_check_out' => false,
        'whatsapp_template_attendance' => 'Halo {parent_name}',
    ]);

    $response->assertRedirect(route('admin.pengaturan'));
    $school = School::find($user->school_id);
    expect($school->getSetting('school_name'))->toBe('SD Negeri 2');
    expect($school->getSetting('default_check_in_time'))->toBe('07:30');
    expect($school->getSetting('timezone'))->toBe('Asia/Makassar');
});

test('pengaturan validates timezone values', function () {
    $user = createAdminUser();

    $response = $this->actingAs($user)->put(route('admin.pengaturan.update'), [
        'timezone' => 'Invalid/Timezone',
    ]);

    $response->assertSessionHasErrors('timezone');
});
