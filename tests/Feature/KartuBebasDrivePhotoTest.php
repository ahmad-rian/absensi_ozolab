<?php

use App\Jobs\GenerateDynamicCardJob;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\SchoolDriveConfig;
use App\Services\DynamicCardGenerator;
use App\Services\GoogleDriveService;
use App\Services\Student\StudentDrivePhotoLocator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = createSuperAdminUser();
    SchoolDriveConfig::create(['school_id' => $this->user->school_id, 'is_active' => true, 'root_folder_id' => 'root-haji']);
    $this->drive = Mockery::mock(GoogleDriveService::class);
    $locator = Mockery::mock(StudentDrivePhotoLocator::class);
    $locator->shouldReceive('driveFor')->andReturn($this->drive);
    app()->instance(StudentDrivePhotoLocator::class, $locator);
    $this->actingAs($this->user);
});

test('card photo picker browses the active school folder', function () {
    $this->drive->shouldReceive('ensureSchoolRoot')->once()->andReturn('root-haji');
    $this->drive->shouldReceive('isInsideSchoolRoot')->with('root-haji')->once()->andReturn(true);
    $this->drive->shouldReceive('folderDetail')->with('root-haji')->andReturn(['id' => 'root-haji', 'name' => 'Haji', 'parent' => 'platform']);
    $this->drive->shouldReceive('subfolders')->with('root-haji')->andReturn([]);
    $this->drive->shouldReceive('imagesForPicker')->with('root-haji')->andReturn([['id' => 'foto', 'name' => 'Peserta.jpg', 'thumb' => true]]);

    $this->getJson(route('kartu-bebas.drive.browse'))->assertOk()
        ->assertJsonPath('folder.induk', null)->assertJsonPath('gambar.0.id', 'foto');
});

test('card picker cannot read files or folders outside the active school', function (string $action) {
    $this->drive->shouldReceive('ensureSchoolRoot')->andReturn('root-haji');
    $this->drive->shouldReceive('isInsideSchoolRoot')->with('outside')->once()->andReturn(false);
    $this->drive->shouldNotReceive('downloadFile');
    $this->drive->shouldNotReceive('thumbnailBytes');
    $this->drive->shouldNotReceive('imagesForPicker');

    $parameters = $action === 'browse' ? ['folder' => 'outside'] : ['fileId' => 'outside'];
    $this->getJson(route('kartu-bebas.drive.'.$action, $parameters))->assertForbidden();
})->with(['browse', 'thumbnail', 'image']);

test('non super admin cannot use card drive endpoints', function () {
    $this->actingAs(createAdminUser());
    $this->getJson(route('kartu-bebas.drive.browse'))->assertForbidden();
    $this->getJson(route('kartu-bebas.drive.image', 'foto'))->assertForbidden();
});

test('disabled drive returns setup instructions without contacting google', function () {
    $this->user->school->driveConfig->update(['is_active' => false]);
    $this->drive->shouldNotReceive('ensureSchoolRoot');

    $this->getJson(route('kartu-bebas.drive.browse'))->assertOk()->assertJsonPath('tersedia', false);
});

test('drive selection returns the actual photo for cropping', function () {
    $image = UploadedFile::fake()->image('peserta.jpg', 60, 80);
    $bytes = file_get_contents($image->getRealPath());
    $this->drive->shouldReceive('isInsideSchoolRoot')->with('foto')->andReturn(true);
    $this->drive->shouldReceive('fileById')->with('foto')->andReturn(['id' => 'foto', 'name' => 'peserta.jpg', 'size' => strlen($bytes)]);
    $this->drive->shouldReceive('downloadFile')->once()->andReturnUsing(function (string $id, string $path) use ($bytes): void {
        file_put_contents($path, $bytes);
    });

    $response = $this->get(route('kartu-bebas.drive.image', 'foto'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $path = $response->baseResponse->getFile()->getPathname();
    try {
        expect(file_get_contents($path))->toBe($bytes);
    } finally {
        unlink($path);
    }
});

test('drive rejects non image content before it reaches the cropper', function () {
    $this->drive->shouldReceive('isInsideSchoolRoot')->andReturn(true);
    $this->drive->shouldReceive('fileById')->andReturn(['id' => 'fake', 'name' => 'fake.jpg']);
    $this->drive->shouldReceive('downloadFile')->andReturnUsing(function (string $id, string $path): void {
        file_put_contents($path, '<html>not an image</html>');
    });

    $this->getJson(route('kartu-bebas.drive.image', 'fake'))->assertUnprocessable();
});

test('manual photo upload is offered during generation and included in card output', function (bool $standalone) {
    Storage::fake('public');
    Queue::fake([GenerateDynamicCardJob::class]);
    $form = CardForm::create([
        'created_by' => $this->user->id, 'token' => str()->random(10), 'name' => 'Haji', 'is_active' => true,
        'orientation' => 'portrait', 'fields' => $standalone
            ? [['key' => 'nama', 'label' => 'Nama', 'type' => 'text']]
            : [['key' => 'nama', 'label' => 'Nama', 'type' => 'text'], ['key' => '__photo', 'label' => 'Foto', 'type' => 'photo', 'required' => true]],
        'layout_config' => ['elements' => ['__photo' => [
            'type' => 'photo', 'source' => '__photo', 'standalone' => $standalone, 'x' => 4, 'y' => 5, 'w' => 24, 'h' => 32, 'enabled' => true,
        ]]],
    ]);
    $this->get(route('kartu-bebas.generate.create', $form))->assertInertia(fn (Assert $page) => $page
        ->where('layout.fields.1.type', 'photo')->where('layout.fields.1.key', '__photo'));

    $this->get('/f/'.$form->token)->assertInertia(fn (Assert $page) => $page
        ->where('form.fields.1.type', 'photo'));

    $response = $this->post(route('kartu-bebas.generate.store', $form), [
        'data' => ['nama' => 'Peserta', '__photo' => UploadedFile::fake()->image('foto-komputer.jpg', 60, 80)],
        'manual_crop' => ['sx' => 0, 'sy' => 0, 'sw' => 1, 'sh' => 1],
    ])->assertOk();
    $submission = CardFormSubmission::findOrFail($response->json('submission.id'));
    expect($submission->photo_path)->not->toBeNull();
    expect($submission->data)->toBe(['nama' => 'Peserta']);
    Storage::disk('public')->assertExists($submission->photo_path);
    Queue::assertPushed(GenerateDynamicCardJob::class, fn ($job) => $job->submissionId === $submission->id);
    expect(app(DynamicCardGenerator::class)->renderHtml($form, $submission))
        ->toContain('src="data:image/png;base64,')->toContain('class="el el-photo"');
})->with(['foto tambahan' => true, 'kolom foto format data' => false]);
