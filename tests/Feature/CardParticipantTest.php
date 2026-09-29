<?php

use App\Jobs\GenerateDynamicCardJob;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Services\CardParticipantService;
use App\Services\DynamicCardFiles;
use App\Services\DynamicCardGenerator;
use App\Services\GoogleDriveService;
use App\Services\PhotoCropService;
use App\Support\StudentPhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
    $this->user = createSuperAdminUser();
    $this->actingAs($this->user);
    $this->layout = CardForm::create([
        'created_by' => $this->user->id, 'name' => 'Kartu Haji', 'token' => 'haji-test', 'orientation' => 'portrait', 'is_active' => true,
        'fields' => [
            ['key' => 'nama', 'label' => 'Nama', 'type' => 'text', 'required' => true],
            ['key' => 'foto', 'label' => 'Foto', 'type' => 'photo', 'required' => true],
        ],
        'layout_config' => ['elements' => []],
    ]);
});

function participantRecord(CardForm $layout, array $attributes = []): CardFormSubmission
{
    Storage::disk('public')->put('photo.png', 'old-photo');

    return $layout->submissions()->create(array_merge(['data' => ['nama' => 'Ahmad'], 'photo_path' => 'photo.png', 'status' => 'draft'], $attributes));
}

test('admin creates participant with preserved original and cropped photo', function () {
    $this->post(route('kartu-bebas.peserta.store'), [
        'layout_id' => $this->layout->id, 'data' => ['nama' => 'Ahmad', 'foto' => UploadedFile::fake()->image('foto.jpg', 500, 600)],
        'manual_crop' => ['sx' => 0, 'sy' => 0, 'sw' => 0.75, 'sh' => 1],
    ])->assertRedirect();
    $record = CardFormSubmission::sole();
    expect($record->status)->toBe('draft')->and($record->data)->toBe(['nama' => 'Ahmad'])
        ->and($record->manual_crop['sw'])->toEqual(0.75);
    Storage::disk('public')->assertExists([$record->photo_path, $record->original_photo_path]);
    Queue::assertNothingPushed();
});

test('text edits retain photos and previous output but mark the card draft', function () {
    $record = participantRecord($this->layout, ['status' => 'completed', 'file_path' => 'card.png', 'drive_file_id' => 'old-drive']);
    Storage::disk('public')->put('card.png', 'old-card');
    $this->put(route('kartu-bebas.peserta.update', $record), ['data' => ['nama' => 'Nama Baru']])->assertRedirect();
    expect($record->fresh()->status)->toBe('draft')->and($record->fresh()->photo_path)->toBe('photo.png')->and($record->fresh()->file_path)->toBe('card.png');
    $this->get(route('kartu-bebas.peserta.download', $record))->assertDownload('kartu-'.$record->id.'.png');
    $this->get(route('kartu-bebas.peserta.show', $record))->assertOk()->assertInertia(fn (Assert $page) => $page->component('kartu-bebas/peserta/show')->where('participant.data.nama', 'Nama Baru')->where('participant.status', 'draft'));
});

test('existing legacy photo can be recropped without uploading another image', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 500, 600);
    $path = $file->store('legacy', 'public');
    $record = participantRecord($this->layout, ['photo_path' => $path]);
    $this->put(route('kartu-bebas.peserta.update', $record), ['data' => ['nama' => 'Ahmad'], 'manual_crop' => ['sx' => 0, 'sy' => 0, 'sw' => 0.75, 'sh' => 1]])->assertRedirect();
    $record->refresh();
    expect($record->original_photo_path)->toBe($path)->and($record->photo_path)->not->toBe($path);
    Storage::disk('public')->assertExists([$path, $record->photo_path]);
});

test('invalid crop is rejected without changing participant or photo', function (array $crop) {
    $record = participantRecord($this->layout);
    $this->putJson(route('kartu-bebas.peserta.update', $record), ['data' => ['nama' => 'Changed'], 'manual_crop' => $crop])->assertUnprocessable();
    expect($record->fresh()->data['nama'])->toBe('Ahmad');
    Storage::disk('public')->assertExists('photo.png');
})->with([
    [['sx' => 0, 'sy' => 0, 'sw' => 0, 'sh' => 1]],
    [['sx' => 0.5, 'sy' => 0, 'sw' => 0.8, 'sh' => 1]],
    [['sx' => 0, 'sy' => 0, 'sw' => 1]],
]);

test('generation enqueues once and locks edits deletion and duplicate generation', function () {
    $record = participantRecord($this->layout);
    $this->post(route('kartu-bebas.peserta.generate', $record))->assertRedirect();
    expect($record->fresh()->status)->toBe('processing')->and($record->fresh()->generation_token)->not->toBeNull();
    Queue::assertPushed(GenerateDynamicCardJob::class, 1);
    $this->postJson(route('kartu-bebas.peserta.generate', $record))->assertConflict();
    $this->putJson(route('kartu-bebas.peserta.update', $record), ['data' => ['nama' => 'Changed']])->assertConflict();
    $this->deleteJson(route('kartu-bebas.peserta.destroy', $record))->assertConflict();
});

test('generation validates fields added to layout after participant was saved', function () {
    $record = participantRecord($this->layout);
    $this->layout->update(['fields' => array_merge($this->layout->fields, [['key' => 'porsi', 'label' => 'Porsi', 'type' => 'text', 'required' => true]])]);
    $this->postJson(route('kartu-bebas.peserta.generate', $record))->assertUnprocessable()->assertJsonValidationErrors('data.porsi');
    expect($record->fresh()->status)->toBe('draft');
});

test('retryable render failures preserve output and final failure sets actionable status', function () {
    $record = participantRecord($this->layout, ['status' => 'processing', 'file_path' => 'old.png', 'generation_token' => 'token']);
    Storage::disk('public')->put('old.png', 'old');
    $generator = Mockery::mock(DynamicCardGenerator::class);
    $generator->shouldReceive('generate')->once()->andThrow(new RuntimeException('Render failure'));
    $job = new GenerateDynamicCardJob($record->id, 'token');
    expect(fn () => $job->handle($generator))->toThrow(RuntimeException::class);
    expect($record->fresh()->status)->toBe('processing');
    $job->failed(new RuntimeException('final failure'));
    expect($record->fresh()->status)->toBe('failed')->and($record->fresh()->generation_error)->not->toBeNull()->and($record->fresh()->file_path)->toBe('old.png');
    Storage::disk('public')->assertExists('old.png');
});

test('successful replacement retains new local output and removes only old generated output', function () {
    $record = participantRecord($this->layout, ['status' => 'processing', 'file_path' => 'old.png', 'drive_file_id' => 'old-drive', 'generation_token' => 'token']);
    Storage::disk('public')->put('old.png', 'old');
    Storage::disk('public')->put('new.png', 'new');
    $generator = Mockery::mock(DynamicCardGenerator::class);
    $generator->shouldReceive('generate')->once()->andReturn(['path' => 'new.png']);
    $files = Mockery::mock(DynamicCardFiles::class);
    $files->shouldReceive('publish')->once()->andReturn(['drive_file_id' => 'new-drive', 'drive_url' => 'https://drive.test/new']);
    $files->shouldReceive('deleteDrive')->withArgs(fn ($form, $id) => $form->is($this->layout) && $id === 'old-drive')->once();
    $this->app->instance(DynamicCardFiles::class, $files);
    $job = new GenerateDynamicCardJob($record->id, 'token');
    $job->handle($generator);
    $job->handle($generator);
    expect($record->fresh()->status)->toBe('completed')->and($record->fresh()->file_path)->toBe('new.png')->and($record->fresh()->drive_file_id)->toBe('new-drive');
    Storage::disk('public')->assertExists(['new.png', 'photo.png']);
    Storage::disk('public')->assertMissing('old.png');
});

test('stale job cannot render or mark a newer generation failed', function () {
    $record = participantRecord($this->layout, ['status' => 'processing', 'generation_token' => 'new']);
    $generator = Mockery::mock(DynamicCardGenerator::class);
    $generator->shouldNotReceive('generate');
    $job = new GenerateDynamicCardJob($record->id, 'old');
    $job->handle($generator);
    $job->failed(new RuntimeException);
    expect($record->fresh()->status)->toBe('processing');
});

test('participant and history lists filter and paginate all statuses', function () {
    foreach (range(1, 23) as $number) {
        participantRecord($this->layout, ['data' => ['nama' => 'Peserta '.$number], 'status' => 'failed']);
    }
    participantRecord($this->layout, ['status' => 'completed']);
    $this->get(route('kartu-bebas.peserta.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('participants.data', 20)->where('participants.total', 24));
    $this->get(route('kartu-bebas.riwayat', ['q' => 'Peserta', 'status' => 'failed', 'layout' => $this->layout->id]))
        ->assertInertia(fn (Assert $page) => $page->where('history', true)->where('participants.total', 23)->has('participants.data', 20));
});

test('participant management is restricted to the workspace super admin', function () {
    $record = participantRecord($this->layout);
    $this->actingAs(createAdminUser());
    foreach (['index' => [], 'show' => $record, 'edit' => $record, 'download' => $record, 'preview' => $record] as $route => $parameters) {
        $this->get(route('kartu-bebas.peserta.'.$route, $parameters))->assertForbidden();
    }
    $this->post(route('kartu-bebas.peserta.generate', $record))->assertForbidden();
    $this->put(route('kartu-bebas.peserta.update', $record))->assertForbidden();
    $this->delete(route('kartu-bebas.peserta.destroy', $record))->assertForbidden();
});

test('legacy Drive-only result is proxied for preview and download', function () {
    $record = participantRecord($this->layout, ['status' => 'completed', 'drive_file_id' => 'legacy-drive']);
    $drive = Mockery::mock(GoogleDriveService::class);
    $temporary = [];
    $drive->shouldReceive('downloadFile')->twice()->withArgs(function ($id, $path) use (&$temporary) {
        $temporary[] = $path;
        file_put_contents($path, 'png-result');

        return $id === 'legacy-drive';
    });
    $files = Mockery::mock(DynamicCardFiles::class)->makePartial();
    $files->shouldReceive('drive')->twice()->andReturn($drive);
    $this->app->instance(DynamicCardFiles::class, $files);
    try {
        $this->get(route('kartu-bebas.peserta.preview', $record))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('kartu-bebas.peserta.download', $record))->assertDownload('kartu-'.$record->id.'.png');
    } finally {
        foreach ($temporary as $path) {
            @unlink($path);
        }
    }
});

test('Drive failure still completes locally and clears obsolete Drive links', function () {
    $record = participantRecord($this->layout, ['status' => 'processing', 'file_path' => 'old.png', 'drive_url' => 'https://drive.test/old', 'generation_token' => 'token']);
    Storage::disk('public')->put('old.png', 'old');
    Storage::disk('public')->put('new.png', 'new');
    $drive = Mockery::mock(GoogleDriveService::class);
    $drive->shouldReceive('ensureSubfolders')->once()->andThrow(new RuntimeException('Drive offline'));
    $files = Mockery::mock(DynamicCardFiles::class)->makePartial();
    $files->shouldReceive('drive')->once()->andReturn($drive);
    $this->app->instance(DynamicCardFiles::class, $files);
    $generator = Mockery::mock(DynamicCardGenerator::class);
    $generator->shouldReceive('generate')->once()->andReturn(['path' => 'new.png']);
    (new GenerateDynamicCardJob($record->id, 'token'))->handle($generator);
    expect($record->fresh()->status)->toBe('completed')->and($record->fresh()->drive_url)->toBeNull()->and($record->fresh()->file_path)->toBe('new.png');
    Storage::disk('public')->assertExists('new.png');
});

test('crop failure rolls back edits and cleans newly uploaded files', function () {
    $record = participantRecord($this->layout, ['status' => 'completed', 'original_photo_path' => 'original.jpg']);
    Storage::disk('public')->put('original.jpg', 'original');
    $before = Storage::disk('public')->allFiles();
    $cropper = Mockery::mock(PhotoCropService::class);
    $cropper->shouldReceive('cropAndStore')->once()->andThrow(new RuntimeException('Unable to crop'));
    $this->app->instance(PhotoCropService::class, $cropper);
    $this->withoutExceptionHandling();
    try {
        $this->put(route('kartu-bebas.peserta.update', $record), ['data' => ['nama' => 'Changed', 'foto' => UploadedFile::fake()->image('new.jpg')]]);
        $this->fail('Expected crop failure');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Unable to crop');
    }
    expect($record->fresh()->data['nama'])->toBe('Ahmad')->and($record->fresh()->status)->toBe('completed')
        ->and(Storage::disk('public')->allFiles())->toBe($before);
});

test('replacement photo and deletion clean original cropped photo and thumbnails', function () {
    $this->post(route('kartu-bebas.peserta.store'), ['layout_id' => $this->layout->id, 'data' => ['nama' => 'Ahmad', 'foto' => UploadedFile::fake()->image('first.jpg')]])->assertRedirect();
    $record = CardFormSubmission::sole();
    $oldPaths = [$record->photo_path, $record->original_photo_path, StudentPhotoStorage::thumbPath($record->photo_path)];
    $this->put(route('kartu-bebas.peserta.update', $record), ['data' => ['nama' => 'Ahmad', 'foto' => UploadedFile::fake()->image('second.jpg')]])->assertRedirect();
    Storage::disk('public')->assertMissing($oldPaths);
    $record->refresh();
    Storage::disk('public')->assertExists([$record->photo_path, $record->original_photo_path]);
    $this->delete(route('kartu-bebas.peserta.destroy', $record))->assertRedirect(route('kartu-bebas.peserta.index'));
    expect(CardFormSubmission::count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('generation replaced during render cannot overwrite a newer request', function () {
    $record = participantRecord($this->layout, ['status' => 'processing', 'file_path' => 'previous.png', 'generation_token' => 'old']);
    Storage::disk('public')->put('previous.png', 'previous');
    Storage::disk('public')->put('stale.png', 'stale');
    $generator = Mockery::mock(DynamicCardGenerator::class);
    $generator->shouldReceive('generate')->once()->andReturnUsing(function () use ($record) {
        $record->update(['generation_token' => 'new']);

        return ['path' => 'stale.png'];
    });
    (new GenerateDynamicCardJob($record->id, 'old'))->handle($generator);
    expect($record->fresh()->file_path)->toBe('previous.png')->and($record->fresh()->status)->toBe('processing');
    Storage::disk('public')->assertExists('previous.png');
    Storage::disk('public')->assertMissing('stale.png');
});

test('generation timeout and lock expire before the queue retries the reservation', function () {
    config(['queue.default' => 'database', 'queue.connections.database.retry_after' => 90]);
    $job = new GenerateDynamicCardJob('participant');
    expect($job->timeout)->toBe(80)->and($job->middleware()[0]->expiresAfter)->toBe(85);
});

test('queue dispatch failure does not leave a participant stuck processing', function () {
    $record = participantRecord($this->layout);
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue offline'));
    expect(fn () => app(CardParticipantService::class)->generate($record))->toThrow(RuntimeException::class, 'Queue offline');
    expect($record->fresh()->status)->not->toBe('processing');
    Storage::disk('public')->assertExists('photo.png');
});
