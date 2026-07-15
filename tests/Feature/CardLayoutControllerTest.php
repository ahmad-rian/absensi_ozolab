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
    $user->assignRole('ADMIN');

    $this->actingAs($user)->get(route('admin.card-layouts'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/card-layouts/index')->has('layouts'));
});

test('card layout can be created', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole('ADMIN');

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
    $user->assignRole('ADMIN');

    $this->actingAs($user)->get(route('admin.card-layouts.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/card-layouts/editor'));
});

test('card layout can be updated', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole('ADMIN');

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

test('card layout persists element positions and orientation', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole('ADMIN');

    $elements = [
        'field_nama' => ['type' => 'field', 'label' => 'NAMA', 'source' => 'full_name', 'x' => 10, 'y' => 12, 'width' => 50, 'fontSize' => 2.2, 'enabled' => true],
        'photo' => ['type' => 'photo', 'x' => 5, 'y' => 30, 'w' => 18, 'h' => 22, 'enabled' => true],
        'qr' => ['type' => 'qr', 'x' => 40, 'y' => 40, 'size' => 14, 'enabled' => false],
    ];

    $this->actingAs($user)->post(route('admin.card-layouts.store'), [
        'name' => 'Dynamic Layout',
        'type' => 'osis',
        'layout_config' => ['orientation' => 'portrait', 'frame_id' => null, 'elements' => $elements],
        'is_default' => false,
    ])->assertRedirect(route('admin.card-layouts'));

    $layout = SchoolCardLayout::where('name', 'Dynamic Layout')->firstOrFail();
    $config = $layout->normalizedConfig();

    expect($config['orientation'])->toBe('portrait');
    expect($config['elements']['field_nama']['x'])->toBe(10);
    expect($config['elements']['photo']['h'])->toBe(22);
    expect($config['elements']['qr']['enabled'])->toBeFalse();
});

test('legacy layout config normalizes into elements', function () {
    $school = School::factory()->create();

    $layout = SchoolCardLayout::create([
        'school_id' => $school->id,
        'name' => 'Legacy',
        'type' => 'osis',
        'layout_config' => ['frame_body_top' => 16, 'frame_body_left' => 3, 'frame_body_font' => 2.0, 'frame_photo_left' => 2.5, 'frame_photo_top' => 30, 'frame_qr_size' => 15, 'show_qr' => true],
    ]);

    $config = $layout->normalizedConfig();

    expect($config)->toHaveKey('elements');
    expect($config['orientation'])->toBe('landscape');
    expect($config['elements']['field_nama']['enabled'])->toBeTrue();
    expect($config['elements']['field_nama']['x'])->toBe(3.0);
    expect($config['elements']['field_nama']['labelWidth'])->toBe(12.0);
    expect($config['elements']['photo']['y'])->toBe(30.0);
});

test('card layout can be deleted', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole('ADMIN');

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
