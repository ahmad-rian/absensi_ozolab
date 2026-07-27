<?php

namespace App\Services\Student;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\PrayerType;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\PrayerAttendance;
use App\Models\Student;
use App\Support\PrayerSchedule;
use App\Support\PrayerSettings;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Statistik absensi satu siswa untuk rentang tanggal tertentu.
 *
 * Dipakai bersama oleh halaman detail siswa dan controller export, supaya
 * angka di layar dan di file CSV/PDF dijamin identik.
 *
 * Prinsip: satu query menarik baris mentah, seluruh turunannya dihitung di PHP.
 * Itu menghindari `strftime` (SQLite) vs `DAYOFWEEK` (MySQL) yang tidak bisa
 * diuji di dua mesin sekaligus, dan menjaga jumlah query tetap konstan.
 */
class StudentStatsBuilder
{
    private const WEEKDAY_LABELS = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    /**
     * Rentang default: bulan berjalan, sama seperti LaporanController.
     *
     * @return array{start: string, end: string}
     */
    public function resolveRange(?string $start, ?string $end): array
    {
        return [
            'start' => $start ?: SchoolTime::now()->startOfMonth()->toDateString(),
            'end' => $end ?: SchoolTime::now()->endOfMonth()->toDateString(),
        ];
    }

    /**
     * Label rentang siap tampil, dirakit di server supaya layar, CSV, dan PDF
     * tidak pernah menampilkan format yang berbeda.
     */
    public function rangeLabel(string $start, string $end): string
    {
        return Carbon::parse($start)->translatedFormat('d M Y')
            .' s/d '.Carbon::parse($end)->translatedFormat('d M Y');
    }

    /**
     * @return array<string, mixed>
     */
    public function attendanceFor(Student $student, string $start, string $end): array
    {
        $rows = $this->attendanceRows($student, $start, $end);
        $checkIns = $rows->where('type', AttendanceType::CheckIn);

        $counts = $checkIns->countBy(fn (Attendance $row) => $row->status->value);

        $hadir = (int) ($counts[AttendanceStatus::Hadir->value] ?? 0);
        $terlambat = (int) ($counts[AttendanceStatus::Terlambat->value] ?? 0);
        $izin = (int) ($counts[AttendanceStatus::Izin->value] ?? 0);
        $sakit = (int) ($counts[AttendanceStatus::Sakit->value] ?? 0);
        $alpa = (int) ($counts[AttendanceStatus::Alpa->value] ?? 0);

        $recorded = $hadir + $terlambat + $izin + $sakit + $alpa;
        $effectiveDates = $this->effectiveDates($student, $start, $end);
        $effectiveDays = count($effectiveDates);

        // Hari efektif tanpa catatan sama sekali tetap dihitung tidak hadir,
        // supaya persentase tidak menipu saat siswa jarang di-scan.
        $noRecord = max(0, $effectiveDays - $recorded);
        $present = $hadir + $terlambat;
        $denominator = max($recorded, $effectiveDays);

        $presentDates = $checkIns
            ->filter(fn (Attendance $row) => in_array($row->status, [AttendanceStatus::Hadir, AttendanceStatus::Terlambat], true))
            ->map(fn (Attendance $row) => $row->attendance_date->toDateString())
            ->unique()
            ->values()
            ->all();

        return [
            'summary' => [
                'hadir' => $hadir,
                'terlambat' => $terlambat,
                'izin' => $izin,
                'sakit' => $sakit,
                'alpa' => $alpa,
                'tanpa_keterangan' => $noRecord,
                'effective_days' => $effectiveDays,
                'recorded_days' => $recorded,
                'rate' => $denominator > 0 ? round($present / $denominator * 100, 1) : 0.0,
                // Kehadiran tanpa keterlambatan — "hadir" saja bisa menutupi
                // siswa yang selalu masuk tapi selalu telat.
                'punctual_rate' => $denominator > 0 ? round($hadir / $denominator * 100, 1) : 0.0,
            ],
            'punctuality' => $this->punctuality($student, $checkIns),
            'by_weekday' => $this->weekdayBuckets($student, $effectiveDates, $checkIns),
            'streaks' => $this->streaks($effectiveDates, $presentDates),
            'monthly' => $this->monthlyTrend($student),
            'heatmap' => $this->attendanceHeatmap($effectiveDates, $checkIns),
            'comparison' => $this->classComparison($student, $start, $end),
            'daily' => $this->fillDailySeries($effectiveDates, $checkIns),
            'recent' => $rows
                ->sortByDesc(fn (Attendance $row) => $row->attendance_date->toDateString().' '.($row->recorded_at?->format('H:i:s') ?? ''))
                ->take(30)
                ->map(fn (Attendance $row) => [
                    'id' => $row->id,
                    'date' => $row->attendance_date->format('d M Y'),
                    'type' => $row->type->value,
                    'type_label' => $row->type->label(),
                    'status' => $row->status->value,
                    'status_label' => $row->status->label(),
                    'time' => $row->recorded_at?->format('H:i'),
                    'device_id' => $row->device_id,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Statistik absen sholat.
     *
     * Seluruh key lama tetap ada dan kini bermakna GABUNGAN semua jenis aktif,
     * ditambah key baru `types` berisi rincian per jenis. Bentuk lama dijaga
     * supaya view PDF dan controller export tidak perlu diubah serentak.
     *
     * @param  ?PrayerType  $only  batasi ke satu jenis; null = semua yang aktif
     * @return array<string, mixed>
     */
    public function prayerFor(Student $student, string $start, string $end, ?PrayerType $only = null): array
    {
        $schedule = $student->school ? PrayerSchedule::for($student->school) : null;

        $types = $schedule
            ? array_map(fn (PrayerSettings $settings) => $settings->type, $schedule->enabled())
            : [];

        if ($only !== null) {
            $types = array_values(array_filter($types, fn (PrayerType $type) => $type === $only));
        }

        // Satu query untuk semua jenis; pengelompokan dikerjakan di PHP supaya
        // tidak ada query tambahan per jenis sholat.
        $rows = PrayerAttendance::query()
            ->withoutGlobalScope('school')
            ->where('student_id', $student->id)
            ->whereDate('prayer_date', '>=', $start)
            ->whereDate('prayer_date', '<=', $end)
            ->when($types !== [], fn ($query) => $query->whereIn(
                'prayer_type',
                array_map(fn (PrayerType $type) => $type->value, $types),
            ))
            ->orderByDesc('prayer_date')
            ->orderByDesc('recorded_at')
            ->get();

        $effectiveDates = $this->effectiveDates($student, $start, $end);
        $effectiveDays = count($effectiveDates);

        $byType = $rows->groupBy(fn (PrayerAttendance $row) => $row->prayer_type->value);

        $perType = [];
        foreach ($types as $type) {
            $settings = $schedule->get($type);
            $typeRows = $byType->get($type->value, collect());
            $hadir = $typeRows->count();

            $presentDates = $typeRows
                ->map(fn (PrayerAttendance $row) => $row->prayer_date->toDateString())
                ->unique()
                ->values()
                ->all();

            $perType[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'short_label' => $type->shortLabel(),
                'enabled' => $settings->enabled,
                'window' => $settings->windowLabel(),
                'summary' => [
                    'hadir' => $hadir,
                    'tidak_hadir' => max(0, $effectiveDays - $hadir),
                    'effective_days' => $effectiveDays,
                    'rate' => $effectiveDays > 0 ? round($hadir / $effectiveDays * 100, 1) : 0.0,
                ],
                'punctuality' => $this->timeStats($typeRows->map(fn (PrayerAttendance $row) => $row->recorded_at)),
                'streaks' => $this->streaks($effectiveDates, $presentDates),
                'by_weekday' => $this->weekdayBuckets($student, $effectiveDates, $typeRows, 'prayer_date'),
                'heatmap' => $this->prayerHeatmap($effectiveDates, $presentDates),
                'daily' => $this->prayerDailySeries($effectiveDates, $presentDates),
                'recent' => $typeRows->take(30)->map(fn (PrayerAttendance $row) => [
                    'id' => $row->id,
                    'date' => $row->prayer_date->format('d M Y'),
                    'type' => $row->prayer_type->value,
                    'type_label' => $row->prayer_type->label(),
                    'status' => $row->status->value,
                    'status_label' => $row->status->label(),
                    'time' => $row->recorded_at?->format('H:i'),
                    'device_id' => $row->device_id,
                ])->values()->all(),
            ];
        }

        $totalHadir = $rows->count();
        // Denominator gabungan = hari efektif × jumlah jenis aktif, supaya 80%
        // berarti 8 dari 10 KESEMPATAN sholat, bukan 8 dari 5 hari.
        $opportunities = $effectiveDays * max(count($types), 1);

        return [
            'enabled' => $schedule?->anyEnabled() ?? false,
            'covered' => $schedule?->covers($student) ?? false,
            // null = ikut aturan sekolah; true/false = dioverride per siswa
            'opt_in' => $student->prayer_opt_in,
            'school_includes_all' => $schedule?->get(PrayerType::Dzuhur)->allReligions ?? false,
            'religion_label' => $student->religion?->label(),
            'window' => $schedule?->anyEnabled() ? $schedule->windowsLabel() : null,
            'summary' => [
                'hadir' => $totalHadir,
                'tidak_hadir' => max(0, $opportunities - $totalHadir),
                'effective_days' => $effectiveDays,
                'opportunities' => $opportunities,
                'rate' => $opportunities > 0 ? round($totalHadir / $opportunities * 100, 1) : 0.0,
            ],
            'daily' => $this->prayerDailySeries(
                $effectiveDates,
                $rows->map(fn (PrayerAttendance $row) => $row->prayer_date->toDateString())->unique()->values()->all(),
            ),
            'recent' => collect($perType)
                ->flatMap(fn (array $type) => $type['recent'])
                ->sortByDesc('date')
                ->take(30)
                ->values()
                ->all(),
            'types' => $perType,
        ];
    }

    /**
     * Dipakai controller export untuk judul file dan header dokumen.
     */
    public function label(Student $student): string
    {
        return trim(($student->nis ?? $student->id).' - '.$student->full_name);
    }

    // ------------------------------------------------------------------ Data

    /**
     * @return Collection<int, Attendance>
     */
    private function attendanceRows(Student $student, string $start, string $end): Collection
    {
        // Global scope `school` mati di konteks console/queue, jadi school_id
        // difilter eksplisit — kalau tidak, angka laporan bisa berbeda antara
        // permintaan web dan command terjadwal.
        return Attendance::query()
            ->withoutGlobalScope('school')
            ->where('student_id', $student->id)
            // whereDate, bukan whereBetween: cast `date` menulis komponen jam
            // di SQLite, sehingga '2026-07-31 00:00:00' > '2026-07-31' secara
            // leksikografis dan baris di tanggal terakhir hilang diam-diam.
            ->whereDate('attendance_date', '>=', $start)
            ->whereDate('attendance_date', '<=', $end)
            ->orderBy('attendance_date')
            ->get();
    }

    /**
     * Daftar tanggal efektif — bukan cuma jumlahnya — karena hitungan runtun,
     * bucket hari, dan heatmap semuanya butuh tanggalnya.
     *
     * @return array<int, string> 'Y-m-d', menaik
     */
    private function effectiveDates(Student $student, string $start, string $end): array
    {
        return Attendance::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $student->school_id)
            ->whereDate('attendance_date', '>=', $start)
            ->whereDate('attendance_date', '<=', $end)
            ->orderBy('attendance_date')
            ->pluck('attendance_date')
            // DISTINCT di SQL bisa meloloskan tanggal yang sama dua kali karena
            // komponen jam di kolom `date`; normalisasi di PHP.
            ->map(fn ($value) => Carbon::parse($value)->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    // --------------------------------------------------------------- Metrik

    /**
     * @param  Collection<int, Attendance>  $checkIns
     * @return array<string, mixed>
     */
    private function punctuality(Student $student, Collection $checkIns): array
    {
        $stats = $this->timeStats($checkIns->map(fn (Attendance $row) => $row->recorded_at));

        $thresholds = $this->lateThresholds($student);
        $lateMinutes = [];

        foreach ($checkIns as $row) {
            if ($row->status !== AttendanceStatus::Terlambat || ! $row->recorded_at) {
                continue;
            }

            $threshold = $thresholds[$row->attendance_date->dayOfWeekIso] ?? null;

            if (! $threshold) {
                continue;
            }

            $lateMinutes[] = $this->minutesOfDay($row->recorded_at) - $this->clockToMinutes($threshold);
        }

        return [
            ...$stats,
            'avg_late_minutes' => $lateMinutes === [] ? null : (int) round(array_sum($lateMinutes) / count($lateMinutes)),
            'deadline' => $thresholds === [] ? null : substr((string) reset($thresholds), 0, 5),
        ];
    }

    /**
     * Ambang telat per hari-ISO, satu query untuk seluruh jadwal sekolah.
     * Tanpa ini, mencari ambang per tanggal berarti N+1 lewat ScheduleResolver.
     *
     * @return array<int, string>
     */
    private function lateThresholds(Student $student): array
    {
        $schedules = AttendanceSchedule::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $student->school_id)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('classroom_id')->orWhere('classroom_id', $student->classroom_id))
            ->get(['day_of_week', 'classroom_id', 'late_threshold']);

        $map = [];

        foreach ($schedules as $schedule) {
            // Jadwal khusus kelas menang atas jadwal global, sama seperti
            // ScheduleResolver::resolve().
            $existing = $map[$schedule->day_of_week] ?? null;

            if ($existing === null || $schedule->classroom_id !== null) {
                $map[$schedule->day_of_week] = (string) $schedule->late_threshold;
            }
        }

        return $map;
    }

    /**
     * @param  Collection<int, ?CarbonInterface>  $times
     * @return array{avg_check_in: ?string, earliest: ?string, latest: ?string}
     */
    private function timeStats(Collection $times): array
    {
        // recorded_at menyimpan jam dinding Jakarta di kolom UTC — dibaca apa
        // adanya. Satu SchoolTime::toLocal() yang tidak sengaja tertambah akan
        // menggeser seluruh statistik jam sebanyak 7 jam.
        $minutes = $times->filter()->map(fn (CarbonInterface $time) => $this->minutesOfDay($time))->values();

        if ($minutes->isEmpty()) {
            return ['avg_check_in' => null, 'earliest' => null, 'latest' => null];
        }

        return [
            'avg_check_in' => $this->minutesToClock((int) round($minutes->avg())),
            'earliest' => $this->minutesToClock((int) $minutes->min()),
            'latest' => $this->minutesToClock((int) $minutes->max()),
        ];
    }

    /**
     * Sebaran per hari-dalam-minggu, plus hari terlemahnya.
     *
     * @param  array<int, string>  $effectiveDates
     * @param  Collection<int, mixed>  $rows
     * @return array<string, mixed>
     */
    private function weekdayBuckets(Student $student, array $effectiveDates, Collection $rows, string $dateColumn = 'attendance_date'): array
    {
        $buckets = [];

        foreach ($effectiveDates as $date) {
            $iso = Carbon::parse($date)->dayOfWeekIso;
            $buckets[$iso] ??= ['effective' => 0, 'hadir' => 0, 'terlambat' => 0];
            $buckets[$iso]['effective']++;
        }

        foreach ($rows as $row) {
            $iso = $row->{$dateColumn}->dayOfWeekIso;

            if (! isset($buckets[$iso])) {
                continue;
            }

            if ($row instanceof Attendance && $row->status === AttendanceStatus::Terlambat) {
                $buckets[$iso]['terlambat']++;

                continue;
            }

            $buckets[$iso]['hadir']++;
        }

        ksort($buckets);

        $series = [];
        foreach ($buckets as $iso => $bucket) {
            $present = $bucket['hadir'] + $bucket['terlambat'];

            $series[] = [
                'weekday' => self::WEEKDAY_LABELS[$iso] ?? (string) $iso,
                'effective' => $bucket['effective'],
                'hadir' => $bucket['hadir'],
                'terlambat' => $bucket['terlambat'],
                'rate' => $bucket['effective'] > 0 ? round($present / $bucket['effective'] * 100, 1) : 0.0,
                'late_rate' => $bucket['effective'] > 0 ? round($bucket['terlambat'] / $bucket['effective'] * 100, 1) : 0.0,
            ];
        }

        // Ambang dua hari supaya satu hari sial tidak langsung jadi kesimpulan.
        $worst = collect($series)
            ->filter(fn (array $row) => $row['effective'] >= 2 && $row['late_rate'] > 0)
            ->sortByDesc('late_rate')
            ->first();

        return ['series' => $series, 'worst_day' => $worst['weekday'] ?? null];
    }

    /**
     * Runtun terpanjang, dihitung atas HARI EFEKTIF (bukan kalender), supaya
     * libur di tengah minggu tidak memutus runtun secara palsu.
     *
     * @param  array<int, string>  $effectiveDates
     * @param  array<int, string>  $presentDates
     * @return array<string, mixed>
     */
    private function streaks(array $effectiveDates, array $presentDates): array
    {
        $present = array_flip($presentDates);
        $longestPresent = 0;
        $longestAbsent = 0;
        $run = 0;
        $runKind = null;
        $lastAbsent = null;

        foreach ($effectiveDates as $date) {
            $kind = isset($present[$date]) ? 'hadir' : 'tidak_hadir';

            if ($kind === 'tidak_hadir') {
                $lastAbsent = $date;
            }

            $run = $kind === $runKind ? $run + 1 : 1;
            $runKind = $kind;

            if ($kind === 'hadir') {
                $longestPresent = max($longestPresent, $run);
            } else {
                $longestAbsent = max($longestAbsent, $run);
            }
        }

        return [
            'current_length' => $run,
            'current_kind' => $runKind,
            'longest_present' => $longestPresent,
            'longest_absent' => $longestAbsent,
            'last_absent_date' => $lastAbsent ? Carbon::parse($lastAbsent)->translatedFormat('d M Y') : null,
        ];
    }

    /**
     * Tren 12 bulan terakhir.
     *
     * SENGAJA mengabaikan filter rentang: rentang default adalah bulan
     * berjalan, jadi tanpa pengecualian ini chart tren selalu satu titik.
     *
     * @return array<int, array<string, mixed>>
     */
    private function monthlyTrend(Student $student): array
    {
        $from = SchoolTime::now()->copy()->startOfMonth()->subMonths(11);

        $rows = Attendance::query()
            ->withoutGlobalScope('school')
            ->where('student_id', $student->id)
            ->where('type', AttendanceType::CheckIn)
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->get(['attendance_date', 'status']);

        $buckets = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $from->copy()->addMonths($i);
            $buckets[$month->format('Y-m')] = [
                'month' => $month->translatedFormat('M y'),
                'hadir' => 0,
                'terlambat' => 0,
                'total' => 0,
            ];
        }

        foreach ($rows as $row) {
            $key = $row->attendance_date->format('Y-m');

            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['total']++;

            if ($row->status === AttendanceStatus::Hadir) {
                $buckets[$key]['hadir']++;
            } elseif ($row->status === AttendanceStatus::Terlambat) {
                $buckets[$key]['terlambat']++;
            }
        }

        return collect($buckets)
            ->map(fn (array $bucket) => [
                'month' => $bucket['month'],
                'hadir' => $bucket['hadir'],
                'terlambat' => $bucket['terlambat'],
                'rate' => $bucket['total'] > 0
                    ? round(($bucket['hadir'] + $bucket['terlambat']) / $bucket['total'] * 100, 1)
                    : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * Perbandingan siswa vs rata-rata kelas — SATU query agregat, bukan satu
     * query per teman sekelas.
     *
     * @return array<string, mixed>
     */
    private function classComparison(Student $student, string $start, string $end): array
    {
        if (! $student->classroom_id) {
            return ['class_rate' => null, 'class_late_rate' => null, 'class_size' => 0, 'class_name' => null];
        }

        $rows = Attendance::query()
            ->withoutGlobalScope('school')
            ->where('attendances.school_id', $student->school_id)
            ->where('type', AttendanceType::CheckIn)
            ->whereDate('attendance_date', '>=', $start)
            ->whereDate('attendance_date', '<=', $end)
            ->whereIn('student_id', function ($query) use ($student) {
                $query->select('id')
                    ->from('students')
                    ->where('classroom_id', $student->classroom_id)
                    ->whereNull('deleted_at');
            })
            ->get(['student_id', 'status']);

        $total = $rows->count();

        if ($total === 0) {
            return ['class_rate' => null, 'class_late_rate' => null, 'class_size' => 0, 'class_name' => $student->classroom?->name];
        }

        $present = $rows->filter(fn ($row) => in_array($row->status, [AttendanceStatus::Hadir, AttendanceStatus::Terlambat], true))->count();
        $late = $rows->where('status', AttendanceStatus::Terlambat)->count();

        return [
            'class_rate' => round($present / $total * 100, 1),
            'class_late_rate' => round($late / $total * 100, 1),
            'class_size' => $rows->pluck('student_id')->unique()->count(),
            'class_name' => $student->classroom?->name,
        ];
    }

    // --------------------------------------------------------------- Series

    /**
     * @param  array<int, string>  $effectiveDates
     * @param  Collection<int, Attendance>  $checkIns
     * @return array<int, array<string, mixed>>
     */
    private function fillDailySeries(array $effectiveDates, Collection $checkIns): array
    {
        $indexed = [];

        foreach ($checkIns as $row) {
            $indexed[$row->attendance_date->toDateString()][$row->status->value] = true;
        }

        $result = [];

        foreach ($effectiveDates as $date) {
            $statuses = $indexed[$date] ?? [];

            $result[] = [
                'date' => Carbon::parse($date)->translatedFormat('d M'),
                'hadir' => isset($statuses[AttendanceStatus::Hadir->value]) ? 1 : 0,
                'terlambat' => isset($statuses[AttendanceStatus::Terlambat->value]) ? 1 : 0,
                'tidak_hadir' => $statuses === [] ? 1 : 0,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $effectiveDates
     * @param  array<int, string>  $presentDates
     * @return array<int, array<string, mixed>>
     */
    private function prayerDailySeries(array $effectiveDates, array $presentDates): array
    {
        $present = array_flip($presentDates);

        return array_map(fn (string $date) => [
            'date' => Carbon::parse($date)->translatedFormat('d M'),
            'hadir' => isset($present[$date]) ? 1 : 0,
            'tidak_hadir' => isset($present[$date]) ? 0 : 1,
        ], $effectiveDates);
    }

    /**
     * @param  array<int, string>  $effectiveDates
     * @param  Collection<int, Attendance>  $checkIns
     * @return array<int, array{date: string, state: string}>
     */
    private function attendanceHeatmap(array $effectiveDates, Collection $checkIns): array
    {
        $byDate = [];

        foreach ($checkIns as $row) {
            $byDate[$row->attendance_date->toDateString()] = strtolower($row->status->value);
        }

        return array_map(fn (string $date) => [
            'date' => $date,
            'state' => $byDate[$date] ?? 'tanpa_keterangan',
        ], $effectiveDates);
    }

    /**
     * @param  array<int, string>  $effectiveDates
     * @param  array<int, string>  $presentDates
     * @return array<int, array{date: string, state: string}>
     */
    private function prayerHeatmap(array $effectiveDates, array $presentDates): array
    {
        $present = array_flip($presentDates);

        return array_map(fn (string $date) => [
            'date' => $date,
            'state' => isset($present[$date]) ? 'hadir' : 'tidak_hadir',
        ], $effectiveDates);
    }

    // --------------------------------------------------------------- Helper

    private function minutesOfDay(CarbonInterface $time): int
    {
        return (int) $time->format('H') * 60 + (int) $time->format('i');
    }

    private function clockToMinutes(string $clock): int
    {
        [$hour, $minute] = array_pad(explode(':', $clock), 2, '0');

        return ((int) $hour) * 60 + (int) $minute;
    }

    private function minutesToClock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
