<?php

use App\Jobs\ApplyStudentImportJob;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentImportJob;
use App\Models\User;
use App\Support\NisSementara;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Berkas contoh berisi satu baris tiap kelompok: siswa baru, siswa lama yang
 * cocok lewat NISN, dan baris yang kelasnya tidak ada di sekolah ini.
 */
function importCsvFile(): UploadedFile
{
    $csv = implode("\n", [
        'NISN,NIS,Nama,Kelas,JK,Agama',
        '3100000001,2025001,Ahmad Fauzi,7A,L,Islam',
        '3100000002,2025002,Siti Diperbarui,7A,P,Islam',
        '3100000003,2025003,Budi Gagal,9Z,L,Islam',
    ]);

    return UploadedFile::fake()->createWithContent('siswa.csv', $csv);
}

function uploadImport(User $admin): string
{
    Classroom::factory()->create(['school_id' => $admin->school_id, 'name' => '7A']);
    Student::factory()->create([
        'school_id' => $admin->school_id,
        'nisn' => '3100000002',
        'nis' => '2025002',
        'full_name' => 'Siti Lama',
    ]);

    $response = test()->actingAs($admin)
        ->post(route('admin.siswa.import.upload'), ['file' => importCsvFile()]);

    $response->assertRedirect();

    return (string) $response->headers->get('Location');
}

test('unggah csv menghasilkan pratinjau tiga kelompok', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->get(uploadImport($admin))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/siswa/import')
            ->has('preview.groups.create', 1)
            ->has('preview.groups.update', 1)
            ->has('preview.groups.reject', 1)
            ->where('preview.summary.total', 3)
            ->where('preview.groups.create.0.classroom_name', '7A')
            ->where('preview.groups.update.0.existing_name', 'SITI LAMA')
            ->where('preview.groups.reject.0.reason', fn (string $reason) => str_contains($reason, '9Z'))
        );
});

test('terapkan membuat baris job dan mengantrekan job', function () {
    Queue::fake();

    $admin = createAdminUser();
    $reviewUrl = uploadImport($admin);
    $key = basename($reviewUrl);

    $this->actingAs($admin)
        ->post(route('admin.siswa.import.apply', ['key' => $key]))
        ->assertRedirect(route('admin.siswa.import'));

    $job = StudentImportJob::query()->withoutGlobalScope('school')->first();

    expect($job->school_id)->toBe($admin->school_id)
        ->and($job->created_by)->toBe($admin->id)
        ->and($job->filename)->toBe('siswa.csv')
        ->and($job->total_rows)->toBe(3)
        ->and($job->status)->toBe(StudentImportJob::STATUS_PENDING);

    Queue::assertPushed(ApplyStudentImportJob::class, fn (ApplyStudentImportJob $queued): bool => $queued->importJobId === $job->id);
});

test('admin sekolah lain tidak bisa membuka staging milik sekolah ini', function () {
    $admin = createAdminUser();
    $reviewUrl = uploadImport($admin);
    $key = basename($reviewUrl);

    $penyusup = createAdminUser();

    $this->actingAs($penyusup)
        ->get(route('admin.siswa.import.review', ['key' => $key]))
        ->assertForbidden();

    $this->actingAs($penyusup)
        ->post(route('admin.siswa.import.apply', ['key' => $key]))
        ->assertForbidden();

    expect(StudentImportJob::query()->withoutGlobalScope('school')->count())->toBe(0);
});

test('unduh template mengembalikan xlsx berisi header dan dua baris contoh', function () {
    $admin = createAdminUser();

    $response = $this->actingAs($admin)->get(route('admin.siswa.import.template'));

    $response->assertOk();

    $rows = xlsxRows($response);

    expect($rows)->toHaveCount(3)
        ->and(array_slice($rows[0], 0, 4))->toBe(['NISN', 'NIS', 'Nama', 'Kelas'])
        // NISN berawalan nol harus bertahan; itu alasan templatenya bukan CSV.
        ->and($rows[1][0])->toBe('0071234567');
});

test('berkas dengan header tak dikenali ditolak dengan pesan', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->post(route('admin.siswa.import.upload'), [
            'file' => UploadedFile::fake()->createWithContent('salah.csv', "Kolom A,Kolom B\n1,2"),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('file');
});

/**
 * Tulis ulang baris hasil unduhan menjadi berkas xlsx untuk diunggah lagi —
 * meniru operator yang melengkapi datanya di Excel.
 *
 * @param  array<int, array<int, string>>  $rows
 */
function unggahUlangData(User $admin, array $rows): string
{
    $path = sys_get_temp_dir().'/unggah-ulang-'.uniqid().'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    $response = test()->actingAs($admin)->post(route('admin.siswa.import.upload'), [
        'file' => new UploadedFile($path, 'data-siswa.xlsx', null, null, true),
    ]);
    $response->assertRedirect();

    return (string) $response->headers->get('Location');
}

test('unduh data siswa berisi ID Sistem, mengosongkan NIS sementara, dan hanya sekolah ini', function () {
    $admin = createAdminUser();
    $kelas = Classroom::factory()->create(['school_id' => $admin->school_id, 'name' => '7A']);
    $lain = Classroom::factory()->create(['school_id' => $admin->school_id, 'name' => '7B']);
    $asli = Student::factory()->create([
        'school_id' => $admin->school_id, 'classroom_id' => $kelas->id,
        'nisn' => '0071234567', 'nis' => '17390', 'full_name' => 'Galuh Aryani', 'is_active' => true,
    ]);
    $cepat = Student::factory()->create([
        'school_id' => $admin->school_id, 'classroom_id' => $kelas->id,
        'nisn' => null, 'nis' => NisSementara::buat(), 'full_name' => 'Tanpa Nomor', 'is_active' => true,
    ]);
    Student::factory()->create(['school_id' => $admin->school_id, 'classroom_id' => $lain->id, 'full_name' => 'Kelas Lain', 'is_active' => true]);
    Student::factory()->create(['full_name' => 'Sekolah Lain', 'is_active' => true]);

    $rows = xlsxRows($this->actingAs($admin)->get(route('admin.siswa.import.data', ['classroom_id' => $kelas->id]))->assertOk());
    $perId = collect($rows)->skip(1)->keyBy(0);

    expect(array_slice($rows[0], 0, 5))->toBe(['ID Sistem', 'NISN', 'NIS', 'Nama', 'Kelas'])
        ->and($perId)->toHaveCount(2)
        ->and($perId[$asli->id][1])->toBe('0071234567')
        ->and($perId[$asli->id][2])->toBe('17390')
        ->and($perId[$asli->id][4])->toBe('7A')
        ->and($perId[$cepat->id][2])->toBe('');

    // Tanpa filter: semua kelas sekolah ini, tetap tanpa sekolah lain.
    $semua = collect(xlsxRows($this->actingAs($admin)->get(route('admin.siswa.import.data'))))->skip(1)->pluck(3);
    expect($semua)->toContain('KELAS LAIN')->not->toContain('SEKOLAH LAIN');

    // Id kelas milik sekolah lain diabaikan, bukan dipakai menyaring.
    $asing = Classroom::factory()->create();
    $this->actingAs($admin)->get(route('admin.siswa.import.data', ['classroom_id' => $asing->id]))->assertOk();
});

test('unduh, lengkapi nama orang tua, unggah ulang: siswa yang sama diperbarui tanpa duplikat', function () {
    $admin = createAdminUser();
    $kelas = Classroom::factory()->create(['school_id' => $admin->school_id, 'name' => '7A']);
    $siswa = Student::factory()->create([
        'school_id' => $admin->school_id, 'classroom_id' => $kelas->id, 'is_active' => true,
        'nisn' => '0071234567', 'nis' => '17390', 'full_name' => 'Galuh Aryani',
        'birth_place' => 'Purwokerto', 'address' => 'Jl. Lama 1', 'parent_name' => null, 'parent_phone' => null,
    ]);
    $utuh = Student::factory()->create([
        'school_id' => $admin->school_id, 'classroom_id' => $kelas->id, 'is_active' => true,
        'nisn' => '0071234568', 'nis' => '17391', 'full_name' => 'Tidak Disentuh',
    ]);

    $rows = xlsxRows($this->actingAs($admin)->get(route('admin.siswa.import.data')));
    $header = $rows[0];
    $kolom = array_flip($header);

    foreach ($rows as $i => $row) {
        if ($i > 0 && $row[0] === $siswa->id) {
            $rows[$i][$kolom['Nama Orang Tua']] = 'Budi Santoso';
            $rows[$i][$kolom['No HP']] = '081234567890';
            // Dikosongkan operator: tidak boleh menghapus data lama.
            $rows[$i][$kolom['Alamat']] = '';
        }
    }

    $reviewUrl = unggahUlangData($admin, $rows);

    $this->actingAs($admin)->get($reviewUrl)->assertOk()->assertInertia(fn ($page) => $page
        ->where('preview.summary.create', 0)
        ->where('preview.summary.update', 1)
        ->where('preview.summary.unchanged', 1)
        ->where('preview.summary.reject', 0)
        ->where('preview.groups.unchanged.0.existing_name', 'TIDAK DISENTUH')
        ->where('preview.groups.update.0.changes', fn ($changes) => collect($changes)->pluck('kolom')->sort()->values()->all() === ['Nama Orang Tua', 'No HP']
            && collect($changes)->firstWhere('kolom', 'Nama Orang Tua')['lama'] === ''
            && collect($changes)->firstWhere('kolom', 'Nama Orang Tua')['baru'] === 'Budi Santoso')
    );

    $jumlahAwal = Student::withoutGlobalScopes()->where('school_id', $admin->school_id)->count();

    $this->actingAs($admin)->post(route('admin.siswa.import.apply', ['key' => basename($reviewUrl)]))->assertRedirect();

    $siswa->refresh();
    $job = StudentImportJob::query()->withoutGlobalScope('school')->latest()->first();

    expect(Student::withoutGlobalScopes()->where('school_id', $admin->school_id)->count())->toBe($jumlahAwal)
        ->and($siswa->parent_name)->toBe('BUDI SANTOSO')
        ->and($siswa->parent_phone)->toBe('081234567890')
        ->and($siswa->parent_profile_id)->not->toBeNull()
        ->and($siswa->address)->toBe('Jl. Lama 1')
        ->and($siswa->birth_place)->toBe('Purwokerto')
        ->and($siswa->nis)->toBe('17390')
        ->and($utuh->fresh()->updated_at->eq($utuh->updated_at))->toBeTrue()
        // Baris tidak berubah tidak dihitung: total = diperbarui saja.
        ->and($job->total_rows)->toBe(1)
        ->and($job->updated_count)->toBe(1)
        ->and($job->created_count)->toBe(0);
});

test('siswa pendaftaran cepat dilengkapi NISN dan NIS lewat ID Sistem tanpa terbuat ganda', function () {
    $admin = createAdminUser();
    $kelas = Classroom::factory()->create(['school_id' => $admin->school_id, 'name' => '7A']);
    $cepat = Student::factory()->create([
        'school_id' => $admin->school_id, 'classroom_id' => $kelas->id, 'is_active' => true,
        'nisn' => null, 'nis' => NisSementara::buat(), 'full_name' => 'Ardian Yusuf',
    ]);

    $rows = xlsxRows($this->actingAs($admin)->get(route('admin.siswa.import.data')));
    $kolom = array_flip($rows[0]);
    $rows[1][$kolom['NISN']] = '0136394963';
    $rows[1][$kolom['NIS']] = '17312';

    $reviewUrl = unggahUlangData($admin, $rows);
    $this->actingAs($admin)->post(route('admin.siswa.import.apply', ['key' => basename($reviewUrl)]))->assertRedirect();

    expect(Student::withoutGlobalScopes()->where('school_id', $admin->school_id)->count())->toBe(1)
        ->and($cepat->fresh()->nisn)->toBe('0136394963')
        ->and($cepat->fresh()->nis)->toBe('17312');
});
