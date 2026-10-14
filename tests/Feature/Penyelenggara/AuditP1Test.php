<?php

use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Kehadiran;
use App\Models\Libur;
use App\Models\Program;
use App\Models\Sesi;
use App\Services\CardAttendanceService;
use App\Support\ProgramShortLink;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

afterEach(fn () => CarbonImmutable::setTestNow());

function konteksP1(array $fitur = []): array
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-05-20 08:00', 'Asia/Jakarta'));
    $admin = createPenyelenggaraUser();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);
    if ($fitur) {
        $kategori->update(['features' => array_merge($kategori->features, $fitur)]);
    }
    $program = Program::factory()->create([
        'kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id,
        'starts_on' => '2027-06-01', 'ends_on' => '2027-06-30',
    ]);

    return [$admin, $program, $kategori];
}

// ── 8. pendamping hanya lihat & catat ──────────────────────────────────────
test('pendamping tidak bisa menambah, mengimpor, mengarsip, atau generate peserta', function () {
    [$admin, $program] = konteksP1();
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);
    $grup = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    $grup->pendamping()->attach($pendamping->id);
    $peserta = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'A'], 'status' => 'draft']);
    $peserta->grups()->attach($grup->id);

    $this->actingAs($pendamping)->post(route('penyelenggara.peserta.store', $program), ['data' => ['nama' => 'B']])->assertForbidden();
    $this->actingAs($pendamping)->post(route('penyelenggara.impor.pratinjau', $program), ['file' => UploadedFile::fake()->createWithContent('a.csv', "Nama\nX\n")])->assertForbidden();
    $this->actingAs($pendamping)->delete(route('penyelenggara.peserta.destroy', $peserta))->assertForbidden();
    $this->actingAs($pendamping)->post(route('penyelenggara.peserta.generate', $peserta))->assertForbidden();
    $this->actingAs($pendamping)->get(route('penyelenggara.peserta.index', $program))->assertOk();
});

test('pendamping bisa mencatat kehadiran manual peserta grupnya', function () {
    [$admin, $program] = konteksP1();
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);
    $grup = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    $grup->pendamping()->attach($pendamping->id);
    $peserta = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'A'], 'status' => 'draft']);
    $peserta->grups()->attach($grup->id);
    $sesi = Sesi::acrossPenyelenggara()->create(['penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'name' => 'Pagi', 'tanggal' => '2027-06-10', 'datang_mulai' => '07:00:00', 'datang_selesai' => '08:00:00']);

    $this->actingAs($pendamping)->put(route('penyelenggara.kehadiran.update', [$sesi, $peserta]), ['status' => 'SAKIT'])->assertSessionHasNoErrors();

    expect(Kehadiran::acrossPenyelenggara()->where('card_form_submission_id', $peserta->id)->value('status'))->toBe('SAKIT');
});

test('pendamping bisa membuka dashboard program dan kehadiran tanpa izin program', function () {
    [$admin, $program] = konteksP1();
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);

    $this->actingAs($pendamping)->get(route('penyelenggara.program.dashboard', $program))
        ->assertInertia(fn ($page) => $page->where('akses.program', false)->where('akses.kehadiran', true));
    $this->actingAs($pendamping)->get(route('penyelenggara.kehadiran.index', $program))->assertOk();
});

test('beranda pendamping hanya menampilkan program tempat ia bertugas', function () {
    [$admin, $program, $kategori] = konteksP1();
    $lain = Program::factory()->create(['kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);
    Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id])->pendamping()->attach($pendamping->id);

    $this->actingAs($pendamping)->get(route('penyelenggara.dashboard'))
        ->assertInertia(fn ($page) => $page->where('programs', fn ($p) => collect($p)->pluck('id')->all() === [$program->id]));
});

// ── 10. saklar fitur kategori ditegakkan ───────────────────────────────────
test('izin/sakit ditolak bila saklar izin_sakit kategori mati', function () {
    [$admin, $program] = konteksP1(['izin_sakit' => false]);
    $sesi = Sesi::acrossPenyelenggara()->create(['penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'name' => 'Pagi', 'tanggal' => '2027-06-10', 'datang_mulai' => '07:00:00', 'datang_selesai' => '08:00:00']);
    $p = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'A'], 'status' => 'draft']);

    $this->actingAs($admin)->put(route('penyelenggara.kehadiran.update', [$sesi, $p]), ['status' => 'IZIN'])->assertSessionHasErrors('status');
    $this->actingAs($admin)->put(route('penyelenggara.kehadiran.update', [$sesi, $p]), ['status' => 'ALPA'])->assertSessionHasNoErrors();
});

test('mematikan saklar pulang menghapus jendela pulang sesi mendatang', function () {
    [$admin, $program, $kategori] = konteksP1();
    $this->actingAs($admin)->post(route('penyelenggara.jadwal.store', $program), [
        'name' => 'Pagi', 'pengulangan' => 'mingguan', 'hari' => [1, 2, 3, 4, 5],
        'datang_mulai' => '07:00', 'datang_selesai' => '08:00', 'batas_terlambat' => '07:30', 'pulang_mulai' => '15:00', 'pulang_selesai' => '17:00',
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->put(route('penyelenggara.kategori.update', $kategori), [
        'name' => $kategori->name, 'istilah_peserta' => 'Jamaah', 'istilah_grup' => 'Rombongan',
        'features' => array_merge($kategori->features, ['pulang' => false, 'terlambat' => false]),
        'fields' => $kategori->visibleFields(),
    ])->assertRedirect();

    expect(Sesi::acrossPenyelenggara()->where('program_id', $program->id)->whereNotNull('pulang_mulai')->count())->toBe(0)
        ->and(Sesi::acrossPenyelenggara()->where('program_id', $program->id)->whereNotNull('batas_terlambat')->count())->toBe(0);
});

// ── 11. tabrakan memperhitungkan hierarki grup ─────────────────────────────
test('sesi grup anak yang bertabrakan dengan sesi grup induk ditolak', function () {
    [$admin, $program] = konteksP1(['pulang' => false]);
    $kloter = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'Kloter 12']);
    $rombongan = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'parent_id' => $kloter->id]);
    $data = fn ($g, $m, $s) => ['name' => 'Ziarah', 'pengulangan' => 'sekali', 'tanggal' => '2027-06-10', 'datang_mulai' => $m, 'datang_selesai' => $s, 'grup_id' => $g->id];

    $this->actingAs($admin)->post(route('penyelenggara.jadwal.store', $program), $data($kloter, '08:00', '10:00'))->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('penyelenggara.jadwal.store', $program), $data($rombongan, '09:00', '11:00'))->assertSessionHasErrors('datang_mulai');
});

// ── 12. manual HADIR lalu scan pulang ──────────────────────────────────────
test('peserta yang dicatat Hadir manual tetap bisa scan pulang', function () {
    [$admin, $program] = konteksP1();
    $sesi = Sesi::acrossPenyelenggara()->create([
        'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'name' => 'Pagi', 'tanggal' => '2027-06-10',
        'datang_mulai' => '07:00:00', 'datang_selesai' => '08:00:00', 'pulang_mulai' => '11:00:00', 'pulang_selesai' => '12:00:00',
    ]);
    $p = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'A'], 'status' => 'completed']);
    $this->actingAs($admin)->put(route('penyelenggara.kehadiran.update', [$sesi, $p]), ['status' => 'HADIR']);
    auth()->logout();

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-10 11:20', 'Asia/Jakarta'));
    $this->postJson('/h/'.ProgramShortLink::codeFor($program->fresh()), ['token' => app(CardAttendanceService::class)->qrToken($p)])->assertOk();

    expect(Kehadiran::acrossPenyelenggara()->first())->status->toBe('HADIR')->pulang_at->not->toBeNull();
});

// ── 13. impor ──────────────────────────────────────────────────────────────
test('klik simpan impor dua kali tidak menggandakan peserta', function () {
    [$admin, $program] = konteksP1();
    $this->actingAs($admin)->post(route('penyelenggara.impor.pratinjau', $program), ['file' => UploadedFile::fake()->createWithContent('a.csv', "Nama\nA\nB\n")]);
    $kunci = session('impor_peserta_kunci');

    $this->actingAs($admin)->post(route('penyelenggara.impor.simpan', [$program, $kunci]))->assertRedirect();
    $this->actingAs($admin)->post(route('penyelenggara.impor.simpan', [$program, $kunci]))->assertStatus(410);

    expect(CardFormSubmission::acrossPenyelenggara()->where('program_id', $program->id)->count())->toBe(2);
});

test('kolom grup dikenali dengan istilah grup kategori', function () {
    [$admin, $program, $kategori] = konteksP1();
    $kategori->update(['istilah_grup' => 'Halaqah']);
    $grup = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'Halaqah 1']);

    $this->actingAs($admin)->post(route('penyelenggara.impor.pratinjau', $program), ['file' => UploadedFile::fake()->createWithContent('a.csv', "Nama,Halaqah\nA,Halaqah 1\n")]);
    $this->actingAs($admin)->post(route('penyelenggara.impor.simpan', [$program, session('impor_peserta_kunci')]));

    expect($grup->peserta()->count())->toBe(1);
});

// ── 14. laporan ────────────────────────────────────────────────────────────
test('laporan per grup memisahkan grup bernama sama dan mengabaikan hari libur', function () {
    [$admin, $program] = konteksP1();
    $a = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'Rombongan A']);
    $b = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'name' => 'Rombongan B']);
    $reguA = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'parent_id' => $a->id, 'name' => 'Regu 1']);
    $reguB = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id, 'parent_id' => $b->id, 'name' => 'Regu 1']);
    foreach ([[$reguA, 'Siti'], [$reguB, 'Budi']] as [$g, $n]) {
        CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => $n], 'status' => 'draft'])->grups()->attach($g->id);
    }
    Sesi::acrossPenyelenggara()->create(['penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'name' => 'Libur', 'tanggal' => '2027-06-17', 'datang_mulai' => '07:00:00', 'datang_selesai' => '08:00:00']);
    Libur::acrossPenyelenggara()->create(['penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'tanggal' => '2027-06-17']);

    $sheets = xlsxSheets($this->actingAs($admin)->get(route('penyelenggara.laporan.export', [
        'program' => $program, 'mode' => 'grup', 'format' => 'xlsx', 'dari' => '2027-06-01', 'sampai' => '2027-06-30',
    ]))->assertOk());

    expect(array_keys($sheets))->toContain('Rombongan A › Regu 1', 'Rombongan B › Regu 1')
        ->and(count($sheets['Ringkasan']))->toBe(1);
});

// ── 15. sidebar penyelenggara di halaman Settings ───────────────────────────
test('akun penyelenggara membawa penyelenggara_id di prop auth untuk memilih sidebar', function () {
    [$admin] = konteksP1();

    $this->actingAs($admin)->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('auth.user.penyelenggara_id', $admin->penyelenggara_id));
});

test('form peserta mengirim status arsip supaya tombol Pulihkan hanya muncul bila perlu', function () {
    [$admin, $program] = konteksP1();
    $p = CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => 'A'], 'status' => 'draft']);
    $p->forceFill(['archived_at' => now()])->save();

    $this->actingAs($admin)->get(route('penyelenggara.peserta.edit', $p))
        ->assertInertia(fn ($page) => $page->where('peserta.archived', true));
});
