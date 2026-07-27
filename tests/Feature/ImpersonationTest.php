<?php

test('a super admin can impersonate a school user and return', function () {
    $superAdmin = createSuperAdminUser();
    $admin = createAdminUser();

    $this->actingAs($superAdmin)
        ->post(route('admin.users.impersonate', $admin))
        ->assertRedirect(route('dashboard'));

    expect(auth()->id())->toBe($admin->id)
        ->and(session('impersonator_id'))->toBe($superAdmin->id)
        ->and(session('current_school_id'))->toBe($admin->school_id);

    $this->post(route('admin.stop-impersonate'))
        ->assertRedirect(route('admin.users.index'));

    expect(auth()->id())->toBe($superAdmin->id)
        ->and(session()->has('impersonator_id'))->toBeFalse();
});

test('an impersonated admin is confined to their own school', function () {
    $superAdmin = createSuperAdminUser();
    $admin = createAdminUser();

    $this->actingAs($superAdmin)->post(route('admin.users.impersonate', $admin));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('currentSchool.id', $admin->school_id)
            ->where('impersonation.active', true)
            ->where('impersonation.original_name', $superAdmin->name)
        );
});

test('a school admin cannot impersonate anyone', function () {
    $admin = createAdminUser();
    $target = createAdminUser();

    $this->actingAs($admin)
        ->post(route('admin.users.impersonate', $target))
        ->assertForbidden();

    expect(auth()->id())->toBe($admin->id);
});

test('a super admin cannot impersonate another super admin', function () {
    $superAdmin = createSuperAdminUser();
    $other = createSuperAdminUser();

    $this->actingAs($superAdmin)
        ->post(route('admin.users.impersonate', $other))
        ->assertForbidden();
});

test('impersonating yourself is rejected', function () {
    $superAdmin = createSuperAdminUser();

    $this->actingAs($superAdmin)
        ->post(route('admin.users.impersonate', $superAdmin))
        ->assertForbidden();
});

test('an inactive account cannot be impersonated', function () {
    $superAdmin = createSuperAdminUser();
    $admin = createAdminUser(['is_active' => false]);

    $this->actingAs($superAdmin)
        ->post(route('admin.users.impersonate', $admin))
        ->assertForbidden();
});

test('nesting impersonation is rejected', function () {
    $superAdmin = createSuperAdminUser();
    $admin = createAdminUser();
    $another = createAdminUser();

    $this->actingAs($superAdmin)->post(route('admin.users.impersonate', $admin));

    $this->post(route('admin.users.impersonate', $another))->assertForbidden();
});

test('stopping without impersonating is a no-op', function () {
    $this->actingAs(createAdminUser())
        ->post(route('admin.stop-impersonate'))
        ->assertRedirect(route('dashboard'));
});

test('impersonation is written to the activity log', function () {
    $superAdmin = createSuperAdminUser();
    $admin = createAdminUser();

    $this->actingAs($superAdmin)->post(route('admin.users.impersonate', $admin));

    $this->assertDatabaseHas('activity_log', [
        'description' => 'impersonation.started',
        'causer_id' => $superAdmin->id,
        'subject_id' => $admin->id,
    ]);
});
