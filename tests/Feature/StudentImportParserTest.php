<?php

use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Services\Import\StudentImportParser;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Direktori berkas contoh. Berkasnya ditulis ulang tiap kali test jalan supaya
 * isinya selalu sinkron dengan ekspektasi test.
 */
function importFixtureDir(): string
{
    $dir = base_path('tests/fixtures/imports');

    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}

/**
 * @param  list<list<string>>  $rows
 */
function writeImportCsv(string $name, array $rows): string
{
    $path = importFixtureDir().'/'.$name.'.csv';
    $handle = fopen($path, 'w');

    foreach ($rows as $row) {
        fputcsv($handle, $row, ',', '"', '\\');
    }

    fclose($handle);

    return $path;
}

/**
 * Berbeda dari CSV, hasilnya TIDAK ditaruh di direktori fixture.
 *
 * XLSX itu arsip zip bertimestamp, jadi isinya berbeda tiap kali ditulis. Selama
 * ia mendarat di direktori fixture yang ikut ter-commit, setiap kali suite
 * dijalankan pohon kerja jadi kotor — dan run berikutnya membaca keadaan yang
 * berbeda lalu gagal di tempat yang tidak ada hubungannya dengan perubahannya.
 *
 * @param  list<list<string>>  $rows
 */
function writeImportXlsx(string $name, array $rows): string
{
    $path = sys_get_temp_dir().'/import-'.$name.'.xlsx';

    $writer = new XlsxWriter;
    $writer->openToFile($path);

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

function makeImportSchool(string $classroomName = '7A'): array
{
    $school = School::factory()->create();
    $classroom = Classroom::factory()->create([
        'school_id' => $school->id,
        'name' => $classroomName,
    ]);

    return [$school, $classroom];
}

beforeEach(function () {
    $this->parser = new StudentImportParser;
});

it('mengenali header dengan huruf besar dan spasi acak', function () {
    [$school] = makeImportSchool();

    $path = writeImportCsv('header-acak', [
        ['  NISN ', 'Nomor Induk', 'NAMA  LENGKAP', 'Rombel', 'Jenis Kelamin', 'Agama', 'Tanggal Lahir'],
        ['1234567890', '2025001', 'Budi Santoso', '7A', 'L', 'Islam', '01/02/2011'],
    ]);

    $result = $this->parser->parse($path, 'csv', $school->id);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['action'])->toBe('create')
        ->and($result['rows'][0]['data']['full_name'])->toBe('Budi Santoso')
        ->and($result['rows'][0]['data']['nisn'])->toBe('1234567890')
        ->and($result['rows'][0]['data']['nis'])->toBe('2025001')
        ->and($result['rows'][0]['data']['religion'])->toBe('ISLAM')
        ->and($result['rows'][0]['data']['birth_date'])->toBe('2011-02-01');
});

it('memperbarui siswa yang NISN-nya cocok, bukan membuat duplikat', function () {
    [$school, $classroom] = makeImportSchool();

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => $classroom->id,
        'nisn' => '9988776655',
        'nis' => '2025010',
        'full_name' => 'Nama Lama',
    ]);

    $path = writeImportCsv('nisn-cocok', [
        ['nisn', 'nama', 'kelas', 'jk'],
        ['9988776655', 'Nama Baru', '7A', 'P'],
    ]);

    $result = $this->parser->parse($path, 'csv', $school->id);

    expect($result['summary']['update'])->toBe(1)
        ->and($result['summary']['create'])->toBe(0)
        ->and($result['rows'][0]['student_id'])->toBe($student->id)
        ->and($result['rows'][0]['data']['full_name'])->toBe('Nama Baru');
});

it('mencocokkan lewat NIS saat kolom NISN kosong', function () {
    [$school, $classroom] = makeImportSchool();

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => $classroom->id,
        'nisn' => '1112223334',
        'nis' => '2025055',
    ]);

    $path = writeImportCsv('nis-cocok', [
        ['nisn', 'nis', 'nama', 'kelas', 'jk'],
        ['', '2025055', 'Siswa Terdaftar', '7A', 'L'],
    ]);

    $result = $this->parser->parse($path, 'csv', $school->id);

    expect($result['rows'][0]['action'])->toBe('update')
        ->and($result['rows'][0]['student_id'])->toBe($student->id);
});

it('memperlakukan nama sama dengan NISN berbeda sebagai dua siswa baru', function () {
    [$school] = makeImportSchool();

    $path = writeImportCsv('nama-kembar', [
        ['nisn', 'nama', 'kelas', 'jk'],
        ['1000000001', 'Ahmad Fauzi', '7A', 'L'],
        ['1000000002', 'Ahmad Fauzi', '7A', 'L'],
    ]);

    $result = $this->parser->parse($path, 'csv', $school->id);

    expect($result['summary']['create'])->toBe(2)
        ->and($result['summary']['update'])->toBe(0)
        ->and($result['rows'][0]['data']['nisn'])->toBe('1000000001')
        ->and($result['rows'][1]['data']['nisn'])->toBe('1000000002');
});

it('menolak baris dengan kelas yang tidak ada dan menyebut nama kelasnya', function () {
    [$school] = makeImportSchool();

    $path = writeImportCsv('kelas-tidak-ada', [
        ['nisn', 'nama', 'kelas', 'jk'],
        ['1000000003', 'Siti Aminah', '9Z', 'P'],
    ]);

    $result = $this->parser->parse($path, 'csv', $school->id);

    expect($result['rows'][0]['action'])->toBe('reject')
        ->and($result['rows'][0]['reason'])->toContain('9Z')
        ->and($result['rows'][0]['row_number'])->toBe(2);
});

it('menerima L maupun laki-laki sebagai LAKI_LAKI', function () {
    [$school] = makeImportSchool();

    $path = writeImportCsv('gender-bebas', [
        ['nisn', 'nama', 'kelas', 'jk'],
        ['1000000004', 'Andi', '7A', 'L'],
        ['1000000005', 'Bayu', '7A', 'laki-laki'],
        ['1000000006', 'Citra', '7A', 'Perempuan'],
    ]);

    $result = $this->parser->parse($path, 'csv', $school->id);

    expect($result['rows'][0]['data']['gender'])->toBe('LAKI_LAKI')
        ->and($result['rows'][1]['data']['gender'])->toBe('LAKI_LAKI')
        ->and($result['rows'][2]['data']['gender'])->toBe('PEREMPUAN');
});

it('menolak agama yang tidak dikenali alih-alih mengosongkannya', function () {
    [$school] = makeImportSchool();

    $path = writeImportCsv('agama-ngawur', [
        ['nisn', 'nama', 'kelas', 'jk', 'agama'],
        ['1000000007', 'Dedi', '7A', 'L', 'Jedi'],
    ]);

    $result = $this->parser->parse($path, 'csv', $school->id);

    expect($result['rows'][0]['action'])->toBe('reject')
        ->and($result['rows'][0]['reason'])->toContain('Jedi')
        ->and($result['rows'][0]['data'])->toBe([]);
});

it('tidak pernah mencocokkan siswa milik sekolah lain', function () {
    [$schoolA] = makeImportSchool();
    [$schoolB, $classroomB] = makeImportSchool();

    Student::factory()->create([
        'school_id' => $schoolB->id,
        'classroom_id' => $classroomB->id,
        'nisn' => '5550001111',
        'nis' => '2025999',
        'full_name' => 'Milik Sekolah B',
    ]);

    $path = writeImportCsv('lintas-sekolah', [
        ['nisn', 'nis', 'nama', 'kelas', 'jk'],
        ['5550001111', '2025999', 'Milik Sekolah B', '7A', 'L'],
    ]);

    $result = $this->parser->parse($path, 'csv', $schoolA->id);

    expect($result['rows'][0]['action'])->toBe('create')
        ->and($result['rows'][0]['student_id'])->toBeNull();
});

it('menolak kelas milik sekolah lain walau namanya sama', function () {
    [$schoolA] = makeImportSchool('7A');
    [, $classroomB] = makeImportSchool('8B');

    $path = writeImportCsv('kelas-sekolah-lain', [
        ['nisn', 'nama', 'kelas', 'jk'],
        ['1000000008', 'Eka', $classroomB->name, 'P'],
    ]);

    $result = $this->parser->parse($path, 'csv', $schoolA->id);

    expect($result['rows'][0]['action'])->toBe('reject')
        ->and($result['rows'][0]['reason'])->toContain('8B');
});

it('membaca berkas xlsx sama seperti csv', function () {
    [$school] = makeImportSchool();

    // Namanya sengaja BUKAN `contoh-impor`: berkas dengan nama itu ikut
    // ter-commit, dan menuliskannya di sini membuat setiap kali suite dijalankan
    // meninggalkan perubahan di pohon kerja — lalu run berikutnya membaca
    // keadaan yang berbeda dan gagal di tempat yang tidak ada hubungannya.
    $path = writeImportXlsx('parser-xlsx', [
        ['NISN', 'NIS', 'Nama Lengkap', 'Kelas', 'L/P', 'Agama', 'No HP'],
        ['2000000001', '2025101', 'Fajar Ramadhan', '7A', 'L', 'Islam', '81234567890'],
    ]);

    $result = $this->parser->parse($path, 'xlsx', $school->id);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['action'])->toBe('create')
        ->and($result['rows'][0]['data']['nisn'])->toBe('2000000001')
        ->and($result['rows'][0]['data']['gender'])->toBe('LAKI_LAKI')
        ->and($result['rows'][0]['data']['parent_phone'])->toBe('081234567890');
});

it('menolak baris siswa baru yang kolom wajibnya kosong', function () {
    [$school] = makeImportSchool();

    $path = writeImportCsv('wajib-kosong', [
        ['nisn', 'nama', 'kelas', 'jk'],
        ['1000000009', 'Gilang', '', ''],
    ]);

    $result = $this->parser->parse($path, 'csv', $school->id);

    expect($result['rows'][0]['action'])->toBe('reject')
        ->and($result['rows'][0]['reason'])->toContain('Kelas');
});

it('menolak baris ber-NISN baru yang memakai NIS milik siswa lain', function () {
    // Pencocokan berhenti pada nomor pertama yang terisi, jadi baris ini tidak
    // cocok dengan siapa pun dan dulu lolos jadi siswa BARU ber-NIS ganda.
    // Database tidak lagi menolaknya sejak deleted_at masuk unique index.
    [$school, $classroom] = makeImportSchool();

    Student::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => $classroom->id,
        'nis' => '2025010',
        'nisn' => '1111111111',
        'full_name' => 'Siswa Lama',
    ]);

    $path = writeImportCsv('nis-bentrok', [
        ['nisn', 'nis', 'nama', 'kelas', 'jk'],
        ['2222222222', '2025010', 'Siswa Baru', '7A', 'L'],
    ]);

    $result = $this->parser->parse($path, 'csv', $school->id);

    expect($result['summary']['create'])->toBe(0)
        ->and($result['summary']['reject'])->toBe(1)
        ->and($result['rows'][0]['reason'])->toContain('2025010')
        ->and($result['rows'][0]['reason'])->toContain('SISWA LAMA');
});

it('menolak baris ber-NIS baru yang memakai NISN milik siswa lain', function () {
    [$school, $classroom] = makeImportSchool();

    Student::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => $classroom->id,
        'nis' => '2025010',
        'nisn' => '1111111111',
        'full_name' => 'Siswa Lama',
    ]);

    // NISN cocok siswa lama -> harusnya update, bukan create. Ini menjaga
    // cabang sebaliknya tetap benar.
    $path = writeImportCsv('nisn-bentrok', [
        ['nisn', 'nis', 'nama', 'kelas', 'jk'],
        ['1111111111', '2025099', 'Siswa Baru', '7A', 'L'],
    ]);

    $result = $this->parser->parse($path, 'csv', $school->id);

    expect($result['summary']['update'])->toBe(1)
        ->and($result['summary']['create'])->toBe(0);
});

it('membolehkan NIS bekas siswa yang sudah dihapus', function () {
    // deleted_at ikut ke unique index justru supaya slot NIS terbebas.
    [$school, $classroom] = makeImportSchool();

    Student::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => $classroom->id,
        'nis' => '2025010',
        'nisn' => '1111111111',
    ])->delete();

    $path = writeImportCsv('nis-bekas', [
        ['nis', 'nama', 'kelas', 'jk'],
        ['2025010', 'Siswa Pengganti', '7A', 'L'],
    ]);

    $result = $this->parser->parse($path, 'csv', $school->id);

    expect($result['summary']['create'])->toBe(1)
        ->and($result['summary']['reject'])->toBe(0);
});
