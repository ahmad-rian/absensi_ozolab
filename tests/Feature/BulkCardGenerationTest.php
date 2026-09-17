<?php

use App\Jobs\GenerateStudentCardJob;
use App\Models\CardGenerationBatch;
use App\Models\CardGenerationLog;
use App\Models\Classroom;
use App\Models\SchoolCardLayout;
use App\Models\Student;
use Illuminate\Support\Facades\Queue;

/*
 | Generate kartu satu sekolah — atau satu kelas — sekaligus.
 |
 | Dua hal yang membedakannya dari sekadar perulangan, dan keduanya dijaga di
 | sini. Pertama: kartu tanpa pas foto tetap jadi, dengan kotak kosong di tempat
 | wajahnya, dan itu baru ketahuan setelah ratusan berkas terunggah ke Drive —
 | jadi servernya sendiri yang menolak, bukan cuma tombolnya yang dimatikan.
 | Kedua: kemajuannya dihitung dari baris log, bukan dari penghitung yang
 | dinaikkan job, supaya job yang gagal atau dicoba ulang tidak bisa membuat
 | bar-nya menggantung selamanya.
 */

beforeEach(function () {
    Queue::fake();

    $this->super = createSuperAdminUser();
    $this->schoolId = $this->super->school_id;
    $this->classroom = Classroom::factory()->create(['school_id' => $this->schoolId]);

    // Dua layout aktif: sisi depan dan sisi belakang kartu OSIS.
    foreach (['osis', 'perpustakaan'] as $jenis) {
        SchoolCardLayout::create([
            'school_id' => $this->schoolId,
            'name' => 'Layout '.$jenis,
            'type' => $jenis,
            'is_active' => true,
            'layout_config' => [],
        ]);
    }
});

function siswaBerfoto(int $jumlah): void
{
    Student::factory()->count($jumlah)->create([
        'school_id' => test()->schoolId,
        'classroom_id' => test()->classroom->id,
        'is_active' => true,
        'photo_path' => 'photos/students/x/ada.png',
    ]);
}

test('server menolak generate selama masih ada siswa tanpa pas foto', function () {
    siswaBerfoto(2);
    Student::factory()->create([
        'school_id' => $this->schoolId,
        'classroom_id' => $this->classroom->id,
        'is_active' => true,
        'photo_path' => null,
    ]);

    // Tombolnya memang sudah mati di klien, tapi tombol yang mati bukan
    // penjagaan — ia hanya menjelaskan. Yang menolak harus servernya.
    $this->actingAs($this->super)
        ->post('/admin/generate-kartu', ['school_id' => $this->schoolId])
        ->assertSessionHasErrors('school_id');

    expect(CardGenerationBatch::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('satu batch berisi lembar 4R dan kedua sisi kartu per siswa', function () {
    siswaBerfoto(3);

    $this->actingAs($this->super)
        ->post('/admin/generate-kartu', ['school_id' => $this->schoolId])
        ->assertRedirect();

    $batch = CardGenerationBatch::firstOrFail();

    expect($batch->total)->toBe(9)
        ->and(CardGenerationLog::withoutGlobalScope('school')
            ->where('card_generation_batch_id', $batch->id)
            ->count())->toBe(9);

    Queue::assertPushed(GenerateStudentCardJob::class, 9);

    $logs = CardGenerationLog::withoutGlobalScope('school')
        ->where('card_generation_batch_id', $batch->id)->get();

    foreach ($logs->groupBy('student_id') as $studentLogs) {
        expect($studentLogs->where('type', 'card'))->toHaveCount(2)
            ->and($studentLogs->where('type', 'photo_sheet'))->toHaveCount(1)
            ->and($studentLogs->firstWhere('type', 'photo_sheet')->school_card_layout_id)->toBeNull();
    }

    foreach ($logs as $log) {
        Queue::assertPushed(GenerateStudentCardJob::class,
            fn (GenerateStudentCardJob $job) => $job->logId === $log->id && $job->afterCommit === true);
    }
});

test('memfilter satu kelas hanya mengambil siswa kelas itu', function () {
    siswaBerfoto(2);

    $kelasLain = Classroom::factory()->create(['school_id' => $this->schoolId]);
    Student::factory()->count(4)->create([
        'school_id' => $this->schoolId,
        'classroom_id' => $kelasLain->id,
        'is_active' => true,
        'photo_path' => 'photos/students/x/ada.png',
    ]);

    $this->actingAs($this->super)
        ->post('/admin/generate-kartu', [
            'school_id' => $this->schoolId,
            'classroom_id' => $this->classroom->id,
        ])
        ->assertRedirect();

    expect(CardGenerationBatch::firstOrFail()->total)->toBe(6);
});

test('persen naik mengikuti status baris log', function () {
    siswaBerfoto(2);

    $this->actingAs($this->super)
        ->post('/admin/generate-kartu', ['school_id' => $this->schoolId])
        ->assertRedirect();

    $batch = CardGenerationBatch::firstOrFail();

    expect($batch->progres())->toMatchArray(['total' => 6, 'selesai' => 0, 'persen' => 0, 'status' => 'processing']);

    CardGenerationLog::withoutGlobalScope('school')
        ->where('card_generation_batch_id', $batch->id)
        ->limit(2)
        ->update(['status' => 'completed']);

    expect($batch->fresh()->progres())->toMatchArray(['selesai' => 2, 'persen' => 33, 'status' => 'processing']);
});

test('batch yang sebagian jobnya gagal tetap mencapai 100 persen', function () {
    siswaBerfoto(2);

    $this->actingAs($this->super)
        ->post('/admin/generate-kartu', ['school_id' => $this->schoolId])
        ->assertRedirect();

    $batch = CardGenerationBatch::firstOrFail();
    $logs = CardGenerationLog::withoutGlobalScope('school')
        ->where('card_generation_batch_id', $batch->id)
        ->pluck('id');

    CardGenerationLog::withoutGlobalScope('school')->whereIn('id', $logs->take(3))->update(['status' => 'completed']);
    CardGenerationLog::withoutGlobalScope('school')->whereIn('id', $logs->skip(3))->update(['status' => 'failed']);

    // Progres yang menggantung selamanya adalah kegagalan yang paling mahal di sini:
    // operator menunggu sesuatu yang tidak akan pernah datang, lalu menekan
    // tombolnya lagi dan menggandakan seluruh batch.
    expect($batch->fresh()->progres())
        ->toMatchArray(['total' => 6, 'selesai' => 3, 'gagal' => 3, 'persen' => 100, 'status' => 'failed']);
});

test('endpoint progres melaporkan angka yang sama dengan modelnya', function () {
    siswaBerfoto(1);

    $this->actingAs($this->super)
        ->post('/admin/generate-kartu', ['school_id' => $this->schoolId])
        ->assertRedirect();

    $batch = CardGenerationBatch::firstOrFail();

    $this->actingAs($this->super)
        ->getJson("/admin/generate-kartu/{$batch->id}/progres")
        ->assertOk()
        ->assertJson(['total' => 3, 'selesai' => 0, 'gagal' => 0, 'persen' => 0]);
});

test('batch menunggu lembar 4R dan tetap selesai ketika lembar itu gagal', function () {
    siswaBerfoto(1);

    $this->actingAs($this->super)
        ->post('/admin/generate-kartu', ['school_id' => $this->schoolId])
        ->assertRedirect();

    $batch = CardGenerationBatch::firstOrFail();
    $logs = CardGenerationLog::withoutGlobalScope('school')->where('card_generation_batch_id', $batch->id);
    (clone $logs)->where('type', 'card')->update(['status' => 'completed']);

    expect($batch->progres())->toMatchArray(['selesai' => 2, 'persen' => 66, 'status' => 'processing']);

    (clone $logs)->where('type', 'photo_sheet')->update(['status' => 'failed']);

    expect($batch->progres())->toMatchArray(['selesai' => 2, 'gagal' => 1, 'persen' => 100, 'status' => 'failed']);
});

test('generate hanya memasukkan siswa aktif sekolah terpilih', function () {
    siswaBerfoto(1);
    Student::factory()->create(['school_id' => $this->schoolId, 'is_active' => false, 'photo_path' => null]);
    Student::factory()->create(['is_active' => true, 'photo_path' => null]);

    $this->actingAs($this->super)
        ->post('/admin/generate-kartu', ['school_id' => $this->schoolId])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(CardGenerationBatch::firstOrFail()->total)->toBe(3)
        ->and(CardGenerationLog::withoutGlobalScope('school')->pluck('school_id')->unique()->all())
        ->toBe([$this->schoolId]);
});

test('bukan super admin ditolak di ketiga rute', function () {
    $admin = createAdminUser();
    $batch = CardGenerationBatch::create([
        'school_id' => $admin->school_id,
        'total' => 1,
        'status' => 'processing',
    ]);

    // Permission `card-generation.access` dipegang ADMIN juga — yang menutup
    // pintu di sini middleware `super-admin`, bukan permission-nya.
    $this->actingAs($admin)->get('/admin/generate-kartu')->assertForbidden();
    $this->actingAs($admin)->post('/admin/generate-kartu', ['school_id' => $admin->school_id])->assertForbidden();
    $this->actingAs($admin)->get("/admin/generate-kartu/{$batch->id}/progres")->assertForbidden();
});

test('kelas milik sekolah lain ditolak, bukan diam-diam diproses', function () {
    siswaBerfoto(1);

    $sekolahLain = createAdminUser();
    $kelasAsing = Classroom::factory()->create(['school_id' => $sekolahLain->school_id]);

    $this->actingAs($this->super)
        ->post('/admin/generate-kartu', [
            'school_id' => $this->schoolId,
            'classroom_id' => $kelasAsing->id,
        ])
        ->assertSessionHasErrors('classroom_id');

    expect(CardGenerationBatch::count())->toBe(0);
});
