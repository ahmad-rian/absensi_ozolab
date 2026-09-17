<?php

use App\Jobs\GenerateStudentCardJob;
use App\Models\CardGenerationLog;
use App\Models\School;
use App\Models\SchoolCardLayout;
use App\Models\SchoolDriveConfig;
use App\Models\Student;
use App\Services\CardGeneratorService;
use App\Services\GoogleDriveService;
use App\Services\PhotoSheetGeneratorService;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->school = School::factory()->create();
    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'photo_path' => 'photos/student.png',
    ]);
    $this->sheetLog = CardGenerationLog::create([
        'school_id' => $this->school->id,
        'student_id' => $this->student->id,
        'type' => 'photo_sheet',
        'status' => 'processing',
        'generated_by' => 'admin',
    ]);
});

test('job membuat lembar 4R tanpa layout dan membersihkan hasil lama', function () {
    Storage::disk('public')->put('sheets/old.png', 'old');
    Storage::disk('public')->put('sheets/new.png', 'new');
    CardGenerationLog::create([
        'school_id' => $this->school->id,
        'student_id' => $this->student->id,
        'type' => 'photo_sheet',
        'status' => 'completed',
        'file_path' => 'sheets/old.png',
        'generated_by' => 'admin',
    ]);

    $this->mock(PhotoSheetGeneratorService::class)
        ->shouldReceive('generate')->once()
        ->withArgs(fn (Student $student, string $template, string $caption) => $student->is($this->student) && $template === '4r_3x4' && $caption === '')
        ->andReturn(['path' => 'sheets/new.png']);

    (new GenerateStudentCardJob($this->sheetLog->id))->handle(app(CardGeneratorService::class));

    expect($this->sheetLog->fresh())->status->toBe('completed')->file_path->toBe('sheets/new.png');
    Storage::disk('public')->assertExists('sheets/new.png');
    Storage::disk('public')->assertMissing('sheets/old.png');
});

test('gagal render 4R dicatat tanpa menghalangi render kartu', function () {
    $this->mock(PhotoSheetGeneratorService::class)
        ->shouldReceive('generate')->once()->andThrow(new RuntimeException('Render 4R gagal'));

    $layout = SchoolCardLayout::create([
        'school_id' => $this->school->id,
        'name' => 'OSIS',
        'type' => 'osis',
        'is_active' => true,
        'layout_config' => [],
    ]);
    $cardLog = CardGenerationLog::create([
        'school_id' => $this->school->id,
        'student_id' => $this->student->id,
        'school_card_layout_id' => $layout->id,
        'type' => 'card',
        'status' => 'processing',
        'generated_by' => 'admin',
    ]);
    $service = Mockery::mock(CardGeneratorService::class)->makePartial();
    $service->shouldReceive('generateCard')->once()
        ->withArgs(fn (Student $student, SchoolCardLayout $cardLayout) => $student->is($this->student) && $cardLayout->is($layout))
        ->andReturn(['path' => 'cards/front.png', 'html' => '']);

    (new GenerateStudentCardJob($this->sheetLog->id))->handle($service);
    (new GenerateStudentCardJob($cardLog->id))->handle($service);

    expect($this->sheetLog->fresh())->status->toBe('failed')->error_message->toBe('Render 4R gagal');
    expect($cardLog->fresh())->status->toBe('completed')->file_path->toBe('cards/front.png');
});

test('kartu tetap membutuhkan layout', function () {
    $this->sheetLog->update(['type' => 'card']);

    $result = app(CardGeneratorService::class)->runLog($this->sheetLog);

    expect($result)->status->toBe('failed')->error_message->toBe('Siswa atau layout tidak ditemukan.');
});

test('lembar 4R diunggah ke folder Drive siswa', function () {
    $driveConfig = SchoolDriveConfig::create([
        'school_id' => $this->school->id,
        'is_active' => true,
        'cards_folder_id' => 'cards-folder',
        'service_account_json' => '{}',
    ]);
    Storage::disk('public')->put('sheets/new.png', 'image');
    $this->mock(PhotoSheetGeneratorService::class)
        ->shouldReceive('generate')->once()->andReturn(['path' => 'sheets/new.png']);

    $drive = Mockery::mock(GoogleDriveService::class);
    $this->app->bind(GoogleDriveService::class, function ($app, array $parameters) use ($drive, $driveConfig) {
        expect($parameters['config']->is($driveConfig))->toBeTrue();

        return $drive;
    });
    $drive->shouldReceive('ensureSubfolders')->once();
    $drive->shouldReceive('resolveStudentFolder')->once()
        ->withArgs(fn (Student $student) => $student->is($this->student))->andReturn('student-folder');
    $drive->shouldReceive('replaceStudentOutput')->once()
        ->withArgs(fn (string $path, Student $student, string $folder, string $name, ?string $id, string $mime) => $path === Storage::disk('public')->path('sheets/new.png')
            && $student->is($this->student) && $folder === 'student-folder'
            && $name === 'new.png' && $id === null && $mime === 'image/png')
        ->andReturn(new DriveFile(['id' => 'sheet-file']));
    $drive->shouldReceive('makePublic')->once()->with('sheet-file')->andReturn('https://example.test/sheet');

    $result = app(CardGeneratorService::class)->runLog($this->sheetLog);

    expect($result)->status->toBe('completed')
        ->drive_file_id->toBe('sheet-file')->drive_url->toBe('https://example.test/sheet');
});
