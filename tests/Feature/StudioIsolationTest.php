<?php

use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Models\StudioToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Penjaga terpenting di seluruh jalur Studio.
 *
 * Global scope `school` membaca `auth()->user()->school_id`. Di jalur bertoken
 * TIDAK ADA user yang login, jadi scope-nya mengembalikan null dan mati
 * sepenuhnya — `Student::query()` akan memuat siswa dari SEMUA sekolah.
 *
 * Artinya penyaringan di sini bukan lapisan kedua di atas scope; ia satu-satunya
 * lapisan. Kalau ia terlewat, tidak ada galat yang muncul — yang muncul adalah
 * kebocoran yang diam.
 */
beforeEach(function () {
    $this->sekolahA = School::factory()->create(['name' => 'SMA SATU']);
    $this->sekolahB = School::factory()->create(['name' => 'SMA DUA']);

    $this->siswaA = Student::factory()->create([
        'school_id' => $this->sekolahA->id,
        'full_name' => 'ANAK SEKOLAH SATU',
        'qr_token' => '11111.aaaabbbbccccddddeeeeffff',
    ]);

    $this->siswaB = Student::factory()->create([
        'school_id' => $this->sekolahB->id,
        'full_name' => 'ANAK SEKOLAH DUA',
        'qr_token' => '22222.aaaabbbbccccddddeeeeffff',
    ]);

    [, $this->kunciA] = StudioToken::terbitkan('Studio A', $this->sekolahA->id);
    [, $this->kunciSemua] = StudioToken::terbitkan('Studio pusat', null);
});

function studio(string $mentah): PendingRequestShim
{
    return new PendingRequestShim($mentah);
}

/** Pembungkus tipis supaya tiap panggilan membawa tokennya sendiri. */
class PendingRequestShim
{
    public function __construct(private string $mentah) {}

    public function get(string $jalur)
    {
        return test()->withHeader('Authorization', 'Bearer '.$this->mentah)->getJson($jalur);
    }

    public function post(string $jalur, array $data = [])
    {
        return test()->withHeader('Authorization', 'Bearer '.$this->mentah)->postJson($jalur, $data);
    }
}

test('pencarian siswa tidak pernah menyeberang ke sekolah lain', function () {
    $isi = studio($this->kunciA)->get('/api/studio/students')->assertOk()->json('students');

    expect(collect($isi)->pluck('id'))->toContain($this->siswaA->id)
        ->not->toContain($this->siswaB->id);
});

test('pencarian dengan kata kunci pun tetap terkurung', function () {
    // Kata kunci yang cocok ke dua-duanya. Tanpa pembatas sekolah, siswa B ikut.
    $isi = studio($this->kunciA)->get('/api/studio/students?search=ANAK')->assertOk()->json('students');

    expect(collect($isi)->pluck('full_name'))->toContain('ANAK SEKOLAH SATU')
        ->not->toContain('ANAK SEKOLAH DUA');
});

test('siswa sekolah lain dijawab 404, bukan 403', function () {
    // 403 sudah membocorkan bahwa siswanya ADA.
    studio($this->kunciA)->get('/api/studio/students/'.$this->siswaB->id)->assertNotFound();
});

test('kartu sekolah lain tidak dikenali walau tokennya sah', function () {
    studio($this->kunciA)->get('/api/studio/students/by-qr/'.$this->siswaB->qr_token)
        ->assertNotFound();

    studio($this->kunciA)->get('/api/studio/students/by-qr/'.$this->siswaA->qr_token)
        ->assertOk();
});

test('daftar sekolah hanya memuat sekolah milik token', function () {
    $isi = studio($this->kunciA)->get('/api/studio/schools')->assertOk()->json('schools');

    expect(collect($isi)->pluck('id'))->toContain($this->sekolahA->id)
        ->not->toContain($this->sekolahB->id);
});

test('kelas sekolah lain tidak bisa ditelusuri', function () {
    studio($this->kunciA)->get('/api/studio/schools/'.$this->sekolahB->id.'/classrooms')
        ->assertNotFound();
});

test('siswa dalam kelas sekolah lain tidak bisa didaftar', function () {
    $kelasB = Classroom::factory()->create(['school_id' => $this->sekolahB->id]);

    studio($this->kunciA)->get('/api/studio/classrooms/'.$kelasB->id.'/students')
        ->assertNotFound();
});

test('salinan massal sekolah lain tidak bisa diambil', function () {
    studio($this->kunciA)->get('/api/studio/schools/'.$this->sekolahB->id.'/students')
        ->assertNotFound();
});

/**
 * Endpoint salinan massal, dipakai Tyas Studio untuk membangun cerminnya.
 *
 * Yang membedakannya dari dua endpoint siswa yang lain: ia HARUS memuat siswa
 * yang belum punya kelas. Siswa baru berada di keadaan itu berhari-hari, dan
 * cermin yang melewatkannya membuat mereka tidak bisa difoto.
 */
test('salinan massal memuat siswa yang belum punya kelas', function () {
    $tanpaKelas = Student::factory()->create([
        'school_id' => $this->sekolahA->id,
        'classroom_id' => null,
        'full_name' => 'ANAK BELUM SEKELAS',
    ]);

    $isi = studio($this->kunciA)->get('/api/studio/schools/'.$this->sekolahA->id.'/students')
        ->assertOk()
        ->json('students');

    expect(collect($isi)->pluck('id'))
        ->toContain($tanpaKelas->id)
        ->toContain($this->siswaA->id)
        ->not->toContain($this->siswaB->id);
});

test('salinan massal berhalaman dan menyebut totalnya', function () {
    Student::factory()->count(5)->create(['school_id' => $this->sekolahA->id]);

    $respons = studio($this->kunciA)
        ->get('/api/studio/schools/'.$this->sekolahA->id.'/students?per_page=2')
        ->assertOk();

    // 5 tambahan + siswaA dari beforeEach.
    expect($respons->json('meta.total'))->toBe(6)
        ->and($respons->json('meta.last_page'))->toBe(3)
        ->and($respons->json('students'))->toHaveCount(2);
});

test('salinan massal tidak pernah mengirim qr_token', function () {
    $isi = studio($this->kunciA)->get('/api/studio/schools/'.$this->sekolahA->id.'/students')
        ->assertOk();

    expect($isi->getContent())->not->toContain($this->siswaA->qr_token);
});

test('unggah foto ke siswa sekolah lain ditolak sebelum apa pun tersimpan', function () {
    Storage::fake('local');
    Storage::fake('public');
    Queue::fake();

    studio($this->kunciA)->post('/api/studio/students/'.$this->siswaB->id.'/photo', [
        'ori' => UploadedFile::fake()->image('foto.jpg', 600, 800),
        'crop' => ['sx' => 0, 'sy' => 0, 'sw' => 0.76, 'sh' => 1],
    ])->assertNotFound();

    Queue::assertNothingPushed();
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

/**
 * Token lintas sekolah memang ada dan memang boleh melihat semuanya — operator
 * studio memotret siswa dari beberapa sekolah dalam satu hari. Yang penting
 * adalah itu keputusan sadar, bukan akibat scope yang mati.
 */
test('token lintas sekolah melihat kedua sekolah', function () {
    $isi = studio($this->kunciSemua)->get('/api/studio/students')->assertOk()->json('students');

    expect(collect($isi)->pluck('id'))
        ->toContain($this->siswaA->id)
        ->toContain($this->siswaB->id);
});

/**
 * Penjaga tingkat sumber. Kalau suatu saat ada yang menambah endpoint Studio
 * dengan `Student::where(...)` telanjang, tes di atas tidak akan menangkapnya —
 * mereka hanya menguji endpoint yang sudah ada.
 */
test('setiap pembacaan siswa di controller Studio lewat pembatas sekolah', function () {
    $sumber = file_get_contents(app_path('Http/Controllers/Api/StudioController.php'));

    // Satu-satunya penyebutan Student:: yang boleh adalah yang langsung
    // dibungkus batasi(), plus type-hint di dokumentasi.
    preg_match_all('/Student::\w+\(/', $sumber, $cocok);

    foreach ($cocok[0] as $panggilan) {
        expect($sumber)->toContain('batasi('.$panggilan);
    }
});
