<?php

use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Kehadiran;
use App\Models\Program;
use App\Models\Sesi;
use Carbon\CarbonImmutable;

beforeEach(fn () => CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-10 08:20', 'Asia/Jakarta')));
afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * Program dengan 2 grup (R3: Siti, Ahmad; R4: Budi) dan 1 sesi hari ini.
 * Siti HADIR, Ahmad TERLAMBAT, Budi belum.
 */
function konteksLaporan(): array
{
    $admin = createPenyelenggaraUser();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);
    $program = Program::factory()->create([
        'kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id,
        'starts_on' => '2027-06-01', 'ends_on' => '2027-06-30',
    ]);
    $sesi = Sesi::acrossPenyelenggara()->create([
        'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'name' => 'Manasik Pagi',
        'tanggal' => '2027-06-10', 'datang_mulai' => '08:00:00', 'datang_selesai' => '09:00:00', 'batas_terlambat' => '08:15:00',
    ]);
    $r3 = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'Rombongan 3']);
    $r4 = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'Rombongan 4']);
    $buat = function (string $nama, Grup $grup, ?string $status = null) use ($program, $sesi) {
        $p = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => $nama], 'status' => 'completed']);
        $p->grups()->attach($grup->id);
        if ($status) {
            Kehadiran::acrossPenyelenggara()->create([
                'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'sesi_id' => $sesi->id,
                'card_form_submission_id' => $p->id, 'status' => $status, 'sumber' => 'scan', 'datang_at' => '2027-06-10 08:10:00',
            ]);
        }

        return $p;
    };
    $buat('Siti', $r3, 'HADIR');
    $buat('Ahmad', $r3, 'TERLAMBAT');
    $buat('Budi', $r4);

    return [$admin, $program, $sesi, $r3, $r4];
}

test('dashboard menghitung status per sesi dan daftar belum hadir', function () {
    [$admin, $program] = konteksLaporan();

    $this->actingAs($admin)->get(route('penyelenggara.program.dashboard', $program))
        ->assertInertia(fn ($page) => $page->component('penyelenggara/program/dashboard')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('ringkasan.0.target', 3)
                ->where('ringkasan.0.counts.HADIR', 1)
                ->where('ringkasan.0.counts.TERLAMBAT', 1)
                ->where('ringkasan.0.counts.BELUM', 1)
                ->where('ringkasan.0.belum.0.nama', 'Budi')));
});

test('filter grup membatasi angka ke grup itu', function () {
    [$admin, $program, , $r3] = konteksLaporan();

    $this->actingAs($admin)->get(route('penyelenggara.program.dashboard', ['program' => $program, 'grup' => $r3->id]))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload->where('ringkasan.0.target', 2)->where('ringkasan.0.counts.BELUM', 0)));
});

test('pendamping hanya melihat angka grup tugasnya', function () {
    [$admin, $program, , , $r4] = konteksLaporan();
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);
    $r4->pendamping()->attach($pendamping->id);

    $this->actingAs($pendamping)->get(route('penyelenggara.program.dashboard', $program))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload->where('ringkasan.0.target', 1)->where('ringkasan.0.belum.0.nama', 'Budi')));
});

test('program tanpa sesi hari ini memberi petunjuk ke halaman jadwal', function () {
    $admin = createPenyelenggaraUser();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);
    $program = Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-30']);

    $this->actingAs($admin)->get(route('penyelenggara.program.dashboard', $program))
        ->assertInertia(fn ($page) => $page->where('adaSesiHariIni', false));
});

test('laporan XLSX per grup: satu sheet per grup dan total sama dengan dashboard', function () {
    [$admin, $program] = konteksLaporan();

    $response = $this->actingAs($admin)->get(route('penyelenggara.laporan.export', [
        'program' => $program, 'mode' => 'grup', 'format' => 'xlsx', 'dari' => '2027-06-01', 'sampai' => '2027-06-30',
    ]))->assertOk();

    $sheets = xlsxSheets($response);

    expect(array_keys($sheets))->toBe(['Ringkasan', 'Rombongan 3', 'Rombongan 4'])
        ->and(collect($sheets['Rombongan 3'])->pluck(0)->all())->toContain('Siti', 'Ahmad')
        ->and(collect($sheets['Ringkasan'])->first(fn ($r) => ($r[0] ?? null) === 'Manasik Pagi'))->toContain('1');
});

test('laporan per sesi dan per peserta tersedia, PDF juga', function () {
    [$admin, $program, $sesi] = konteksLaporan();
    $siti = CardFormSubmission::acrossPenyelenggara()->where('program_id', $program->id)->get()->first(fn ($p) => $p->data['nama'] === 'Siti');

    $this->actingAs($admin)->get(route('penyelenggara.laporan.export', ['program' => $program, 'mode' => 'sesi', 'format' => 'xlsx', 'sesi' => $sesi->id]))->assertOk();
    $this->actingAs($admin)->get(route('penyelenggara.laporan.export', ['program' => $program, 'mode' => 'peserta', 'format' => 'xlsx', 'peserta' => $siti->id, 'dari' => '2027-06-01', 'sampai' => '2027-06-30']))->assertOk();
    $this->actingAs($admin)->get(route('penyelenggara.laporan.export', ['program' => $program, 'mode' => 'grup', 'format' => 'pdf', 'dari' => '2027-06-01', 'sampai' => '2027-06-30']))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('rentang laporan lebih dari setahun ditolak', function () {
    [$admin, $program] = konteksLaporan();

    $this->actingAs($admin)->get(route('penyelenggara.laporan.export', [
        'program' => $program, 'mode' => 'grup', 'format' => 'xlsx', 'dari' => '2026-01-01', 'sampai' => '2027-06-30',
    ]))->assertSessionHasErrors('sampai');
});
