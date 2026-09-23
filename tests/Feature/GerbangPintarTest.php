<?php

use App\Enums\AttendanceType;
use App\Enums\Religion;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\PrayerAttendance;
use App\Models\School;
use App\Models\Student;
use App\Support\SchoolTime;
use Carbon\Carbon;

/*
 | Satu gerbang, empat jenis absen.
 |
 | Sebelum ini absen sholat hanya bisa lewat URL-nya sendiri, dan halaman ringan
 | — yang justru dibuat untuk box Android berspesifikasi rendah — tidak punya
 | versi sholat sama sekali. Sekarang satu tautan melayani datang, Dhuha,
 | Dzuhur, dan pulang.
 |
 | Yang dijaga berkas ini bukan "sholat bisa dicatat" (itu sudah dijaga
 | PrayerDhuhaTest), melainkan ATURAN PRIORITASNYA: pada jam yang sama sebuah
 | tempelan kartu bisa berarti dua hal, dan yang menentukan adalah apa yang
 | belum tercatat.
 */

beforeEach(function () {
    // Dihitung sebelum travelTo apa pun, supaya rentangnya tidak bergeser.
    $this->senin = SchoolTime::now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();

    $this->school = School::factory()->create([
        'scan_short_code' => 'uji-gerbang',
        'settings' => [
            'prayer_enabled' => true,
            'prayer_dhuha_enabled' => true,
        ],
    ]);

    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'religion' => Religion::Islam,
    ]);

    foreach (range(1, 5) as $hari) {
        AttendanceSchedule::factory()->create([
            'school_id' => $this->school->id,
            'classroom_id' => null,
            'day_of_week' => $hari,
            'is_active' => true,
        ]);
    }
});

/** Tempel kartu lewat alamat pendek — jalur yang dipakai box Android. */
function tempel(string $jam, ?Student $siswa = null)
{
    test()->travelTo(test()->senin->copy()->setTimeFromTimeString($jam));

    return test()->postJson('/g/uji-gerbang', [
        'token' => ($siswa ?? test()->student)->qr_token,
    ]);
}

test('satu hari penuh lewat satu tautan yang sama', function () {
    /*
        Inti seluruh pekerjaan ini. Empat tempelan pada satu URL menghasilkan
        empat catatan berbeda — dan yang memilih jenisnya server, bukan
        operator yang harus ingat membuka halaman mana.
    */
    tempel('06:42')->assertOk()
        ->assertJsonPath('student.type', 'CHECK_IN')
        ->assertJsonPath('student.type_label', 'Masuk');

    tempel('07:51')->assertOk()
        ->assertJsonPath('student.type', 'PRAYER')
        ->assertJsonPath('student.type_label', 'Sholat Dhuha');

    tempel('11:20')->assertOk()
        ->assertJsonPath('student.type', 'PRAYER')
        ->assertJsonPath('student.type_label', 'Sholat Dzuhur');

    tempel('13:30')->assertOk()
        ->assertJsonPath('student.type', 'CHECK_OUT')
        ->assertJsonPath('student.type_label', 'Pulang');

    // Statistik kehadiran sekolah tetap bersih: sholat tidak ikut menambah
    // baris di `attendances`.
    expect(Attendance::where('student_id', $this->student->id)->count())->toBe(2)
        ->and(PrayerAttendance::where('student_id', $this->student->id)->count())->toBe(2);
});

test('datang menang atas sholat ketika keduanya mungkin', function () {
    /*
        Pukul 07:45 berada di dalam jendela masuk DAN jendela Dhuha. Kalau
        sholat yang menang, siswa yang baru tiba tidak pernah tercatat hadir —
        ia dihitung alpa seharian dan orang tuanya menerima peringatan.
    */
    tempel('07:45')->assertOk()->assertJsonPath('student.type', 'CHECK_IN');

    expect(PrayerAttendance::where('student_id', $this->student->id)->count())->toBe(0);
});

test('sholat tercatat pada tempelan berikutnya, sesudah kehadirannya terekam', function () {
    tempel('07:45')->assertOk()->assertJsonPath('student.type', 'CHECK_IN');
    tempel('07:46')->assertOk()->assertJsonPath('student.type_label', 'Sholat Dhuha');

    expect(Attendance::where('student_id', $this->student->id)->count())->toBe(1)
        ->and(PrayerAttendance::where('student_id', $this->student->id)->count())->toBe(1);
});

test('siswa yang tidak ikut sholat dijawab soal absensinya, bukan soal agamanya', function () {
    /*
        Pesan `PrayerAttendanceRecorder` untuk siswa non-Islam berbunyi "Absen
        sholat hanya untuk siswa beragama Islam". Kalimat itu benar di
        tempatnya, tapi muncul di gerbang utama pada anak yang sekadar menempel
        kartu dua kali akan terbaca seperti tuduhan.
    */
    $kristen = Student::factory()->create([
        'school_id' => $this->school->id,
        'religion' => Religion::Kristen,
    ]);

    tempel('07:40', $kristen)->assertOk()->assertJsonPath('student.type', 'CHECK_IN');

    $ulang = tempel('07:45', $kristen);

    $ulang->assertStatus(422);
    expect($ulang->json('message'))->toContain('Sudah absen masuk')
        ->and($ulang->json('message'))->not->toContain('beragama Islam');
});

test('tempelan berlebih menyebut apa saja yang sudah tercatat', function () {
    /*
        "Sudah absen masuk" saja membuat operator mengira sholatnya belum masuk,
        lalu menempelkan kartu berulang kali. Sebutkan keduanya sekalian.
    */
    tempel('06:42')->assertOk();
    tempel('07:51')->assertOk();

    $ketiga = tempel('07:55');

    $ketiga->assertStatus(422);
    expect($ketiga->json('message'))
        ->toContain('Sudah lengkap hari ini')
        ->toContain('masuk 06:42')
        ->toContain('Sholat Dhuha 07:51');
});

test('sekolah tanpa fitur sholat berperilaku persis seperti sebelumnya', function () {
    /*
        Penjaga regresi. Seluruh PublicScannerTest, LightScannerTest, dan
        ScanShortCodeTest berjalan pada sekolah tanpa sholat dan harus tetap
        hijau tanpa diubah; tes ini menyatakan aturannya secara langsung.
    */
    $this->school->forceFill(['settings' => ['prayer_enabled' => false, 'prayer_dhuha_enabled' => false]])->save();

    tempel('07:45')->assertOk()->assertJsonPath('student.type', 'CHECK_IN');

    $ulang = tempel('07:50');
    $ulang->assertStatus(422);

    expect($ulang->json('message'))->toContain('Sudah absen masuk')
        ->and(PrayerAttendance::count())->toBe(0);
});

test('sekolah yang hanya memakai absen sholat tidak terkunci dari gerbangnya', function () {
    /*
        Penjaga fitur dulu hanya melihat `AbsensiSekolah`. Begitu satu gerbang
        melayani semuanya, sekolah yang mematikan absensi sekolah tapi memakai
        sholat akan dijawab 403 di pintunya sendiri.
    */
    $this->school->forceFill(['settings' => [
        'feature_absensi_sekolah' => false,
        'prayer_enabled' => true,
        'prayer_dhuha_enabled' => true,
    ]])->save();

    $this->get('/g/uji-gerbang')->assertOk();

    tempel('07:45')->assertOk()->assertJsonPath('student.type', 'PRAYER');
});

test('halaman ringan menampilkan jenis absen tanpa pernah memuat token', function () {
    // Label jenisnya datang dari respons scan, bukan dari HTML — yang diperiksa
    // di sini hanya bahwa halamannya tetap tidak membocorkan token sekolah,
    // karena alamat pendeknya memang dibuat gampang ditebak.
    $this->get('/g/uji-gerbang')
        ->assertOk()
        ->assertDontSee($this->school->scanner_token);
});

test('jenis yang dicatat tidak pernah datang dari client', function () {
    // Client yang boleh memilih jenis berarti siapa pun yang memegang qr_token
    // bisa mengarang catatan sholat dari rumah.
    test()->travelTo($this->senin->copy()->setTimeFromTimeString('13:30'));

    $this->postJson('/g/uji-gerbang', [
        'token' => $this->student->qr_token,
        'type' => AttendanceType::CheckIn->value,
        'jenis' => 'PRAYER',
    ])->assertOk()->assertJsonPath('student.type', 'CHECK_OUT');
});
