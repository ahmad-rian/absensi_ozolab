<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Support\KelompokKelas;

test('guests are redirected from laporan page', function () {
    $this->get(route('admin.laporan'))->assertRedirect(route('login'));
});

test('authenticated users can visit laporan page', function () {
    $user = createAdminUser();

    $response = $this->actingAs($user)->get(route('admin.laporan'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/laporan/index')
        ->has('reportData')
        ->has('summary')
        ->has('classrooms')
        ->has('filters')
    );
});

test('laporan returns attendance summary data', function () {
    $user = createAdminUser();
    $student = Student::factory()->create(['school_id' => $user->school_id]);

    Attendance::factory()->create([
        'student_id' => $student->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => now(),
    ]);

    Attendance::factory()->create([
        'student_id' => $student->id,
        'attendance_date' => today()->subDay(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Terlambat,
        'recorded_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('admin.laporan', [
        'start_date' => today()->startOfMonth()->toDateString(),
        'end_date' => today()->endOfMonth()->toDateString(),
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('reportData', 1)
        ->where('summary.total_hadir', 1)
        ->where('summary.total_terlambat', 1)
    );
});

test('guests are redirected from laporan export', function () {
    $this->get(route('admin.laporan.export'))->assertRedirect(route('login'));
});

test('authenticated users can export laporan as xlsx', function () {
    $user = createAdminUser();
    $student = Student::factory()->create(['school_id' => $user->school_id]);

    Attendance::factory()->create([
        'student_id' => $student->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('admin.laporan.export', [
        'start_date' => today()->startOfMonth()->toDateString(),
        'end_date' => today()->endOfMonth()->toDateString(),
    ]));

    $response->assertOk();
    $response->assertDownload();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml.sheet');

    $isi = collect(xlsxRows($response))->flatten()->all();

    expect($isi)->toContain('NIS')
        ->toContain('Nama Siswa')
        ->toContain('% Kehadiran')
        ->toContain($student->nis)
        ->toContain($student->full_name);
});

test('laporan export filters by classroom', function () {
    $user = createAdminUser();
    $classroom1 = Classroom::factory()->create(['school_id' => $user->school_id]);
    $classroom2 = Classroom::factory()->create(['school_id' => $user->school_id]);
    $student1 = Student::factory()->create(['classroom_id' => $classroom1->id, 'school_id' => $user->school_id]);
    $student2 = Student::factory()->create(['classroom_id' => $classroom2->id, 'school_id' => $user->school_id]);

    Attendance::factory()->create([
        'student_id' => $student1->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => now(),
    ]);

    Attendance::factory()->create([
        'student_id' => $student2->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('admin.laporan.export', [
        'start_date' => today()->startOfMonth()->toDateString(),
        'end_date' => today()->endOfMonth()->toDateString(),
        'classroom_id' => $classroom1->id,
    ]));

    $response->assertOk();

    expect(collect(xlsxRows($response))->flatten()->all())
        ->toContain($student1->full_name)
        ->not->toContain($student2->full_name);
});

test('laporan filters by classroom', function () {
    $user = createAdminUser();
    $classroom1 = Classroom::factory()->create(['school_id' => $user->school_id]);
    $classroom2 = Classroom::factory()->create(['school_id' => $user->school_id]);
    $student1 = Student::factory()->create(['classroom_id' => $classroom1->id, 'school_id' => $user->school_id]);
    $student2 = Student::factory()->create(['classroom_id' => $classroom2->id, 'school_id' => $user->school_id]);

    Attendance::factory()->create([
        'student_id' => $student1->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => now(),
    ]);

    Attendance::factory()->create([
        'student_id' => $student2->id,
        'attendance_date' => today(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
        'recorded_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('admin.laporan', [
        'start_date' => today()->startOfMonth()->toDateString(),
        'end_date' => today()->endOfMonth()->toDateString(),
        'classroom_id' => $classroom1->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('reportData', 1)
    );
});

test('laporan export membuat satu sheet per kelas, urut nama kelas', function () {
    $user = createAdminUser();
    $kelas10 = Classroom::factory()->create(['school_id' => $user->school_id, 'name' => '10 A']);
    $kelas7 = Classroom::factory()->create(['school_id' => $user->school_id, 'name' => '7 A']);
    $budi = Student::factory()->create(['classroom_id' => $kelas10->id, 'school_id' => $user->school_id, 'full_name' => 'Budi']);
    $ani = Student::factory()->create(['classroom_id' => $kelas7->id, 'school_id' => $user->school_id, 'full_name' => 'Ani']);
    $tanpaKelas = Student::factory()->create(['classroom_id' => null, 'school_id' => $user->school_id, 'full_name' => 'Cici']);

    $sheets = xlsxSheets($this->actingAs($user)->get(route('admin.laporan.export'))->assertOk());

    expect(array_keys($sheets))->toBe(['7 A', '10 A', 'Tanpa Kelas']);
    expect(collect($sheets['7 A'])->flatten()->all())->toContain('NIS', $ani->full_name)->not->toContain($budi->full_name);
    expect(collect($sheets['10 A'])->flatten()->all())->toContain($budi->full_name)->not->toContain($ani->full_name);
    expect(collect($sheets['Tanpa Kelas'])->flatten()->all())->toContain($tanpaKelas->full_name);
});

test('laporan export dengan filter kelas hanya berisi satu sheet', function () {
    $user = createAdminUser();
    $kelas = Classroom::factory()->create(['school_id' => $user->school_id, 'name' => '8 B']);
    Classroom::factory()->create(['school_id' => $user->school_id, 'name' => '8 C']);
    Student::factory()->create(['classroom_id' => $kelas->id, 'school_id' => $user->school_id]);

    $sheets = xlsxSheets($this->actingAs($user)->get(route('admin.laporan.export', ['classroom_id' => $kelas->id]))->assertOk());

    expect(array_keys($sheets))->toBe(['8 B']);
});

test('nama kelas yang dilarang excel atau kembar setelah dipotong tetap jadi sheet', function () {
    $user = createAdminUser();
    $panjang = str_repeat('Kelas Unggulan Sains ', 2);
    foreach (['XI IPA/1', $panjang.'Satu', $panjang.'Dua'] as $nama) {
        $kelas = Classroom::factory()->create(['school_id' => $user->school_id, 'name' => $nama]);
        Student::factory()->create(['classroom_id' => $kelas->id, 'school_id' => $user->school_id]);
    }

    $nama = array_keys(xlsxSheets($this->actingAs($user)->get(route('admin.laporan.export'))->assertOk()));

    expect($nama)->toHaveCount(3)->toContain('XI IPA 1');
    expect(collect($nama)->every(fn ($n) => mb_strlen($n) <= 31))->toBeTrue();
});

test('laporan export tanpa siswa tetap menghasilkan berkas', function () {
    $user = createAdminUser();

    $sheets = xlsxSheets($this->actingAs($user)->get(route('admin.laporan.export'))->assertOk());

    expect(array_keys($sheets))->toBe(['Laporan']);
});

test('pdf laporan memisah siswa per kelas dengan halaman baru', function () {
    $user = createAdminUser();
    $kelas7 = Classroom::factory()->create(['school_id' => $user->school_id, 'name' => '7 A']);
    $kelas8 = Classroom::factory()->create(['school_id' => $user->school_id, 'name' => '8 A']);
    Student::factory()->create(['classroom_id' => $kelas7->id, 'school_id' => $user->school_id]);
    Student::factory()->create(['classroom_id' => $kelas8->id, 'school_id' => $user->school_id]);

    $this->actingAs($user)->get(route('admin.laporan.export-pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');

    $html = view('pdf.laporan', [
        'kelompok' => KelompokKelas::dari(collect([
            ['nis' => '1', 'full_name' => 'Ani', 'classroom_name' => '8 A', 'hadir' => 1, 'terlambat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'attendance_rate' => 100, 'prayers' => []],
            ['nis' => '2', 'full_name' => 'Budi', 'classroom_name' => '7 A', 'hadir' => 2, 'terlambat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'attendance_rate' => 100, 'prayers' => []],
        ])),
        'summary' => ['total_hadir' => 3, 'total_terlambat' => 0, 'total_izin' => 0, 'total_sakit' => 0, 'total_alpa' => 0],
        'startDate' => '2026-10-01', 'endDate' => '2026-10-31', 'schoolName' => 'SMP', 'reportKind' => 'absensi', 'kinds' => ['absensi' => 'Absensi Sekolah'],
    ])->render();

    expect($html)->toContain('Kelas 7 A')->toContain('Kelas 8 A')->toContain('kelas-baru');
    expect(strpos($html, 'Kelas 7 A'))->toBeLessThan(strpos($html, 'Kelas 8 A'));
});

test('laporan dhuha juga dipisah per kelas dan semuanya tetap per jenis', function () {
    $user = createAdminUser();
    $user->school->update(['settings' => array_merge($user->school->settings ?? [], ['prayer_dhuha_enabled' => true])]);
    $kelas = Classroom::factory()->create(['school_id' => $user->school_id, 'name' => '9 A']);
    Student::factory()->create(['classroom_id' => $kelas->id, 'school_id' => $user->school_id]);

    $dhuha = xlsxSheets($this->actingAs($user)->get(route('admin.laporan.export', ['jenis' => 'dhuha']))->assertOk());
    $semua = xlsxSheets($this->actingAs($user)->get(route('admin.laporan.export', ['jenis' => 'semuanya']))->assertOk());

    expect(array_keys($dhuha))->toBe(['9 A'])
        ->and($dhuha['9 A'][0])->toContain('Ikut', 'Hari Efektif')
        ->and(array_keys($semua))->toBe(['Ringkasan', 'Absensi Sekolah', 'Dhuha']);
});
