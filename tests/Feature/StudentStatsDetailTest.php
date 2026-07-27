<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\PrayerType;
use App\Enums\Religion;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\Classroom;
use App\Models\PrayerAttendance;
use App\Models\School;
use App\Models\Student;
use App\Services\Student\StudentStatsBuilder;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Senin PERTAMA di dalam bulan berjalan — startOfWeek() bisa melompat ke
    // bulan sebelumnya dan membuat seluruh baris jatuh di luar rentang default.
    $this->monday = SchoolTime::now()->startOfMonth();

    while ($this->monday->dayOfWeekIso !== Carbon::MONDAY) {
        $this->monday = $this->monday->addDay();
    }

    $this->school = School::factory()->create(['settings' => ['prayer_enabled' => true]]);
    $this->classroom = Classroom::factory()->create(['school_id' => $this->school->id]);

    foreach (range(1, 5) as $day) {
        AttendanceSchedule::factory()->create([
            'school_id' => $this->school->id,
            'classroom_id' => null,
            'day_of_week' => $day,
            'is_active' => true,
            'late_threshold' => '08:00',
        ]);
    }

    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'classroom_id' => $this->classroom->id,
        'religion' => Religion::Islam,
    ]);

    $this->stats = app(StudentStatsBuilder::class);
});

function checkIn(Student $student, Carbon $day, string $time, AttendanceStatus $status = AttendanceStatus::Hadir): void
{
    Attendance::factory()->create([
        'school_id' => $student->school_id,
        'student_id' => $student->id,
        'attendance_date' => $day->toDateString(),
        'type' => AttendanceType::CheckIn,
        'status' => $status,
        'recorded_at' => $day->copy()->setTimeFromTimeString($time),
    ]);
}

function statsRange(): array
{
    return [
        SchoolTime::now()->startOfMonth()->toDateString(),
        SchoolTime::now()->endOfMonth()->toDateString(),
    ];
}

test('it reports the average, earliest and latest check-in time', function () {
    checkIn($this->student, $this->monday, '06:50');
    checkIn($this->student, $this->monday->copy()->addDay(), '07:10');
    checkIn($this->student, $this->monday->copy()->addDays(2), '07:40');

    [$start, $end] = statsRange();
    $data = $this->stats->attendanceFor($this->student, $start, $end);

    expect($data['punctuality']['avg_check_in'])->toBe('07:13')
        ->and($data['punctuality']['earliest'])->toBe('06:50')
        ->and($data['punctuality']['latest'])->toBe('07:40');
});

test('the stored clock time is the reported clock time', function () {
    // recorded_at adalah jam dinding Jakarta di kolom UTC. Satu
    // SchoolTime::toLocal() yang tidak sengaja tertambah akan menggeser
    // seluruh statistik jam sebanyak tujuh jam.
    checkIn($this->student, $this->monday, '07:05');

    [$start, $end] = statsRange();
    $data = $this->stats->attendanceFor($this->student, $start, $end);

    expect($data['punctuality']['avg_check_in'])->toBe('07:05');
});

test('it measures how late a late check-in actually is', function () {
    checkIn($this->student, $this->monday, '08:20', AttendanceStatus::Terlambat);
    checkIn($this->student, $this->monday->copy()->addDay(), '08:40', AttendanceStatus::Terlambat);

    [$start, $end] = statsRange();
    $data = $this->stats->attendanceFor($this->student, $start, $end);

    // Ambang 08:00 → 20 dan 40 menit, rata-rata 30.
    expect($data['punctuality']['avg_late_minutes'])->toBe(30);
});

test('it buckets lateness by weekday and names the worst day', function () {
    checkIn($this->student, $this->monday, '08:20', AttendanceStatus::Terlambat);
    checkIn($this->student, $this->monday->copy()->addWeek(), '08:30', AttendanceStatus::Terlambat);
    checkIn($this->student, $this->monday->copy()->addDay(), '07:00');
    checkIn($this->student, $this->monday->copy()->addWeek()->addDay(), '07:00');

    [$start, $end] = statsRange();
    $data = $this->stats->attendanceFor($this->student, $start, $end);

    expect($data['by_weekday']['worst_day'])->toBe('Senin');

    $senin = collect($data['by_weekday']['series'])->firstWhere('weekday', 'Senin');
    expect($senin['terlambat'])->toBe(2);
});

test('it measures the longest present and absent streaks', function () {
    // Lima hari efektif; siswa hadir di hari 1, 2, dan 4.
    foreach (range(0, 4) as $offset) {
        $day = $this->monday->copy()->addDays($offset);

        // Hari efektif ditentukan oleh adanya baris absensi di sekolah itu.
        Attendance::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => Student::factory()->create(['school_id' => $this->school->id])->id,
            'attendance_date' => $day->toDateString(),
            'type' => AttendanceType::CheckIn,
        ]);

        if (in_array($offset, [0, 1, 3], true)) {
            checkIn($this->student, $day, '07:00');
        }
    }

    [$start, $end] = statsRange();
    $data = $this->stats->attendanceFor($this->student, $start, $end);

    expect($data['streaks']['longest_present'])->toBe(2)
        ->and($data['streaks']['longest_absent'])->toBe(1);
});

test('it compares the student against the class average', function () {
    checkIn($this->student, $this->monday, '07:00');

    foreach (range(1, 2) as $index) {
        $peer = Student::factory()->create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom->id,
        ]);

        checkIn($peer, $this->monday, '08:30', AttendanceStatus::Terlambat);
    }

    [$start, $end] = statsRange();
    $data = $this->stats->attendanceFor($this->student, $start, $end);

    expect($data['comparison']['class_size'])->toBe(3)
        ->and($data['comparison']['class_rate'])->toBe(100.0)
        ->and($data['comparison']['class_late_rate'])->toBe(66.7);
});

test('a row on the very last day of the range is still counted', function () {
    // Regresi untuk whereBetween pada kolom `date`: cast menulis komponen jam,
    // sehingga '…-31 00:00:00' > '…-31' secara leksikografis dan barisnya
    // hilang diam-diam di SQLite.
    $lastDay = SchoolTime::now()->endOfMonth()->startOfDay();

    checkIn($this->student, $lastDay, '07:00');

    [$start, $end] = statsRange();
    $data = $this->stats->attendanceFor($this->student, $start, $end);

    expect($data['summary']['hadir'])->toBe(1)
        ->and($data['recent'])->toHaveCount(1);
});

test('the query count stays constant as students and rows grow', function () {
    foreach (range(0, 4) as $offset) {
        checkIn($this->student, $this->monday->copy()->addDays($offset), '07:00');
    }

    foreach (range(1, 10) as $index) {
        $peer = Student::factory()->create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom->id,
        ]);

        foreach (range(0, 3) as $offset) {
            checkIn($peer, $this->monday->copy()->addDays($offset), '07:00');
        }
    }

    [$start, $end] = statsRange();

    DB::enableQueryLog();
    $this->stats->attendanceFor($this->student, $start, $end);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Jaring pengaman N+1: turunan dihitung di PHP, bukan lewat query per hari
    // atau per teman sekelas.
    expect($queries)->toBeLessThanOrEqual(8);
});

test('prayer stats break down per type', function () {
    $this->school->setSetting('prayer_dhuha_enabled', true);

    checkIn($this->student, $this->monday, '07:00');

    PrayerAttendance::factory()->create([
        'school_id' => $this->school->id,
        'student_id' => $this->student->id,
        'prayer_date' => $this->monday->toDateString(),
        'prayer_type' => PrayerType::Dhuha,
    ]);

    [$start, $end] = statsRange();
    $data = $this->stats->prayerFor($this->student, $start, $end);

    expect($data['types'])->toHaveCount(2)
        ->and($data['summary']['opportunities'])->toBe(2)
        ->and($data['summary']['hadir'])->toBe(1);

    $dhuha = collect($data['types'])->firstWhere('type', PrayerType::Dhuha->value);
    $dzuhur = collect($data['types'])->firstWhere('type', PrayerType::Dzuhur->value);

    expect($dhuha['summary']['hadir'])->toBe(1)
        ->and($dzuhur['summary']['hadir'])->toBe(0);
});

test('a disabled prayer type disappears from the breakdown', function () {
    checkIn($this->student, $this->monday, '07:00');

    [$start, $end] = statsRange();
    $data = $this->stats->prayerFor($this->student, $start, $end);

    expect($data['types'])->toHaveCount(1)
        ->and($data['types'][0]['type'])->toBe(PrayerType::Dzuhur->value);
});

test('prayer stats can be narrowed to a single type', function () {
    $this->school->setSetting('prayer_dhuha_enabled', true);

    checkIn($this->student, $this->monday, '07:00');

    [$start, $end] = statsRange();
    $data = $this->stats->prayerFor($this->student, $start, $end, PrayerType::Dhuha);

    expect($data['types'])->toHaveCount(1)
        ->and($data['types'][0]['type'])->toBe(PrayerType::Dhuha->value);
});

// ---------------------------------------------------------------- Regresi audit

test('B-7: an ALPA day is not counted as present in the weekday chart', function () {
    // Dulu hanya TERLAMBAT yang dipisah, sehingga Senin bisa tampil 100% hadir
    // di chart sementara kartu ringkasan di layar yang sama menyebut alpa 2.
    checkIn($this->student, $this->monday, '07:00', AttendanceStatus::Alpa);
    checkIn($this->student, $this->monday->copy()->addWeek(), '07:00', AttendanceStatus::Alpa);

    [$start, $end] = statsRange();
    $data = $this->stats->attendanceFor($this->student, $start, $end);

    $senin = collect($data['by_weekday']['series'])->firstWhere('weekday', 'Senin');

    expect($senin['alpa'])->toBe(2)
        ->and($senin['hadir'])->toBe(0)
        ->and($senin['rate'])->toBe(0.0);
});

test('B-8: an IZIN day shows up as its own series, not an empty bar', function () {
    checkIn($this->student, $this->monday, '07:00', AttendanceStatus::Izin);
    checkIn($this->student, $this->monday->copy()->addDay(), '07:00', AttendanceStatus::Sakit);

    [$start, $end] = statsRange();
    $data = $this->stats->attendanceFor($this->student, $start, $end);

    $first = collect($data['daily'])->firstWhere('date', $this->monday->translatedFormat('d M'));
    $second = collect($data['daily'])->firstWhere('date', $this->monday->copy()->addDay()->translatedFormat('d M'));

    expect($first['izin'])->toBe(1)
        ->and($first['tanpa_keterangan'])->toBe(0)
        ->and($second['sakit'])->toBe(1);
});

test('B-9: effective days stay correct after the SQL-side dedup', function () {
    // Dua baris (check-in + check-out) pada hari yang sama harus tetap satu
    // hari efektif.
    checkIn($this->student, $this->monday, '07:00');

    Attendance::factory()->create([
        'school_id' => $this->school->id,
        'student_id' => $this->student->id,
        'attendance_date' => $this->monday->toDateString(),
        'type' => AttendanceType::CheckOut,
        'recorded_at' => $this->monday->copy()->setTime(14, 0),
    ]);

    [$start, $end] = statsRange();
    $data = $this->stats->attendanceFor($this->student, $start, $end);

    expect($data['summary']['effective_days'])->toBe(1);
});

test('B-3: a student without a school gets an empty prayer payload, not a leak', function () {
    $orphan = Student::factory()->create(['school_id' => null]);

    [$start, $end] = statsRange();
    $data = $this->stats->prayerFor($orphan, $start, $end);

    expect($data['types'])->toBe([])
        ->and($data['summary']['hadir'])->toBe(0)
        ->and($data['enabled'])->toBeFalse();
});
