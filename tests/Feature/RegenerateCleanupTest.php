<?php

use App\Jobs\RegisterStudentCardsJob;
use App\Models\CardGenerationLog;
use App\Models\School;
use App\Models\Student;
use App\Support\StudentOutputCleanup;
use Illuminate\Support\Facades\Storage;

/**
 * Laporan lapangan: "generate ulang tidak menimpa, berkas lama tetap ada".
 *
 * Sisi Drive sudah dibereskan di c16d197 lewat replaceStudentOutput(). Yang tidak
 * pernah ikut dibereskan adalah disk lokal, dan di sanalah penumpukannya terjadi.
 */
function pngKecil(): string
{
    $img = imagecreatetruecolor(120, 160);
    imagefill($img, 0, 0, imagecolorallocate($img, 200, 200, 200));

    ob_start();
    imagepng($img);
    $bytes = ob_get_clean();
    imagedestroy($img);

    return $bytes;
}

test('generate ulang foto membuang berkas lama, bukan menaruhnya di sebelahnya', function () {
    Storage::fake('public');
    Storage::fake('local');

    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id]);

    $fotoLama = 'photos/students/'.$school->id.'/'.$student->id.'-lamalamalamalama.png';
    Storage::disk('public')->put($fotoLama, pngKecil());
    $student->update(['photo_path' => $fotoLama]);

    Storage::disk('local')->put('tmp/sumber.png', pngKecil());

    (new RegisterStudentCardsJob(
        studentId: $student->id,
        photoTemp: 'tmp/sumber.png',
        generateCards: false,
        outputs: [RegisterStudentCardsJob::OUTPUT_PHOTO],
    ))->handle();

    $student->refresh();

    expect($student->photo_path)->not->toBe($fotoLama);

    Storage::disk('public')->assertExists($student->photo_path);
    // Inti perbaikannya: nama berkas memuat 16 karakter acak, jadi keluaran baru
    // TIDAK PERNAH menimpa yang lama — ia harus dibuang eksplisit.
    Storage::disk('public')->assertMissing($fotoLama);
});

test('kartu lama dari nama yang sudah berubah ikut dibuang', function () {
    Storage::fake('public');

    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id, 'full_name' => 'BUDI SANTOSA']);

    $kartuLama = 'cards/'.$school->id.'/budi-santosa-17357-osis.png';
    $kartuBaru = 'cards/'.$school->id.'/budi-santoso-17357-osis.png';

    Storage::disk('public')->put($kartuLama, pngKecil());
    Storage::disk('public')->put($kartuBaru, pngKecil());

    CardGenerationLog::create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'type' => 'card',
        'status' => 'completed',
        'file_path' => $kartuLama,
        'generated_by' => 'admin',
    ]);

    $logBaru = CardGenerationLog::create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'type' => 'card',
        'status' => 'completed',
        'file_path' => $kartuBaru,
        'generated_by' => 'admin',
    ]);

    StudentOutputCleanup::buangKeluaranLokalLama($logBaru, $kartuBaru);

    Storage::disk('public')->assertMissing($kartuLama);
    Storage::disk('public')->assertExists($kartuBaru);
});

test('keluaran siswa lain tidak ikut terbuang', function () {
    Storage::fake('public');

    $school = School::factory()->create();
    $budi = Student::factory()->create(['school_id' => $school->id]);
    $tono = Student::factory()->create(['school_id' => $school->id]);

    $kartuTono = 'cards/'.$school->id.'/tono-11111-osis.png';
    Storage::disk('public')->put($kartuTono, pngKecil());

    CardGenerationLog::create([
        'school_id' => $school->id,
        'student_id' => $tono->id,
        'type' => 'card',
        'status' => 'completed',
        'file_path' => $kartuTono,
        'generated_by' => 'admin',
    ]);

    $logBudi = CardGenerationLog::create([
        'school_id' => $school->id,
        'student_id' => $budi->id,
        'type' => 'card',
        'status' => 'completed',
        'file_path' => 'cards/'.$school->id.'/budi-22222-osis.png',
        'generated_by' => 'admin',
    ]);

    StudentOutputCleanup::buangKeluaranLokalLama($logBudi, $logBudi->file_path);

    Storage::disk('public')->assertExists($kartuTono);
});

/**
 * Log bertipe `photo` menyimpan photo_path. Siswa yang fotonya tidak pernah
 * berganti akan memunculkan jalur yang sama di riwayatnya, dan membuangnya
 * berarti menghapus foto yang sedang terpasang.
 */
test('foto yang sedang terpasang tidak pernah ikut terbuang', function () {
    Storage::fake('public');

    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id]);

    $foto = 'photos/students/'.$school->id.'/'.$student->id.'-aaaabbbbccccdddd.png';
    Storage::disk('public')->put($foto, pngKecil());
    $student->update(['photo_path' => $foto]);

    CardGenerationLog::create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'type' => 'photo',
        'status' => 'completed',
        'file_path' => $foto,
        'generated_by' => 'admin',
    ]);

    $logBaru = CardGenerationLog::create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'type' => 'photo',
        'status' => 'processing',
        'file_path' => $foto,
        'generated_by' => 'admin',
    ]);

    StudentOutputCleanup::buangKeluaranLokalLama($logBaru->load('student'), 'photos/students/x/berbeda.png');

    Storage::disk('public')->assertExists($foto);
});
