<?php

use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Kehadiran;
use App\Models\Program;
use App\Models\Sesi;
use App\Services\CardAttendanceService;
use App\Support\ProgramShortLink;
use Carbon\CarbonImmutable;
use Spatie\Activitylog\Models\Activity;

afterEach(fn () => CarbonImmutable::setTestNow());

function konteksManual(): array
{
    $admin = createPenyelenggaraUser();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);
    $program = Program::factory()->create([
        'kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id,
        'starts_on' => '2027-06-01', 'ends_on' => '2027-06-30',
    ]);
    $sesi = Sesi::acrossPenyelenggara()->create([
        'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'name' => 'Manasik Pagi',
        'tanggal' => '2027-06-10', 'datang_mulai' => '08:00:00', 'datang_selesai' => '09:00:00',
    ]);
    $r3 = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    $siti = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'Siti'], 'status' => 'completed']);
    $siti->grups()->attach($r3->id);

    return [$admin, $program, $sesi, $r3, $siti];
}

test('pendamping mencatat Sakit dengan catatan dan perubahan tercatat di log', function () {
    [$admin, $program, $sesi, $r3, $siti] = konteksManual();
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);
    $r3->pendamping()->attach($pendamping->id);

    $this->actingAs($pendamping)
        ->put(route('penyelenggara.kehadiran.update', [$sesi, $siti]), ['status' => 'SAKIT', 'catatan' => 'demam, di hotel'])
        ->assertRedirect();

    $kehadiran = Kehadiran::acrossPenyelenggara()->where('sesi_id', $sesi->id)->first();

    expect($kehadiran->status)->toBe('SAKIT')
        ->and($kehadiran->catatan)->toBe('demam, di hotel')
        ->and($kehadiran->sumber)->toBe('manual')
        ->and(Activity::where('subject_id', $kehadiran->id)->where('causer_id', $pendamping->id)->exists())->toBeTrue();
});

test('scan tidak menimpa status manual', function () {
    [$admin, $program, $sesi, , $siti] = konteksManual();
    $this->actingAs($admin)->put(route('penyelenggara.kehadiran.update', [$sesi, $siti]), ['status' => 'SAKIT']);
    auth()->logout();

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-10 08:10', 'Asia/Jakarta'));
    $this->postJson('/h/'.ProgramShortLink::codeFor($program->fresh()), ['token' => app(CardAttendanceService::class)->qrToken($siti)])
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'Sakit'));

    expect(Kehadiran::acrossPenyelenggara()->first()->status)->toBe('SAKIT');
});

test('pendamping grup lain tidak bisa mengubah status', function () {
    [$admin, $program, $sesi, , $siti] = konteksManual();
    $lain = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);
    $lain->pendamping()->attach($pendamping->id);

    $this->actingAs($pendamping)
        ->put(route('penyelenggara.kehadiran.update', [$sesi, $siti]), ['status' => 'IZIN'])
        ->assertNotFound();
});

test('admin mengubah Alpa menjadi Izin dan riwayat menampilkan keduanya', function () {
    [$admin, $program, $sesi, , $siti] = konteksManual();

    $this->actingAs($admin)->put(route('penyelenggara.kehadiran.update', [$sesi, $siti]), ['status' => 'ALPA']);
    $this->actingAs($admin)->put(route('penyelenggara.kehadiran.update', [$sesi, $siti]), ['status' => 'IZIN']);

    $this->actingAs($admin)->get(route('penyelenggara.kehadiran.index', ['program' => $program, 'tanggal' => '2027-06-10']))
        ->assertInertia(fn ($page) => $page
            ->where('peserta.0.status', 'IZIN')
            ->where('riwayat', fn ($r) => collect($r)->pluck('baru')->filter()->values()->all() === ['IZIN', 'ALPA']));
});

test('halaman kehadiran menampilkan peserta yang belum hadir', function () {
    [$admin, $program] = konteksManual();

    $this->actingAs($admin)->get(route('penyelenggara.kehadiran.index', ['program' => $program, 'tanggal' => '2027-06-10']))
        ->assertInertia(fn ($page) => $page->where('peserta.0.status', 'BELUM')->where('peserta.0.datang', null));
});

test('kehadiran program yang diarsip tidak bisa diubah', function () {
    [$admin, $program, $sesi, , $siti] = konteksManual();
    $program->update(['archived_at' => now()]);

    $this->actingAs($admin)->put(route('penyelenggara.kehadiran.update', [$sesi, $siti]), ['status' => 'IZIN'])->assertForbidden();
});
