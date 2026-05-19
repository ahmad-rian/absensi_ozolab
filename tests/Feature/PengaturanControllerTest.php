<?php

use App\Models\Setting;
use App\Models\User;

test('guests are redirected from pengaturan page', function () {
    $this->get(route('admin.pengaturan'))->assertRedirect(route('login'));
});

test('authenticated users can visit pengaturan page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.pengaturan'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/pengaturan/index')
        ->has('settings')
    );
});

test('pengaturan returns existing setting values', function () {
    $user = User::factory()->create();
    Setting::setValue('school_name', 'SD Negeri 1');

    $response = $this->actingAs($user)->get(route('admin.pengaturan'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('settings.school_name', 'SD Negeri 1')
    );
});

test('pengaturan can be updated', function () {
    $user = User::factory()->create();

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
    expect(Setting::getValue('school_name'))->toBe('SD Negeri 2');
    expect(Setting::getValue('default_check_in_time'))->toBe('07:30');
    expect(Setting::getValue('timezone'))->toBe('Asia/Makassar');
});

test('pengaturan validates timezone values', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('admin.pengaturan.update'), [
        'timezone' => 'Invalid/Timezone',
    ]);

    $response->assertSessionHasErrors('timezone');
});
