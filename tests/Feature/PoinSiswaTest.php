<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\Classroom;
use App\Models\ParentProfile;
use App\Models\PoinSiswa;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Poin\SapuanPoin;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Poin pelanggaran siswa: otomatis dari terlambat / tidak hadir (angka per
 * sekolah) plus catatan manual guru BK. Waktu dibekukan Kamis 15 Okt 2026
 * 15:00 WIB — jendela terlambat (08:00) dan pulang (13:00) sudah tutup.
 */
beforeEach(fn () => $this->travelTo(Carbon::create(2026, 10, 15, 15, 0, 0, SchoolTime::timezone())));

function sekolahPoin(array $settings = []): School
{
    $school = School::factory()->create(['is_active' => true, 'settings' => [
        'feature_poin_pelanggaran' => true,
        SapuanPoin::SETTING_AKTIF_SEJAK => '2026-10-01',
        ...$settings,
    ]]);

    foreach (range(1, 5) as $hari) {
        AttendanceSchedule::factory()->create(['school_id' => $school->id, 'classroom_id' => null, 'day_of_week' => $hari, 'is_active' => true]);
    }

    return $school;
}

function siswaPoin(School $school, string $nama = 'SISWA'): Student
{
    return Student::factory()->create(['school_id' => $school->id, 'full_name' => $nama, 'is_active' => true]);
}

function absenMasuk(Student $siswa, AttendanceStatus $status, string $tanggal = '2026-10-15'): Attendance
{
    return Attendance::withoutGlobalScopes()->create([
        'school_id' => $siswa->school_id, 'student_id' => $siswa->id, 'attendance_date' => $tanggal,
        'type' => AttendanceType::CheckIn->value, 'status' => $status->value, 'recorded_at' => "{$tanggal} 07:00:00",
    ]);
}

function poinOtomatis(Student $siswa): array
{
    return PoinSiswa::withoutGlobalScopes()->where('student_id', $siswa->id)->orderBy('tanggal')
        ->get()->map(fn (PoinSiswa $p) => "{$p->tanggal} {$p->sumber} {$p->poin}")->all();
}

function adminSekolah(School $school, string $peran = 'ADMIN'): User
{
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole($peran);

    return $user;
}

// ── sapuan otomatis ─────────────────────────────────────────────────────────
test('sapuan memberi poin terlambat dan alpa sesuai angka masing-masing sekolah', function () {
    $a = sekolahPoin(['poin_terlambat' => 3, 'poin_alpa' => 7]);
    $b = sekolahPoin(['poin_terlambat' => 10, 'poin_alpa' => 20]);
    $telatA = siswaPoin($a);
    $alpaA = siswaPoin($a);
    $telatB = siswaPoin($b);
    $alpaB = siswaPoin($b);
    absenMasuk($telatA, AttendanceStatus::Terlambat);
    absenMasuk($telatB, AttendanceStatus::Terlambat);

    $this->artisan('poin:sapu', ['--date' => '2026-10-15'])->assertSuccessful();

    expect(poinOtomatis($telatA))->toBe(['2026-10-15 TERLAMBAT 3'])
        ->and(poinOtomatis($alpaA))->toBe(['2026-10-15 ALPA 7'])
        ->and(poinOtomatis($telatB))->toBe(['2026-10-15 TERLAMBAT 10'])
        ->and(poinOtomatis($alpaB))->toBe(['2026-10-15 ALPA 20']);
});

test('sapuan dua kali tidak mencatat ganda', function () {
    $school = sekolahPoin();
    $siswa = siswaPoin($school);
    absenMasuk(siswaPoin($school), AttendanceStatus::Hadir);

    $this->artisan('poin:sapu', ['--date' => '2026-10-15']);
    $this->artisan('poin:sapu', ['--date' => '2026-10-15']);

    expect(poinOtomatis($siswa))->toBe(['2026-10-15 ALPA 10']);
});

test('hari tanpa satu pun check-in dianggap libur dan tidak memberi poin alpa', function () {
    $school = sekolahPoin();
    $siswa = siswaPoin($school);

    $this->artisan('poin:sapu', ['--date' => '2026-10-15']);

    expect(poinOtomatis($siswa))->toBe([]);
});

test('izin dan sakit tidak kena poin', function () {
    $school = sekolahPoin();
    $izin = siswaPoin($school);
    $sakit = siswaPoin($school);
    absenMasuk($izin, AttendanceStatus::Izin);
    absenMasuk($sakit, AttendanceStatus::Sakit);

    $this->artisan('poin:sapu', ['--date' => '2026-10-15']);

    expect(poinOtomatis($izin))->toBe([])->and(poinOtomatis($sakit))->toBe([]);
});

test('koreksi absensi menghapus poin otomatis tetapi poin manual tetap', function () {
    $school = sekolahPoin();
    $siswa = siswaPoin($school);
    absenMasuk(siswaPoin($school), AttendanceStatus::Hadir, '2026-10-13');
    $this->artisan('poin:sapu', ['--date' => '2026-10-13']);
    PoinSiswa::withoutGlobalScopes()->create([
        'school_id' => $school->id, 'student_id' => $siswa->id, 'tanggal' => '2026-10-13',
        'sumber' => PoinSiswa::MANUAL, 'kategori' => 'Bolos', 'poin' => 15, 'alasan' => 'Kabur jam ke-3',
    ]);
    expect(poinOtomatis($siswa))->toBe(['2026-10-13 ALPA 10', '2026-10-13 MANUAL 15']);

    // Izin disetujui dua hari kemudian: sapuan hari ini ikut mencocokkan ulang.
    absenMasuk($siswa, AttendanceStatus::Izin, '2026-10-13');
    $this->artisan('poin:sapu', ['--date' => '2026-10-15']);

    expect(poinOtomatis($siswa))->toBe(['2026-10-13 MANUAL 15']);
});

test('sekolah yang belum menyalakan fitur tidak disapu dan halamannya tertutup', function () {
    $school = sekolahPoin(['feature_poin_pelanggaran' => false]);
    $siswa = siswaPoin($school);
    absenMasuk(siswaPoin($school), AttendanceStatus::Hadir);

    $this->artisan('poin:sapu', ['--date' => '2026-10-15']);

    expect(poinOtomatis($siswa))->toBe([]);
    $this->actingAs(adminSekolah($school))->get(route('admin.poin.index'))->assertForbidden();
});

test('menyalakan fitur tidak menimpakan poin hari-hari sebelumnya', function () {
    $school = sekolahPoin();
    $school->settings = collect($school->settings)->except(SapuanPoin::SETTING_AKTIF_SEJAK)->all();
    $school->save();
    $siswa = siswaPoin($school);
    absenMasuk(siswaPoin($school), AttendanceStatus::Hadir, '2026-10-14');
    absenMasuk(siswaPoin($school), AttendanceStatus::Hadir, '2026-10-15');

    $this->artisan('poin:sapu');

    expect(poinOtomatis($siswa))->toBe(['2026-10-15 ALPA 10'])
        ->and($school->fresh()->getSetting(SapuanPoin::SETTING_AKTIF_SEJAK))->toBe('2026-10-15');
});

test('poin 0 berarti jenis itu tidak dihitung', function () {
    $school = sekolahPoin(['poin_terlambat' => 0]);
    $siswa = siswaPoin($school);
    absenMasuk($siswa, AttendanceStatus::Terlambat);

    $this->artisan('poin:sapu', ['--date' => '2026-10-15']);

    expect(poinOtomatis($siswa))->toBe([]);
});

// ── halaman admin ──────────────────────────────────────────────────────────
test('admin mencatat pelanggaran manual dan rekap menampilkan level', function () {
    $school = sekolahPoin(['poin_level' => ['kuning' => 10, 'orange' => 20, 'merah' => 30]]);
    $kelas = Classroom::factory()->create(['school_id' => $school->id]);
    $siswa = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $kelas->id, 'is_active' => true]);
    $admin = adminSekolah($school);

    $this->actingAs($admin)->post(route('admin.poin.store'), [
        'student_id' => $siswa->id, 'kategori' => 'Berkelahi', 'poin' => 25,
        'tanggal' => '2026-10-15', 'alasan' => 'Berkelahi di kantin',
    ])->assertSessionHasNoErrors();

    $poin = PoinSiswa::withoutGlobalScopes()->sole();
    expect($poin->sumber)->toBe('MANUAL')->and($poin->dicatat_oleh)->toBe($admin->id)->and($poin->kunci_otomatis)->toBeNull();

    $this->actingAs($admin)->get(route('admin.poin.index'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/poin/index')
            ->where('siswa.data.0.total', 25)
            ->where('siswa.data.0.level', 'orange')
            ->where('ringkasan.orange', 1)
            ->where('bolehKelola', true));
});

test('tanggal masa depan dan alasan kosong ditolak', function () {
    $school = sekolahPoin();
    $siswa = siswaPoin($school);

    $this->actingAs(adminSekolah($school))->post(route('admin.poin.store'), [
        'student_id' => $siswa->id, 'kategori' => 'Bolos', 'poin' => 5, 'tanggal' => '2026-10-16', 'alasan' => '',
    ])->assertSessionHasErrors(['tanggal', 'alasan']);
});

test('guru bisa melihat tetapi tidak bisa mencatat, menghapus, atau mengatur', function () {
    $school = sekolahPoin();
    $siswa = siswaPoin($school);
    $poin = PoinSiswa::withoutGlobalScopes()->create([
        'school_id' => $school->id, 'student_id' => $siswa->id, 'tanggal' => '2026-10-15',
        'sumber' => PoinSiswa::MANUAL, 'kategori' => 'Bolos', 'poin' => 5, 'alasan' => 'x',
    ]);
    $guru = adminSekolah($school, 'GURU');

    $this->actingAs($guru)->get(route('admin.poin.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('bolehKelola', false));
    $this->actingAs($guru)->get(route('admin.poin.riwayat'))->assertOk();
    $this->actingAs($guru)->post(route('admin.poin.store'), ['student_id' => $siswa->id])->assertForbidden();
    $this->actingAs($guru)->delete(route('admin.poin.destroy', $poin))->assertForbidden();
    $this->actingAs($guru)->get(route('admin.poin.pengaturan'))->assertForbidden();
});

test('poin otomatis tidak bisa dihapus manual, poin manual bisa', function () {
    $school = sekolahPoin();
    $siswa = siswaPoin($school);
    $otomatis = PoinSiswa::withoutGlobalScopes()->create([
        'school_id' => $school->id, 'student_id' => $siswa->id, 'tanggal' => '2026-10-15',
        'sumber' => PoinSiswa::ALPA, 'kunci_otomatis' => 'auto', 'poin' => 10,
    ]);
    $manual = PoinSiswa::withoutGlobalScopes()->create([
        'school_id' => $school->id, 'student_id' => $siswa->id, 'tanggal' => '2026-10-15',
        'sumber' => PoinSiswa::MANUAL, 'kategori' => 'Bolos', 'poin' => 5, 'alasan' => 'x',
    ]);
    $admin = adminSekolah($school);

    $this->actingAs($admin)->delete(route('admin.poin.destroy', $otomatis))->assertStatus(422);
    $this->actingAs($admin)->delete(route('admin.poin.destroy', $manual))->assertRedirect();

    expect(PoinSiswa::withoutGlobalScopes()->pluck('id')->all())->toBe([$otomatis->id]);
});

test('admin sekolah lain tidak bisa melihat, mencatat, atau menghapus poin', function () {
    $a = sekolahPoin();
    $b = sekolahPoin();
    $siswaA = siswaPoin($a, 'SISWA A');
    $poinA = PoinSiswa::withoutGlobalScopes()->create([
        'school_id' => $a->id, 'student_id' => $siswaA->id, 'tanggal' => '2026-10-15',
        'sumber' => PoinSiswa::MANUAL, 'kategori' => 'Bolos', 'poin' => 5, 'alasan' => 'x',
    ]);
    $adminB = adminSekolah($b);

    $this->actingAs($adminB)->get(route('admin.poin.index'))
        ->assertInertia(fn (Assert $page) => $page->has('siswa.data', 0));
    $this->actingAs($adminB)->delete(route('admin.poin.destroy', $poinA))->assertNotFound();
    $this->actingAs($adminB)->post(route('admin.poin.store'), [
        'student_id' => $siswaA->id, 'kategori' => 'Bolos', 'poin' => 5, 'tanggal' => '2026-10-15', 'alasan' => 'coba',
    ])->assertSessionHasErrors('student_id');

    expect(PoinSiswa::withoutGlobalScopes()->count())->toBe(1);
});

test('total hanya menghitung sejak awal semester', function () {
    $school = sekolahPoin(['poin_semester_mulai' => '2026-07-01']);
    $siswa = siswaPoin($school);
    foreach (['2026-06-30' => 40, '2026-07-01' => 5] as $tanggal => $poin) {
        PoinSiswa::withoutGlobalScopes()->create([
            'school_id' => $school->id, 'student_id' => $siswa->id, 'tanggal' => $tanggal,
            'sumber' => PoinSiswa::MANUAL, 'kategori' => 'Bolos', 'poin' => $poin, 'alasan' => 'x',
        ]);
    }

    $this->actingAs(adminSekolah($school))->get(route('admin.poin.index'))
        ->assertInertia(fn (Assert $page) => $page->where('siswa.data.0.total', 5));
});

test('pengaturan disimpan per sekolah dan ambang level wajib menaik', function () {
    $school = sekolahPoin();
    $lain = sekolahPoin();
    $admin = adminSekolah($school);

    $this->actingAs($admin)->put(route('admin.poin.pengaturan.update'), [
        'terlambat' => 2, 'alpa' => 4, 'kategori' => [['nama' => 'Rambut panjang', 'poin' => 3]],
        'level' => ['kuning' => 50, 'orange' => 40, 'merah' => 60], 'semester_mulai' => '2026-07-01',
    ])->assertSessionHasErrors('level.orange');

    $this->actingAs($admin)->put(route('admin.poin.pengaturan.update'), [
        'terlambat' => 2, 'alpa' => 4, 'kategori' => [['nama' => 'Rambut panjang', 'poin' => 3]],
        'level' => ['kuning' => 10, 'orange' => 20, 'merah' => 30], 'semester_mulai' => '2026-07-01',
    ])->assertSessionHasNoErrors();

    $settings = $school->fresh()->settings;
    expect($settings['poin_terlambat'])->toBe(2)
        ->and($settings['poin_kategori'])->toBe([['nama' => 'Rambut panjang', 'poin' => 3]])
        ->and($settings['poin_level'])->toBe(['kuning' => 10, 'orange' => 20, 'merah' => 30])
        ->and($settings['feature_poin_pelanggaran'])->toBeTrue()
        ->and($lain->fresh()->getSetting('poin_terlambat'))->toBeNull();
});

// ── detail siswa & portal orang tua ──────────────────────────────────────────
test('tab poin di detail siswa memuat total dan riwayat', function () {
    $school = sekolahPoin();
    $siswa = siswaPoin($school);
    PoinSiswa::withoutGlobalScopes()->create([
        'school_id' => $school->id, 'student_id' => $siswa->id, 'tanggal' => '2026-10-15',
        'sumber' => PoinSiswa::MANUAL, 'kategori' => 'Bolos', 'poin' => 15, 'alasan' => 'Kabur',
    ]);

    $this->actingAs(adminSekolah($school))->get(route('admin.siswa.show', $siswa))
        ->assertInertia(fn (Assert $page) => $page->where('fiturPoin', true)->missing('poin')
            ->reloadOnly('poin', fn (Assert $reload) => $reload
                ->where('poin.total', 15)
                ->where('poin.riwayat.0.label', 'Bolos')));
});

test('portal orang tua hanya menampilkan poin anaknya sendiri', function () {
    $school = sekolahPoin();
    $ortu = User::factory()->create(['school_id' => $school->id]);
    $ortu->assignRole('ORANG_TUA');
    $profil = ParentProfile::factory()->create(['school_id' => $school->id, 'user_id' => $ortu->id]);
    $anak = Student::factory()->create(['school_id' => $school->id, 'parent_profile_id' => $profil->id, 'is_active' => true]);
    $orangLain = siswaPoin($school);
    foreach ([$anak, $orangLain] as $siswa) {
        PoinSiswa::withoutGlobalScopes()->create([
            'school_id' => $school->id, 'student_id' => $siswa->id, 'tanggal' => '2026-10-15',
            'sumber' => PoinSiswa::MANUAL, 'kategori' => 'Bolos', 'poin' => 15, 'alasan' => 'Kabur',
        ]);
    }

    $this->actingAs($ortu)->get(route('orangtua.poin', ['anak' => $anak->id]))
        ->assertInertia(fn (Assert $page) => $page->component('orangtua/poin')
            ->where('poin.total', 15)->has('poin.riwayat', 1));
    $this->actingAs($ortu)->get(route('orangtua.poin', ['anak' => $orangLain->id]))->assertForbidden();
});

test('pengaturan sekolah punya tab poin hanya bila fitur aktif dan boleh kelola', function () {
    $aktif = sekolahPoin(['poin_alpa' => 12]);
    $mati = sekolahPoin(['feature_poin_pelanggaran' => false]);

    $this->actingAs(adminSekolah($aktif))->get(route('admin.pengaturan', ['tab' => 'poin']))
        ->assertInertia(fn (Assert $page) => $page->where('poinSetup.alpa', 12));
    $this->actingAs(adminSekolah($mati))->get(route('admin.pengaturan'))
        ->assertInertia(fn (Assert $page) => $page->where('poinSetup', null));
});

test('guru BK mencatat pelanggaran bebas langsung dari detail siswa tanpa setup', function () {
    $school = School::factory()->create(['is_active' => true, 'settings' => ['feature_poin_pelanggaran' => true]]);
    $siswa = siswaPoin($school);

    $this->actingAs(adminSekolah($school))->get(route('admin.siswa.show', $siswa))
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('poin', fn (Assert $reload) => $reload
            ->where('poin.total', 0)->has('poin.kategori')));

    $this->actingAs(adminSekolah($school))->post(route('admin.poin.store'), [
        'student_id' => $siswa->id, 'kategori' => 'Merokok di toilet', 'poin' => 40,
        'tanggal' => '2026-10-15', 'alasan' => 'Tertangkap saat istirahat',
    ])->assertSessionHasNoErrors();

    expect(poinOtomatis($siswa))->toBe(['2026-10-15 MANUAL 40'])
        ->and(PoinSiswa::withoutGlobalScopes()->sole()->kategori)->toBe('Merokok di toilet');
});
