<?php

use App\Enums\AppModule;
use App\Models\Classroom;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = createAdminUser();
    $this->schoolId = $this->admin->school_id;
    $this->kelasA = Classroom::factory()->create(['school_id' => $this->schoolId, 'name' => 'VII A']);
    $this->kelasB = Classroom::factory()->create(['school_id' => $this->schoolId, 'name' => 'VII B']);
    $this->murid = fn (string $nama, array $lain = []) => Student::factory()->create([
        'school_id' => $this->schoolId,
        'classroom_id' => $this->kelasA->id,
        'full_name' => $nama,
        'photo_path' => null,
        'photo_drive_file_id' => null,
        'is_active' => true,
        ...$lain,
    ]);
});

test('hanya siswa aktif tanpa foto lokal maupun foto Drive yang terdaftar', function () {
    ($this->murid)('Belum Foto', ['nis' => '1001']);
    ($this->murid)('Sudah Foto Lokal', ['photo_path' => 'photos/students/x.jpg']);
    // Foto yang tersimpan di Drive sudah dihitung punya foto, seperti audit Drive.
    ($this->murid)('Sudah Foto Drive', ['photo_drive_file_id' => 'drive-123']);
    ($this->murid)('Sudah Keluar', ['is_active' => false]);

    $this->actingAs($this->admin)
        ->get(route('admin.siswa-tanpa-foto'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/siswa-tanpa-foto/index')
            ->has('siswa.data', 1)
            ->where('siswa.data.0.full_name', 'BELUM FOTO')
            ->where('siswa.data.0.nis', '1001')
            ->where('siswa.data.0.classroom', 'VII A')
            ->where('ringkasan', ['total' => 3, 'berfoto' => 2, 'tanpa_foto' => 1, 'persen_berfoto' => 66.7])
        );
});

test('siswa tanpa foto dari sekolah lain tidak pernah ikut', function () {
    ($this->murid)('Milik Sendiri');
    Student::factory()->create(['school_id' => School::factory()->create()->id, 'full_name' => 'Sekolah Lain', 'photo_path' => null, 'photo_drive_file_id' => null]);

    $this->actingAs($this->admin)
        ->get(route('admin.siswa-tanpa-foto'))
        ->assertInertia(fn ($page) => $page
            ->has('siswa.data', 1)
            ->where('siswa.data.0.full_name', 'MILIK SENDIRI')
            ->where('ringkasan.total', 1)
        );
});

test('filter kelas mempersempit daftar dan ringkasan', function () {
    ($this->murid)('Anak A');
    ($this->murid)('Anak B', ['classroom_id' => $this->kelasB->id]);
    ($this->murid)('Anak B Berfoto', ['classroom_id' => $this->kelasB->id, 'photo_path' => 'photos/students/b.jpg']);

    $this->actingAs($this->admin)
        ->get(route('admin.siswa-tanpa-foto', ['kelas' => $this->kelasB->id]))
        ->assertInertia(fn ($page) => $page
            ->has('siswa.data', 1)
            ->where('siswa.data.0.full_name', 'ANAK B')
            ->where('ringkasan', ['total' => 2, 'berfoto' => 1, 'tanpa_foto' => 1, 'persen_berfoto' => 50])
            ->where('filters.kelas', $this->kelasB->id)
        );
});

test('kelas milik sekolah lain diabaikan', function () {
    ($this->murid)('Anak A');
    $kelasLain = Classroom::factory()->create(['school_id' => School::factory()->create()->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.siswa-tanpa-foto', ['kelas' => $kelasLain->id]))
        ->assertInertia(fn ($page) => $page
            ->has('siswa.data', 1)
            ->where('filters.kelas', '')
        );
});

test('pencarian mencocokkan nama atau NIS', function () {
    ($this->murid)('Citra Lestari', ['nis' => '2001']);
    ($this->murid)('Dodi Saputra', ['nis' => '2002']);

    $this->actingAs($this->admin)
        ->get(route('admin.siswa-tanpa-foto', ['q' => 'citra']))
        ->assertInertia(fn ($page) => $page->has('siswa.data', 1)->where('siswa.data.0.full_name', 'CITRA LESTARI'));

    $this->actingAs($this->admin)
        ->get(route('admin.siswa-tanpa-foto', ['q' => '2002']))
        ->assertInertia(fn ($page) => $page->has('siswa.data', 1)->where('siswa.data.0.full_name', 'DODI SAPUTRA'));
});

test('daftar dipaginasi', function () {
    foreach (range(1, 30) as $i) {
        ($this->murid)(sprintf('Siswa %02d', $i));
    }

    $this->actingAs($this->admin)
        ->get(route('admin.siswa-tanpa-foto', ['page' => 2]))
        ->assertInertia(fn ($page) => $page
            ->where('siswa.total', 30)
            ->where('siswa.current_page', 2)
            ->has('siswa.data', 5)
        );
});

test('halaman butuh hak akses siswa', function () {
    $role = Role::create(['name' => 'ROLE_'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions([AppModule::Dashboard->permission()]);
    $user = User::factory()->create(['school_id' => $this->schoolId]);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.siswa-tanpa-foto'))->assertForbidden();
});

test('unggah foto dari daftar menyimpan foto dan kembali ke daftar', function () {
    Storage::fake('public');
    Queue::fake();
    $siswa = ($this->murid)('Eka');

    $this->actingAs($this->admin)
        ->from(route('admin.siswa-tanpa-foto'))
        ->post(route('admin.siswa-tanpa-foto.foto', $siswa), ['photo' => UploadedFile::fake()->image('pasfoto.jpg', 600, 800)])
        ->assertRedirect(route('admin.siswa-tanpa-foto'));

    expect($siswa->fresh()->photo_path)->not->toBeNull();
});

test('unggah foto untuk siswa sekolah lain tidak bisa', function () {
    Storage::fake('public');
    $lain = Student::factory()->create(['school_id' => School::factory()->create()->id, 'photo_path' => null]);

    $this->actingAs($this->admin)
        ->post(route('admin.siswa-tanpa-foto.foto', $lain), ['photo' => UploadedFile::fake()->image('pasfoto.jpg', 600, 800)])
        ->assertNotFound();

    expect($lain->fresh()->photo_path)->toBeNull();
});
