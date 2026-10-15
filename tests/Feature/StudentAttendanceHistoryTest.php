<?php

use App\Enums\AppModule;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\PrayerType;
use App\Enums\Religion;
use App\Models\Attendance;
use App\Models\PrayerAttendance;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Student\StudentStatsBuilder;
use App\Support\SchoolTime;
use Carbon\Carbon;

beforeEach(function () {
    $this->admin = createAdminUser();
    $this->schoolId = $this->admin->school_id;
    $this->student = Student::factory()->create([
        'school_id' => $this->schoolId,
        'religion' => Religion::Islam,
    ]);
    $this->start = '2026-09-01';
    $this->end = '2026-09-30';
});

/**
 * Satu baris absensi siswa ini. `$jam` adalah jam dinding WIB, persis seperti
 * yang ditulis AttendanceRecorder.
 */
function catatAbsen(Student $siswa, string $tanggal, AttendanceType $jenis, AttendanceStatus $status, string $jam, array $lain = []): Attendance
{
    return Attendance::factory()->create([
        'student_id' => $siswa->id,
        'attendance_date' => $tanggal,
        'type' => $jenis,
        'status' => $status,
        'recorded_at' => "{$tanggal} {$jam}:00",
        ...$lain,
    ]);
}

function urlRiwayat(Student $siswa, array $query = []): string
{
    return route('admin.siswa.show', ['siswa' => $siswa, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30', ...$query]);
}

test('riwayat absensi sekolah: satu baris per hari dengan jam masuk, jam pulang, status, keterangan, dan sumber', function () {
    catatAbsen($this->student, '2026-09-14', AttendanceType::CheckIn, AttendanceStatus::Hadir, '07:05', ['device_id' => 'PUBLIC-SCAN']);
    catatAbsen($this->student, '2026-09-14', AttendanceType::CheckOut, AttendanceStatus::Hadir, '14:30', ['device_id' => 'PUBLIC-SCAN']);
    catatAbsen($this->student, '2026-09-15', AttendanceType::CheckIn, AttendanceStatus::Terlambat, '07:42', ['device_id' => 'PUBLIC-SCAN']);
    catatAbsen($this->student, '2026-09-15', AttendanceType::CheckOut, AttendanceStatus::Hadir, '15:00', [
        'device_id' => 'AUTO', 'notes' => 'Auto check-out oleh sistem (lupa scan pulang).',
    ]);
    catatAbsen($this->student, '2026-09-16', AttendanceType::CheckIn, AttendanceStatus::Sakit, '07:00', [
        'device_id' => null, 'recorded_by' => $this->admin->id, 'notes' => 'Demam, surat dokter',
    ]);

    $this->actingAs($this->admin)
        ->get(urlRiwayat($this->student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/siswa/show')
            ->missing('riwayatAbsensi')
            ->loadDeferredProps('riwayat', fn ($reload) => $reload
                ->where('riwayatAbsensi.total', 3)
                ->has('riwayatAbsensi.data', 3)
                ->where('riwayatAbsensi.data.0.tanggal', '2026-09-16')
                ->where('riwayatAbsensi.data.0.status', 'SAKIT')
                ->where('riwayatAbsensi.data.0.status_label', 'Sakit')
                // Izin/sakit/alpa bukan kedatangan: jam catatnya tidak dipajang sebagai jam masuk.
                ->where('riwayatAbsensi.data.0.jam_masuk', null)
                ->where('riwayatAbsensi.data.0.keterangan', 'Demam, surat dokter')
                ->where('riwayatAbsensi.data.0.sumber', 'Input manual')
                ->where('riwayatAbsensi.data.1.tanggal', '2026-09-15')
                ->where('riwayatAbsensi.data.1.status', 'TERLAMBAT')
                ->where('riwayatAbsensi.data.1.jam_masuk', '07:42')
                ->where('riwayatAbsensi.data.1.jam_pulang', '15:00')
                ->where('riwayatAbsensi.data.1.pulang_otomatis', true)
                ->where('riwayatAbsensi.data.1.keterangan', 'Auto check-out oleh sistem (lupa scan pulang).')
                ->where('riwayatAbsensi.data.2.tanggal', '2026-09-14')
                ->where('riwayatAbsensi.data.2.hari', 'Senin')
                ->where('riwayatAbsensi.data.2.jam_masuk', '07:05')
                ->where('riwayatAbsensi.data.2.jam_pulang', '14:30')
                ->where('riwayatAbsensi.data.2.sumber', 'Scan gerbang')
                ->where('riwayatAbsensi.data.2.pulang_otomatis', false)
                ->where('riwayatAbsensi.data.2.keterangan', null)
            )
        );
});

test('riwayat absensi sekolah dipaginasi per hari lewat riwayat_page', function () {
    $hari = Carbon::parse('2026-09-01');
    foreach (range(0, 19) as $i) {
        $tanggal = $hari->copy()->addDays($i)->toDateString();
        catatAbsen($this->student, $tanggal, AttendanceType::CheckIn, AttendanceStatus::Hadir, '07:10');
        catatAbsen($this->student, $tanggal, AttendanceType::CheckOut, AttendanceStatus::Hadir, '14:30');
    }

    $this->actingAs($this->admin)
        ->get(urlRiwayat($this->student))
        ->assertInertia(fn ($page) => $page->reloadOnly('riwayatAbsensi', fn ($reload) => $reload
            ->where('riwayatAbsensi.total', 20)
            ->where('riwayatAbsensi.per_page', 15)
            ->where('riwayatAbsensi.current_page', 1)
            ->where('riwayatAbsensi.last_page', 2)
            ->has('riwayatAbsensi.data', 15)
            ->where('riwayatAbsensi.data.0.tanggal', '2026-09-20')
        ));

    $this->actingAs($this->admin)
        ->get(urlRiwayat($this->student, ['riwayat_page' => 2]))
        ->assertInertia(fn ($page) => $page->reloadOnly('riwayatAbsensi', fn ($reload) => $reload
            ->where('riwayatAbsensi.current_page', 2)
            ->has('riwayatAbsensi.data', 5)
            ->where('riwayatAbsensi.data.0.tanggal', '2026-09-05')
            ->where('riwayatAbsensi.data.4.tanggal', '2026-09-01')
            ->where('riwayatAbsensi.data.4.jam_pulang', '14:30')
        ));
});

test('riwayat absensi sekolah mengikuti rentang tanggal yang dipilih', function () {
    catatAbsen($this->student, '2026-08-31', AttendanceType::CheckIn, AttendanceStatus::Hadir, '07:00');
    catatAbsen($this->student, '2026-09-10', AttendanceType::CheckIn, AttendanceStatus::Hadir, '07:00');
    catatAbsen($this->student, '2026-10-01', AttendanceType::CheckIn, AttendanceStatus::Hadir, '07:00');

    $this->actingAs($this->admin)
        ->get(urlRiwayat($this->student))
        ->assertInertia(fn ($page) => $page->reloadOnly('riwayatAbsensi', fn ($reload) => $reload
            ->where('riwayatAbsensi.total', 1)
            ->where('riwayatAbsensi.data.0.tanggal', '2026-09-10')
        ));
});

test('riwayat tidak memuat absensi siswa lain', function () {
    $teman = Student::factory()->create(['school_id' => $this->schoolId]);
    catatAbsen($teman, '2026-09-10', AttendanceType::CheckIn, AttendanceStatus::Hadir, '07:00');
    catatAbsen($this->student, '2026-09-11', AttendanceType::CheckIn, AttendanceStatus::Hadir, '07:00');

    $this->actingAs($this->admin)
        ->get(urlRiwayat($this->student))
        ->assertInertia(fn ($page) => $page->reloadOnly('riwayatAbsensi', fn ($reload) => $reload
            ->where('riwayatAbsensi.total', 1)
            ->where('riwayatAbsensi.data.0.tanggal', '2026-09-11')
        ));
});

test('riwayat siswa sekolah lain tidak bisa dibuka', function () {
    $lain = Student::factory()->create(['school_id' => School::factory()->create()->id]);
    catatAbsen($lain, '2026-09-10', AttendanceType::CheckIn, AttendanceStatus::Hadir, '07:00');

    $this->actingAs($this->admin)
        ->get(urlRiwayat($lain))
        ->assertNotFound();
});

test('riwayat butuh hak akses siswa', function () {
    $role = Role::create(['name' => 'ROLE_'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions([AppModule::Dashboard->permission()]);
    $tanpaAkses = User::factory()->create(['school_id' => $this->schoolId]);
    $tanpaAkses->assignRole($role);

    $this->actingAs($tanpaAkses)
        ->get(urlRiwayat($this->student))
        ->assertForbidden();
});

test('riwayat sholat dzuhur hanya dimuat saat tabnya dibuka dan hanya berisi dzuhur', function () {
    $this->admin->school->setSetting('prayer_enabled', true);
    foreach (['2026-09-14' => '12:05', '2026-09-15' => '12:25'] as $tanggal => $jam) {
        PrayerAttendance::factory()->create([
            'student_id' => $this->student->id,
            'prayer_date' => $tanggal,
            'recorded_at' => "{$tanggal} {$jam}:00",
        ]);
    }
    PrayerAttendance::factory()->dhuha()->create(['student_id' => $this->student->id, 'prayer_date' => '2026-09-14']);
    PrayerAttendance::factory()->create(['student_id' => $this->student->id, 'prayer_date' => '2026-10-02', 'recorded_at' => '2026-10-02 12:00:00']);

    $this->actingAs($this->admin)
        ->get(urlRiwayat($this->student))
        ->assertInertia(fn ($page) => $page
            ->missing('riwayatDzuhur')
            ->reloadOnly('riwayatDzuhur', fn ($reload) => $reload
                ->where('riwayatDzuhur.total', 2)
                ->where('riwayatDzuhur.data.0.tanggal', '2026-09-15')
                ->where('riwayatDzuhur.data.0.jam', '12:25')
                ->where('riwayatDzuhur.data.0.status_label', 'Hadir')
                ->where('riwayatDzuhur.data.0.sumber', 'Scan sholat')
                ->where('riwayatDzuhur.data.1.jam', '12:05')
            ));
});

test('riwayat sholat dzuhur dipaginasi lewat riwayat_sholat_page', function () {
    $this->admin->school->setSetting('prayer_enabled', true);
    foreach (range(1, 17) as $i) {
        $tanggal = sprintf('2026-09-%02d', $i);
        PrayerAttendance::factory()->create(['student_id' => $this->student->id, 'prayer_date' => $tanggal, 'recorded_at' => "{$tanggal} 12:10:00"]);
    }

    $this->actingAs($this->admin)
        ->get(urlRiwayat($this->student, ['riwayat_sholat_page' => 2]))
        ->assertInertia(fn ($page) => $page->reloadOnly('riwayatDzuhur', fn ($reload) => $reload
            ->where('riwayatDzuhur.total', 17)
            ->where('riwayatDzuhur.current_page', 2)
            ->has('riwayatDzuhur.data', 2)
            ->where('riwayatDzuhur.data.1.tanggal', '2026-09-01')
        ));
});

test('riwayat sholat jenis yang dimatikan sekolah selalu kosong', function () {
    PrayerAttendance::factory()->create(['student_id' => $this->student->id, 'prayer_date' => '2026-09-14', 'recorded_at' => '2026-09-14 12:00:00']);

    $this->actingAs($this->admin)
        ->get(urlRiwayat($this->student))
        ->assertInertia(fn ($page) => $page->reloadOnly('riwayatDzuhur', fn ($reload) => $reload
            ->where('riwayatDzuhur.total', 0)
            ->has('riwayatDzuhur.data', 0)
        ));
});

test('ringkasan rata-rata jam datang siswa memakai jam dinding Jakarta', function () {
    // 06:50 WIB tercatat 23:50 UTC di hari sebelumnya; tetap dihitung 06:50.
    foreach (['2026-09-14' => '23:50', '2026-09-15' => '00:20'] as $tanggal => $jamUtc) {
        $utcTanggal = $jamUtc === '23:50' ? Carbon::parse($tanggal)->subDay()->toDateString() : $tanggal;
        $wib = Carbon::parse("{$utcTanggal} {$jamUtc}:00", 'UTC')->setTimezone(SchoolTime::timezone());

        Attendance::factory()->create([
            'student_id' => $this->student->id,
            'attendance_date' => $wib->toDateString(),
            'type' => AttendanceType::CheckIn,
            'status' => AttendanceStatus::Hadir,
            'recorded_at' => $wib,
        ]);
    }
    // Izin tidak ikut dirata-rata: jamnya bukan jam datang.
    catatAbsen($this->student, '2026-09-16', AttendanceType::CheckIn, AttendanceStatus::Izin, '10:00');

    $this->actingAs($this->admin);
    $stats = app(StudentStatsBuilder::class)->attendanceFor($this->student, $this->start, $this->end);

    expect($stats['punctuality']['avg_check_in'])->toBe('07:05')
        ->and($stats['punctuality']['earliest'])->toBe('06:50')
        ->and($stats['punctuality']['latest'])->toBe('07:20')
        ->and($stats['summary']['hadir'])->toBe(2)
        ->and($stats['summary']['izin'])->toBe(1);
});

test('rata-rata jam sholat dzuhur siswa memakai jam dinding Jakarta', function () {
    $this->admin->school->setSetting('prayer_enabled', true);
    foreach (['2026-09-14' => '12:00', '2026-09-15' => '12:30'] as $tanggal => $jam) {
        PrayerAttendance::factory()->create(['student_id' => $this->student->id, 'prayer_date' => $tanggal, 'recorded_at' => "{$tanggal} {$jam}:00"]);
    }

    $this->actingAs($this->admin);
    $stats = app(StudentStatsBuilder::class)->prayerFor($this->student->fresh(), $this->start, $this->end, PrayerType::Dzuhur);

    expect($stats['types'][0]['punctuality']['avg_check_in'])->toBe('12:15');
});
