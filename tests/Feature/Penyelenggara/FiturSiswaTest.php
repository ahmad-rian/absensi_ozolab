<?php

use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Kategori;
use App\Models\Kehadiran;
use App\Models\Program;
use App\Models\Sesi;
use App\Services\Penyelenggara\RingkasanKehadiran;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Fitur yang lebih dulu dibangun untuk siswa sekolah, dibawa ke peserta
 * absensi general: daftar belum ada foto, riwayat + statistik kehadiran per
 * peserta, dan peringkat tercepat yang hanya menghitung kedatangan sungguhan.
 */

beforeEach(fn () => Storage::fake('public'));
afterEach(fn () => CarbonImmutable::setTestNow());

function programFitur(): array
{
    $admin = createPenyelenggaraUser();
    $kategori = Kategori::factory()->create(['penyelenggara_id' => $admin->penyelenggara_id]);
    $program = Program::factory()->create([
        'kategori_id' => $kategori->id, 'penyelenggara_id' => $admin->penyelenggara_id,
        'starts_on' => '2027-06-01', 'ends_on' => '2027-06-30',
    ]);

    return [$admin, $program];
}

function pesertaFitur(Program $program, string $nama, array $atribut = []): CardFormSubmission
{
    return CardFormSubmission::acrossPenyelenggara()->create(['card_form_id' => $program->card_form_id, 'data' => ['nama' => $nama], 'status' => 'draft', ...$atribut]);
}

function sesiFitur(Program $program, string $tanggal): Sesi
{
    return Sesi::acrossPenyelenggara()->create([
        'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'name' => 'Pagi',
        'tanggal' => $tanggal, 'datang_mulai' => '07:00:00', 'datang_selesai' => '08:00:00', 'batas_terlambat' => '07:30:00',
    ]);
}

function catatFitur(Sesi $sesi, CardFormSubmission $p, string $status, ?string $jam): void
{
    Kehadiran::acrossPenyelenggara()->create([
        'penyelenggara_id' => $sesi->penyelenggara_id, 'program_id' => $sesi->program_id, 'sesi_id' => $sesi->id,
        'card_form_submission_id' => $p->id, 'status' => $status, 'sumber' => $jam ? 'scan' : 'manual',
        'datang_at' => $jam ? "{$sesi->tanggal} {$jam}:00" : null,
    ]);
}

// ── belum ada foto ─────────────────────────────────────────────────────────
test('daftar peserta belum ada foto hanya memuat yang aktif tanpa foto beserta ringkasannya', function () {
    [$admin, $program] = programFitur();
    pesertaFitur($program, 'Ada Foto', ['photo_path' => 'card-forms/x/photos/a.png']);
    pesertaFitur($program, 'Belum Foto');
    pesertaFitur($program, 'Diarsip', ['archived_at' => now()]);

    $this->actingAs($admin)->get(route('penyelenggara.peserta.tanpa-foto', $program))
        ->assertInertia(fn ($page) => $page->component('penyelenggara/peserta/tanpa-foto')
            ->has('peserta.data', 1)
            ->where('peserta.data.0.full_name', 'Belum Foto')
            ->where('ringkasan.total', 2)
            ->where('ringkasan.tanpa_foto', 1)
            ->where('ringkasan.persen_berfoto', 50));
});

test('pendamping hanya melihat peserta grupnya di daftar belum ada foto', function () {
    [$admin, $program] = programFitur();
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);
    $grup = Grup::factory()->create(['program_id' => $program->id, 'penyelenggara_id' => $admin->penyelenggara_id]);
    $grup->pendamping()->attach($pendamping->id);
    pesertaFitur($program, 'Grupku')->grups()->attach($grup->id);
    pesertaFitur($program, 'Orang Lain');

    $this->actingAs($pendamping)->get(route('penyelenggara.peserta.tanpa-foto', $program))
        ->assertInertia(fn ($page) => $page->has('peserta.data', 1)->where('peserta.data.0.full_name', 'Grupku'));
});

test('admin memasang foto peserta langsung dari daftar', function () {
    [$admin, $program] = programFitur();
    $p = pesertaFitur($program, 'Siti');

    $this->actingAs($admin)
        ->post(route('penyelenggara.peserta.foto', $p), ['photo' => UploadedFile::fake()->image('siti.jpg', 600, 800)])
        ->assertSessionHasNoErrors();

    expect($p->fresh()->photo_path)->not->toBeNull();
});

test('pendamping tidak bisa memasang foto peserta', function () {
    [$admin, $program] = programFitur();
    $pendamping = createPenyelenggaraUser('PENDAMPING', $admin->penyelenggara);
    $p = pesertaFitur($program, 'Siti');

    $this->actingAs($pendamping)
        ->post(route('penyelenggara.peserta.foto', $p), ['photo' => UploadedFile::fake()->image('siti.jpg')])
        ->assertForbidden();
});

// ── riwayat + statistik ────────────────────────────────────────────────────
test('detail peserta memuat riwayat kehadiran berhalaman dan statistik jam datang', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-20 12:00', 'Asia/Jakarta'));
    [$admin, $program] = programFitur();
    $p = pesertaFitur($program, 'Siti');
    catatFitur(sesiFitur($program, '2027-06-10'), $p, 'HADIR', '07:10');
    catatFitur(sesiFitur($program, '2027-06-11'), $p, 'TERLAMBAT', '07:50');
    // Izin dicatat petugas jam 09:30 — bukan jam datang, tidak ikut rata-rata.
    catatFitur(sesiFitur($program, '2027-06-12'), $p, 'IZIN', null);

    $this->actingAs($admin)->get(route('penyelenggara.peserta.edit', $p))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('kehadiran', fn ($reload) => $reload
            ->has('riwayatKehadiran.data', 3)
            ->where('riwayatKehadiran.data.0.tanggal', '2027-06-12')
            ->where('riwayatKehadiran.data.0.jam_datang', null)
            ->where('statistikKehadiran.counts.HADIR', 1)
            ->where('statistikKehadiran.counts.TERLAMBAT', 1)
            ->where('statistikKehadiran.counts.IZIN', 1)
            ->where('statistikKehadiran.rata_datang', '07:30')
            ->where('statistikKehadiran.paling_awal', '07:10')
            ->where('statistikKehadiran.paling_akhir', '07:50')
            ->where('statistikKehadiran.rata_terlambat_menit', 20)));
});

// ── peringkat tercepat ─────────────────────────────────────────────────────
test('peringkat tercepat hanya menghitung kedatangan sungguhan dan menampilkan rata-rata jam', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-20 12:00', 'Asia/Jakarta'));
    [$admin, $program] = programFitur();
    $cepat = pesertaFitur($program, 'Cepat');
    $sesis = collect(['2027-06-14', '2027-06-15', '2027-06-16'])->map(fn ($t) => sesiFitur($program, $t));
    $sesis->each(fn ($s) => catatFitur($s, $cepat, 'HADIR', '06:50'));
    // Baris izin dengan jam yang terisi tidak boleh menyeret rata-rata.
    Kehadiran::acrossPenyelenggara()->create([
        'penyelenggara_id' => $program->penyelenggara_id, 'program_id' => $program->id, 'sesi_id' => sesiFitur($program, '2027-06-17')->id,
        'card_form_submission_id' => $cepat->id, 'status' => 'IZIN', 'sumber' => 'manual', 'datang_at' => '2027-06-17 10:00:00',
    ]);
    konteksPenyelenggara($admin->penyelenggara);

    $hasil = app(RingkasanKehadiran::class)->peringkat($program, $admin, 1);

    expect($hasil)->toHaveCount(1)
        ->and($hasil[0]['nama'])->toBe('Cepat')
        ->and($hasil[0]['hari'])->toBe(3)
        ->and($hasil[0]['rata_jam'])->toBe('06:50')
        ->and($hasil[0]['rata_menit'])->toEqual(-10);
});
