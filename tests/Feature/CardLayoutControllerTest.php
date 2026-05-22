<?php

use App\Models\School;
use App\Models\SchoolCardLayout;
use App\Models\User;

test('guests cannot access card layouts page', function () {
    $this->get(route('admin.card-layouts'))->assertRedirect(route('login'));
});

test('authenticated users can view card layouts', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);

    $this->actingAs($user)->get(route('admin.card-layouts'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/card-layouts/index')->has('layouts'));
});

test('card layout can be created', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);

    $response = $this->actingAs($user)->post(route('admin.card-layouts.store'), [
        'name' => 'Kartu OSIS Biru',
        'type' => 'osis',
        'layout_config' => ['card_width' => 638, 'card_height' => 1011],
        'is_default' => true,
    ]);

    $response->assertRedirect(route('admin.card-layouts'));
    $this->assertDatabaseHas('school_card_layouts', [
        'school_id' => $school->id,
        'name' => 'Kartu OSIS Biru',
        'type' => 'osis',
    ]);
});

test('card layout editor page loads for create', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);

    $this->actingAs($user)->get(route('admin.card-layouts.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/card-layouts/editor'));
});

test('card layout can be updated', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);

    $layout = SchoolCardLayout::create([
        'school_id' => $school->id,
        'name' => 'Old Name',
        'type' => 'osis',
        'layout_config' => ['card_width' => 638],
    ]);

    $this->actingAs($user)->put(route('admin.card-layouts.update', $layout), [
        'name' => 'New Name',
        'type' => 'perpustakaan',
        'layout_config' => ['card_width' => 700],
        'is_default' => false,
        'is_active' => true,
    ])->assertRedirect(route('admin.card-layouts'));

    $this->assertDatabaseHas('school_card_layouts', [
        'id' => $layout->id,
        'name' => 'New Name',
        'type' => 'perpustakaan',
    ]);
});

test('card layout can be deleted', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);

    $layout = SchoolCardLayout::create([
        'school_id' => $school->id,
        'name' => 'Delete Me',
        'type' => 'osis',
        'layout_config' => [],
    ]);

    $this->actingAs($user)->delete(route('admin.card-layouts.destroy', $layout))
        ->assertRedirect(route('admin.card-layouts'));

    $this->assertDatabaseMissing('school_card_layouts', ['id' => $layout->id]);
});
