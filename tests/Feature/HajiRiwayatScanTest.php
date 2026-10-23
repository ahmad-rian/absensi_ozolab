<?php

use App\Models\CardAttendance;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\Kategori;
use App\Models\Kehadiran;
use App\Models\Penyelenggara;
use App\Models\Program;
use App\Models\Sesi;
use App\Services\CardAttendanceService;
use App\Support\PesertaShortLink;
use App\Support\ProgramShortLink;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Riwayat scan Haji dibaca dari database: sama di semua perangkat, jam
 * server, tetap ada setelah reload — dan link scan lama tidak lagi menerima
 * peserta program.
 */

afterEach(function () {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

function layoutHajiRiwayat(): array
{
    Carbon::setTestNow(Carbon::parse('2026-10-21 07:30:00', 'Asia/Jakarta'));
    $form = CardForm::create(['name' => 'Haji 2027', 'token' => Str::random(40), 'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text']], 'orientation' => 'portrait', 'is_active' => true, 'layout_config' => ['elements' => []]]);
    $peserta = $form->submissions()->create(['data' => ['nama' => 'Siti'], 'status' => 'completed']);

    return [$form, $peserta, app(CardAttendanceService::class)->qrToken($peserta)];
}

test('scan haji lama membawa riwayat dari database dan endpoint riwayat sama di semua perangkat', function () {
    [$form, $peserta, $qr] = layoutHajiRiwayat();
    $url = route('public.card-scanner.scan', ['token' => $form->scanner_token, 'mode' => 'masuk']);

    $this->postJson($url, ['token' => $qr])->assertOk()
        ->assertJsonPath('duplikat', false)
        ->assertJsonPath('riwayat.0.nama', 'Siti')
        ->assertJsonPath('riwayat.0.jenis', 'Masuk')
        ->assertJsonPath('riwayat.0.jam', '07:30:00');

    // Scan ulang ditandai, tidak dihitung sebagai kejadian baru.
    $this->postJson($url, ['token' => $qr])->assertOk()->assertJsonPath('duplikat', true)->assertJsonCount(1, 'riwayat');

    Carbon::setTestNow(Carbon::parse('2026-10-21 15:00:00', 'Asia/Jakarta'));
    $this->postJson(route('public.card-scanner.scan', ['token' => $form->scanner_token, 'mode' => 'pulang']), ['token' => $qr])->assertOk();

    // Perangkat lain / setelah reload: riwayat yang sama dari server.
    $this->getJson(route('public.card-scanner.riwayat', $form->scanner_token))->assertOk()
        ->assertJsonCount(2, 'riwayat')
        ->assertJsonPath('riwayat.0.jenis', 'Pulang')
        ->assertJsonPath('riwayat.1.jenis', 'Masuk');
    $this->getJson(route('public.card-scanner.short.riwayat', PesertaShortLink::codeFor($form)))->assertOk()->assertJsonCount(2, 'riwayat');
});

test('riwayat haji lama hanya berisi layout itu dan token salah ditolak', function () {
    [$form, , $qr] = layoutHajiRiwayat();
    [$lain, , $qrLain] = layoutHajiRiwayat();
    $this->postJson(route('public.card-scanner.scan', ['token' => $lain->scanner_token, 'mode' => 'masuk']), ['token' => $qrLain]);

    $this->getJson(route('public.card-scanner.riwayat', $form->scanner_token))->assertOk()->assertJsonCount(0, 'riwayat');
    $this->getJson(route('public.card-scanner.riwayat', 'token-ngawur'))->assertNotFound();
});

test('link scan haji lama menolak peserta program penyelenggara', function () {
    $kategori = Kategori::factory()->create();
    $program = Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $kategori->penyelenggara_id]);
    $form = CardForm::acrossPenyelenggara()->findOrFail($program->card_form_id);
    $peserta = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $form->id, 'data' => ['nama' => 'Budi'], 'status' => 'completed']);

    $this->postJson(route('public.card-scanner.scan', ['token' => $form->scanner_token, 'mode' => 'masuk']), ['token' => app(CardAttendanceService::class)->qrToken($peserta)])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'program penyelenggara'));

    expect(CardAttendance::count())->toBe(0);
});

test('detail peserta haji lama menampilkan riwayat kehadiran dari scan', function () {
    [$form, $peserta, $qr] = layoutHajiRiwayat();
    $this->postJson(route('public.card-scanner.scan', ['token' => $form->scanner_token, 'mode' => 'masuk']), ['token' => $qr]);

    $this->withoutVite()->actingAs(createSuperAdminUser())->get(route('kartu-bebas.peserta.show', $peserta))
        ->assertInertia(fn (Assert $page) => $page->component('kartu-bebas/peserta/show')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('kehadiran.0.tanggal', '2026-10-21')
                ->where('kehadiran.0.masuk', '07:30')));
});

function programRiwayat(): array
{
    $kategori = Kategori::factory()->create();
    $program = Program::factory()->create([
        'kategori_id' => $kategori->id, 'penyelenggara_id' => $kategori->penyelenggara_id,
        'starts_on' => '2027-06-01', 'ends_on' => '2027-06-30',
    ]);
    foreach ([['Pagi', '08:00:00', '09:00:00', '11:00:00', '12:00:00'], ['Sore', '13:00:00', '14:00:00', '16:00:00', '17:00:00']] as [$nama, $dm, $ds, $pm, $ps]) {
        Sesi::acrossPenyelenggara()->create([
            'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'name' => $nama,
            'tanggal' => '2027-06-10', 'datang_mulai' => $dm, 'datang_selesai' => $ds,
            'batas_terlambat' => $ds, 'pulang_mulai' => $pm, 'pulang_selesai' => $ps,
        ]);
    }
    $peserta = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'Aminah'], 'status' => 'completed']);

    return [$program, $peserta, app(CardAttendanceService::class)->qrToken($peserta)];
}

test('scan program membawa riwayat sesi hari ini dan endpoint riwayat bisa dibuka ulang', function () {
    [$program, , $qr] = programRiwayat();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-10 13:10', 'Asia/Jakarta'));
    Carbon::setTestNow(Carbon::parse('2027-06-10 13:10', 'Asia/Jakarta'));
    $kode = ProgramShortLink::codeFor($program);

    $this->postJson('/h/'.$kode, ['token' => $qr])->assertOk()
        ->assertJsonPath('riwayat.0.nama', 'Aminah')
        ->assertJsonPath('riwayat.0.sesi', 'Sore')
        ->assertJsonPath('riwayat.0.jenis', 'Datang');

    $this->getJson('/h/'.$kode.'/riwayat')->assertOk()->assertJsonCount(1, 'riwayat');
});

test('halaman kehadiran membuka sesi yang sedang berjalan dan mencatat jam pulang di riwayat', function () {
    [$program, $peserta, $qr] = programRiwayat();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-10 13:10', 'Asia/Jakarta'));
    Carbon::setTestNow(Carbon::parse('2027-06-10 13:10', 'Asia/Jakarta'));
    $this->postJson('/h/'.ProgramShortLink::codeFor($program), ['token' => $qr])->assertOk();

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-10 16:20', 'Asia/Jakarta'));
    Carbon::setTestNow(Carbon::parse('2027-06-10 16:20', 'Asia/Jakarta'));
    $this->postJson('/h/'.ProgramShortLink::codeFor($program), ['token' => $qr])->assertOk()->assertJsonPath('student.type', 'CHECK_OUT');

    $admin = createPenyelenggaraUser(penyelenggara: Penyelenggara::find($program->penyelenggara_id));
    $this->withoutVite()->actingAs($admin)->get(route('penyelenggara.kehadiran.index', $program))
        ->assertInertia(fn (Assert $page) => $page->where('sesi.name', 'Sore')
            ->where('peserta.0.pulang', '16:20')
            ->where('riwayat', fn ($r) => collect($r)->contains(fn ($x) => str_contains($x['perubahan'], 'Pulang 16:20'))
                && collect($r)->contains(fn ($x) => str_contains($x['perubahan'], 'Datang 13:10'))));

    expect(Kehadiran::acrossPenyelenggara()->count())->toBe(1);
});

test('halaman scan ringan haji dan program menyediakan tombol ke halaman kamera HP', function () {
    [$form, , $qr] = layoutHajiRiwayat();
    $kode = PesertaShortLink::codeFor($form);
    $this->get(route('public.card-scanner.short', $kode))
        ->assertOk()->assertSee('/p/'.$kode.'/kamera', false)->assertDontSee($form->scanner_token);

    // Halaman kamera via kode pendek: endpoint pendek (mode otomatis), riwayat bersama.
    $this->withoutVite()->get('/p/'.$kode.'/kamera')->assertOk()
        ->assertDontSee($form->scanner_token)
        ->assertInertia(fn (Assert $page) => $page->component('scan/program')
            ->where('scanUrl', '/p/'.$kode)
            ->where('riwayatUrl', '/p/'.$kode.'/riwayat'));
    $this->postJson('/p/'.$kode, ['token' => $qr])->assertOk()->assertJsonPath('student.type', 'CHECK_IN');

    [$program] = programRiwayat();
    $kodeProgram = ProgramShortLink::codeFor($program);
    $this->get('/h/'.$kodeProgram)->assertOk()->assertSee('/h/'.$kodeProgram.'/kamera', false);
    $this->withoutVite()->get('/h/'.$kodeProgram.'/kamera')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('scan/program')->where('scanUrl', '/h/'.$kodeProgram));
});
