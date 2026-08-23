<?php

use App\Jobs\RegisterStudentCardsJob;
use App\Models\CardGenerationLog;
use App\Models\Student;
use Illuminate\Testing\TestResponse;

/**
 * Halaman siswa tidak punya penanda apa pun untuk pas foto: menekan "Ambil Ulang
 * Foto" tidak meninggalkan jejak sampai seseorang membuka foldernya di Drive.
 *
 * Lebih buruk lagi, unggahan yang gagal dulu tetap dicatat `completed` dengan
 * `drive_file_id` kosong — jadi penanda yang cuma membaca status akan menyebut
 * berhasil sesuatu yang tidak pernah sampai ke Drive.
 */
beforeEach(function () {
    $this->admin = createAdminUser();

    $this->student = Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'photo_path' => 'photos/students/x/y.png',
    ]);
});

function photoLog(Student $student, string $status, ?string $driveFileId): CardGenerationLog
{
    return CardGenerationLog::create([
        'school_id' => $student->school_id,
        'student_id' => $student->id,
        'type' => 'photo',
        'status' => $status,
        'file_path' => $student->photo_path,
        'drive_file_id' => $driveFileId,
        'drive_url' => $driveFileId ? 'https://drive.google.com/file/d/'.$driveFileId.'/view' : null,
        'generated_by' => 'admin',
    ]);
}

function bukaSiswa(): TestResponse
{
    return test()->actingAs(test()->admin)->get(route('admin.siswa.show', test()->student));
}

test('siswa yang fotonya belum pernah diproses tidak punya status sama sekali', function () {
    bukaSiswa()
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('photoStatus', null));
});

test('foto yang sudah sampai di Drive dilaporkan terunggah', function () {
    photoLog($this->student, 'completed', 'berkas-drive-1');

    bukaSiswa()->assertInertia(fn ($page) => $page
        ->where('photoStatus.status', 'completed')
        ->where('photoStatus.uploaded', true)
    );
});

/**
 * Inti perbaikannya. Baris lama berstatus `completed` tapi tanpa berkas Drive
 * harus tetap terbaca sebagai belum sampai ke Drive, bukan berhasil.
 */
test('status selesai tanpa berkas Drive tidak dianggap terunggah', function () {
    photoLog($this->student, 'completed', null);

    bukaSiswa()->assertInertia(fn ($page) => $page
        ->where('photoStatus.status', 'completed')
        ->where('photoStatus.uploaded', false)
    );
});

test('unggahan yang gagal dilaporkan gagal', function () {
    photoLog($this->student, 'failed', null);

    bukaSiswa()->assertInertia(fn ($page) => $page->where('photoStatus.status', 'failed'));
});

test('yang sedang berjalan sudah terlihat sebelum selesai', function () {
    photoLog($this->student, 'processing', null);

    bukaSiswa()->assertInertia(fn ($page) => $page->where('photoStatus.status', 'processing'));
});

/**
 * Satu siswa bisa punya banyak riwayat karena tombolnya boleh ditekan berkali-kali.
 * Yang ditampilkan harus yang terakhir, bukan yang pertama.
 */
test('yang ditampilkan riwayat paling akhir', function () {
    photoLog($this->student, 'failed', null)
        ->forceFill(['created_at' => now()->subDay()])->saveQuietly();
    photoLog($this->student, 'completed', 'berkas-drive-2');

    bukaSiswa()->assertInertia(fn ($page) => $page
        ->where('photoStatus.status', 'completed')
        ->where('photoStatus.uploaded', true)
    );
});

test('riwayat foto siswa lain tidak ikut terbaca', function () {
    $lain = Student::factory()->create(['school_id' => $this->admin->school_id]);
    photoLog($lain, 'completed', 'berkas-orang-lain');

    bukaSiswa()->assertInertia(fn ($page) => $page->where('photoStatus', null));
});

/**
 * Sekolah tanpa integrasi Drive bukan kegagalan: fotonya memang hanya perlu ada
 * di server. Kalau keadaan ini ikut ditandai merah, operator sekolah yang
 * Drive-nya sengaja dimatikan akan melihat peringatan seumur hidup.
 *
 * Cabang `failed`-nya sendiri butuh folder Drive yang nyata plus unggahan yang
 * gagal, jadi belum tertutup di sini — jalur unggah Drive memang belum bisa
 * dipalsukan tanpa memalsukan seluruh klien Google.
 */
test('sekolah tanpa Drive tetap dicatat selesai, bukan gagal', function () {
    RegisterStudentCardsJob::dispatchSync(
        studentId: $this->student->id,
        outputs: [RegisterStudentCardsJob::OUTPUT_PHOTO],
        generatedBy: 'admin',
    );

    $log = CardGenerationLog::where('student_id', $this->student->id)->where('type', 'photo')->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('completed')
        ->and($log->drive_file_id)->toBeNull();
});

/**
 * Lembar pas foto memakai tipe `photo_sheet`, bukan `photo`. Keduanya pernah
 * tercampur karena namanya mirip.
 */
test('lembar pas foto tidak dikira status foto', function () {
    CardGenerationLog::create([
        'school_id' => $this->student->school_id,
        'student_id' => $this->student->id,
        'type' => 'photo_sheet',
        'status' => 'completed',
        'generated_by' => 'admin',
    ]);

    bukaSiswa()->assertInertia(fn ($page) => $page->where('photoStatus', null));
});
