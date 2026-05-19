<?php

use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\User;

test('guests are redirected from orang tua index', function () {
    $this->get(route('admin.orang-tua.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit orang tua index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.orang-tua.index'))
        ->assertOk();
});

test('orang tua index returns paginated parents', function () {
    $user = User::factory()->create();
    ParentProfile::factory()->count(3)->create();

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
    $user = User::factory()->create();
    $parentUser = User::factory()->create(['name' => 'Budi Santoso']);
    ParentProfile::factory()->create(['user_id' => $parentUser->id]);
    $otherUser = User::factory()->create(['name' => 'Siti Rahayu']);
    ParentProfile::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->get(route('admin.orang-tua.index', ['search' => 'Budi']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('parents.data', 1)
        );
});

test('orang tua index can search by whatsapp number', function () {
    $user = User::factory()->create();
    ParentProfile::factory()->create(['whatsapp_number' => '+6281234567890']);
    ParentProfile::factory()->create(['whatsapp_number' => '+6289999999999']);

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
    $user = User::factory()->create();
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
