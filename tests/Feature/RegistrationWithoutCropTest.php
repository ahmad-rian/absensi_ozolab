<?php

use App\Jobs\RegisterStudentCardsJob;
use App\Models\School;
use App\Models\Student;
use App\Services\PhotoCropService;
use Illuminate\Support\Facades\Storage;

/*
 | Pendaftaran memakai foto Drive apa adanya.
 |
 | Klien meminta kotak croping DAN pemotongan otomatisnya dibuang: pendaftar
 | mengetik nama berkas, melihat fotonya, selesai. Yang dijaga di sini adalah
 | batas perubahannya — jalur kartu bebas (Public\CardFormController) masih
 | memotong ke rasio pas foto, dan tidak boleh ikut mati.
 */

beforeEach(function () {
    @ini_set('memory_limit', '512M');
    Storage::fake('public');
});

function referencePhoto(): string
{
    return dirname(__DIR__).'/fixtures/photos/reference.jpg';
}

test('without cropping the photo keeps its own shape', function () {
    $src = referencePhoto();
    [$srcW, $srcH] = getimagesize($src);

    (new PhotoCropService)->cropAndStore($src, 'photos/test/utuh.png', 9, null, crop: false);

    [$w, $h] = getimagesize(Storage::disk('public')->path('photos/test/utuh.png'));

    // Rasio aslinya bertahan, bukan dipaksa ke 16:21 pas foto.
    expect($w / $h)->toBeBetween(($srcW / $srcH) * 0.99, ($srcW / $srcH) * 1.01);
});

test('cropping is still the default, so the free-card path is untouched', function () {
    // Mutasi yang harus memerahkan ini: jadikan `crop: false` sebagai default.
    (new PhotoCropService)->cropAndStore(referencePhoto(), 'photos/test/dipotong.png');

    [$w, $h] = getimagesize(Storage::disk('public')->path('photos/test/dipotong.png'));

    expect($w / $h)->toBeBetween(16 / 21 * 0.99, 16 / 21 * 1.01);
});

test('the registration job itself stores the photo uncropped', function () {
    /*
     | Mutasi yang harus memerahkan ini: kembalikan `crop: false` di
     | RegisterStudentCardsJob menjadi crop bawaan. Test servis di atas hanya
     | membuktikan flag-nya bekerja — ini yang membuktikan jalur pendaftaran
     | benar-benar memakainya.
     */
    Storage::fake('local');

    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id]);

    $temp = 'registration-previews/uji.jpg';
    Storage::disk('local')->put($temp, file_get_contents(referencePhoto()));

    (new RegisterStudentCardsJob(
        studentId: $student->id,
        photoTemp: $temp,
        generateCards: false,
    ))->handle();

    $stored = $student->fresh()->photo_path;
    expect($stored)->not->toBeNull();

    [$srcW, $srcH] = getimagesize(referencePhoto());
    [$w, $h] = getimagesize(Storage::disk('public')->path($stored));

    expect($w / $h)->toBeBetween(($srcW / $srcH) * 0.99, ($srcW / $srcH) * 1.01);
});

test('a manual rect is ignored when cropping is off', function () {
    // Klien lama yang masih mengirim `manual_crop` tidak boleh diam-diam
    // menghidupkan kembali pemotongan lewat pintu belakang.
    $src = referencePhoto();
    [$srcW, $srcH] = getimagesize($src);

    (new PhotoCropService)->cropAndStore(
        $src,
        'photos/test/abaikan.png',
        9,
        ['sx' => 0.1, 'sy' => 0.1, 'sw' => 0.3, 'sh' => 0.3],
        crop: false,
    );

    [$w, $h] = getimagesize(Storage::disk('public')->path('photos/test/abaikan.png'));

    expect($w / $h)->toBeBetween(($srcW / $srcH) * 0.99, ($srcW / $srcH) * 1.01);
});
