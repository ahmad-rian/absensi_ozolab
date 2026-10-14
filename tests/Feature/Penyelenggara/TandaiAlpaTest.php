<?php

use App\Models\CardFormSubmission;
use App\Models\Kategori;
use App\Models\Kehadiran;
use App\Models\Libur;
use App\Models\Program;
use App\Models\Sesi;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

function konteksAlpa(): array
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-01 08:00', 'Asia/Jakarta'));
    $kategori = Kategori::factory()->create();
    $program = Program::factory()->create([
        'kategori_id' => $kategori->id, 'penyelenggara_id' => $kategori->penyelenggara_id,
        'starts_on' => '2027-06-01', 'ends_on' => '2027-06-30',
    ]);
    $sesi = Sesi::acrossPenyelenggara()->create([
        'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'name' => 'Manasik',
        'tanggal' => '2027-06-10', 'datang_mulai' => '08:00:00', 'datang_selesai' => '09:00:00',
    ]);
    $buat = fn (string $nama) => CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => $nama], 'status' => 'completed']);

    return [$program, $sesi, $buat];
}

test('setelah jendela datang tutup, peserta tanpa catatan menjadi Alpa otomatis', function () {
    [$program, $sesi, $buat] = konteksAlpa();
    $hadir = $buat('Hadir');
    $absen = $buat('Absen');
    Kehadiran::acrossPenyelenggara()->create(['penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'sesi_id' => $sesi->id, 'card_form_submission_id' => $hadir->id, 'status' => 'HADIR', 'sumber' => 'scan']);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-10 08:30', 'Asia/Jakarta'));
    $this->artisan('penyelenggara:tandai-alpa')->assertSuccessful();
    expect(Kehadiran::acrossPenyelenggara()->where('status', 'ALPA')->count())->toBe(0);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-10 09:05', 'Asia/Jakarta'));
    $this->artisan('penyelenggara:tandai-alpa')->assertSuccessful();

    $alpa = Kehadiran::acrossPenyelenggara()->where('status', 'ALPA')->get();
    expect($alpa)->toHaveCount(1)
        ->and($alpa->first()->card_form_submission_id)->toBe($absen->id)
        ->and($alpa->first()->sumber)->toBe('otomatis');
});

test('sapuan aman diulang dan tidak menyentuh status manual', function () {
    [$program, $sesi, $buat] = konteksAlpa();
    $sakit = $buat('Sakit');
    Kehadiran::acrossPenyelenggara()->create(['penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'sesi_id' => $sesi->id, 'card_form_submission_id' => $sakit->id, 'status' => 'SAKIT', 'sumber' => 'manual']);
    $buat('Absen');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-10 10:00', 'Asia/Jakarta'));
    $this->artisan('penyelenggara:tandai-alpa');
    $this->artisan('penyelenggara:tandai-alpa');

    expect(Kehadiran::acrossPenyelenggara()->count())->toBe(2)
        ->and(Kehadiran::acrossPenyelenggara()->where('card_form_submission_id', $sakit->id)->value('status'))->toBe('SAKIT');
});

test('hari libur tidak menghasilkan Alpa', function () {
    [$program, , $buat] = konteksAlpa();
    $buat('Absen');
    Libur::acrossPenyelenggara()->create(['penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'tanggal' => '2027-06-10']);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-10 10:00', 'Asia/Jakarta'));
    $this->artisan('penyelenggara:tandai-alpa');

    expect(Kehadiran::acrossPenyelenggara()->count())->toBe(0);
});

test('peserta yang didaftarkan setelah tanggal sesi tidak dialpa-kan mundur', function () {
    [, , $buat] = konteksAlpa();

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-11 10:00', 'Asia/Jakarta'));
    $buat('Baru');
    $this->artisan('penyelenggara:tandai-alpa');

    expect(Kehadiran::acrossPenyelenggara()->count())->toBe(0);
});
