<?php

use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 | Memasang pas foto dari layar generate massal, lintas sekolah.
 |
 | Layar `/admin/generate-kartu` memilih sekolah lewat query string dan tidak
 | pernah menyentuh sesi. Sementara itu `Student` disaring global scope `school`
 | yang membaca `auth()->user()->school_id` — nilai yang untuk super admin
 | diambil `SetCurrentSchool` dari `session('current_school_id')`.
 |
 | Akibatnya tombol "Pasang foto" yang menautkan ke halaman siswa 404 setiap
 | kali sekolah yang dipilih berbeda dari sekolah aktif di sesi, yaitu keadaan
 | normal layar ini. Endpoint di sini yang memperbaikinya, dan tes pertama di
 | bawah adalah seluruh alasan keberadaannya.
 |
 | Yang TIDAK boleh ikut longgar: admin biasa tetap terkunci di sekolahnya.
 | Penjagaan itu diuji di StudentPhotoUploadTest dan harus tetap 404 di sana.
 */

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();

    $this->super = createSuperAdminUser();

    // Sekolah SASARAN, sengaja bukan sekolah super admin dan bukan sekolah
    // yang akan dipasang di sesi.
    $this->sasaran = School::factory()->create();
    $this->kelas = Classroom::factory()->create(['school_id' => $this->sasaran->id]);
    $this->siswa = Student::factory()->create([
        'school_id' => $this->sasaran->id,
        'classroom_id' => $this->kelas->id,
    ]);
});

function unggahMassal(Student $siswa, array $ganti = [])
{
    return test()->actingAs(test()->super)
        // Sesi menunjuk sekolah milik super admin, BUKAN sekolah siswanya.
        // Inilah pemisahan yang dulu menghasilkan 404.
        ->withSession(['current_school_id' => test()->super->school_id])
        ->post("/admin/generate-kartu/siswa/{$siswa->id}/foto", array_merge([
            'photo' => UploadedFile::fake()->image('pasfoto.jpg', 600, 800),
            'school_id' => $siswa->school_id,
        ], $ganti));
}

test('super admin memasang foto siswa sekolah lain dan berhasil', function () {
    unggahMassal($this->siswa)->assertRedirect();

    $siswa = $this->siswa->fresh();

    expect($siswa->photo_path)
        ->toStartWith('photos/students/'.$this->sasaran->id.'/')
        ->toEndWith('.png')
        ->and(Storage::disk('public')->exists($siswa->photo_path))->toBeTrue();
});

test('balasannya kembali ke layar generate, bukan ke halaman detail siswa', function () {
    // Kalau redirect jalur lama ikut terbawa, modalnya hilang di tengah
    // antrean dan operator harus membukanya lagi untuk tiap anak.
    test()->actingAs($this->super)
        ->withSession(['current_school_id' => $this->super->school_id])
        ->from('/admin/generate-kartu?school_id='.$this->sasaran->id)
        ->post("/admin/generate-kartu/siswa/{$this->siswa->id}/foto", [
            'photo' => UploadedFile::fake()->image('pasfoto.jpg', 600, 800),
            'school_id' => $this->sasaran->id,
        ])
        ->assertRedirect('/admin/generate-kartu?school_id='.$this->sasaran->id);
});

test('foto lama dibuang saat diganti', function () {
    // Nama berkas memuat 16 karakter acak, jadi yang lama TIDAK tertimpa — ia
    // tertinggal selamanya kalau tidak dihapus. Disk penuh sudah pernah
    // menjatuhkan server ini, dan langkah inilah yang paling mudah hilang
    // ketika logikanya diangkat ke servis bersama.
    unggahMassal($this->siswa)->assertRedirect();
    $lama = $this->siswa->fresh()->photo_path;

    unggahMassal($this->siswa->fresh())->assertRedirect();
    $baru = $this->siswa->fresh()->photo_path;

    expect($baru)->not->toBe($lama)
        ->and(Storage::disk('public')->exists($lama))->toBeFalse()
        ->and(Storage::disk('public')->exists($baru))->toBeTrue();
});

test('school_id yang tidak cocok dengan siswanya ditolak', function () {
    $sekolahLain = School::factory()->create();

    // Scope tenant sengaja dilepas di endpoint ini, jadi pencocokan sekolah
    // adalah satu-satunya yang mencegah id nyasar diproses diam-diam.
    unggahMassal($this->siswa, ['school_id' => $sekolahLain->id])
        ->assertSessionHasErrors('photo');

    expect($this->siswa->fresh()->photo_path)->toBeNull();
});

test('siswa yang tidak ada dijawab 404', function () {
    test()->actingAs($this->super)
        ->post('/admin/generate-kartu/siswa/tidak-ada/foto', [
            'photo' => UploadedFile::fake()->image('pasfoto.jpg', 600, 800),
            'school_id' => $this->sasaran->id,
        ])
        ->assertNotFound();
});

test('non-superadmin ditolak di endpoint ini', function () {
    $admin = createAdminUser();

    // Permission `card-generation.access` dipegang ADMIN juga — yang menutup
    // pintu di sini middleware `super-admin`, bukan permission-nya.
    test()->actingAs($admin)
        ->post("/admin/generate-kartu/siswa/{$this->siswa->id}/foto", [
            'photo' => UploadedFile::fake()->image('pasfoto.jpg', 600, 800),
            'school_id' => $this->sasaran->id,
        ])
        ->assertForbidden();

    expect($this->siswa->fresh()->photo_path)->toBeNull();
});

test('berkas rusak dijawab pesan validasi, bukan galat 500', function () {
    // Aturan `image`/`mimes` mengendus isi berkas, tapi gambar yang terpotong
    // tetap lolos dan baru meledak di GD. Penangkapnya ikut terangkat ke
    // servis; tes ini yang membuktikannya tidak tertinggal.
    $rusak = UploadedFile::fake()->create('pasfoto.png', 20, 'image/png');

    unggahMassal($this->siswa, ['photo' => $rusak])
        ->assertSessionHasErrors('photo');

    expect($this->siswa->fresh()->photo_path)->toBeNull();
});

test('berkas di atas 5 MB ditolak', function () {
    unggahMassal($this->siswa, ['photo' => UploadedFile::fake()->create('besar.jpg', 6000, 'image/jpeg')])
        ->assertSessionHasErrors('photo');
});

test('ringkasan layar ikut berubah setelah foto terpasang', function () {
    $lain = Student::factory()->create([
        'school_id' => $this->sasaran->id,
        'classroom_id' => $this->kelas->id,
    ]);

    $layar = fn () => test()->actingAs($this->super)
        ->get('/admin/generate-kartu?school_id='.$this->sasaran->id);

    $layar()->assertInertia(fn ($page) => $page
        ->where('ringkasan.berfoto', 0)
        ->count('ringkasan.tanpa_foto', 2));

    unggahMassal($this->siswa)->assertRedirect();

    // Hitungan dan daftarnya sama-sama diturunkan dari `photo_path`, jadi
    // keduanya harus bergerak bersama — kalau tidak, tombol Generate bisa
    // terbuka padahal masih ada yang kurang.
    $layar()->assertInertia(fn ($page) => $page
        ->where('ringkasan.berfoto', 1)
        ->count('ringkasan.tanpa_foto', 1)
        ->where('ringkasan.tanpa_foto.0.id', $lain->id));
});
