<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\School;
use App\Models\Student;
use App\Services\Monitor\MonitorSekolah;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Monitor super admin. Kamis 29 Okt 2026 10:00 WIB.
 */
beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(Carbon::create(2026, 10, 29, 10, 0, 0, SchoolTime::timezone()));
});

function sekolahMonitor(string $nama, bool $dipantau, bool $jadwal = true): School
{
    $sekolah = School::factory()->create(['name' => $nama, 'is_active' => true, 'settings' => [MonitorSekolah::SETTING_DIPANTAU => $dipantau]]);

    if ($jadwal) {
        AttendanceSchedule::factory()->create([
            'school_id' => $sekolah->id, 'classroom_id' => null, 'day_of_week' => 4,
            'check_in_start' => '06:00', 'check_in_end' => '07:15', 'is_active' => true,
        ]);
    }

    return $sekolah;
}

function absenMonitor(School $sekolah, int $jumlah, string $tanggal = '2026-10-29'): void
{
    Student::factory()->count($jumlah)->create(['school_id' => $sekolah->id, 'is_active' => true])
        ->each(fn (Student $s) => Attendance::factory()->create([
            'school_id' => $sekolah->id, 'student_id' => $s->id, 'attendance_date' => $tanggal,
            'type' => AttendanceType::CheckIn, 'status' => AttendanceStatus::Hadir, 'recorded_at' => $tanggal.' 06:30:00',
        ]));
}

test('super admin mendarat di monitor setelah login', function () {
    $user = createSuperAdminUser(['password' => 'sandi-kuat-123']);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'sandi-kuat-123'])
        ->assertRedirect(route('admin.monitor'));
});

test('admin sekolah tidak bisa membuka monitor', function () {
    $this->actingAs(createAdminUser())->get(route('admin.monitor'))->assertForbidden();
});

test('peringkat 1 adalah sekolah dipantau yang tidak ada absen sama sekali', function () {
    $user = createSuperAdminUser();
    $ramai = sekolahMonitor('SMP Ramai', true);
    absenMonitor($ramai, 3);
    $sepi = sekolahMonitor('SMP Sepi', true);
    Student::factory()->count(2)->create(['school_id' => $sepi->id, 'is_active' => true]);
    sekolahMonitor('SMP Libur', true, jadwal: false);
    sekolahMonitor('SMP Tanpa Sistem', false);

    $this->actingAs($user)->get(route('admin.monitor'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/monitor/index')
            ->where('monitor.dipantau.0.nama', 'SMP Sepi')
            ->where('monitor.dipantau.0.keadaan', 'tanpa_absen')
            ->where('monitor.dipantau.0.peringkat', 1)
            ->where('monitor.dipantau.1.nama', 'SMP Ramai')
            ->where('monitor.dipantau.1.keadaan', 'berjalan')
            ->where('monitor.dipantau.1.hadir', 3)
            ->where('monitor.dipantau.2.nama', 'SMP Libur')
            ->where('monitor.dipantau.2.keadaan', 'libur')
            ->where('monitor.ringkasan.tanpa_absen', 1)
            // Sekolah yang tidak dicentang tidak ikut peringkat walau 0 absen.
            ->where('monitor.lainnya', fn ($lainnya) => collect($lainnya)->pluck('nama')->contains('SMP Tanpa Sistem')
                && collect($lainnya)->every(fn ($b) => ! isset($b['peringkat'])))
        );
});

test('sebelum jam masuk paling awal sekolah tanpa absen belum dinilai', function () {
    $this->travelTo(Carbon::create(2026, 10, 29, 5, 30, 0, SchoolTime::timezone()));
    $user = createSuperAdminUser();
    sekolahMonitor('SMP Pagi', true);

    $this->actingAs($user)->get(route('admin.monitor'))
        ->assertInertia(fn (Assert $page) => $page->where('monitor.dipantau.0.keadaan', 'belum_mulai'));
});

test('absen terakhir menunjukkan tanggal sekolah terakhir kali absen', function () {
    $user = createSuperAdminUser();
    $sekolah = sekolahMonitor('SMP Lama', true);
    absenMonitor($sekolah, 1, '2026-10-20');

    $this->actingAs($user)->get(route('admin.monitor'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('monitor.dipantau.0.absen_terakhir', '2026-10-20')
            ->where('monitor.dipantau.0.keadaan', 'tanpa_absen'));
});

test('super admin bisa mencentang dan melepas sekolah dari pantauan', function () {
    $user = createSuperAdminUser();
    $sekolah = sekolahMonitor('SMP Baru', false);

    $this->actingAs($user)->post(route('admin.monitor.pantau', $sekolah), ['dipantau' => true])->assertRedirect();
    expect($sekolah->fresh()->getSetting(MonitorSekolah::SETTING_DIPANTAU))->toBeTrue();

    $this->actingAs($user)->post(route('admin.monitor.pantau', $sekolah), ['dipantau' => false])->assertRedirect();
    expect($sekolah->fresh()->getSetting(MonitorSekolah::SETTING_DIPANTAU))->toBeFalse();
});

test('admin sekolah tidak bisa mengubah pantauan', function () {
    $sekolah = sekolahMonitor('SMP X', false);

    $this->actingAs(createAdminUser())->post(route('admin.monitor.pantau', $sekolah), ['dipantau' => true])->assertForbidden();
    expect($sekolah->fresh()->getSetting(MonitorSekolah::SETTING_DIPANTAU))->toBeFalse();
});

test('tanggal masa depan ditolak', function () {
    $this->actingAs(createSuperAdminUser())->get(route('admin.monitor', ['tanggal' => '2026-11-05']))
        ->assertSessionHasErrors('tanggal');
});
