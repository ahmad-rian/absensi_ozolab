<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Support\SchoolTime;
use Illuminate\Support\Carbon;

function absenMasukHariIni(Student $student, AttendanceStatus $status): void
{
    Attendance::factory()->create([
        'student_id' => $student->id,
        'attendance_date' => SchoolTime::today(),
        'type' => AttendanceType::CheckIn,
        'status' => $status,
        'recorded_at' => SchoolTime::now(),
    ]);
}

function filterLaporanBulanIni(array $tambahan = []): array
{
    return [
        'start_date' => SchoolTime::today()->startOfMonth()->toDateString(),
        'end_date' => SchoolTime::today()->endOfMonth()->toDateString(),
        ...$tambahan,
    ];
}

test('dashboard menghitung siswa terlambat sebagai hadir', function () {
    $user = createAdminUser();
    absenMasukHariIni(Student::factory()->create(['school_id' => $user->school_id]), AttendanceStatus::Hadir);
    absenMasukHariIni(Student::factory()->create(['school_id' => $user->school_id]), AttendanceStatus::Terlambat);
    absenMasukHariIni(Student::factory()->create(['school_id' => $user->school_id]), AttendanceStatus::Alpa);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.presentToday', 2)
            ->where('stats.lateToday', 1)
            ->where('stats.attendanceRate', fn ($rate) => (float) $rate === 66.7)
        );
});

test('rekap laporan menghitung terlambat ke kolom hadir', function () {
    $user = createAdminUser();
    $siswa = Student::factory()->create(['school_id' => $user->school_id]);
    absenMasukHariIni($siswa, AttendanceStatus::Terlambat);

    $this->actingAs($user)->get(route('admin.laporan', filterLaporanBulanIni()))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reportData.0.hadir', 1)
            ->where('reportData.0.terlambat', 1)
            ->where('summary.total_hadir', 1)
        );
});

test('laporan bisa difilter per angkatan', function () {
    $user = createAdminUser();
    $kelas7 = Classroom::factory()->create(['school_id' => $user->school_id, 'grade_level' => 7]);
    $kelas8 = Classroom::factory()->create(['school_id' => $user->school_id, 'grade_level' => 8]);
    Student::factory()->count(2)->create(['school_id' => $user->school_id, 'classroom_id' => $kelas7->id]);
    Student::factory()->create(['school_id' => $user->school_id, 'classroom_id' => $kelas8->id]);

    $this->actingAs($user)->get(route('admin.laporan', filterLaporanBulanIni(['angkatan' => 7])))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('reportData', 2)
            ->where('filters.angkatan', '7')
            ->has('classrooms.0.grade_level')
        );
});

test('unduhan excel dan pdf mengikuti filter angkatan', function () {
    $user = createAdminUser();
    $kelas9 = Classroom::factory()->create(['school_id' => $user->school_id, 'grade_level' => 9]);
    Student::factory()->create(['school_id' => $user->school_id, 'classroom_id' => $kelas9->id]);

    $this->actingAs($user)->get(route('admin.laporan.export', filterLaporanBulanIni(['angkatan' => 9])))
        ->assertOk()
        ->assertDownload('laporan-kehadiran-'.SchoolTime::now()->format('Y-m-d').'-kelas-9.xlsx');

    $this->actingAs($user)->get(route('admin.laporan.export-pdf', filterLaporanBulanIni(['angkatan' => 9])))
        ->assertOk()
        ->assertDownload('laporan-kehadiran-'.SchoolTime::now()->format('Y-m-d').'-kelas-9.pdf');
});

test('angkatan di luar rentang ditolak', function () {
    $user = createAdminUser();

    $this->actingAs($user)->get(route('admin.laporan', filterLaporanBulanIni(['angkatan' => 99])))
        ->assertSessionHasErrors('angkatan');
});

test('grafik tren kehadiran memuat angka hari ini, terlambat ikut hadir', function () {
    // Senin; tren melewati Sabtu-Minggu.
    $this->travelTo(Carbon::parse('2026-10-26 03:00:00', 'UTC'));
    $user = createAdminUser();
    absenMasukHariIni(Student::factory()->create(['school_id' => $user->school_id]), AttendanceStatus::Hadir);
    absenMasukHariIni(Student::factory()->create(['school_id' => $user->school_id]), AttendanceStatus::Terlambat);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->reloadOnly('trend', fn ($reload) => $reload
                ->where('trend', fn ($trend) => collect($trend)->last()['hadir'] === 2
                    && collect($trend)->last()['terlambat'] === 1)
            )
        );
});

test('aktivitas dashboard tidak menampilkan izin untuk hari ke depan', function () {
    $user = createAdminUser();
    $siswa = Student::factory()->create(['school_id' => $user->school_id]);
    absenMasukHariIni($siswa, AttendanceStatus::Hadir);
    Attendance::factory()->create([
        'student_id' => $siswa->id,
        'attendance_date' => SchoolTime::today()->addDays(4),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Sakit,
        'recorded_at' => SchoolTime::now()->addDays(4),
    ]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->reloadOnly('activityFeed,latestCheckins', fn ($reload) => $reload
                ->has('activityFeed', 1)
                ->where('activityFeed.0.status', AttendanceStatus::Hadir->value)
                ->has('latestCheckins', 1)
            )
        );
});
