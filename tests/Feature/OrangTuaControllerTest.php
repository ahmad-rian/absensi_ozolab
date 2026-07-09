<?php

use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\User;

test('guests are redirected from orang tua index', function () {
    $this->get(route('admin.orang-tua.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit orang tua index', function () {
    $user = createAdminUser();

    $this->actingAs($user)
        ->get(route('admin.orang-tua.index'))
        ->assertOk();
});

test('orang tua index returns paginated parents', function () {
    $user = createAdminUser();
    ParentProfile::factory()->count(3)->create(['school_id' => $user->school_id]);

    $this->actingAs($user)
        ->get(route('admin.orang-tua.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/orang-tua/index')
            ->has('parents.data', 3)
            ->has('filters')
        );
});

test('orang tua index can search by name', function () {
    $user = createAdminUser();
    $parentUser = User::factory()->create(['name' => 'Budi Santoso']);
    ParentProfile::factory()->create(['user_id' => $parentUser->id, 'school_id' => $user->school_id]);
    $otherUser = User::factory()->create(['name' => 'Siti Rahayu']);
    ParentProfile::factory()->create(['user_id' => $otherUser->id, 'school_id' => $user->school_id]);

    $this->actingAs($user)
        ->get(route('admin.orang-tua.index', ['search' => 'Budi']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('parents.data', 1)
        );
});

test('orang tua index can search by whatsapp number', function () {
    $user = createAdminUser();
    ParentProfile::factory()->create(['whatsapp_number' => '+6281234567890', 'school_id' => $user->school_id]);
    ParentProfile::factory()->create(['whatsapp_number' => '+6289999999999', 'school_id' => $user->school_id]);

    $this->actingAs($user)
        ->get(route('admin.orang-tua.index', ['search' => '81234567890']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('parents.data', 1)
        );
});

test('guests are redirected from orang tua show', function () {
    $parent = ParentProfile::factory()->create();

    $this->get(route('admin.orang-tua.show', $parent))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit orang tua show', function () {
    $user = createAdminUser();
    $parent = ParentProfile::factory()->create();
    Student::factory()->create(['parent_profile_id' => $parent->id]);

    $this->actingAs($user)
        ->get(route('admin.orang-tua.show', $parent))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/orang-tua/show')
            ->has('parent')
            ->has('parent.students', 1)
        );
});

test('admin can store parent with telegram chat id', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->post(route('admin.orang-tua.store'), [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'relation' => 'AYAH',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'telegram_chat_id' => '987654321',
        ])
        ->assertRedirect(route('admin.orang-tua.index'));

    $this->assertDatabaseHas('parent_profiles', [
        'telegram_chat_id' => '987654321',
        'whatsapp_number' => '081234567890',
    ]);
});

test('admin can update parent phone', function () {
    $admin = createAdminUser();
    $parent = ParentProfile::factory()->create(['school_id' => $admin->school_id]);

    $this->actingAs($admin)
        ->put(route('admin.orang-tua.update', $parent), [
            'name' => 'Nama Baru',
            'email' => $parent->user->email,
            'phone' => '081200000001',
            'relation' => 'IBU',
        ])
        ->assertRedirect(route('admin.orang-tua.index'));

    $this->assertDatabaseHas('parent_profiles', [
        'id' => $parent->id,
        'whatsapp_number' => '081200000001',
    ]);
});

test('updating phone to a number used by another parent shows validation error, not 500', function () {
    $admin = createAdminUser();
    $other = ParentProfile::factory()->create(['school_id' => $admin->school_id, 'whatsapp_number' => '081277777777']);
    $parent = ParentProfile::factory()->create(['school_id' => $admin->school_id, 'whatsapp_number' => '081200000009']);

    $this->actingAs($admin)
        ->put(route('admin.orang-tua.update', $parent), [
            'name' => 'Nama',
            'email' => $parent->user->email,
            'phone' => '081277777777',
            'relation' => 'AYAH',
        ])
        ->assertSessionHasErrors('phone');
});

test('phone with letters is rejected', function () {
    $admin = createAdminUser();
    $parent = ParentProfile::factory()->create(['school_id' => $admin->school_id]);

    $this->actingAs($admin)
        ->put(route('admin.orang-tua.update', $parent), [
            'name' => 'Nama',
            'email' => $parent->user->email,
            'phone' => '0812ABC7890',
            'relation' => 'AYAH',
        ])
        ->assertSessionHasErrors('phone');
});

test('nik and telegram chat id must be numeric', function () {
    $admin = createAdminUser();
    $parent = ParentProfile::factory()->create(['school_id' => $admin->school_id]);

    $this->actingAs($admin)
        ->put(route('admin.orang-tua.update', $parent), [
            'name' => 'Nama',
            'email' => $parent->user->email,
            'phone' => '081200000002',
            'relation' => 'AYAH',
            'nik' => 'ABC123',
            'telegram_chat_id' => 'chat_abc',
        ])
        ->assertSessionHasErrors(['nik', 'telegram_chat_id']);
});

test('storing two parents with same phone shows validation error, not 500', function () {
    $admin = createAdminUser();
    ParentProfile::factory()->create(['school_id' => $admin->school_id, 'whatsapp_number' => '081255555555']);

    $this->actingAs($admin)
        ->post(route('admin.orang-tua.store'), [
            'name' => 'Budi',
            'email' => 'budi2@example.com',
            'phone' => '081255555555',
            'relation' => 'AYAH',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertSessionHasErrors('phone');
});
