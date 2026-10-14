<?php

use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Libur;
use App\Models\Program;
use App\Models\Sesi;
use App\Services\Penyelenggara\SesiResolver;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

function konteksResolver(string $tz = 'Asia/Jakarta'): array
{
    $kategori = Kategori::factory()->create();
    $program = Program::factory()->create([
        'kategori_id' => $kategori->id, 'penyelenggara_id' => $kategori->penyelenggara_id,
        'starts_on' => '2027-06-01', 'ends_on' => '2027-06-30', 'timezone' => $tz,
    ]);
    $peserta = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'A'], 'status' => 'draft']);
    $sesi = fn (string $nama, string $mulai, string $selesai, ?string $grupId = null) => Sesi::acrossPenyelenggara()->create([
        'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'grup_id' => $grupId,
        'name' => $nama, 'tanggal' => '2027-06-10', 'datang_mulai' => $mulai, 'datang_selesai' => $selesai,
    ]);

    return [$program, $peserta, $sesi];
}

test('memilih sesi yang sedang berlangsung dan menyebut sesi berikutnya', function () {
    [, $peserta, $sesi] = konteksResolver();
    $sesi('Manasik Pagi', '08:00:00', '11:00:00');
    $sesi('Manasik Siang', '13:00:00', '15:00:00');

    $pagi = app(SesiResolver::class)->untuk($peserta, CarbonImmutable::parse('2027-06-10 09:00', 'Asia/Jakarta'));
    $jeda = app(SesiResolver::class)->untuk($peserta, CarbonImmutable::parse('2027-06-10 12:00', 'Asia/Jakarta'));

    expect($pagi['sesi']->name)->toBe('Manasik Pagi')
        ->and($jeda['sesi'])->toBeNull()
        ->and($jeda['berikutnya']->name)->toBe('Manasik Siang');
});

test('jam dibaca dalam zona waktu program', function () {
    [, $peserta, $sesi] = konteksResolver('Asia/Riyadh');
    $sesi('Ziarah', '08:00:00', '10:00:00');

    // 12.30 WIB = 08.30 waktu Riyadh.
    $hasil = app(SesiResolver::class)->untuk($peserta, CarbonImmutable::parse('2027-06-10 12:30', 'Asia/Jakarta'));

    expect($hasil['sesi']?->name)->toBe('Ziarah');
});

test('sesi khusus grup induk berlaku untuk peserta di sub-grupnya, bukan grup lain', function () {
    [$program, $peserta, $sesi] = konteksResolver();
    $kloter = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $program->penyelenggara_id]);
    $rombongan = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $program->penyelenggara_id, 'parent_id' => $kloter->id]);
    $lain = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $program->penyelenggara_id]);
    $peserta->grups()->attach($rombongan->id);
    $sesi('Ziarah Kloter', '08:00:00', '10:00:00', $kloter->id);
    $sesi('Ziarah Lain', '10:30:00', '11:30:00', $lain->id);

    $resolver = app(SesiResolver::class);

    expect($resolver->untuk($peserta, CarbonImmutable::parse('2027-06-10 09:00', 'Asia/Jakarta'))['sesi']?->name)->toBe('Ziarah Kloter')
        ->and($resolver->untuk($peserta, CarbonImmutable::parse('2027-06-10 11:00', 'Asia/Jakarta'))['sesi'])->toBeNull();
});

test('hari libur tidak punya sesi aktif', function () {
    [$program, $peserta, $sesi] = konteksResolver();
    $sesi('Manasik', '08:00:00', '11:00:00');
    Libur::acrossPenyelenggara()->create(['penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'tanggal' => '2027-06-10']);

    $hasil = app(SesiResolver::class)->untuk($peserta, CarbonImmutable::parse('2027-06-10 09:00', 'Asia/Jakarta'));

    expect($hasil['sesi'])->toBeNull()->and($hasil['libur'])->not->toBeNull();
});
