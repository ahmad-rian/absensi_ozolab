<?php

use App\Models\School;
use App\Models\SchoolAlbumLayout;
use App\Models\User;

test('guests cannot access album layouts page', function () {
    $this->get(route('admin.album-layouts'))->assertRedirect(route('login'));
});

test('authenticated users can view album layouts', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole('ADMIN');

    $this->actingAs($user)->get(route('admin.album-layouts'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/album-layouts/index')->has('layouts'));
});

test('album layout can be created', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole('ADMIN');

    $response = $this->actingAs($user)->post(route('admin.album-layouts.store'), [
        'name' => 'Album A4 3x4',
        'paper_size' => 'A4',
        'orientation' => 'portrait',
        'columns' => 3,
        'rows' => 4,
        'layout_config' => ['bg_color' => '#ffffff'],
        'is_default' => true,
    ]);

    $response->assertRedirect(route('admin.album-layouts'));
    $this->assertDatabaseHas('school_album_layouts', [
        'school_id' => $school->id,
        'name' => 'Album A4 3x4',
        'columns' => 3,
        'rows' => 4,
    ]);
});

test('album layout can be updated', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole('ADMIN');

    $layout = SchoolAlbumLayout::create([
        'school_id' => $school->id,
        'name' => 'Old',
        'paper_size' => 'A4',
        'orientation' => 'portrait',
        'columns' => 3,
        'rows' => 4,
        'layout_config' => [],
    ]);

    $this->actingAs($user)->put(route('admin.album-layouts.update', $layout), [
        'name' => 'Updated',
        'paper_size' => 'A3',
        'orientation' => 'landscape',
        'columns' => 4,
        'rows' => 5,
        'layout_config' => ['bg_color' => '#f0f0f0'],
        'is_default' => false,
        'is_active' => true,
    ])->assertRedirect(route('admin.album-layouts'));

    $this->assertDatabaseHas('school_album_layouts', [
        'id' => $layout->id,
        'name' => 'Updated',
        'paper_size' => 'A3',
        'columns' => 4,
    ]);
});

test('album layout can be deleted', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole('ADMIN');

    $layout = SchoolAlbumLayout::create([
        'school_id' => $school->id,
        'name' => 'Delete Me',
        'paper_size' => 'A4',
        'orientation' => 'portrait',
        'columns' => 3,
        'rows' => 4,
        'layout_config' => [],
    ]);

    $this->actingAs($user)->delete(route('admin.album-layouts.destroy', $layout))
        ->assertRedirect(route('admin.album-layouts'));

    $this->assertDatabaseMissing('school_album_layouts', ['id' => $layout->id]);
});
