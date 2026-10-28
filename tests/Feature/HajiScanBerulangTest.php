<?php

use App\Models\CardAttendance;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\CardScan;
use App\Models\Kategori;
use App\Models\Kehadiran;
use App\Models\Program;
use App\Models\Sesi;
use App\Services\CardAttendanceService;
use App\Support\PesertaShortLink;
use App\Support\ProgramShortLink;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/*
 * Jamaah boleh scan berkali-kali: setiap scan masuk riwayat (`card_scans`),
 * rekap tetap Masuk = scan pertama, Pulang = scan terakhir. Scan dobel dalam
 * 60 detik diabaikan.
 */

afterEach(function () {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

function berulangLayout(string $nama = 'Haji A'): array
{
    $form = CardForm::create(['name' => $nama, 'token' => Str::random(40), 'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text']], 'orientation' => 'portrait', 'is_active' => true, 'layout_config' => ['elements' => []]]);
    $peserta = $form->submissions()->create(['data' => ['nama' => 'Ahmad'], 'status' => 'completed']);

    return [$form, $peserta, app(CardAttendanceService::class)->qrToken($peserta)];
}

function berulangScanHaji(CardForm $form, string $qr, string $jam)
{
    Carbon::setTestNow(Carbon::parse("2026-09-29 {$jam}", 'Asia/Jakarta'));

    return test()->postJson('/p/'.PesertaShortLink::codeFor($form), ['token' => $qr]);
}

/** Satu sesi: datang 08.00–09.00, pulang 11.00–12.00. */
function berulangProgram(): array
{
    $kategori = Kategori::factory()->create();
    $program = Program::factory()->create([
        'kategori_id' => $kategori->id, 'penyelenggara_id' => $kategori->penyelenggara_id,
        'starts_on' => '2027-06-01', 'ends_on' => '2027-06-30',
    ]);
    Sesi::acrossPenyelenggara()->create([
        'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'name' => 'Manasik Pagi',
        'tanggal' => '2027-06-10', 'datang_mulai' => '08:00:00', 'datang_selesai' => '09:00:00',
        'batas_terlambat' => '08:15:00', 'pulang_mulai' => '11:00:00', 'pulang_selesai' => '12:00:00',
    ]);
    $peserta = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'Siti Aminah'], 'status' => 'completed']);

    return [$program, $peserta, app(CardAttendanceService::class)->qrToken($peserta)];
}

function berulangScanProgram(Program $program, string $qr, string $jam)
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse("2027-06-10 {$jam}", 'Asia/Jakarta'));

    return test()->postJson('/h/'.ProgramShortLink::codeFor($program), ['token' => $qr]);
}

test('haji lama: scan dobel dalam 60 detik diabaikan tanpa menulis apa pun', function () {
    [$form, , $qr] = berulangLayout();

    berulangScanHaji($form, $qr, '08:00:00')->assertOk()->assertJsonPath('duplikat', false);
    berulangScanHaji($form, $qr, '08:00:40')->assertOk()
        ->assertJsonPath('duplikat', true)
        ->assertJsonPath('message', 'Baru saja tercatat — tunggu sebentar sebelum scan lagi.');

    expect(CardScan::count())->toBe(1);
});

test('haji lama: masuk tetap scan pertama, pulang ikut scan terakhir, semua scan tercatat', function () {
    [$form, $peserta, $qr] = berulangLayout();

    berulangScanHaji($form, $qr, '08:00:00')->assertJsonPath('message', 'Masuk tercatat 08:00.');
    berulangScanHaji($form, $qr, '08:05:00')->assertJsonPath('message', 'Scan ke-2 hari ini tercatat · masuk 08:00.');
    berulangScanHaji($form, $qr, '08:40:00')->assertJsonPath('student.type', 'CHECK_OUT')->assertJsonPath('message', 'Pulang diperbarui 08:40 · scan ke-3 hari ini.');
    berulangScanHaji($form, $qr, '13:15:00')->assertJsonPath('student.time', '13:15:00');

    $rekap = CardAttendance::sole();
    expect($rekap->check_in)->toBe('08:00:00')
        ->and($rekap->check_out)->toBe('13:15:00')
        ->and(CardScan::where('card_form_submission_id', $peserta->id)->pluck('jenis')->all())
        ->toBe(['masuk', 'scan', 'pulang', 'pulang']);

    $this->getJson('/p/'.PesertaShortLink::codeFor($form).'/riwayat')->assertOk()
        ->assertJsonCount(4, 'riwayat')
        ->assertJsonPath('riwayat.0.jenis', 'Pulang')
        ->assertJsonPath('riwayat.0.jam', '13:15:00')
        ->assertJsonPath('riwayat.2.jenis', 'Scan')
        ->assertJsonPath('riwayat.3.jenis', 'Masuk');
});

test('haji lama: riwayat satu layout tidak memuat scan layout lain', function () {
    [$formA, , $qrA] = berulangLayout('Haji A');
    [$formB, , $qrB] = berulangLayout('Haji B');

    berulangScanHaji($formA, $qrA, '08:00:00')->assertOk();
    berulangScanHaji($formB, $qrB, '08:01:00')->assertOk();

    $this->getJson('/p/'.PesertaShortLink::codeFor($formA).'/riwayat')->assertOk()->assertJsonCount(1, 'riwayat');
});

test('haji lama: rekap yang tercatat sebelum card_scans ada tetap tampil di riwayat', function () {
    [$form, $peserta] = berulangLayout();
    Carbon::setTestNow(Carbon::parse('2026-09-29 09:00:00', 'Asia/Jakarta'));
    CardAttendance::create(['card_form_submission_id' => $peserta->id, 'attendance_date' => '2026-09-29', 'status' => 'hadir', 'check_in' => '07:10:00']);

    $this->getJson('/p/'.PesertaShortLink::codeFor($form).'/riwayat')->assertOk()
        ->assertJsonCount(1, 'riwayat')
        ->assertJsonPath('riwayat.0.jam', '07:10:00');
});

test('program: datang sekali, scan di jendela datang dan di antara jendela masuk riwayat', function () {
    [$program, $peserta, $qr] = berulangProgram();

    berulangScanProgram($program, $qr, '08:10')->assertOk()->assertJsonPath('message', 'Datang tercatat 08:10 · Manasik Pagi.');
    berulangScanProgram($program, $qr, '08:10:30')->assertOk()->assertJsonPath('duplikat', true);
    berulangScanProgram($program, $qr, '08:30')->assertOk()->assertJsonPath('message', 'Scan ke-2 tercatat · datang 08:10 · Manasik Pagi.');
    // Di antara jendela datang dan pulang: sudah datang, jadi tetap diterima.
    berulangScanProgram($program, $qr, '10:00')->assertOk()->assertJsonPath('duplikat', false);

    $kehadiran = Kehadiran::acrossPenyelenggara()->sole();
    expect($kehadiran->datang_at->format('H:i'))->toBe('08:10')
        ->and($kehadiran->pulang_at)->toBeNull()
        ->and(CardScan::where('card_form_submission_id', $peserta->id)->pluck('jenis')->all())->toBe(['datang', 'scan', 'scan']);
});

test('program: yang belum datang tetap ditolak di antara jendela', function () {
    [$program, , $qr] = berulangProgram();

    berulangScanProgram($program, $qr, '10:00')->assertStatus(422)->assertJsonPath('message', 'Di luar jam scan Manasik Pagi. Jendela pulang mulai 11:00.');

    expect(CardScan::count())->toBe(0);
});

test('program: pulang diperbarui ke scan terakhir dan riwayat memuat semua scan', function () {
    [$program, , $qr] = berulangProgram();

    berulangScanProgram($program, $qr, '08:10');
    berulangScanProgram($program, $qr, '11:05')->assertJsonPath('message', 'Pulang tercatat 11:05 · Manasik Pagi.');
    berulangScanProgram($program, $qr, '11:40')->assertJsonPath('message', 'Pulang diperbarui 11:40 · scan ke-3 · Manasik Pagi.');

    expect(Kehadiran::acrossPenyelenggara()->sole()->pulang_at->format('H:i'))->toBe('11:40');

    $this->getJson('/h/'.ProgramShortLink::codeFor($program).'/riwayat')->assertOk()
        ->assertJsonCount(3, 'riwayat')
        ->assertJsonPath('riwayat.0.jenis', 'Pulang')
        ->assertJsonPath('riwayat.0.jam', '11:40:00')
        ->assertJsonPath('riwayat.0.sesi', 'Manasik Pagi')
        ->assertJsonPath('riwayat.2.jenis', 'Datang');
});
