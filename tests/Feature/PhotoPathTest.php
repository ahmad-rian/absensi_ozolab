<?php

use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\Storage;

/**
 * H-7 — `sprintf('%d', <ULID>)` menghasilkan "1", sehingga seluruh sekolah
 * menulis ke `photos/students/1/` dengan nama berkas yang bisa ditebak dari
 * nama siswa.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->school = School::factory()->create();
    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'full_name' => 'Budi Santoso',
    ]);
});

test('the legacy path is what the migration command looks for', function () {
    $legacy = 'photos/students/1/1-budi-santoso.png';
    Storage::disk('public')->put($legacy, 'x');
    $this->student->forceFill(['photo_path' => $legacy])->saveQuietly();

    $this->artisan('photos:fix-paths', ['--dry-run' => true])->assertSuccessful();

    // Dry-run tidak menyentuh apa pun.
    expect(Storage::disk('public')->exists($legacy))->toBeTrue()
        ->and($this->student->fresh()->photo_path)->toBe($legacy);
});

test('the command moves the file under the school ULID and updates the column', function () {
    $legacy = 'photos/students/1/1-budi-santoso.png';
    Storage::disk('public')->put($legacy, 'x');
    $this->student->forceFill(['photo_path' => $legacy])->saveQuietly();

    $this->artisan('photos:fix-paths')->assertSuccessful();

    $moved = $this->student->fresh()->photo_path;

    expect($moved)->toStartWith("photos/students/{$this->school->id}/")
        ->and($moved)->toContain($this->student->id)
        // Nama siswa tidak lagi menentukan nama berkas.
        ->and($moved)->not->toContain('budi-santoso')
        ->and(Storage::disk('public')->exists($moved))->toBeTrue()
        ->and(Storage::disk('public')->exists($legacy))->toBeFalse();
});

test('running the command twice is safe', function () {
    $legacy = 'photos/students/1/1-budi-santoso.png';
    Storage::disk('public')->put($legacy, 'x');
    $this->student->forceFill(['photo_path' => $legacy])->saveQuietly();

    $this->artisan('photos:fix-paths')->assertSuccessful();
    $first = $this->student->fresh()->photo_path;

    $this->artisan('photos:fix-paths')->assertSuccessful();

    expect($this->student->fresh()->photo_path)->toBe($first)
        ->and(Storage::disk('public')->exists($first))->toBeTrue();
});

test('a photo already on the new path is left alone', function () {
    $current = "photos/students/{$this->school->id}/{$this->student->id}-abc123.png";
    Storage::disk('public')->put($current, 'x');
    $this->student->forceFill(['photo_path' => $current])->saveQuietly();

    $this->artisan('photos:fix-paths')->assertSuccessful();

    expect($this->student->fresh()->photo_path)->toBe($current);
});

test('two students with the same name in different schools no longer collide', function () {
    $otherSchool = School::factory()->create();
    $twin = Student::factory()->create([
        'school_id' => $otherSchool->id,
        'full_name' => 'Budi Santoso',
    ]);

    foreach ([$this->student, $twin] as $index => $student) {
        $legacy = "photos/students/1/1-budi-santoso-{$index}.png";
        Storage::disk('public')->put($legacy, 'x');
        $student->forceFill(['photo_path' => $legacy])->saveQuietly();
    }

    $this->artisan('photos:fix-paths')->assertSuccessful();

    expect($this->student->fresh()->photo_path)
        ->not->toBe($twin->fresh()->photo_path)
        ->and($this->student->fresh()->photo_path)->toStartWith("photos/students/{$this->school->id}/")
        ->and($twin->fresh()->photo_path)->toStartWith("photos/students/{$otherSchool->id}/");
});
