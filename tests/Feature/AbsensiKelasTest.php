<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\Classroom;
use App\Models\PoinSiswa;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Poin\SapuanPoin;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Absen manual satu kelas sekaligus. Kamis 15 Okt 2026 15:00 WIB.
 */
beforeEach(function () {
    // Halaman baru belum ada di manifest Vite sampai `npm run build`.
    $this->withoutVite();
    $this->travelTo(Carbon::create(2026, 10, 15, 15, 0, 0, SchoolTime::timezone()));
});

function akKelas(array $settings = []): array
{
    $school = School::factory()->create(['is_active' => true, 'settings' => $settings]);
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('ADMIN');
    $kelas = Classroom::factory()->create(['school_id' => $school->id]);

    return [$school, $admin, $kelas];
}

function akSiswa(Classroom $kelas): Student
{
    return Student::factory()->create(['school_id' => $kelas->school_id, 'classroom_id' => $kelas->id, 'is_active' => true]);
}

function akStatus(Student $siswa, string $tanggal = '2026-10-15'): ?string
{
    return Attendance::withoutGlobalScopes()->where('student_id', $siswa->id)
        ->whereDate('attendance_date', $tanggal)->where('type', AttendanceType::CheckIn->value)
        ->first()?->status?->value;
}

test('halaman kelas menampilkan siswa beserta status masuk hari itu', function () {
    [, $admin, $kelas] = akKelas();
    $a = akSiswa($kelas);
    akSiswa($kelas);
    Attendance::withoutGlobalScopes()->create([
        'school_id' => $kelas->school_id, 'student_id' => $a->id, 'attendance_date' => '2026-10-15',
        'type' => AttendanceType::CheckIn->value, 'status' => AttendanceStatus::Terlambat->value, 'recorded_at' => '2026-10-15 07:30:00',
    ]);

    $this->actingAs($admin)->get(route('admin.absensi.kelas', $kelas))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/absensi/kelas')
            ->where('tanggal', '2026-10-15')
            ->has('siswa', 2)
            ->where('siswa', fn ($siswa) => collect($siswa)->firstWhere('id', $a->id)['status'] === 'TERLAMBAT'
                && collect($siswa)->firstWhere('id', $a->id)['sumber'] === 'scan'));
});

test('simpan massal membuat baris baru dan mengoreksi yang sudah ada, baris kosong tidak disentuh', function () {
    [, $admin, $kelas] = akKelas();
    $baru = akSiswa($kelas);
    $koreksi = akSiswa($kelas);
    $dibiarkan = akSiswa($kelas);
    Attendance::withoutGlobalScopes()->create([
        'school_id' => $kelas->school_id, 'student_id' => $koreksi->id, 'attendance_date' => '2026-10-15',
        'type' => AttendanceType::CheckIn->value, 'status' => AttendanceStatus::Alpa->value, 'recorded_at' => '2026-10-15 07:30:00',
    ]);

    $this->actingAs($admin)->post(route('admin.absensi.kelas.simpan', $kelas), [
        'tanggal' => '2026-10-15',
        'baris' => [
            ['student_id' => $baru->id, 'status' => 'HADIR'],
            ['student_id' => $koreksi->id, 'status' => 'SAKIT', 'catatan' => 'surat dokter'],
        ],
    ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Absensi 2 siswa tersimpan.');

    expect(akStatus($baru))->toBe('HADIR')
        ->and(akStatus($koreksi))->toBe('SAKIT')
        ->and(akStatus($dibiarkan))->toBeNull();

    $row = Attendance::withoutGlobalScopes()->where('student_id', $koreksi->id)->first();
    expect($row->recorded_by)->toBe($admin->id)->and($row->notes)->toBe('surat dokter');
});

test('siswa dari kelas lain ditolak tanpa menulis apa pun', function () {
    [$school, $admin, $kelas] = akKelas();
    $kelasLain = Classroom::factory()->create(['school_id' => $school->id]);
    $anggota = akSiswa($kelas);
    $penyusup = akSiswa($kelasLain);

    $this->actingAs($admin)->post(route('admin.absensi.kelas.simpan', $kelas), [
        'tanggal' => '2026-10-15',
        'baris' => [
            ['student_id' => $anggota->id, 'status' => 'HADIR'],
            ['student_id' => $penyusup->id, 'status' => 'HADIR'],
        ],
    ])->assertSessionHasErrors('baris');

    expect(akStatus($anggota))->toBeNull()->and(akStatus($penyusup))->toBeNull();
});

test('kelas milik sekolah lain menghasilkan 404', function () {
    [, $admin] = akKelas();
    [, , $kelasLain] = akKelas();
    $siswaLain = akSiswa($kelasLain);

    $this->actingAs($admin)->get(route('admin.absensi.kelas', $kelasLain))->assertNotFound();
    $this->actingAs($admin)->post(route('admin.absensi.kelas.simpan', $kelasLain), [
        'tanggal' => '2026-10-15',
        'baris' => [['student_id' => $siswaLain->id, 'status' => 'HADIR']],
    ])->assertNotFound();

    expect(akStatus($siswaLain))->toBeNull();
});

test('tanggal yang belum terjadi ditolak', function () {
    [, $admin, $kelas] = akKelas();
    $siswa = akSiswa($kelas);

    $this->actingAs($admin)->post(route('admin.absensi.kelas.simpan', $kelas), [
        'tanggal' => '2026-10-16',
        'baris' => [['student_id' => $siswa->id, 'status' => 'HADIR']],
    ])->assertSessionHasErrors('tanggal');
});

test('mengoreksi alpa menjadi hadir langsung menghapus poin otomatis', function () {
    [$school, $admin, $kelas] = akKelas([
        'feature_poin_pelanggaran' => true,
        'poin_alpa' => 5,
        SapuanPoin::SETTING_AKTIF_SEJAK => '2026-10-01',
    ]);
    foreach (range(1, 5) as $hari) {
        AttendanceSchedule::factory()->create(['school_id' => $school->id, 'classroom_id' => null, 'day_of_week' => $hari, 'is_active' => true]);
    }
    $siswa = akSiswa($kelas);
    $lain = akSiswa($kelas);
    Attendance::withoutGlobalScopes()->create([
        'school_id' => $school->id, 'student_id' => $lain->id, 'attendance_date' => '2026-10-15',
        'type' => AttendanceType::CheckIn->value, 'status' => AttendanceStatus::Hadir->value, 'recorded_at' => '2026-10-15 07:00:00',
    ]);

    $this->artisan('poin:sapu', ['--date' => '2026-10-15'])->assertSuccessful();
    expect(PoinSiswa::withoutGlobalScopes()->where('student_id', $siswa->id)->count())->toBe(1);

    $this->actingAs($admin)->post(route('admin.absensi.kelas.simpan', $kelas), [
        'tanggal' => '2026-10-15',
        'baris' => [['student_id' => $siswa->id, 'status' => 'HADIR']],
    ])->assertSessionHasNoErrors();

    expect(PoinSiswa::withoutGlobalScopes()->where('student_id', $siswa->id)->count())->toBe(0);
});
