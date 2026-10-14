<?php

use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Kehadiran;
use App\Models\Program;
use App\Models\Sesi;
use App\Models\SesiPola;
use Carbon\CarbonImmutable;

beforeEach(fn () => CarbonImmutable::setTestNow('2027-05-20 08:00:00'));
afterEach(fn () => CarbonImmutable::setTestNow());

function programJadwal($admin, array $fitur = []): Program
{
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);
    if ($fitur) {
        $kategori->update(['features' => array_merge($kategori->features, $fitur)]);
    }

    // 1 Juni 2027 = Selasa; Juni punya 22 hari kerja.
    return Program::factory()->create([
        'kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id,
        'starts_on' => '2027-06-01', 'ends_on' => '2027-06-30',
    ]);
}

function polaHarian(array $ubah = []): array
{
    return array_merge([
        'name' => 'Kelas Pagi',
        'pengulangan' => 'mingguan',
        'hari' => [1, 2, 3, 4, 5],
        'datang_mulai' => '07:00',
        'datang_selesai' => '08:30',
        'batas_terlambat' => '07:30',
        'pulang_mulai' => '15:00',
        'pulang_selesai' => '17:00',
    ], $ubah);
}

test('pola Senin–Jumat menurunkan sesi tepat di hari kerja program', function () {
    $admin = createPenyelenggaraUser();
    $program = programJadwal($admin);

    $this->actingAs($admin)->post(route('penyelenggara.jadwal.store', $program), polaHarian())->assertRedirect();

    $tanggal = Sesi::acrossPenyelenggara()->where('program_id', $program->id)->pluck('tanggal');

    expect($tanggal)->toHaveCount(22)
        ->and($tanggal)->not->toContain('2027-06-05')
        ->and($tanggal)->toContain('2027-06-01')
        ->and(Sesi::acrossPenyelenggara()->first()->batas_terlambat)->toStartWith('07:30');
});

test('batas terlambat di luar jendela datang ditolak', function () {
    $admin = createPenyelenggaraUser();

    $this->actingAs($admin)
        ->post(route('penyelenggara.jadwal.store', programJadwal($admin)), polaHarian(['batas_terlambat' => '09:00']))
        ->assertSessionHasErrors('batas_terlambat');
});

test('jendela pulang tidak wajib bila saklar pulang kategori mati', function () {
    $admin = createPenyelenggaraUser();
    $program = programJadwal($admin, ['pulang' => false]);

    $this->actingAs($admin)
        ->post(route('penyelenggara.jadwal.store', $program), polaHarian(['pulang_mulai' => null, 'pulang_selesai' => null]))
        ->assertSessionHasNoErrors();

    expect(Sesi::acrossPenyelenggara()->where('program_id', $program->id)->whereNotNull('pulang_mulai')->count())->toBe(0);
});

test('tiga sesi bernama dalam sehari dan sesi yang bertabrakan ditolak dengan namanya', function () {
    $admin = createPenyelenggaraUser();
    $program = programJadwal($admin, ['pulang' => false]);
    $sekali = fn (string $nama, string $mulai, string $selesai) => [
        'name' => $nama, 'pengulangan' => 'sekali', 'tanggal' => '2027-06-10',
        'datang_mulai' => $mulai, 'datang_selesai' => $selesai,
    ];

    $this->actingAs($admin)->post(route('penyelenggara.jadwal.store', $program), $sekali('Manasik Pagi', '08:00', '11:00'))->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('penyelenggara.jadwal.store', $program), $sekali('Sholat Dzuhur', '11:45', '12:30'))->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('penyelenggara.jadwal.store', $program), $sekali('Manasik Siang', '13:00', '15:00'))->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('penyelenggara.jadwal.store', $program), $sekali('Tambahan', '10:30', '12:00'))
        ->assertSessionHasErrors(['datang_mulai' => 'Bertabrakan dengan Manasik Pagi (08:00–11:00) pada 10 Juni 2027.']);
});

test('sesi bersamaan untuk grup berbeda diterima', function () {
    $admin = createPenyelenggaraUser();
    $program = programJadwal($admin, ['pulang' => false]);
    $r3 = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    $r4 = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    $data = fn (Grup $g) => ['name' => 'Ziarah', 'pengulangan' => 'sekali', 'tanggal' => '2027-06-10', 'datang_mulai' => '08:00', 'datang_selesai' => '10:00', 'grup_id' => $g->id];

    $this->actingAs($admin)->post(route('penyelenggara.jadwal.store', $program), $data($r3))->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('penyelenggara.jadwal.store', $program), $data($r4))->assertSessionHasNoErrors();
});

test('libur menonaktifkan sesi hari itu dan bisa dihapus', function () {
    $admin = createPenyelenggaraUser();
    $program = programJadwal($admin);

    $this->actingAs($admin)->post(route('penyelenggara.libur.store', $program), ['tanggal' => '2027-06-17', 'keterangan' => 'Idul Adha'])->assertRedirect();

    $this->actingAs($admin)->get(route('penyelenggara.jadwal.index', $program))
        ->assertInertia(fn ($page) => $page->where('liburs.0.tanggal', '2027-06-17'));
});

test('mengubah pola tidak mengubah sesi lampau dan sesi hari ini yang berkehadiran butuh konfirmasi', function () {
    $admin = createPenyelenggaraUser();
    $program = programJadwal($admin, ['pulang' => false]);
    $this->actingAs($admin)->post(route('penyelenggara.jadwal.store', $program), polaHarian(['pulang_mulai' => null, 'pulang_selesai' => null]));
    $pola = SesiPola::acrossPenyelenggara()->first();

    // Waktu bergerak ke 10 Juni; sesi 9 Juni sudah lewat, sesi 10 Juni sudah ada kehadiran.
    CarbonImmutable::setTestNow('2027-06-10 09:00:00');
    $hariIni = Sesi::acrossPenyelenggara()->where('tanggal', '2027-06-10')->first();
    $peserta = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'A'], 'status' => 'draft']);
    Kehadiran::acrossPenyelenggara()->create([
        'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'sesi_id' => $hariIni->id,
        'card_form_submission_id' => $peserta->id, 'status' => 'HADIR', 'sumber' => 'scan',
    ]);

    $this->actingAs($admin)->put(route('penyelenggara.jadwal.update', $pola), polaHarian(['datang_mulai' => '06:30', 'batas_terlambat' => '07:00', 'pulang_mulai' => null, 'pulang_selesai' => null]))->assertRedirect();

    $jam = fn (string $t) => Sesi::acrossPenyelenggara()->where('tanggal', $t)->value('datang_mulai');

    expect($jam('2027-06-09'))->toStartWith('07:00')
        ->and($jam('2027-06-10'))->toStartWith('07:00')
        ->and($jam('2027-06-11'))->toStartWith('06:30');

    $this->actingAs($admin)->put(route('penyelenggara.jadwal.update', $pola), polaHarian(['datang_mulai' => '06:30', 'batas_terlambat' => '07:00', 'pulang_mulai' => null, 'pulang_selesai' => null, 'termasuk_hari_ini' => true]));

    expect($jam('2027-06-10'))->toStartWith('06:30');
});

test('program diperpendek menghapus sesi mendatang di luar rentang', function () {
    $admin = createPenyelenggaraUser();
    $program = programJadwal($admin);
    $this->actingAs($admin)->post(route('penyelenggara.jadwal.store', $program), polaHarian());

    $this->actingAs($admin)->put(route('penyelenggara.program.update', $program), [
        'name' => $program->name, 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-15',
    ])->assertRedirect();

    expect(Sesi::acrossPenyelenggara()->where('program_id', $program->id)->max('tanggal'))->toBe('2027-06-15');
});
