<?php

use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\Kategori;
use App\Models\Kehadiran;
use App\Models\Libur;
use App\Models\Program;
use App\Models\Sesi;
use App\Services\CardAttendanceService;
use App\Support\ProgramShortLink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * Program dengan satu sesi 10 Juni 2027: datang 08.00–09.00 (terlambat > 08.15),
 * pulang 11.00–12.00.
 */
function programScan(): array
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

function scanRingan(Program $program, string $token, string $jam)
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse("2027-06-10 {$jam}", 'Asia/Jakarta'));

    return test()->postJson('/h/'.ProgramShortLink::codeFor($program), ['token' => $token]);
}

test('scan dalam jendela datang mencatat Hadir, setelah batas mencatat Terlambat', function () {
    [$program, $peserta, $token] = programScan();
    $lain = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'Ahmad'], 'status' => 'completed']);

    scanRingan($program, $token, '08:10')->assertOk()->assertJsonPath('student.full_name', 'Siti Aminah')->assertJsonPath('student.status', 'HADIR');
    scanRingan($program, app(CardAttendanceService::class)->qrToken($lain), '08:40')->assertOk()->assertJsonPath('student.status', 'TERLAMBAT');
});

test('scan ulang dalam jendela datang hanya masuk riwayat, jam datang tetap', function () {
    [$program, $peserta, $token] = programScan();

    scanRingan($program, $token, '08:10')->assertOk();
    scanRingan($program, $token, '08:30')->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'Scan ke-2'));

    $kehadiran = Kehadiran::acrossPenyelenggara()->where('card_form_submission_id', $peserta->id)->get();
    expect($kehadiran)->toHaveCount(1)->and($kehadiran->first()->datang_at->format('H:i'))->toBe('08:10');
});

test('pulang tercatat di kehadiran yang sama', function () {
    [$program, $peserta, $token] = programScan();

    scanRingan($program, $token, '08:10');
    scanRingan($program, $token, '11:20')->assertOk()->assertJsonPath('student.type', 'CHECK_OUT');

    expect(Kehadiran::acrossPenyelenggara()->where('card_form_submission_id', $peserta->id)->first()->pulang_at->format('H:i'))->toBe('11:20');
});

test('tanpa sesi berlaku, layar menyebut sesi berikutnya dan tidak mencatat', function () {
    [$program, $peserta, $token] = programScan();

    scanRingan($program, $token, '07:00')->assertStatus(422)->assertJsonPath('message', 'Tidak ada sesi sekarang · berikutnya Manasik Pagi 08:00.');

    expect(Kehadiran::acrossPenyelenggara()->count())->toBe(0);
});

test('hari libur menolak scan', function () {
    [$program, $peserta, $token] = programScan();
    Libur::acrossPenyelenggara()->create(['penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'tanggal' => '2027-06-10', 'keterangan' => 'Idul Adha']);

    scanRingan($program, $token, '08:10')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'libur'));
});

test('token huruf besar dengan spasi tetap dikenali', function () {
    [$program, , $token] = programScan();

    scanRingan($program, '  '.strtoupper($token).' ', '08:10')->assertOk();
});

test('kartu program lain dan kartu Kartu Bebas lama ditolak di link program', function () {
    [$program] = programScan();
    [, , $tokenLain] = programScan();
    $layoutLama = CardForm::acrossPenyelenggara()->create(['name' => 'Haji lama', 'token' => Str::random(10), 'fields' => [], 'orientation' => 'portrait', 'layout_config' => [], 'is_active' => true]);
    $pesertaLama = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $layoutLama->id, 'data' => ['nama' => 'X'], 'status' => 'completed']);

    scanRingan($program, $tokenLain, '08:10')->assertStatus(422);
    scanRingan($program, app(CardAttendanceService::class)->qrToken($pesertaLama), '08:10')->assertStatus(422);

    expect(Kehadiran::acrossPenyelenggara()->count())->toBe(0);
});

test('peserta yang diarsip tidak bisa absen', function () {
    [$program, $peserta, $token] = programScan();
    $peserta->forceFill(['archived_at' => now()])->save();

    scanRingan($program, $token, '08:10')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'diarsip'));
});

test('link lama mati setelah diganti, kartu tetap berlaku di link baru', function () {
    [$program, , $token] = programScan();
    $kodeLama = ProgramShortLink::codeFor($program);
    $admin = createPenyelenggaraUser(penyelenggara: $program->penyelenggara()->first());

    $this->actingAs($admin)->post(route('penyelenggara.kehadiran.rotate', $program))->assertRedirect();
    auth()->logout();

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-10 08:10', 'Asia/Jakarta'));
    $this->postJson('/h/'.$kodeLama, ['token' => $token])->assertNotFound();
    scanRingan($program->fresh(), $token, '08:10')->assertOk();
});

test('halaman scan kamera dan ringan terbuka tanpa login', function () {
    [$program] = programScan();

    $this->get('/h/'.ProgramShortLink::codeFor($program))->assertOk()->assertSee($program->name);
    $this->get('/hadir/'.$program->scanner_token)->assertOk();
});
