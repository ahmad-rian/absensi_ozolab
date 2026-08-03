<?php

use App\Jobs\GeneratePhotoSheetBatchJob;
use App\Models\Classroom;
use App\Models\PhotoSheetBatch;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->admin = createAdminUser();
    $this->school = School::find($this->admin->school_id);
    $this->classroom = Classroom::factory()->create(['school_id' => $this->school->id]);
});

function makeStudentWithPhoto(string $schoolId, ?string $classroomId = null, bool $withPhoto = true): Student
{
    return Student::factory()->create([
        'school_id' => $schoolId,
        'classroom_id' => $classroomId,
        'photo_path' => $withPhoto ? 'photos/students/'.$schoolId.'/'.fake()->uuid().'.png' : null,
    ]);
}

test('guests are redirected from the photo sheet studio', function () {
    $this->get(route('admin.photo-sheets'))->assertRedirect(route('login'));
});

test('admin can open the photo sheet studio', function () {
    makeStudentWithPhoto($this->school->id, $this->classroom->id);

    $this->actingAs($this->admin)->get(route('admin.photo-sheets'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/photo-sheets/index')
            ->has('students', 1)
            ->has('templates')
            ->has('batches')
        );
});

test('the module is closed when the kartu album feature is switched off', function () {
    $this->school->setSetting('feature_kartu_album', false);

    $this->actingAs($this->admin)->get(route('admin.photo-sheets'))->assertForbidden();
});

test('a user without the module permission is refused', function () {
    $user = User::factory()->create(['school_id' => $this->school->id]);
    $user->assignRole('GURU');

    $this->actingAs($user)->get(route('admin.photo-sheets'))->assertForbidden();
});

test('generating a batch stores the composition and queues the render', function () {
    Queue::fake();

    $ana = makeStudentWithPhoto($this->school->id, $this->classroom->id);
    $budi = makeStudentWithPhoto($this->school->id, $this->classroom->id);

    $this->actingAs($this->admin)->post(route('admin.photo-sheets.store'), [
        'template' => '4r_3x4',
        'items' => [
            ['student_id' => $ana->id, 'quantity' => 4],
            ['student_id' => $budi->id, 'quantity' => 2],
        ],
    ])->assertRedirect(route('admin.photo-sheets'));

    $batch = PhotoSheetBatch::withoutGlobalScopes()->firstOrFail();

    expect($batch->total_slots)->toBe(6);
    expect($batch->pages)->toBe(1);
    expect($batch->status)->toBe('processing');
    expect($batch->created_by)->toBe($this->admin->id);
    // Nama ikut disimpan supaya riwayat tetap terbaca walau siswanya dihapus.
    expect($batch->items[0]['name'])->toBe($ana->full_name);

    Queue::assertPushedOn(config('cards.queue'), GeneratePhotoSheetBatchJob::class);
});

// Halaman sempat mengirim payload kosong karena salah memakai useForm; yang
// terlihat operator cuma tombol yang tidak melakukan apa-apa. Pesan error di
// bawah ini yang sekarang ditampilkan di UI.
test('an empty order is refused with a readable message', function () {
    $this->actingAs($this->admin)->post(route('admin.photo-sheets.store'), [
        'template' => '4r_3x4',
    ])->assertSessionHasErrors(['items' => 'Pilih minimal satu siswa.']);

    expect(PhotoSheetBatch::withoutGlobalScopes()->count())->toBe(0);
});

test('students without a photo are refused', function () {
    $tanpaFoto = makeStudentWithPhoto($this->school->id, $this->classroom->id, withPhoto: false);

    $this->actingAs($this->admin)->post(route('admin.photo-sheets.store'), [
        'template' => '4r_3x4',
        'items' => [['student_id' => $tanpaFoto->id, 'quantity' => 2]],
    ])->assertSessionHasErrors('items');

    expect(PhotoSheetBatch::withoutGlobalScopes()->count())->toBe(0);
});

test('a student from another school cannot be slipped into the order', function () {
    $lain = School::factory()->create();
    $asing = makeStudentWithPhoto($lain->id);

    $this->actingAs($this->admin)->post(route('admin.photo-sheets.store'), [
        'template' => '4r_3x4',
        'items' => [['student_id' => $asing->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('items.0.student_id');

    expect(PhotoSheetBatch::withoutGlobalScopes()->count())->toBe(0);
});

test('an order larger than the page limit is refused', function () {
    $siswa = makeStudentWithPhoto($this->school->id, $this->classroom->id);

    // 4 siswa × 100 lembar pada kapasitas 8 = 50 halaman, di atas batas 30.
    $items = [];
    for ($i = 0; $i < 4; $i++) {
        $items[] = ['student_id' => $siswa->id, 'quantity' => 100];
    }

    $this->actingAs($this->admin)->post(route('admin.photo-sheets.store'), [
        'template' => '4r_3x4',
        'items' => $items,
    ])->assertSessionHasErrors('items');

    expect(PhotoSheetBatch::withoutGlobalScopes()->count())->toBe(0);
});

test('an unknown template is refused', function () {
    $siswa = makeStudentWithPhoto($this->school->id, $this->classroom->id);

    $this->actingAs($this->admin)->post(route('admin.photo-sheets.store'), [
        'template' => 'a4_karangan',
        'items' => [['student_id' => $siswa->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('template');
});

// Global scope `school` menutup lebih dulu, jadi batch sekolah lain hilang
// sebagai 404 — keberadaannya tidak bocor. Cek `school_id` di controller tetap
// dipertahankan sebagai lapis kedua kalau scope itu suatu saat dilepas.
test('the pdf of another school cannot be downloaded', function () {
    $lain = School::factory()->create();
    $batch = PhotoSheetBatch::create([
        'school_id' => $lain->id,
        'template' => '4r_3x4',
        'status' => 'completed',
        'items' => [],
        'total_slots' => 1,
        'pages' => 1,
        'file_path' => 'sheets/batches/x/y.pdf',
    ]);

    $this->actingAs($this->admin)->get(route('admin.photo-sheets.download', $batch))->assertNotFound();
});

test('an unfinished batch has nothing to download yet', function () {
    $batch = PhotoSheetBatch::create([
        'school_id' => $this->school->id,
        'template' => '4r_3x4',
        'status' => 'processing',
        'items' => [],
        'total_slots' => 1,
        'pages' => 1,
    ]);

    $this->actingAs($this->admin)->get(route('admin.photo-sheets.download', $batch))->assertNotFound();
});

test('old batches are pruned along with their pdf', function () {
    Storage::disk('public')->put('sheets/batches/lama.pdf', 'x');
    Storage::disk('public')->put('sheets/batches/baru.pdf', 'x');

    $lama = PhotoSheetBatch::create([
        'school_id' => $this->school->id, 'template' => '4r_3x4', 'status' => 'completed',
        'items' => [], 'total_slots' => 1, 'pages' => 1, 'file_path' => 'sheets/batches/lama.pdf',
    ]);
    $lama->forceFill(['created_at' => now()->subDays(8)])->saveQuietly();

    PhotoSheetBatch::create([
        'school_id' => $this->school->id, 'template' => '4r_3x4', 'status' => 'completed',
        'items' => [], 'total_slots' => 1, 'pages' => 1, 'file_path' => 'sheets/batches/baru.pdf',
    ]);

    $this->artisan('photo-sheets:prune')->assertSuccessful();

    expect(PhotoSheetBatch::withoutGlobalScopes()->count())->toBe(1);
    expect(Storage::disk('public')->exists('sheets/batches/lama.pdf'))->toBeFalse();
    expect(Storage::disk('public')->exists('sheets/batches/baru.pdf'))->toBeTrue();
});
