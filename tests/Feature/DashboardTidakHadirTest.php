<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Kartu + daftar "Tidak Hadir Hari Ini" di dashboard. Kamis 15 Okt 2026.
 */
function dthSekolah(): array
{
    $school = School::factory()->create(['is_active' => true]);
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('ADMIN');

    foreach (range(1, 5) as $hari) {
        AttendanceSchedule::factory()->create(['school_id' => $school->id, 'classroom_id' => null, 'day_of_week' => $hari, 'is_active' => true]);
    }

    return [$school, $admin];
}

function dthMasuk(Student $siswa, AttendanceStatus $status): void
{
    Attendance::withoutGlobalScopes()->create([
        'school_id' => $siswa->school_id, 'student_id' => $siswa->id, 'attendance_date' => '2026-10-15',
        'type' => AttendanceType::CheckIn->value, 'status' => $status->value, 'recorded_at' => '2026-10-15 07:00:00',
    ]);
}

test('setelah jam masuk tutup, daftar berisi siswa tanpa absen masuk per kelas, izin dan sakit tidak ikut', function () {
    $this->travelTo(Carbon::create(2026, 10, 15, 10, 0, 0, SchoolTime::timezone()));
    [$school, $admin] = dthSekolah();
    $kelasA = Classroom::factory()->create(['school_id' => $school->id, 'name' => '7A']);
    $kelasB = Classroom::factory()->create(['school_id' => $school->id, 'name' => '7B']);

    $hadir = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $kelasA->id, 'is_active' => true]);
    $bolos = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $kelasA->id, 'is_active' => true, 'full_name' => 'BOLOS']);
    $sakit = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $kelasB->id, 'is_active' => true]);
    $alpa = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $kelasB->id, 'is_active' => true, 'full_name' => 'ALPA']);
    dthMasuk($hadir, AttendanceStatus::Hadir);
    dthMasuk($sakit, AttendanceStatus::Sakit);
    dthMasuk($alpa, AttendanceStatus::Alpa);

    // Sekolah lain: tidak boleh ikut terhitung.
    $lain = School::factory()->create(['is_active' => true]);
    Student::factory()->create(['school_id' => $lain->id, 'is_active' => true]);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('tidakHadir', fn (Assert $reload) => $reload
                ->where('tidakHadir.keadaan', 'tutup')
                ->where('tidakHadir.jumlah', 2)
                ->has('tidakHadir.kelas', 2)
                ->where('tidakHadir.kelas.0.nama', '7A')
                ->where('tidakHadir.kelas.0.id', $kelasA->id)
                ->where('tidakHadir.kelas.0.siswa.0.nama', 'BOLOS')
                ->where('tidakHadir.kelas.1.siswa.0.nama', 'ALPA')
            ));
});

test('sebelum jam masuk tutup, keadaannya berjalan (belum hadir)', function () {
    $this->travelTo(Carbon::create(2026, 10, 15, 7, 15, 0, SchoolTime::timezone()));
    [$school, $admin] = dthSekolah();
    $siswa = Student::factory()->count(2)->create(['school_id' => $school->id, 'is_active' => true]);
    dthMasuk($siswa[0], AttendanceStatus::Hadir);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('tidakHadir', fn (Assert $reload) => $reload
                ->where('tidakHadir.keadaan', 'berjalan')
                ->where('tidakHadir.jumlah', 1)
            ));
});

test('hari tanpa jadwal dianggap libur dan tidak diberi angka', function () {
    $this->travelTo(Carbon::create(2026, 10, 17, 10, 0, 0, SchoolTime::timezone())); // Sabtu
    [$school, $admin] = dthSekolah();
    Student::factory()->create(['school_id' => $school->id, 'is_active' => true]);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('tidakHadir', fn (Assert $reload) => $reload
                ->where('tidakHadir.keadaan', 'libur')
                ->where('tidakHadir.jumlah', null)
            ));
});

test('belum ada scan sama sekali tidak dihitung sebagai seluruh siswa bolos', function () {
    $this->travelTo(Carbon::create(2026, 10, 15, 10, 0, 0, SchoolTime::timezone()));
    [$school, $admin] = dthSekolah();
    Student::factory()->count(3)->create(['school_id' => $school->id, 'is_active' => true]);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('tidakHadir', fn (Assert $reload) => $reload
                ->where('tidakHadir.keadaan', 'belum_ada_scan')
                ->where('tidakHadir.jumlah', null)
            ));
});
