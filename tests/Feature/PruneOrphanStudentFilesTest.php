<?php

use App\Models\CardGenerationLog;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\Storage;

/**
 * Penyapu berkas yatim. Disk penuh sudah pernah menjatuhkan server ini, jadi
 * perintah yang salah sasaran akan menjatuhkannya lagi dengan cara yang lebih
 * sulit dipulihkan — karena itu menghapus harus diminta eksplisit.
 */
test('tanpa --force tidak ada berkas yang hilang', function () {
    Storage::fake('public');
    Storage::disk('public')->put('cards/abc/yatim-osis.png', 'x');

    $this->artisan('student-files:prune', ['--minutes' => 0])
        ->expectsOutputToContain('yatim')
        ->assertSuccessful();

    Storage::disk('public')->assertExists('cards/abc/yatim-osis.png');
});

test('--force membuang yang yatim dan menyisakan yang masih ditunjuk', function () {
    Storage::fake('public');

    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id]);

    $fotoTerpakai = 'photos/students/'.$school->id.'/'.$student->id.'-aaaabbbbccccdddd.png';
    $kartuTerpakai = 'cards/'.$school->id.'/budi-17357-osis.png';
    $yatim = 'cards/'.$school->id.'/budi-lama-osis.png';

    Storage::disk('public')->put($fotoTerpakai, 'x');
    Storage::disk('public')->put($kartuTerpakai, 'x');
    Storage::disk('public')->put($yatim, 'x');

    $student->update(['photo_path' => $fotoTerpakai]);

    CardGenerationLog::create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'type' => 'card',
        'status' => 'completed',
        'file_path' => $kartuTerpakai,
        'generated_by' => 'admin',
    ]);

    $this->artisan('student-files:prune', ['--force' => true, '--minutes' => 0])->assertSuccessful();

    Storage::disk('public')->assertMissing($yatim);
    Storage::disk('public')->assertExists($fotoTerpakai);
    Storage::disk('public')->assertExists($kartuTerpakai);
});

/**
 * Job render menulis berkasnya sebelum baris log-nya diperbarui. Tanpa jeda umur,
 * penyapu yang jalan bersamaan antrean memakan hasil kerja mereka.
 */
test('berkas yang baru ditulis dilewati', function () {
    Storage::fake('public');
    Storage::disk('public')->put('cards/abc/baru-osis.png', 'x');

    $this->artisan('student-files:prune', ['--force' => true, '--minutes' => 60])->assertSuccessful();

    Storage::disk('public')->assertExists('cards/abc/baru-osis.png');
});

/**
 * Siswa yang di-soft-delete masih memegang berkasnya sampai dibuang lewat jalur
 * penghapusan siswa. Perintah ini bukan jalur itu.
 */
test('foto siswa yang di-soft-delete tidak dianggap yatim', function () {
    Storage::fake('public');

    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id]);

    $foto = 'photos/students/'.$school->id.'/'.$student->id.'-aaaabbbbccccdddd.png';
    Storage::disk('public')->put($foto, 'x');
    $student->update(['photo_path' => $foto]);
    $student->delete();

    $this->artisan('student-files:prune', ['--force' => true, '--minutes' => 0])->assertSuccessful();

    Storage::disk('public')->assertExists($foto);
});
