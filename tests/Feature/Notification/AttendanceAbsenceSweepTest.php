<?php

use App\Enums\AttendanceAlertKind;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\SchoolChannelType;
use App\Jobs\SendAttendanceAlert;
use App\Models\Attendance;
use App\Models\AttendanceAlert;
use App\Models\AttendanceSchedule;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use App\Models\Student;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * Waktu dibekukan pada Senin 24 Agustus 2026 supaya hasilnya tidak bergantung
 * pada hari saat suite dijalankan. Jendela bawaan jadwal: masuk 06:00, ambang
 * terlambat 08:00, mulai pulang 13:00, tenggang 30 menit — jadi 14:00 berarti
 * kedua jendela sudah tutup, dan 09:00 hanya jendela terlambat.
 */
beforeEach(function () {
    $this->senin = Carbon::create(2026, 8, 24, 14, 0, 0, SchoolTime::timezone());
    $this->travelTo($this->senin);

    $this->school = School::factory()->create([
        'is_active' => true,
        'settings' => [
            'whatsapp_enabled' => true,
            'wa_alert_only' => true,
            'wa_alert_terlambat' => true,
            'wa_alert_alpa' => true,
        ],
    ]);

    // Hari kerja saja; akhir pekan sengaja dibiarkan tanpa jadwal.
    foreach (range(1, 5) as $day) {
        AttendanceSchedule::factory()->create([
            'school_id' => $this->school->id,
            'classroom_id' => null,
            'day_of_week' => $day,
            'is_active' => true,
        ]);
    }
});

function siswaBerortu(School $school, string $nama): Student
{
    $parent = ParentProfile::factory()->create([
        'school_id' => $school->id,
        'whatsapp_number' => '0812'.fake()->unique()->numerify('########'),
    ]);

    return Student::factory()->create([
        'school_id' => $school->id,
        'parent_profile_id' => $parent->id,
        'full_name' => $nama,
        'is_active' => true,
    ]);
}

function catatMasuk(Student $student, AttendanceStatus $status, Carbon $date): Attendance
{
    return Attendance::factory()->create([
        'school_id' => $student->school_id,
        'student_id' => $student->id,
        'attendance_date' => $date->toDateString(),
        'type' => AttendanceType::CheckIn,
        'status' => $status,
        'recorded_at' => $date->copy()->setTime(8, 30),
    ]);
}

test('yang terlambat dan yang tidak datang sama-sama terkumpul', function () {
    Queue::fake();

    $terlambat = siswaBerortu($this->school, 'Siswa Terlambat');
    $absen = siswaBerortu($this->school, 'Siswa Absen');
    $hadir = siswaBerortu($this->school, 'Siswa Hadir');

    catatMasuk($terlambat, AttendanceStatus::Terlambat, $this->senin);
    catatMasuk($hadir, AttendanceStatus::Hadir, $this->senin);

    $this->artisan('attendance:notify-absence')->assertSuccessful();

    $alerts = AttendanceAlert::withoutGlobalScope('school')->get();

    expect($alerts)->toHaveCount(2)
        ->and($alerts->firstWhere('student_id', $terlambat->id)->kind)->toBe(AttendanceAlertKind::Terlambat)
        ->and($alerts->firstWhere('student_id', $absen->id)->kind)->toBe(AttendanceAlertKind::Alpa)
        ->and($alerts->firstWhere('student_id', $hadir->id))->toBeNull();

    Queue::assertPushed(SendAttendanceAlert::class, 2);
});

/**
 * Izin dan sakit adalah ketidakhadiran berizin. Mengabarkannya sebagai
 * peringatan berarti menegur orang tua yang sudah memberi kabar.
 */
test('izin dan sakit tidak pernah jadi sasaran', function () {
    Queue::fake();

    $izin = siswaBerortu($this->school, 'Siswa Izin');
    $sakit = siswaBerortu($this->school, 'Siswa Sakit');

    catatMasuk($izin, AttendanceStatus::Izin, $this->senin);
    catatMasuk($sakit, AttendanceStatus::Sakit, $this->senin);

    $this->artisan('attendance:notify-absence')->assertSuccessful();

    expect(AttendanceAlert::withoutGlobalScope('school')->count())->toBe(0);
});

/**
 * ALPA yang diisi admin manual adalah pernyataan eksplisit bahwa siswanya tidak
 * datang — justru itu yang perlu dikabarkan.
 */
test('alpa yang diisi manual tetap dikabarkan', function () {
    Queue::fake();

    $alpa = siswaBerortu($this->school, 'Siswa Alpa Manual');
    catatMasuk($alpa, AttendanceStatus::Alpa, $this->senin);

    $this->artisan('attendance:notify-absence')->assertSuccessful();

    expect(AttendanceAlert::withoutGlobalScope('school')->where('student_id', $alpa->id)->first()?->kind)
        ->toBe(AttendanceAlertKind::Alpa);
});

/**
 * Penjaga terpenting. Nol check-in sepanjang hari berarti libur yang tidak
 * terdaftar, bukan seluruh siswa membolos bersamaan — tanpa ini satu libur
 * nasional mengirimi SETIAP orang tua pesan "anak Anda tidak hadir".
 */
test('hari tanpa satu pun check-in dianggap libur', function () {
    Queue::fake();

    siswaBerortu($this->school, 'Siswa A');
    siswaBerortu($this->school, 'Siswa B');

    $this->artisan('attendance:notify-absence')->assertSuccessful();

    expect(AttendanceAlert::withoutGlobalScope('school')->count())->toBe(0);
    Queue::assertNotPushed(SendAttendanceAlert::class);
});

test('hari tanpa jadwal aktif dilewati', function () {
    Queue::fake();

    // Minggu; jadwal hanya dibuat untuk hari 1-5.
    $this->travelTo(Carbon::create(2026, 8, 23, 14, 0, 0, SchoolTime::timezone()));

    $siswa = siswaBerortu($this->school, 'Siswa Minggu');
    catatMasuk($siswa, AttendanceStatus::Terlambat, SchoolTime::now());

    $this->artisan('attendance:notify-absence')->assertSuccessful();

    expect(AttendanceAlert::withoutGlobalScope('school')->count())->toBe(0);
});

/**
 * Ketidakhadiran baru boleh dinilai setelah jendela masuk BENAR-BENAR habis,
 * yaitu `check_out_start` — bukan `check_in_end`. Pukul 09:00 siswa yang belum
 * datang masih bisa absen dan tercatat terlambat.
 */
test('sebelum jendela masuk habis hanya keterlambatan yang dikumpulkan', function () {
    Queue::fake();

    $this->travelTo($this->senin->copy()->setTime(9, 0));

    $terlambat = siswaBerortu($this->school, 'Siswa Terlambat');
    $belumDatang = siswaBerortu($this->school, 'Siswa Belum Datang');

    catatMasuk($terlambat, AttendanceStatus::Terlambat, $this->senin);

    $this->artisan('attendance:notify-absence')->assertSuccessful();

    $alerts = AttendanceAlert::withoutGlobalScope('school')->get();

    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()->student_id)->toBe($terlambat->id)
        ->and($alerts->firstWhere('student_id', $belumDatang->id))->toBeNull();
});

test('sebelum ambang terlambat lewat tidak ada apa-apa yang dikumpulkan', function () {
    Queue::fake();

    $this->travelTo($this->senin->copy()->setTime(7, 30));

    $terlambat = siswaBerortu($this->school, 'Siswa Terlambat');
    catatMasuk($terlambat, AttendanceStatus::Terlambat, $this->senin);

    $this->artisan('attendance:notify-absence')->assertSuccessful();

    expect(AttendanceAlert::withoutGlobalScope('school')->count())->toBe(0);
});

test('sapuan kedua tidak mengabarkan ulang', function () {
    Queue::fake();

    $terlambat = siswaBerortu($this->school, 'Siswa Terlambat');
    catatMasuk($terlambat, AttendanceStatus::Terlambat, $this->senin);

    $this->artisan('attendance:notify-absence')->assertSuccessful();
    $this->artisan('attendance:notify-absence')->assertSuccessful();

    expect(AttendanceAlert::withoutGlobalScope('school')->count())->toBe(1);
    Queue::assertPushed(SendAttendanceAlert::class, 1);
});

test('skenario yang dimatikan tidak dikumpulkan', function () {
    Queue::fake();

    $this->school->setSetting('wa_alert_terlambat', false);
    $this->school->save();

    $terlambat = siswaBerortu($this->school, 'Siswa Terlambat');
    $absen = siswaBerortu($this->school, 'Siswa Absen');
    catatMasuk($terlambat, AttendanceStatus::Terlambat, $this->senin);

    $this->artisan('attendance:notify-absence')->assertSuccessful();

    $alerts = AttendanceAlert::withoutGlobalScope('school')->get();

    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()->student_id)->toBe($absen->id);
});

test('sekolah yang belum membalik kebijakan tidak disapu sama sekali', function () {
    Queue::fake();

    $this->school->setSetting('wa_alert_only', false);
    $this->school->save();

    $terlambat = siswaBerortu($this->school, 'Siswa Terlambat');
    catatMasuk($terlambat, AttendanceStatus::Terlambat, $this->senin);

    $this->artisan('attendance:notify-absence')->assertSuccessful();

    expect(AttendanceAlert::withoutGlobalScope('school')->count())->toBe(0);
});

test('siswa tanpa nomor WhatsApp orang tua dilewati tanpa meninggalkan alert', function () {
    Queue::fake();

    $tanpaNomor = Student::factory()->create([
        'school_id' => $this->school->id,
        'parent_profile_id' => ParentProfile::factory()->create([
            'school_id' => $this->school->id,
            // Kolomnya NOT NULL; "tidak punya nomor" di data nyata berupa
            // string kosong, bukan null.
            'whatsapp_number' => '',
        ])->id,
        'is_active' => true,
    ]);

    catatMasuk($tanpaNomor, AttendanceStatus::Terlambat, $this->senin);

    $this->artisan('attendance:notify-absence')->assertSuccessful();

    expect(AttendanceAlert::withoutGlobalScope('school')->count())->toBe(0);
});

/**
 * Satu nomor dengan enam anak ditemukan di data prod. Tanpa penggabungan,
 * nomor itu menerima enam WhatsApp nyaris identik dalam satu detik.
 */
test('anak dari satu nomor digabung jadi satu pesan', function () {
    Queue::fake();

    $ortu = ParentProfile::factory()->create([
        'school_id' => $this->school->id,
        'whatsapp_number' => '081391444323',
    ]);

    $anak = collect(['ADIK BUNGSU', 'KAKAK SULUNG', 'ANAK TENGAH'])->map(
        fn (string $nama) => Student::factory()->create([
            'school_id' => $this->school->id,
            'parent_profile_id' => $ortu->id,
            'full_name' => $nama,
            'is_active' => true,
        ]),
    );

    catatMasuk($anak[1], AttendanceStatus::Terlambat, $this->senin);

    $this->artisan('attendance:notify-absence')->assertSuccessful();

    // Tiga baris alert tetap ditulis — sapuan berikutnya harus tahu ketiganya
    // sudah dikabarkan — tapi hanya satu pesan yang dikirim.
    expect(AttendanceAlert::withoutGlobalScope('school')->count())->toBe(3);
    Queue::assertPushed(SendAttendanceAlert::class, 1);

    $primary = AttendanceAlert::withoutGlobalScope('school')->whereNotNull('combined_children')->get();

    expect($primary)->toHaveCount(1)
        // Urut nama, jadi ADIK BUNGSU yang jadi primary.
        ->and($primary->first()->student_id)->toBe($anak[0]->id)
        ->and($primary->first()->combined_children)->toHaveCount(3)
        ->and(array_column($primary->first()->combined_children, 'nama'))
        ->toBe(['ADIK BUNGSU', 'ANAK TENGAH', 'KAKAK SULUNG'])
        ->and(collect($primary->first()->combined_children)->firstWhere('nama', 'KAKAK SULUNG')['status'])
        ->toBe('Terlambat');
});

/**
 * `081391444323` dan `81391444323` orang yang sama, dan keduanya ada di data
 * prod untuk satu keluarga.
 */
test('nomor dengan dan tanpa nol depan dihitung satu orang', function () {
    Queue::fake();

    foreach (['082223201301', '82223201301', '+6282223201301'] as $index => $nomor) {
        $ortu = ParentProfile::factory()->create([
            'school_id' => $this->school->id,
            'whatsapp_number' => $nomor,
        ]);

        Student::factory()->create([
            'school_id' => $this->school->id,
            'parent_profile_id' => $ortu->id,
            'full_name' => "Anak {$index}",
            'is_active' => true,
        ]);
    }

    catatMasuk(siswaBerortu($this->school, 'Siswa Hadir'), AttendanceStatus::Hadir, $this->senin);

    $this->artisan('attendance:notify-absence')->assertSuccessful();

    Queue::assertPushed(SendAttendanceAlert::class, 1);
});

/**
 * Anak yang sudah dikabarkan pagi tidak boleh ikut lagi ke pesan sore —
 * orang tuanya akan membaca keterlambatan yang sama dua kali.
 */
test('anak yang sudah dikabarkan tidak ikut digabung di sapuan berikutnya', function () {
    Queue::fake();

    $ortu = ParentProfile::factory()->create([
        'school_id' => $this->school->id,
        'whatsapp_number' => '081391444323',
    ]);

    $terlambat = Student::factory()->create([
        'school_id' => $this->school->id,
        'parent_profile_id' => $ortu->id,
        'full_name' => 'ANAK TERLAMBAT',
        'is_active' => true,
    ]);

    $absen = Student::factory()->create([
        'school_id' => $this->school->id,
        'parent_profile_id' => $ortu->id,
        'full_name' => 'ANAK ABSEN',
        'is_active' => true,
    ]);

    catatMasuk($terlambat, AttendanceStatus::Terlambat, $this->senin);

    // Pagi: hanya jendela terlambat yang sudah tutup.
    $this->travelTo($this->senin->copy()->setTime(9, 0));
    $this->artisan('attendance:notify-absence')->assertSuccessful();

    // Sore: giliran yang tidak datang.
    $this->travelTo($this->senin->copy()->setTime(14, 0));
    $this->artisan('attendance:notify-absence')->assertSuccessful();

    Queue::assertPushed(SendAttendanceAlert::class, 2);

    $sore = AttendanceAlert::withoutGlobalScope('school')->where('student_id', $absen->id)->firstOrFail();

    expect($sore->combined_children)->toHaveCount(1)
        ->and($sore->combined_children[0]['nama'])->toBe('ANAK ABSEN');
});

/**
 * Ujung ke ujung: satu panggilan ke gateway, isinya memuat ketiga anak.
 */
test('pesan yang benar-benar terkirim memuat seluruh anak', function () {
    Http::fake(['wa.ozolab.id/*' => Http::response(['status' => true])]);

    SchoolNotificationChannel::create([
        'school_id' => $this->school->id,
        'channel' => SchoolChannelType::OzolabWa,
        'is_active' => true,
    ]);

    $ortu = ParentProfile::factory()->create([
        'school_id' => $this->school->id,
        'whatsapp_number' => '081391444323',
    ]);

    foreach (['ADIK BUNGSU', 'ANAK TENGAH', 'KAKAK SULUNG'] as $nama) {
        Student::factory()->create([
            'school_id' => $this->school->id,
            'parent_profile_id' => $ortu->id,
            'full_name' => $nama,
            'is_active' => true,
        ]);
    }

    catatMasuk(siswaBerortu($this->school, 'Siswa Hadir'), AttendanceStatus::Hadir, $this->senin);

    $this->artisan('attendance:notify-absence')->assertSuccessful();

    Http::assertSentCount(1);

    Http::assertSent(function ($request) {
        $pesan = $request['message'];

        return str_contains($pesan, 'ADIK BUNGSU')
            && str_contains($pesan, 'ANAK TENGAH')
            && str_contains($pesan, 'KAKAK SULUNG')
            && str_contains($pesan, 'Tidak Hadir');
    });
});

test('dry-run tidak menulis maupun mengantrekan apa pun', function () {
    Queue::fake();

    $terlambat = siswaBerortu($this->school, 'Siswa Terlambat');
    siswaBerortu($this->school, 'Siswa Absen');
    catatMasuk($terlambat, AttendanceStatus::Terlambat, $this->senin);

    $this->artisan('attendance:notify-absence', ['--dry-run' => true])->assertSuccessful();

    expect(AttendanceAlert::withoutGlobalScope('school')->count())->toBe(0);
    Queue::assertNotPushed(SendAttendanceAlert::class);
});
