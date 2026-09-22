<?php

use App\Models\School;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guest public and login pages use published school branding including favicon html', function () {
    $school = School::factory()->create(['settings' => ['app_logo' => 'images/branding/logo.webp', 'app_favicon' => 'images/branding/icon.webp']]);
    Setting::setValue('public_branding_school_id', $school->id);
    foreach (['/', '/login'] as $url) {
        $this->get($url)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('app.logo', Storage::disk('public')->url('images/branding/logo.webp'))
                ->where('app.favicon', Storage::disk('public')->url('images/branding/icon.webp')))
            ->assertSee('href="'.Storage::disk('public')->url('images/branding/icon.webp').'"', false);
    }
    $school->setSetting('app_logo', 'images/branding/new.webp');
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('app.logo', Storage::disk('public')->url('images/branding/new.webp')));
});

test('only super admin can select the school used for public branding', function () {
    $admin = createAdminUser();
    $this->actingAs($admin)->post(route('admin.pengaturan.branding-publik'))->assertForbidden();
    expect(Setting::getValue('public_branding_school_id'))->toBeNull();
    $super = createSuperAdminUser();
    $this->actingAs($super)->post(route('admin.pengaturan.branding-publik'))->assertRedirect();
    expect(Setting::getValue('public_branding_school_id'))->toBe($super->school_id);
});

test('another school keeps its own branding after public branding is selected', function () {
    $public = School::factory()->create(['settings' => ['app_logo' => 'public.webp']]);
    Setting::setValue('public_branding_school_id', $public->id);
    $admin = createAdminUser();
    $admin->school->setSetting('app_logo', 'private.webp');
    $this->actingAs($admin)->get('/admin/pengaturan')->assertInertia(fn (Assert $page) => $page->where('app.logo', Storage::disk('public')->url('private.webp')));
});

test('unpublished and inactive schools fall back to global branding', function () {
    Setting::setValue('app_logo', 'global.webp');
    $school = School::factory()->create(['is_active' => false, 'settings' => ['app_logo' => 'inactive.webp']]);
    Setting::setValue('public_branding_school_id', $school->id);
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('app.logo', Storage::disk('public')->url('global.webp')));
});
