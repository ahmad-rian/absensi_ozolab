<?php

use App\Models\School;
use App\Models\SchoolDriveConfig;
use App\Models\User;

function createUserWithSchool(): User
{
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole('ADMIN');

    return $user;
}

test('guests are redirected from drive config page', function () {
    $this->get(route('admin.drive-config'))->assertRedirect(route('login'));
});

test('authenticated users can visit drive config page', function () {
    $user = createUserWithSchool();

    $response = $this->actingAs($user)->get(route('admin.drive-config'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/drive-config/index')
        ->has('driveConfig')
    );
});

test('drive config folders can be saved', function () {
    $user = createUserWithSchool();

    $response = $this->actingAs($user)->post(route('admin.drive-config.update'), [
        'root_folder_id' => 'abc123folder',
        'cards_folder_id' => 'cards-folder-id',
        'albums_folder_id' => 'albums-folder-id',
        'parents_folder_id' => 'parents-folder-id',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('admin.drive-config'));

    $this->assertDatabaseHas('school_drive_configs', [
        'school_id' => $user->school_id,
        'root_folder_id' => 'abc123folder',
        'cards_folder_id' => 'cards-folder-id',
        'is_active' => true,
    ]);
});

test('drive config page shows existing config', function () {
    $user = createUserWithSchool();

    SchoolDriveConfig::create([
        'school_id' => $user->school_id,
        'root_folder_id' => 'existing-folder-id',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get(route('admin.drive-config'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('driveConfig.root_folder_id', 'existing-folder-id')
        ->where('driveConfig.is_active', true)
    );
});
