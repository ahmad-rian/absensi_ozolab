<?php

use App\Models\AttendanceSchedule;
use App\Models\School;
use App\Models\Student;
use App\Services\PhotoCropService;
use App\Support\SchoolTime;
use App\Support\StudentPhotoStorage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 | Yang membuat gerbang tetap kencang di box Android TV.
 |
 | Halaman scan ringan sudah ramping sejak awal — 17 KB, satu berkas, nol
 | request eksternal. Yang tidak ramping adalah apa yang menyusul SESUDAH kartu
 | ditempel: pas foto PNG 1600 px berukuran megabita, diunduh utuh lalu
 | digambar di kotak 240×320 dan dibuang 2,5 detik kemudian.
 |
 | Berkas ini menjaga tiga hal yang mudah hilang tanpa disadari: fotonya tetap
 | kecil, gerbangnya tetap tanpa session, dan kuota scan tidak lagi dibagi rata
 | dengan seluruh perangkat di gedung yang sama.
 */

beforeEach(function () {
    $this->monday = SchoolTime::now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();
    $this->travelTo($this->monday->copy()->setTime(7, 0));
});

/**
 * PNG BERDERAU, bukan blok warna rata.
 *
 * Foto sungguhan penuh derau, dan PNG memampatkannya dengan buruk — itulah
 * sebabnya thumbnail JPEG bisa puluhan kali lebih kecil. Blok warna rata
 * justru kasus terbaik bagi PNG (4 KB untuk 800×1050), sehingga menguji
 * penyusutan dengan gambar seperti itu menguji hal yang salah.
 */
function pngUji(int $w = 800, int $h = 1050): string
{
    $img = imagecreatetruecolor($w, $h);

    for ($y = 0; $y < $h; $y += 2) {
        for ($x = 0; $x < $w; $x += 2) {
            $warna = imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagefilledrectangle($img, $x, $y, $x + 1, $y + 1, $warna);
        }
    }

    $jalur = tempnam(sys_get_temp_dir(), 'ujipng').'.png';
    imagepng($img, $jalur);
    imagedestroy($img);

    return $jalur;
}

function siswaGerbang(?School $school = null): array
{
    $school ??= School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id]);

    foreach (range(1, 5) as $day) {
        AttendanceSchedule::factory()->create([
            'school_id' => $school->id,
            'classroom_id' => null,
            'day_of_week' => $day,
            'is_active' => true,
        ]);
    }

    return [$school, $student];
}

// ---------------------------------------------------------------- thumbnail

test('menyimpan pas foto ikut menulis thumbnail yang jauh lebih kecil', function () {
    Storage::fake('public');

    $sumber = pngUji();
    $jalur = 'photos/students/sekolah/siswa-abc.png';

    (new PhotoCropService)->cropAndStore($sumber, $jalur, 9, null, crop: false);

    $thumb = StudentPhotoStorage::thumbPath($jalur);
    $disk = Storage::disk('public');

    expect($disk->exists($thumb))->toBeTrue();

    [$tw, $th] = getimagesize($disk->path($thumb));

    expect($tw)->toBeLessThanOrEqual(StudentPhotoStorage::THUMB_WIDTH)
        ->and($th)->toBeLessThanOrEqual(StudentPhotoStorage::THUMB_HEIGHT)
        // Inti seluruh pekerjaan ini: yang diunduh gerbang harus jauh lebih
        // ringan daripada aslinya, bukan sekadar sedikit lebih kecil.
        ->and($disk->size($thumb))->toBeLessThan($disk->size($jalur) / 4);

    @unlink($sumber);
});

test('nama thumbnail diturunkan dari nama fotonya, bukan disimpan terpisah', function () {
    // Kolom kedua berarti dua kebenaran yang bisa menyimpang begitu foto
    // diganti tanpa thumbnailnya ikut diperbarui.
    expect(StudentPhotoStorage::thumbPath('photos/students/a/b-xyz.png'))
        ->toBe('photos/students/a/b-xyz-kecil.jpg');
});

test('respons scan memakai thumbnail kalau ada', function () {
    Storage::fake('public');
    [$school, $student] = siswaGerbang();

    $jalur = 'photos/students/x/siswa.png';
    Storage::disk('public')->put($jalur, 'png-palsu');
    Storage::disk('public')->put(StudentPhotoStorage::thumbPath($jalur), 'jpg-palsu');
    $student->forceFill(['photo_path' => $jalur])->saveQuietly();

    $url = $this->postJson("/scan/{$school->scanner_token}", ['token' => $student->qr_token])
        ->assertOk()
        ->json('student.photo_url');

    expect($url)->toContain('-kecil.jpg');
});

test('respons scan jatuh ke foto asli selama thumbnailnya belum dibuat', function () {
    Storage::fake('public');
    [$school, $student] = siswaGerbang();

    // Keadaan ini nyata dan berlangsung berhari-hari: backfill jalan bertahap
    // di server yang RAM-nya tipis. Selama itu tidak boleh ada satu pun siswa
    // yang wajahnya hilang dari layar gerbang.
    $jalur = 'photos/students/x/siswa.png';
    Storage::disk('public')->put($jalur, 'png-palsu');
    $student->forceFill(['photo_path' => $jalur])->saveQuietly();

    $url = $this->postJson("/scan/{$school->scanner_token}", ['token' => $student->qr_token])
        ->assertOk()
        ->json('student.photo_url');

    expect($url)->toContain('siswa.png')
        ->and($url)->not->toContain('-kecil.jpg');
});

// ----------------------------------------------------------------- backfill

test('backfill menghormati --limit dan melewati yang sudah punya thumbnail', function () {
    Storage::fake('public');
    $school = School::factory()->create();

    $sumber = pngUji(400, 520);

    foreach (range(1, 3) as $i) {
        $s = Student::factory()->create(['school_id' => $school->id]);
        $jalur = "photos/students/{$school->id}/{$s->id}.png";
        Storage::disk('public')->put($jalur, file_get_contents($sumber));
        $s->forceFill(['photo_path' => $jalur])->saveQuietly();
    }

    $this->artisan('foto:thumbnail', ['--limit' => 2])->assertSuccessful();

    $dibuat = collect(Storage::disk('public')->allFiles())
        ->filter(fn ($f) => str_ends_with($f, '-kecil.jpg'))
        ->count();

    expect($dibuat)->toBe(2);

    // Jalan kedua membereskan sisanya dan tidak menulis ulang yang sudah ada.
    $this->artisan('foto:thumbnail', ['--limit' => 10])->assertSuccessful();

    $dibuat = collect(Storage::disk('public')->allFiles())
        ->filter(fn ($f) => str_ends_with($f, '-kecil.jpg'))
        ->count();

    expect($dibuat)->toBe(3);

    @unlink($sumber);
});

test('backfill --dry-run tidak menulis apa pun', function () {
    Storage::fake('public');
    $school = School::factory()->create();

    $sumber = pngUji(400, 520);
    $s = Student::factory()->create(['school_id' => $school->id]);
    $jalur = "photos/students/{$school->id}/{$s->id}.png";
    Storage::disk('public')->put($jalur, file_get_contents($sumber));
    $s->forceFill(['photo_path' => $jalur])->saveQuietly();

    $this->artisan('foto:thumbnail', ['--dry-run' => true])->assertSuccessful();

    expect(Storage::disk('public')->exists(StudentPhotoStorage::thumbPath($jalur)))->toBeFalse();

    @unlink($sumber);
});

// ------------------------------------------------------------------ session

test('rute gerbang tidak menerbitkan cookie session', function () {
    [$school, $student] = siswaGerbang();
    $kode = substr($school->scanner_token, 0, 8);
    $nama = config('session.cookie');

    $punyaSession = function ($response) use ($nama) {
        return collect($response->headers->getCookies())
            ->contains(fn ($c) => $c->getName() === $nama);
    };

    expect($punyaSession($this->get("/scan/{$school->scanner_token}/ringan")))->toBeFalse()
        ->and($punyaSession($this->get("/g/{$kode}")))->toBeFalse()
        ->and($punyaSession(
            $this->postJson("/scan/{$school->scanner_token}", ['token' => $student->qr_token])
        ))->toBeFalse();
});

test('rute publik lain TETAP bersession — pengecualiannya tidak boleh melebar', function () {
    $school = School::factory()->create();
    $nama = config('session.cookie');

    // Konsol scan React, gerbang sholat, dan perpustakaan masing-masing punya
    // pertimbangan sendiri. Kalau salah satu ikut kehilangan session tanpa
    // sengaja, tes ini yang menangkapnya sebelum tayang.
    $response = $this->get("/scan/{$school->scanner_token}");

    expect(collect($response->headers->getCookies())->contains(fn ($c) => $c->getName() === $nama))
        ->toBeTrue();
});

// ------------------------------------------------------------------ throttle

test('kuota scan dihitung per sekolah, bukan per alamat IP', function () {
    [$sekolahA, $siswaA] = siswaGerbang();
    [$sekolahB, $siswaB] = siswaGerbang();

    // Semua gerbang satu sekolah keluar lewat satu IP publik. Sebelum ini,
    // sekolah yang ramai menghabiskan kuota sekolah lain yang kebetulan
    // berbagi jaringan — dan 429 di layar gerbang terlihat persis seperti
    // "kadang berhenti merespons".
    $this->postJson("/scan/{$sekolahA->scanner_token}", ['token' => $siswaA->qr_token])->assertOk();

    $batasA = $this->postJson("/scan/{$sekolahA->scanner_token}", ['token' => $siswaA->qr_token])
        ->headers->get('X-RateLimit-Remaining');

    $batasB = $this->postJson("/scan/{$sekolahB->scanner_token}", ['token' => $siswaB->qr_token])
        ->headers->get('X-RateLimit-Remaining');

    // Sekolah B baru memakai satu jatah, sekolah A sudah dua — kalau kuncinya
    // masih IP, keduanya akan menunjuk penghitung yang sama.
    expect((int) $batasB)->toBeGreaterThan((int) $batasA);
});

// ----------------------------------------------------------------- 404 polos

test('kode gerbang yang tidak dikenal dijawab teks polos, bukan halaman React', function () {
    $response = $this->get('/g/tidakada');

    $response->assertNotFound();

    // Halaman yang sengaja dibuat seringan mungkin tidak boleh gagal dengan
    // mengirim bundel React ke perangkat yang justru tidak sanggup
    // menjalankannya.
    expect($response->headers->get('Content-Type'))->toContain('text/plain')
        ->and($response->getContent())->not->toContain('data-page');
});

test('token ringan yang tidak dikenal juga dijawab teks polos', function () {
    $response = $this->get('/scan/token-karangan/ringan');

    $response->assertNotFound();

    expect($response->headers->get('Content-Type'))->toContain('text/plain');
});

// -------------------------------------------------------------- jumlah query

test('satu scan sukses menjalankan jumlah query yang tetap', function () {
    [$school, $student] = siswaGerbang();

    $jumlah = 0;
    DB::listen(function () use (&$jumlah) {
        $jumlah++;
    });

    $this->postJson("/scan/{$school->scanner_token}", ['token' => $student->qr_token])->assertOk();

    // Angka pastinya tidak penting; yang dijaga adalah ia tidak diam-diam
    // naik. Relasi `classroom` di-eager-load di StudentLookup, dan lazy-load
    // yang kembali ke sini berarti satu query tambahan untuk setiap kartu yang
    // ditempel sepanjang hari.
    /*
        Dua puluh tiga, dan hanya TUJUH di antaranya pekerjaan scan itu
        sendiri: cari sekolah, cari siswa, muat kelas, dua jadwal, cek
        duplikat, simpan kehadiran.

        Enam belas sisanya milik dua pendengar `StudentCheckedIn` yang berjalan
        SINKRON di dalam request — keduanya memuat ulang siswa, sekolah, profil
        orang tua, dan saluran notifikasi sebelum respons dikirim ke gerbang.
        Itu latensi nyata pada tiap kartu yang ditempel, tapi membuatnya
        antre menyentuh kebijakan kuota WhatsApp; dicatat, bukan diubah diam-diam.

        Angkanya dipatok sebagai LANGIT-LANGIT, bukan target: yang dijaga
        adalah ia tidak naik lagi tanpa ada yang sadar.
    */
    expect($jumlah)->toBeLessThanOrEqual(23);
});
