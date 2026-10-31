<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\School;
use App\Models\Student;
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

function sekolahMonitor(string $nama, bool $jadwal = true): School
{
    $sekolah = School::factory()->create(['name' => $nama, 'is_active' => true]);

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

test('semua sekolah aktif masuk peringkat, nomor 1 sekolah tanpa absen sama sekali', function () {
    $user = createSuperAdminUser();
    $ramai = sekolahMonitor('SMP Ramai');
    absenMonitor($ramai, 3);
    $sepi = sekolahMonitor('SMP Sepi');
    Student::factory()->count(2)->create(['school_id' => $sepi->id, 'is_active' => true]);
    sekolahMonitor('SMP Libur', jadwal: false);

    $this->actingAs($user)->get(route('admin.monitor'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/monitor/index')
            ->where('monitor.sekolah.0.nama', 'SMP Sepi')
            ->where('monitor.sekolah.0.keadaan', 'tanpa_absen')
            ->where('monitor.sekolah.0.peringkat', 1)
            ->where('monitor.sekolah.1.nama', 'SMP Ramai')
            ->where('monitor.sekolah.1.keadaan', 'berjalan')
            ->where('monitor.sekolah.1.hadir', 3)
            ->where('monitor.sekolah', fn ($sekolah) => collect($sekolah)->firstWhere('nama', 'SMP Libur')['keadaan'] === 'libur')
            ->where('monitor.ringkasan.tanpa_absen', 1)
            // Tanpa centang: sekolah milik akun super admin sendiri ikut juga.
            ->where('monitor.ringkasan.total', School::where('is_active', true)->count())
        );
});

test('sebelum jam masuk paling awal sekolah tanpa absen belum dinilai', function () {
    $this->travelTo(Carbon::create(2026, 10, 29, 5, 30, 0, SchoolTime::timezone()));
    $user = createSuperAdminUser();
    sekolahMonitor('SMP Pagi');

    $this->actingAs($user)->get(route('admin.monitor'))
        ->assertInertia(fn (Assert $page) => $page->where('monitor.sekolah.0.keadaan', 'belum_mulai'));
});

test('absen terakhir menunjukkan tanggal sekolah terakhir kali absen', function () {
    $user = createSuperAdminUser();
    $sekolah = sekolahMonitor('SMP Lama');
    absenMonitor($sekolah, 1, '2026-10-20');

    $this->actingAs($user)->get(route('admin.monitor'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('monitor.sekolah.0.absen_terakhir', '2026-10-20')
            ->where('monitor.sekolah.0.keadaan', 'tanpa_absen'));
});

test('tanggal masa depan ditolak', function () {
    $this->actingAs(createSuperAdminUser())->get(route('admin.monitor', ['tanggal' => '2026-11-05']))
        ->assertSessionHasErrors('tanggal');
});
