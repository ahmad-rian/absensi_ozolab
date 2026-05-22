<?php

use App\Models\School;
use App\Models\SchoolFrame;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createSchoolUser(): User
{
    $school = School::factory()->create();

    return User::factory()->create(['school_id' => $school->id]);
}

test('guests cannot access frames page', function () {
    $this->get(route('admin.frames'))->assertRedirect(route('login'));
});

test('authenticated users can view frames page', function () {
    $user = createSchoolUser();

    $this->actingAs($user)->get(route('admin.frames'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/frames/index')->has('frames'));
});

test('frames can be uploaded', function () {
    Storage::fake('public');
    $user = createSchoolUser();

    $response = $this->actingAs($user)->post(route('admin.frames.store'), [
        'name' => 'Test Frame',
        'category' => 'card',
        'image' => UploadedFile::fake()->image('frame.png', 638, 1011),
    ]);

    $response->assertRedirect(route('admin.frames'));
    $this->assertDatabaseHas('school_frames', [
        'school_id' => $user->school_id,
        'name' => 'Test Frame',
        'category' => 'card',
    ]);
});

test('frames can be deleted', function () {
    Storage::fake('public');
    $user = createSchoolUser();

    $frame = SchoolFrame::create([
        'school_id' => $user->school_id,
        'name' => 'Delete Me',
        'image_path' => 'frames/test.webp',
        'category' => 'card',
    ]);

    $this->actingAs($user)->delete(route('admin.frames.destroy', $frame))
        ->assertRedirect(route('admin.frames'));

    $this->assertDatabaseMissing('school_frames', ['id' => $frame->id]);
});
