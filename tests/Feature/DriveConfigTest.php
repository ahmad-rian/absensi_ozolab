<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\SchoolDriveConfig;
use App\Models\User;
use App\Services\GoogleDriveService;

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
        'cards_folder_id' => 'cards-folder-id',
        'albums_folder_id' => 'albums-folder-id',
        'parents_folder_id' => 'parents-folder-id',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('admin.drive-config'));

    $this->assertDatabaseHas('school_drive_configs', [
        'school_id' => $user->school_id,
        'cards_folder_id' => 'cards-folder-id',
        'is_active' => true,
    ]);
});

test('school admin cannot change the platform root folder', function () {
    $user = createUserWithSchool();
    GoogleDriveService::setPlatformRootFolderId('platform-root-id');

    $this->actingAs($user)->post(route('admin.drive-config.update'), [
        'platform_root_folder_id' => 'sekolah-coba-rebut-root',
        'cards_folder_id' => 'cards-folder-id',
        'is_active' => true,
    ])->assertRedirect(route('admin.drive-config'));

    expect(GoogleDriveService::platformRootFolderId())->toBe('platform-root-id');

    // Folder sekolah tetap dikelola otomatis, tidak ikut tertulis dari request.
    $this->assertDatabaseHas('school_drive_configs', [
        'school_id' => $user->school_id,
        'root_folder_id' => null,
    ]);
});

test('super admin can change the platform root folder', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole(UserRole::SuperAdmin->value);

    $this->actingAs($user)->post(route('admin.drive-config.update'), [
        'platform_root_folder_id' => 'root-ozolab',
        'is_active' => true,
    ])->assertRedirect(route('admin.drive-config'));

    expect(GoogleDriveService::platformRootFolderId())->toBe('root-ozolab');
});

test('root folder input is only exposed to super admin', function () {
    $user = createUserWithSchool();
    GoogleDriveService::setPlatformRootFolderId('root-ozolab');

    $this->actingAs($user)->get(route('admin.drive-config'))
        ->assertInertia(fn ($page) => $page->where('isSuperAdmin', false));

    $superAdmin = User::factory()->create(['school_id' => $user->school_id]);
    $superAdmin->assignRole(UserRole::SuperAdmin->value);

    $this->actingAs($superAdmin)->get(route('admin.drive-config'))
        ->assertInertia(fn ($page) => $page
            ->where('isSuperAdmin', true)
            ->where('platformRootFolderId', 'root-ozolab')
        );
});

test('platform root folder falls back to env when no setting is stored', function () {
    config()->set('services.google.drive_root_folder_id', 'root-dari-env');

    expect(GoogleDriveService::platformRootFolderId())->toBe('root-dari-env');

    GoogleDriveService::setPlatformRootFolderId('root-dari-setting');

    expect(GoogleDriveService::platformRootFolderId())->toBe('root-dari-setting');
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
