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

/**
 * @param  iterable<int, array<string, mixed>>  $sekolah
 * @return array<string, array<string, mixed>> baris per nama sekolah
 */
function barisMonitor(iterable $sekolah): array
{
    return collect($sekolah)->keyBy('nama')->all();
}

test('sekolah paling jarang absen di atas, paling sering di bawah', function () {
    $user = createSuperAdminUser();
    $sering = sekolahMonitor('SMP Sering');
    foreach (['2026-10-26', '2026-10-27', '2026-10-28', '2026-10-29'] as $tgl) {
        absenMonitor($sering, 1, $tgl);
    }
    $jarang = sekolahMonitor('SMP Jarang');
    absenMonitor($jarang, 2, '2026-10-29');
    sekolahMonitor('SMP Belum Pernah');

    $this->actingAs($user)->get(route('admin.monitor'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/monitor/index')
            ->where('monitor.sekolah', function ($sekolah) {
                $urut = collect($sekolah)->pluck('nama')->values();
                $baris = barisMonitor($sekolah);

                return $urut->search('SMP Belum Pernah') < $urut->search('SMP Jarang')
                    && $urut->search('SMP Jarang') < $urut->search('SMP Sering')
                    && $urut->last() === 'SMP Sering'
                    && $baris['SMP Sering']['hari_aktif'] === 4
                    && $baris['SMP Jarang']['hari_aktif'] === 1
                    && $baris['SMP Belum Pernah']['hari_aktif'] === 0
                    && $baris['SMP Belum Pernah']['keadaan'] === 'tanpa_absen'
                    && $baris['SMP Jarang']['keadaan'] === 'berjalan'
                    && $baris['SMP Jarang']['hadir'] === 2;
            })
            ->where('monitor.ringkasan.total', School::where('is_active', true)->count())
        );
});

test('sekolah tanpa jadwal hari itu ditandai libur', function () {
    $user = createSuperAdminUser();
    sekolahMonitor('SMP Libur', jadwal: false);

    $this->actingAs($user)->get(route('admin.monitor'))
        ->assertInertia(fn (Assert $page) => $page->where('monitor.sekolah', fn ($s) => barisMonitor($s)['SMP Libur']['keadaan'] === 'libur'));
});

test('sebelum jam masuk paling awal sekolah tanpa absen belum dinilai', function () {
    $this->travelTo(Carbon::create(2026, 10, 29, 5, 30, 0, SchoolTime::timezone()));
    $user = createSuperAdminUser();
    sekolahMonitor('SMP Pagi');

    $this->actingAs($user)->get(route('admin.monitor'))
        ->assertInertia(fn (Assert $page) => $page->where('monitor.sekolah', fn ($s) => barisMonitor($s)['SMP Pagi']['keadaan'] === 'belum_mulai'));
});

test('absen terakhir menunjukkan tanggal sekolah terakhir kali absen', function () {
    $user = createSuperAdminUser();
    $sekolah = sekolahMonitor('SMP Lama');
    absenMonitor($sekolah, 1, '2026-10-20');

    $this->actingAs($user)->get(route('admin.monitor'))
        ->assertInertia(fn (Assert $page) => $page->where('monitor.sekolah', fn ($s) => barisMonitor($s)['SMP Lama']['absen_terakhir'] === '2026-10-20'
            && barisMonitor($s)['SMP Lama']['keadaan'] === 'tanpa_absen'));
});

test('tanggal masa depan ditolak', function () {
    $this->actingAs(createSuperAdminUser())->get(route('admin.monitor', ['tanggal' => '2026-11-05']))
        ->assertSessionHasErrors('tanggal');
});
