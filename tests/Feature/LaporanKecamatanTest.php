<?php

use App\Models\CardAttendance;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\Kategori;
use App\Models\Kehadiran;
use App\Models\Libur;
use App\Models\Penyelenggara;
use App\Models\Program;
use App\Models\Sesi;
use App\Models\User;
use App\Support\FilterKolomPeserta;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Laporan Haji per kecamatan: Haji lama (Kartu Bebas) dan program
 * penyelenggara. Isian bebas lama "X" dan "X, Kabupaten" dihitung satu
 * kecamatan; yang kosong masuk "Tanpa kecamatan".
 */

beforeEach(function () {
    $this->withoutVite();
    Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'Asia/Jakarta'));
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-29 08:00:00', 'Asia/Jakarta'));
});
afterEach(function () {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

function formHajiKecamatan(): CardForm
{
    return CardForm::create([
        'name' => 'Haji 2026', 'token' => Str::random(40), 'orientation' => 'portrait', 'is_active' => true, 'layout_config' => ['elements' => []],
        'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text'], ['key' => 'kecamatan', 'label' => 'Kecamatan', 'type' => 'text']],
    ]);
}

function pesertaHaji(CardForm $form, string $nama, ?string $kecamatan, string $status = 'hadir'): CardFormSubmission
{
    $p = $form->submissions()->create(['data' => ['nama' => $nama, 'kecamatan' => $kecamatan], 'status' => 'completed']);
    CardAttendance::factory()->create(['card_form_submission_id' => $p->id, 'status' => $status, 'attendance_date' => '2026-09-28']);

    return $p;
}

test('nama kecamatan dinormalkan supaya isian lama dan baru satu kelompok', function () {
    expect(FilterKolomPeserta::namaKecamatan('Purwokerto Barat'))->toBe('Purwokerto Barat')
        ->and(FilterKolomPeserta::namaKecamatan('purwokerto barat, Banyumas'))->toBe('Purwokerto Barat')
        ->and(FilterKolomPeserta::namaKecamatan('Kec. Sumbang'))->toBe('Sumbang')
        ->and(FilterKolomPeserta::namaKecamatan(''))->toBe(FilterKolomPeserta::TANPA_KECAMATAN)
        ->and(FilterKolomPeserta::kolomKecamatan([['key' => 'kec_asal', 'label' => 'Kecamatan asal', 'type' => 'text'], ['key' => 'alamat', 'label' => 'Kecamatan', 'type' => 'kecamatan']]))->toBe('alamat')
        ->and(FilterKolomPeserta::kolomKecamatan([['key' => 'nama', 'label' => 'Nama']]))->toBeNull();
});

test('laporan haji lama punya ringkasan dan filter per kecamatan', function () {
    $form = formHajiKecamatan();
    pesertaHaji($form, 'Ahmad', 'Purwokerto Barat');
    pesertaHaji($form, 'Budi', 'Purwokerto Barat, Banyumas', 'izin');
    pesertaHaji($form, 'Citra', 'Sumbang');
    pesertaHaji($form, 'Dodi', null, 'sakit');
    $this->actingAs(createSuperAdminUser());

    $this->get(route('kartu-bebas.laporan.index', ['start_date' => '2026-09-01', 'end_date' => '2026-09-29']))
        ->assertInertia(fn (Assert $page) => $page->component('kartu-bebas/absensi/report')
            ->where('kecamatan', ['Purwokerto Barat', 'Sumbang', FilterKolomPeserta::TANPA_KECAMATAN])
            ->where('ringkasanKecamatan.0', ['kecamatan' => 'Purwokerto Barat', 'peserta' => 2, 'hadir' => 1, 'izin' => 1, 'sakit' => 0, 'alpa' => 0, 'total' => 2])
            ->where('ringkasanKecamatan.2.kecamatan', FilterKolomPeserta::TANPA_KECAMATAN)
            ->where('records.data.0.kecamatan', 'Purwokerto Barat'));

    $this->get(route('kartu-bebas.laporan.index', ['start_date' => '2026-09-01', 'end_date' => '2026-09-29', 'kecamatan' => 'Purwokerto Barat']))
        ->assertInertia(fn (Assert $page) => $page->where('records.total', 2)
            ->where('records.data', fn ($rows) => collect($rows)->pluck('name')->sort()->values()->all() === ['Ahmad', 'Budi']));

    $this->get(route('kartu-bebas.laporan.index', ['start_date' => '2026-09-01', 'end_date' => '2026-09-29', 'kecamatan' => FilterKolomPeserta::TANPA_KECAMATAN]))
        ->assertInertia(fn (Assert $page) => $page->where('records.total', 1)->where('records.data.0.name', 'Dodi'));
});

test('ekspor haji lama berisi sheet ringkasan lalu satu sheet per kecamatan, PDF juga', function () {
    $form = formHajiKecamatan();
    pesertaHaji($form, 'Ahmad', 'Purwokerto Barat');
    pesertaHaji($form, 'Citra', 'Sumbang');
    $this->actingAs(createSuperAdminUser());

    $sheets = xlsxSheets($this->get(route('kartu-bebas.laporan.export', ['format' => 'xlsx', 'start_date' => '2026-09-01', 'end_date' => '2026-09-29']))->assertOk());

    expect(array_keys($sheets))->toBe(['Ringkasan per Kecamatan', 'Purwokerto Barat', 'Sumbang'])
        ->and($sheets['Ringkasan per Kecamatan'][1])->toBe(['Purwokerto Barat', '1', '1', '0', '0', '0', '1'])
        ->and($sheets['Sumbang'][0])->toBe(['Tanggal', 'Peserta', 'Layout', 'Kecamatan', 'Status', 'Masuk', 'Pulang', 'Catatan'])
        ->and($sheets['Sumbang'][1][1])->toBe('Citra');

    $this->get(route('kartu-bebas.laporan.export', ['format' => 'pdf', 'start_date' => '2026-09-01', 'end_date' => '2026-09-29', 'kecamatan' => 'Sumbang']))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

/**
 * Program haji (template punya kolom kecamatan) dengan 3 peserta, 1 sesi
 * biasa dan 1 sesi di hari libur.
 *
 * @return array{0: User, 1: Program, 2: Sesi, 3: Sesi}
 */
function programKecamatan(): array
{
    $admin = createPenyelenggaraUser();
    $kategori = Kategori::dariTemplate(Penyelenggara::findOrFail($admin->penyelenggara_id), 'haji', null, null);
    $program = Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);
    $sesi = fn (string $tgl) => Sesi::acrossPenyelenggara()->create([
        'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'name' => 'Manasik '.$tgl,
        'tanggal' => $tgl, 'datang_mulai' => '08:00:00', 'datang_selesai' => '09:00:00', 'batas_terlambat' => '08:15:00',
    ]);
    $biasa = $sesi('2026-09-20');
    $libur = $sesi('2026-09-21');
    Libur::create(['penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'tanggal' => '2026-09-21', 'keterangan' => 'Libur']);

    foreach ([['Siti', 'Kembaran, Banyumas', 'HADIR'], ['Rudi', 'Kembaran', null], ['Tono', 'Sumbang, Banyumas', 'IZIN']] as [$nama, $kec, $status]) {
        $p = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => $nama, 'kecamatan' => $kec], 'status' => 'completed']);
        if ($status) {
            Kehadiran::acrossPenyelenggara()->create([
                'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'sesi_id' => $biasa->id,
                'card_form_submission_id' => $p->id, 'status' => $status, 'sumber' => 'scan', 'datang_at' => '2026-09-20 08:05:00',
            ]);
        }
    }

    return [$admin, $program, $biasa, $libur];
}

test('laporan program per kecamatan: ringkasan dan sheet per kecamatan, sesi libur tidak dihitung', function () {
    [$admin, $program] = programKecamatan();

    $sheets = xlsxSheets($this->actingAs($admin)->get(route('penyelenggara.laporan.export', [
        'program' => $program, 'mode' => 'kecamatan', 'format' => 'xlsx', 'dari' => '2026-09-01', 'sampai' => '2026-09-30',
    ]))->assertOk());

    expect(array_keys($sheets))->toBe(['Ringkasan per Kecamatan', 'Kembaran', 'Sumbang'])
        // Kembaran: 2 peserta, 1 hadir + 1 belum — sesi libur tidak ikut.
        ->and($sheets['Ringkasan per Kecamatan'][1])->toBe(['Kembaran', '2', '1', '0', '0', '0', '0', '1'])
        ->and(collect($sheets['Kembaran'])->pluck(0)->all())->toContain('Siti', 'Rudi');

    $this->actingAs($admin)->get(route('penyelenggara.laporan.export', [
        'program' => $program, 'mode' => 'kecamatan', 'format' => 'pdf', 'dari' => '2026-09-01', 'sampai' => '2026-09-30',
    ]))->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('filter kecamatan berlaku di laporan per grup dan halaman menampilkan ringkasan', function () {
    [$admin, $program] = programKecamatan();

    $sheets = xlsxSheets($this->actingAs($admin)->get(route('penyelenggara.laporan.export', [
        'program' => $program, 'mode' => 'grup', 'format' => 'xlsx', 'dari' => '2026-09-01', 'sampai' => '2026-09-30', 'kecamatan' => 'Sumbang',
    ]))->assertOk());
    expect(collect($sheets['(Tanpa grup)'])->pluck(0)->all())->toBe(['Nama', 'Tono']);

    $this->actingAs($admin)->get(route('penyelenggara.laporan.index', ['program' => $program, 'dari' => '2026-09-01', 'sampai' => '2026-09-30']))
        ->assertInertia(fn (Assert $page) => $page->where('kecamatan', ['Kembaran', 'Sumbang'])
            ->where('peserta', fn ($rows) => collect($rows)->pluck('nama')->all() === ['Rudi', 'Siti', 'Tono'])
            ->where('sesis', fn ($rows) => collect($rows)->pluck('tanggal')->all() === ['2026-09-20'])
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('ringkasanKecamatan.0.kecamatan', 'Kembaran')
                ->where('ringkasanKecamatan.0.peserta', 2)
                ->where('ringkasanKecamatan.0.hadir', 1)
                ->where('ringkasanKecamatan.1.izin', 1)));
});

test('laporan program menolak tanggal berformat salah dan sesi hari libur', function () {
    [$admin, $program, , $libur] = programKecamatan();

    $this->actingAs($admin)->get(route('penyelenggara.laporan.export', [
        'program' => $program, 'mode' => 'grup', 'format' => 'xlsx', 'dari' => '2026-9-1', 'sampai' => '2026-09-30',
    ]))->assertSessionHasErrors('dari');

    $this->actingAs($admin)->get(route('penyelenggara.laporan.export', [
        'program' => $program, 'mode' => 'sesi', 'format' => 'xlsx', 'sesi' => $libur->id,
    ]))->assertSessionHasErrors('sesi');
});
