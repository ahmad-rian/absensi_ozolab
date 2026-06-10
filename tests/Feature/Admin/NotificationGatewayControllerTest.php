<?php

use App\Enums\SchoolChannelType;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use Illuminate\Support\Facades\Http;

test('guests are redirected from notification gateways page', function () {
    $this->get(route('admin.notification-gateways'))->assertRedirect(route('login'));
});

test('non superadmin cannot access notification gateways', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->get(route('admin.notification-gateways'))
        ->assertForbidden();
});

test('superadmin can view notification gateways page', function () {
    $su = createSuperAdminUser();

    $this->actingAs($su)
        ->get(route('admin.notification-gateways'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/notification-gateways/index')
            ->has('schools')
            ->has('channels')
        );
});

test('superadmin can update channel config', function () {
    $su = createSuperAdminUser();
    $school = School::find($su->school_id);

    $this->actingAs($su)
        ->put(route('admin.notification-gateways.update', $school), [
            'channels' => [
                'OZOLAB_WA' => ['is_active' => true],
                'FONNTE_WA' => ['is_active' => true, 'fonnte_token' => 'tok-1234567890', 'display_phone' => '08123'],
                'TELEGRAM' => ['is_active' => false],
            ],
        ])
        ->assertRedirect();

    $fonnte = SchoolNotificationChannel::where('school_id', $school->id)
        ->where('channel', SchoolChannelType::FonnteWa->value)
        ->first();

    expect($fonnte->is_active)->toBeTrue()
        ->and($fonnte->setting('fonnte_token'))->toBe('tok-1234567890');
});

test('superadmin reset restores ozolab default', function () {
    $su = createSuperAdminUser();
    $school = School::find($su->school_id);

    SchoolNotificationChannel::create([
        'school_id' => $school->id,
        'channel' => SchoolChannelType::FonnteWa,
        'is_active' => true,
        'settings' => ['fonnte_token' => 'tok-1234567890'],
    ]);

    $this->actingAs($su)
        ->delete(route('admin.notification-gateways.destroy', $school))
        ->assertRedirect();

    expect(SchoolNotificationChannel::where('school_id', $school->id)->where('channel', SchoolChannelType::FonnteWa->value)->exists())->toBeFalse()
        ->and(SchoolNotificationChannel::where('school_id', $school->id)->where('channel', SchoolChannelType::OzolabWa->value)->where('is_active', true)->exists())->toBeTrue();
});

test('superadmin test endpoint sends message', function () {
    $su = createSuperAdminUser();
    $school = School::find($su->school_id);
    SchoolNotificationChannel::create(['school_id' => $school->id, 'channel' => SchoolChannelType::OzolabWa, 'is_active' => true]);

    config(['whatsapp.api_key' => 'key', 'whatsapp.sender' => '628', 'whatsapp.base_url' => 'https://wa.ozolab.id']);
    Http::fake(['wa.ozolab.id/*' => Http::response(['status' => true])]);

    $this->actingAs($su)
        ->postJson(route('admin.notification-gateways.test', $school), [
            'channel' => 'OZOLAB_WA',
            'destination' => '08123456789',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);
});
