<?php

use App\Http\Middleware\EnsureGenerateMassalAktif;
use App\Jobs\GeneratePhotoSheetBatchJob;
use App\Models\Classroom;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => config(['cards.generate_massal' => false]));

test('semua pintu generate massal dijaga saklar', function (string $rute) {
    expect(Route::getRoutes()->getByName($rute)->gatherMiddleware())->toContain('generate-massal');
})->with([
    'generate kartu (halaman)' => 'admin.generate-kartu',
    'generate kartu (jalan)' => 'admin.generate-kartu.jalan',
    'riwayat kartu generate' => 'admin.card-generation.generate',
    'pas foto (halaman)' => 'admin.photo-sheets',
    'pas foto (jalan)' => 'admin.photo-sheets.store',
    'generate ulang semua kartu haji' => 'kartu-bebas.absensi.regenerate',
]);

test('generate per siswa tidak ikut dimatikan', function (string $rute) {
    expect(Route::getRoutes()->getByName($rute)->gatherMiddleware())->not->toContain('generate-massal');
})->with([
    'admin.siswa.regenerate.cards',
    'admin.siswa.regenerate.photo-sheet',
    'admin.siswa.regenerate.photo',
    'kartu-bebas.peserta.generate',
]);

test('halaman generate massal mengarah ke dashboard dengan pesan saat mati', function () {
    $this->actingAs(createAdminUser())
        ->get(route('admin.photo-sheets'))
        ->assertRedirect(route('dashboard'));
});

test('generate massal yang dikirim saat mati tidak mengantrekan apa pun', function () {
    Queue::fake();
    $admin = createAdminUser();
    $kelas = Classroom::factory()->create(['school_id' => $admin->school_id]);

    $this->actingAs($admin)
        ->from(route('dashboard'))
        ->post(route('admin.photo-sheets.store'), ['classroom_id' => $kelas->id])
        ->assertRedirect(route('dashboard'));

    Queue::assertNotPushed(GeneratePhotoSheetBatchJob::class);
});

test('permintaan JSON mendapat 403 berisi alasan', function () {
    $this->actingAs(createAdminUser())
        ->postJson(route('admin.photo-sheets.store'))
        ->assertForbidden()
        ->assertJson(['message' => EnsureGenerateMassalAktif::PESAN]);
});

test('saklar dibagikan ke frontend supaya menu dan tombolnya hilang', function (bool $nyala) {
    config(['cards.generate_massal' => $nyala]);

    $this->actingAs(createAdminUser())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('generateMassal', $nyala));
})->with([true, false]);
