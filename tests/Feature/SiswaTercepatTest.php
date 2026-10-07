<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\PrayerAttendance;
use App\Models\School;
use App\Models\Student;
use App\Services\Dashboard\SiswaTercepat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/** Hari ini yang tetap, supaya rentang 1/2/3 bulan tidak bergeser mengikuti jam tes. */
function hariTes(): Carbon
{
    return Carbon::parse('2026-10-06', 'Asia/Jakarta');
}

function datangPagi(Student $siswa, string $tanggal, string $jam, AttendanceStatus $status = AttendanceStatus::Hadir): void
{
    Attendance::factory()->create([
        'student_id' => $siswa->id,
        'attendance_date' => $tanggal,
        'type' => AttendanceType::CheckIn,
        'status' => $status,
        'recorded_at' => "{$tanggal} {$jam}:00",
    ]);
}

/** Hari sekolah berurutan mundur dari tanggal tes. */
function hariSekolah(int $jumlah, int $mundurAwal = 0): array
{
    return collect(range($mundurAwal, $mundurAwal + $jumlah - 1))
        ->map(fn (int $i) => hariTes()->copy()->subDays($i)->toDateString())
        ->all();
}

/** @return array<string, array<int, mixed>> */
function kategori(array $hasil): array
{
    return collect($hasil)->mapWithKeys(fn (array $k) => [$k['kunci'] => $k['periode']])->all();
}

beforeEach(function () {
    $this->sekolah = School::factory()->create(['settings' => []]);
    $this->kelas = Classroom::factory()->create(['school_id' => $this->sekolah->id, 'name' => 'VIII A']);
    $this->siswa = fn (string $nama, bool $aktif = true) => Student::factory()->create([
        'school_id' => $this->sekolah->id, 'classroom_id' => $this->kelas->id, 'full_name' => $nama, 'is_active' => $aktif,
    ]);
});

test('peringkat absen pagi diurutkan dari rata-rata jam datang paling awal', function () {
    $ani = ($this->siswa)('Ani Lestari');
    $budi = ($this->siswa)('Budi Santoso');
    foreach (hariSekolah(10) as $i => $tanggal) {
        datangPagi($ani, $tanggal, $i % 2 ? '06:10' : '06:20');
        datangPagi($budi, $tanggal, '06:40');
    }

    $pagi = kategori(app(SiswaTercepat::class)->hitung($this->sekolah, hariTes()))['pagi'][1];

    expect($pagi)->toHaveCount(2)
        ->and($pagi[0])->toMatchArray(['peringkat' => 1, 'nama' => 'ANI LESTARI', 'kelas' => 'VIII A', 'rataRata' => '06.15', 'hari' => 10, 'inisial' => 'AL'])
        ->and($pagi[1])->toMatchArray(['peringkat' => 2, 'nama' => 'BUDI SANTOSO', 'rataRata' => '06.40']);
});

test('siswa yang jarang hadir, nonaktif, atau dari sekolah lain tidak ikut peringkat', function () {
    $rajin = ($this->siswa)('Rajin');
    $sekaliPagi = ($this->siswa)('Sekali Pagi');
    $pindah = ($this->siswa)('Sudah Pindah', aktif: false);
    $lain = Student::factory()->create(['school_id' => School::factory()->create()->id]);
    foreach (hariSekolah(10) as $tanggal) {
        datangPagi($rajin, $tanggal, '06:45');
        datangPagi($pindah, $tanggal, '05:50');
        datangPagi($lain, $tanggal, '05:40');
    }
    datangPagi($sekaliPagi, hariTes()->toDateString(), '05:30');

    $nama = collect(kategori(app(SiswaTercepat::class)->hitung($this->sekolah, hariTes()))['pagi'][1])->pluck('nama')->all();

    expect($nama)->toBe(['RAJIN']);
});

test('izin, sakit, dan alpa bukan kedatangan, terlambat tetap kedatangan', function () {
    $siswa = ($this->siswa)('Citra');
    foreach (hariSekolah(4) as $tanggal) {
        datangPagi($siswa, $tanggal, '07:20', AttendanceStatus::Terlambat);
    }
    foreach (hariSekolah(6, 4) as $tanggal) {
        datangPagi($siswa, $tanggal, '05:00', AttendanceStatus::Izin);
    }

    $pagi = kategori(app(SiswaTercepat::class)->hitung($this->sekolah, hariTes()))['pagi'][1];

    expect($pagi[0])->toMatchArray(['rataRata' => '07.20', 'hari' => 4]);
});

test('periode 1, 2, dan 3 bulan memakai rentang masing-masing', function () {
    $baru = ($this->siswa)('Baru Rajin');
    $lama = ($this->siswa)('Dulu Rajin');
    foreach (hariSekolah(20) as $tanggal) {
        datangPagi($baru, $tanggal, '06:30');
    }
    foreach (hariSekolah(20, 40) as $tanggal) {
        datangPagi($lama, $tanggal, '06:00');
    }

    $periode = kategori(app(SiswaTercepat::class)->hitung($this->sekolah, hariTes()))['pagi'];

    expect(collect($periode[1])->pluck('nama')->all())->toBe(['BARU RAJIN'])
        ->and(collect($periode[3])->pluck('nama')->all())->toBe(['DULU RAJIN', 'BARU RAJIN']);
});

test('sholat dhuha dan dzuhur hanya muncul kalau fiturnya aktif', function () {
    $siswa = ($this->siswa)('Dewi');
    foreach (hariSekolah(5) as $i => $tanggal) {
        PrayerAttendance::factory()->dhuha()->create(['student_id' => $siswa->id, 'prayer_date' => $tanggal, 'recorded_at' => "{$tanggal} 07:4{$i}:00"]);
    }

    $mati = kategori(app(SiswaTercepat::class)->hitung($this->sekolah, hariTes()));
    $this->sekolah->update(['settings' => ['prayer_dhuha_enabled' => true]]);
    $hidup = kategori(app(SiswaTercepat::class)->hitung($this->sekolah->fresh(), hariTes()));

    expect(array_keys($mati))->toBe(['pagi'])
        ->and(array_keys($hidup))->toBe(['pagi', 'dhuha'])
        ->and($hidup['dhuha'][1][0])->toMatchArray(['nama' => 'DEWI', 'rataRata' => '07.42', 'hari' => 5]);
});

test('dashboard mengirim peringkat siswa tercepat sebagai prop tertunda', function () {
    Cache::flush();
    $user = createAdminUser();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('siswaTercepat')
            ->loadDeferredProps('peringkat', fn ($reload) => $reload->has('siswaTercepat.0', fn ($k) => $k
                ->where('kunci', 'pagi')
                ->where('label', 'Absen pagi')
                ->has('periode.1')
                ->has('periode.2')
                ->has('periode.3')
            ))
        );
});
