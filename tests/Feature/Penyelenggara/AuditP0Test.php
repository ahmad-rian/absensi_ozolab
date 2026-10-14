<?php

use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Kehadiran;
use App\Models\Program;
use App\Models\Sesi;
use App\Models\SesiPola;
use App\Services\Penyelenggara\SesiGenerator;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * Audit 2026-10-14 (P0): perintah console berjalan tanpa konteks penyelenggara,
 * dan scope fail-closed ikut berlaku di relasi/whereHas.
 */
function programAudit(): array
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-05-20 08:00', 'Asia/Jakarta'));
    $kategori = Kategori::factory()->create();
    $kategori->update(['features' => array_merge($kategori->features, ['pulang' => false])]);
    $program = Program::factory()->create([
        'kategori_id' => $kategori->id, 'penyelenggara_id' => $kategori->penyelenggara_id,
        'starts_on' => '2027-06-01', 'ends_on' => '2027-06-30',
    ]);

    return [$program, $kategori];
}

test('sinkron sesi dari console tidak menghapus sesi yang sudah punya kehadiran', function () {
    [$program] = programAudit();
    konteksPenyelenggara($program->penyelenggara()->first());
    $pola = app(SesiGenerator::class)->simpan($program, [
        'name' => 'Pagi', 'pengulangan' => 'mingguan', 'hari' => [1, 2, 3, 4, 5],
        'datang_mulai' => '07:00', 'datang_selesai' => '08:00',
    ]);
    $senin = Sesi::acrossPenyelenggara()->where('tanggal', '2027-06-07')->first();
    $peserta = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'A'], 'status' => 'draft']);
    Kehadiran::acrossPenyelenggara()->create([
        'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'sesi_id' => $senin->id,
        'card_form_submission_id' => $peserta->id, 'status' => 'IZIN', 'sumber' => 'manual',
    ]);
    // Senin dihapus dari pola langsung di DB (meniru perubahan pola), lalu console jalan.
    SesiPola::acrossPenyelenggara()->whereKey($pola->id)->update(['hari' => json_encode([2, 3, 4, 5])]);
    konteksPenyelenggara(null);

    $this->artisan('penyelenggara:sesi-sinkron')->assertSuccessful();

    expect(Sesi::acrossPenyelenggara()->find($senin->id))->not->toBeNull()
        ->and(Kehadiran::acrossPenyelenggara()->where('sesi_id', $senin->id)->count())->toBe(1);
});

test('dry-run sapuan alpa hanya menghitung peserta tanpa catatan', function () {
    [$program] = programAudit();
    $sesi = Sesi::acrossPenyelenggara()->create([
        'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'name' => 'Pagi',
        'tanggal' => '2027-06-10', 'datang_mulai' => '07:00:00', 'datang_selesai' => '08:00:00',
    ]);
    foreach (['A', 'B', 'C'] as $i => $nama) {
        $p = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => $nama], 'status' => 'draft']);
        if ($i < 2) {
            Kehadiran::acrossPenyelenggara()->create([
                'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'sesi_id' => $sesi->id,
                'card_form_submission_id' => $p->id, 'status' => 'HADIR', 'sumber' => 'scan',
            ]);
        }
    }

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-10 09:00', 'Asia/Jakarta'));
    $this->artisan('penyelenggara:tandai-alpa', ['--dry-run' => true])
        ->expectsOutputToContain('1 kehadiran ditandai Alpa')
        ->assertSuccessful();
});

test('peserta yang mendaftar setelah jendela datang tutup tidak dialpa-kan', function () {
    [$program] = programAudit();
    Sesi::acrossPenyelenggara()->create([
        'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'name' => 'Pagi',
        'tanggal' => '2027-06-10', 'datang_mulai' => '07:00:00', 'datang_selesai' => '08:00:00',
    ]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-10 15:00', 'Asia/Jakarta'));
    CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'Telat daftar'], 'status' => 'draft']);

    $this->artisan('penyelenggara:tandai-alpa');

    expect(Kehadiran::acrossPenyelenggara()->count())->toBe(0);
});

test('grup yang punya sesi tidak bisa dihapus agar riwayat kehadiran aman', function () {
    $admin = createPenyelenggaraUser();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);
    $program = Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    $grup = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    Sesi::acrossPenyelenggara()->create([
        'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'grup_id' => $grup->id,
        'name' => 'Ziarah', 'tanggal' => '2027-06-10', 'datang_mulai' => '07:00:00', 'datang_selesai' => '08:00:00',
    ]);

    $this->actingAs($admin)->delete(route('penyelenggara.grup.destroy', $grup))->assertSessionHasErrors('grup');

    expect(Grup::acrossPenyelenggara()->find($grup->id))->not->toBeNull();
});

test('menghapus program menghapus grup bertingkat tanpa error FK', function () {
    [$program] = programAudit();
    $induk = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $program->penyelenggara_id]);
    Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $program->penyelenggara_id, 'parent_id' => $induk->id]);

    Program::acrossPenyelenggara()->whereKey($program->id)->delete();

    expect(Grup::acrossPenyelenggara()->where('program_id', $program->id)->count())->toBe(0);
});
