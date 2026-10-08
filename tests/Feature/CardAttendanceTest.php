<?php

use App\Jobs\GenerateDynamicCardJob;
use App\Models\CardAttendance;
use App\Models\CardDataset;
use App\Models\CardForm;
use App\Services\CardAttendanceService;
use App\Services\DynamicCardGenerator;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Reader\XLSX\Reader;

beforeEach(function () {
    $this->withoutVite();
    Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'Asia/Jakarta'));
    $this->form = CardForm::create(['name' => 'Haji A', 'token' => Str::random(40), 'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text']], 'orientation' => 'portrait', 'is_active' => true, 'layout_config' => ['elements' => ['qr' => ['type' => 'qr', 'source' => '__attendance', 'x' => 3, 'y' => 3, 'w' => 15, 'h' => 15, 'enabled' => true]]]]);
    $this->participant = $this->form->submissions()->create(['data' => ['nama' => 'Ahmad'], 'status' => 'completed']);
    $this->qr = app(CardAttendanceService::class)->qrToken($this->participant);
});

afterEach(fn () => Carbon::setTestNow());

function cardScanUrl(CardForm $form, string $mode = 'masuk'): string
{
    return route('public.card-scanner.scan', ['token' => $form->scanner_token, 'mode' => $mode]);
}

test('public scanner opens without login and separate token never exposes report routes', function () {
    expect($this->form->scanner_token)->toHaveLength(48)->not->toBe($this->form->token);
    $this->get(route('public.card-scanner', $this->form->scanner_token))->assertOk()->assertInertia(fn (Assert $page) => $page->component('scan/card-participant')->where('layout.name', 'Haji A'));
    $this->get(route('public.card-scanner', $this->form->token))->assertNotFound();
    $this->getJson(route('kartu-bebas.absensi.index'))->assertUnauthorized();
    $this->getJson(route('kartu-bebas.laporan.index'))->assertUnauthorized();
});

test('scan masuk is idempotent and pulang requires explicit mode', function () {
    $this->postJson(cardScanUrl($this->form), ['token' => $this->qr])->assertOk()->assertJsonPath('student.type', 'CHECK_IN')->assertJsonPath('student.time', '08:00:00');
    Carbon::setTestNow(Carbon::parse('2026-09-29 09:00:00', 'Asia/Jakarta'));
    $this->postJson(cardScanUrl($this->form), ['token' => $this->qr])->assertOk()->assertJsonPath('student.time', '08:00:00');
    expect(CardAttendance::count())->toBe(1)->and(CardAttendance::sole()->check_out)->toBeNull();
    $this->postJson(cardScanUrl($this->form, 'pulang'), ['token' => $this->qr])->assertOk()->assertJsonPath('student.type', 'CHECK_OUT')->assertJsonPath('student.time', '09:00:00');
    Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'Asia/Jakarta'));
    $this->postJson(cardScanUrl($this->form, 'pulang'), ['token' => $this->qr])->assertOk()->assertJsonPath('student.time', '09:00:00');
});

test('pulang before masuk and invalid scan modes cannot create records', function () {
    $this->postJson(cardScanUrl($this->form, 'pulang'), ['token' => $this->qr])->assertUnprocessable();
    $this->postJson(cardScanUrl($this->form, 'invalid'), ['token' => $this->qr])->assertNotFound();
    expect(CardAttendance::count())->toBe(0);
});

test('tokens cannot be forged matched by name or scanned into another layout', function () {
    foreach (['Ahmad', $this->participant->id, substr($this->qr, 0, -4).'ffff'] as $token) {
        $this->postJson(cardScanUrl($this->form), ['token' => $token])->assertUnprocessable();
    }
    $other = CardForm::create(['name' => 'Haji B', 'token' => Str::random(40), 'fields' => [], 'layout_config' => ['elements' => []], 'orientation' => 'portrait', 'is_active' => true]);
    $this->postJson(cardScanUrl($other), ['token' => $this->qr])->assertUnprocessable();
    expect(CardAttendance::count())->toBe(0);
});

test('daily attendance uses Jakarta date across UTC midnight and new days', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-29 00:05:00', 'Asia/Jakarta'));
    $this->postJson(cardScanUrl($this->form), ['token' => $this->qr])->assertOk();
    expect(CardAttendance::sole()->attendance_date->format('Y-m-d'))->toBe('2026-09-29');
    Carbon::setTestNow(Carbon::parse('2026-09-30 00:05:00', 'Asia/Jakarta'));
    $this->postJson(cardScanUrl($this->form), ['token' => $this->qr])->assertOk();
    expect(CardAttendance::count())->toBe(2);
});

test('inactive or rotated links stop scanning while the card QR stays stable', function () {
    $old = $this->form->scanner_token;
    $this->actingAs(createSuperAdminUser())->post(route('kartu-bebas.absensi.rotate', $this->form))->assertRedirect();
    $this->get(route('public.card-scanner', $old))->assertNotFound();
    $this->form->refresh();
    expect(app(CardAttendanceService::class)->qrToken($this->participant))->toBe($this->qr);
    $this->form->update(['is_active' => false]);
    $this->postJson(cardScanUrl($this->form), ['token' => $this->qr])->assertNotFound();
});

test('admin can record and correct statuses but scanners cannot overwrite manual absence', function () {
    $this->actingAs(createSuperAdminUser());
    $url = route('kartu-bebas.absensi.store', $this->participant);
    $this->post($url, ['date' => '2026-09-29', 'status' => 'izin', 'note' => 'Keperluan keluarga'])->assertRedirect();
    $this->postJson(cardScanUrl($this->form), ['token' => $this->qr])->assertUnprocessable();
    $this->post($url, ['date' => '2026-09-29', 'status' => 'hadir', 'check_in' => '08:30', 'check_out' => '10:30'])->assertRedirect();
    expect(CardAttendance::count())->toBe(1)->and(CardAttendance::sole()->status)->toBe('hadir');
    $this->postJson($url, ['date' => '2026-09-29', 'status' => 'hadir', 'check_in' => '09:00', 'check_out' => '08:00'])->assertUnprocessable();
    $this->postJson($url, ['date' => '2026-09-30', 'status' => 'hadir'])->assertUnprocessable();
});

test('attendance lists unscanned participants without inventing alpa and reports filter real records', function () {
    $this->actingAs(createSuperAdminUser());
    $this->get(route('kartu-bebas.absensi.index', ['layout' => $this->form->id]))->assertOk()->assertInertia(fn (Assert $page) => $page->component('kartu-bebas/absensi/index')->where('layouts.0.qr_ready', true)->where('participants.data.0.attendance', null));
    CardAttendance::factory()->create(['card_form_submission_id' => $this->participant->id, 'status' => 'izin', 'check_in' => null]);
    $this->get(route('kartu-bebas.laporan.index', ['status' => 'izin', 'layout' => $this->form->id]))->assertOk()->assertInertia(fn (Assert $page) => $page->component('kartu-bebas/absensi/report')->where('records.total', 1)->where('records.data.0.name', 'Ahmad')->where('counts.izin', 1));
    $this->get(route('kartu-bebas.laporan.index', ['status' => 'hadir']))->assertInertia(fn (Assert $page) => $page->where('records.total', 0));
    $this->getJson(route('kartu-bebas.laporan.index', ['start_date' => '2026-09-30', 'end_date' => '2026-09-01']))->assertUnprocessable();
});

test('exports provide grouped xlsx sheets and a rendered PDF', function () {
    $this->actingAs(createSuperAdminUser());
    CardAttendance::factory()->create(['card_form_submission_id' => $this->participant->id]);
    CardAttendance::factory()->create();
    $response = $this->get(route('kartu-bebas.laporan.export', 'xlsx'))->assertOk()->assertDownload('absensi-peserta.xlsx');
    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        $sheets[] = $sheet->getName();
    }
    $reader->close();
    expect($sheets)->toHaveCount(2);
    $this->get(route('kartu-bebas.laporan.export', ['format' => 'pdf', 'layout' => $this->form->id]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('non super admin cannot manage attendance or download reports', function () {
    $this->actingAs(createAdminUser());
    $this->get(route('kartu-bebas.absensi.index'))->assertForbidden();
    $this->post(route('kartu-bebas.absensi.store', $this->participant))->assertForbidden();
    $this->get(route('kartu-bebas.laporan.export', 'xlsx'))->assertForbidden();
    $this->post(route('kartu-bebas.absensi.rotate', $this->form))->assertForbidden();
    $this->post(route('kartu-bebas.absensi.regenerate', $this->form))->assertForbidden();
    $this->get(route('kartu-bebas.absensi.download-cards', $this->form))->assertForbidden();
});

test('barcode gun yang mengubah huruf besar kecil tetap terbaca', function () {
    $this->postJson(cardScanUrl($this->form), ['token' => strtoupper($this->qr)])->assertOk();
    $this->postJson(cardScanUrl($this->form, 'pulang'), ['token' => ' '.strtolower($this->qr)."\n"])->assertOk();
    expect(CardAttendance::sole()->check_out)->not->toBeNull();
});

test('QR berisi data peserta menyuruh generate ulang dan cetak ulang', function () {
    $this->postJson(cardScanUrl($this->form), ['token' => '1100621603'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'QR di kartu ini berisi data peserta, bukan QR absensi. Generate ulang kartu dari menu Absensi peserta lalu cetak ulang.');
});

test('generate ulang semua kartu satu layout mengantre semua pesertanya saja', function () {
    Queue::fake();
    $this->form->submissions()->create(['data' => ['nama' => 'Budi'], 'status' => 'completed']);
    $sedangDiproses = $this->form->submissions()->create(['data' => ['nama' => 'Cici'], 'status' => 'processing']);
    $lain = CardForm::create(['name' => 'Haji B', 'token' => Str::random(40), 'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text']], 'layout_config' => ['elements' => []], 'orientation' => 'portrait', 'is_active' => true]);
    $lain->submissions()->create(['data' => ['nama' => 'Dodi'], 'status' => 'completed']);

    $this->actingAs(createSuperAdminUser())
        ->post(route('kartu-bebas.absensi.regenerate', $this->form))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.message', '2 kartu masuk antrean generate ulang. Cetak ulang kartu setelah selesai. 1 peserta masih diproses, coba lagi nanti.');

    Queue::assertPushed(GenerateDynamicCardJob::class, 2);
    expect($sedangDiproses->fresh()->status)->toBe('processing');
});

test('migrasi mengarahkan QR lama ke QR absensi', function () {
    $lama = CardForm::create(['name' => 'Haji Lama', 'token' => Str::random(40), 'fields' => [['key' => 'porsi', 'label' => 'No Porsi', 'type' => 'text']], 'orientation' => 'portrait', 'is_active' => true, 'layout_config' => ['elements' => ['qr' => ['type' => 'qr', 'source' => 'porsi', 'x' => 3, 'y' => 3, 'w' => 15, 'h' => 15, 'enabled' => true], 'nama' => ['type' => 'text', 'source' => 'porsi']]]]);
    $mati = CardForm::create(['name' => 'QR Mati', 'token' => Str::random(40), 'fields' => [], 'orientation' => 'portrait', 'is_active' => true, 'layout_config' => ['elements' => ['qr' => ['type' => 'qr', 'source' => 'porsi', 'enabled' => false]]]]);
    $tanpaQr = CardForm::create(['name' => 'Tanpa QR', 'token' => Str::random(40), 'fields' => [], 'orientation' => 'portrait', 'is_active' => true, 'layout_config' => ['elements' => []]]);
    $diubahSebelum = DB::table('card_forms')->where('id', $tanpaQr->id)->value('updated_at');

    (require database_path('migrations/2026_10_05_000000_point_card_qr_to_attendance.php'))->up();

    expect($lama->fresh()->layout_config['elements']['qr']['source'])->toBe('__attendance')
        ->and($lama->fresh()->layout_config['elements']['nama']['source'])->toBe('porsi')
        ->and($this->form->fresh()->layout_config['elements']['qr']['source'])->toBe('__attendance')
        ->and($mati->fresh()->layout_config['elements']['qr'])->toMatchArray(['source' => '__attendance', 'enabled' => false])
        ->and($tanpaQr->fresh()->layout_config['elements'])->toBe([])
        ->and(DB::table('card_forms')->where('id', $tanpaQr->id)->value('updated_at'))->toBe($diubahSebelum);
});

test('participant QR renders without a user editable data field', function () {
    $this->participant->update(['data' => ['nama' => 'Ahmad', '__attendance' => 'forged']]);
    $html = app(DynamicCardGenerator::class)->renderHtml($this->form, $this->participant);
    $writer = new Writer(new ImageRenderer(new RendererStyle(300, 4), new SvgImageBackEnd));
    expect($html)->toContain($writer->writeString(strtoupper($this->qr)))->not->toContain($writer->writeString('forged'));
    expect(app(CardAttendanceService::class)->qrToken($this->participant))->toBe($this->qr);
});

test('layout editor accepts attendance QR source without adding a participant input field', function () {
    $this->actingAs(createSuperAdminUser());
    $dataset = CardDataset::create(['name' => 'Haji', 'fields' => [['key' => 'nama', 'label' => 'Nama', 'type' => 'text']]]);
    $this->put(route('kartu-bebas.layouts.update', $this->form), ['name' => 'Haji A', 'card_dataset_id' => $dataset->id, 'orientation' => 'portrait', 'layout_config' => $this->form->layout_config, 'is_active' => true])->assertRedirect();
    expect($this->form->fresh()->layout_config['elements']['qr']['source'])->toBe('__attendance');
    expect(collect($this->form->fresh()->inputFields())->pluck('key')->all())->not->toContain('__attendance');
});

test('migration gives existing layouts a separate scanner link without changing public form links', function () {
    $publicToken = $this->form->token;
    $migration = require database_path('migrations/2026_09_29_045312_add_scanner_token_to_card_forms.php');
    $migration->down();
    $migration->up();
    $this->form->refresh();
    expect($this->form->scanner_token)->toHaveLength(48)->and($this->form->token)->toBe($publicToken);
});

test('generate ulang tidak melewatkan peserta di atas seribu', function () {
    Queue::fake();
    $sekarang = now();
    DB::table('card_form_submissions')->insert(collect(range(1, 1000))->map(fn (int $ke) => [
        'id' => strtolower((string) Str::ulid()), 'card_form_id' => $this->form->id, 'data' => json_encode(['nama' => 'Peserta '.$ke]),
        'status' => 'completed', 'created_at' => $sekarang, 'updated_at' => $sekarang,
    ])->all());

    $this->actingAs(createSuperAdminUser())->post(route('kartu-bebas.absensi.regenerate', $this->form))->assertRedirect();

    Queue::assertPushed(GenerateDynamicCardJob::class, 1001);
});

test('peserta yang masih diproses disebut di notifikasi generate ulang', function () {
    Queue::fake();
    $this->form->submissions()->create(['data' => ['nama' => 'Cici'], 'status' => 'processing']);

    $this->actingAs(createSuperAdminUser())->post(route('kartu-bebas.absensi.regenerate', $this->form))
        ->assertSessionHas('inertia.flash_data.toast.type', 'warning')
        ->assertSessionHas('inertia.flash_data.toast.message', '1 kartu masuk antrean generate ulang. Cetak ulang kartu setelah selesai. 1 peserta masih diproses, coba lagi nanti.');
});

test('unduh semua kartu mengemas PNG peserta yang sudah selesai saja', function () {
    Storage::fake('public');
    Storage::disk('public')->put('kartu/ahmad.png', 'png-ahmad');
    $this->participant->update(['file_path' => 'kartu/ahmad.png']);
    $kembar = $this->form->submissions()->create(['data' => ['nama' => 'Ahmad'], 'status' => 'completed', 'file_path' => 'kartu/ahmad-2.png']);
    Storage::disk('public')->put('kartu/ahmad-2.png', 'png-ahmad-2');
    $this->form->submissions()->create(['data' => ['nama' => 'Proses'], 'status' => 'processing', 'file_path' => 'kartu/proses.png']);
    Storage::disk('public')->put('kartu/proses.png', 'png-proses');
    $this->form->submissions()->create(['data' => ['nama' => 'Hilang'], 'status' => 'completed', 'file_path' => 'kartu/hilang.png']);

    $response = $this->actingAs(createSuperAdminUser())->get(route('kartu-bebas.absensi.download-cards', $this->form))
        ->assertOk()->assertDownload('kartu-haji-a-2026-09-29.zip');

    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $nama = collect(range(0, $zip->numFiles - 1))->map(fn (int $i) => $zip->getNameIndex($i))->sort()->values()->all();
    $isi = collect($nama)->map(fn (string $n) => $zip->getFromName($n))->sort()->values()->all();
    $zip->close();

    expect($nama)->toBe(collect(['ahmad-'.substr($this->participant->id, -6).'.png', 'ahmad-'.substr($kembar->id, -6).'.png'])->sort()->values()->all())
        ->and($isi)->toBe(['png-ahmad', 'png-ahmad-2']);
});

test('unduh semua kartu tanpa kartu siap kembali dengan pesan', function () {
    Storage::fake('public');

    $this->actingAs(createSuperAdminUser())->get(route('kartu-bebas.absensi.download-cards', $this->form))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.message', 'Belum ada kartu yang selesai dibuat untuk layout ini.');
});

test('halaman ringan peserta lewat alamat pendek, tanpa token penuh di HTML', function () {
    $kode = substr($this->form->scanner_token, 0, 8);

    $html = str_replace('\\/', '/', $this->get('/p/'.$kode)->assertOk()->assertSee('Haji A')->assertSee('peserta')->getContent());
    expect($html)->not->toContain($this->form->scanner_token)
        ->toContain('/p/'.$kode.'/masuk');
    expect(str_replace('\\/', '/', $this->get('/p/'.$kode.'?mode=pulang')->getContent()))->toContain('/p/'.$kode.'/pulang');

    $this->get('/p/zzzzzzzz')->assertNotFound();
    $this->get('/p/'.substr($kode, 0, 6))->assertNotFound();
});

test('scan lewat halaman ringan memakai logika yang sama, tanpa CSRF dan session', function () {
    $kode = substr($this->form->scanner_token, 0, 8);

    $this->postJson('/p/'.$kode.'/masuk', ['token' => $this->qr])->assertOk()->assertJsonPath('student.type', 'CHECK_IN');
    $this->postJson('/p/'.$kode.'/pulang', ['token' => $this->qr])->assertOk()->assertJsonPath('student.type', 'CHECK_OUT');
    expect(CardAttendance::sole()->check_out)->not->toBeNull();

    $this->form->update(['is_active' => false]);
    $this->postJson('/p/'.$kode.'/masuk', ['token' => $this->qr])->assertNotFound()->assertJsonPath('success', false);
    $this->get('/p/'.$kode)->assertOk()->assertSee('Layout ini sedang dinonaktifkan.');
});

test('halaman absensi admin menampilkan link ringan peserta', function () {
    $this->actingAs(createSuperAdminUser());

    $this->get(route('kartu-bebas.absensi.index', ['layout' => $this->form->id]))
        ->assertInertia(fn (Assert $page) => $page->where('layouts.0.light_url', url('/p/'.substr($this->form->scanner_token, 0, 8))));
});
