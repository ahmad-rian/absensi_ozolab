<?php

namespace App\Services\Student;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\PrayerAttendance;
use App\Models\Student;
use App\Support\PrayerSettings;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Statistik absensi satu siswa untuk rentang tanggal tertentu.
 *
 * Dipakai bersama oleh halaman detail siswa dan controller export, supaya
 * angka di layar dan di file CSV/PDF dijamin identik.
 */
class StudentStatsBuilder
{
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
     * @return array{summary: array<string, mixed>, daily: array<int, array<string, mixed>>, recent: array<int, array<string, mixed>>}
     */
    public function attendanceFor(Student $student, string $start, string $end): array
    {
        $counts = Attendance::where('student_id', $student->id)
            ->where('type', AttendanceType::CheckIn)
            ->whereBetween('attendance_date', [$start, $end])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $hadir = (int) ($counts[AttendanceStatus::Hadir->value] ?? 0);
        $terlambat = (int) ($counts[AttendanceStatus::Terlambat->value] ?? 0);
        $izin = (int) ($counts[AttendanceStatus::Izin->value] ?? 0);
        $sakit = (int) ($counts[AttendanceStatus::Sakit->value] ?? 0);
        $alpa = (int) ($counts[AttendanceStatus::Alpa->value] ?? 0);

        $recorded = $hadir + $terlambat + $izin + $sakit + $alpa;
        $effectiveDays = $this->effectiveDays($student, $start, $end);

        // Hari efektif tanpa catatan sama sekali tetap dihitung tidak hadir,
        // supaya persentase tidak menipu saat siswa jarang di-scan.
        $noRecord = max(0, $effectiveDays - $recorded);
        $present = $hadir + $terlambat;
        $denominator = max($recorded, $effectiveDays);

        $daily = Attendance::where('student_id', $student->id)
            ->where('type', AttendanceType::CheckIn)
            ->whereBetween('attendance_date', [$start, $end])
            ->selectRaw('attendance_date, status, MIN(recorded_at) as recorded_at')
            ->groupBy('attendance_date', 'status')
            ->orderBy('attendance_date')
            ->get();

        $recent = Attendance::with('recorder:id,name')
            ->where('student_id', $student->id)
            ->whereBetween('attendance_date', [$start, $end])
            ->orderByDesc('attendance_date')
            ->orderByDesc('recorded_at')
            ->take(30)
            ->get()
            ->map(fn (Attendance $row) => [
                'id' => $row->id,
                'date' => $row->attendance_date->format('d M Y'),
                'type' => $row->type->value,
                'type_label' => $row->type->label(),
                'status' => $row->status->value,
                'status_label' => $row->status->label(),
                'time' => $row->recorded_at?->format('H:i'),
                'device_id' => $row->device_id,
            ]);

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
            ],
            'daily' => $this->fillDailySeries($start, $end, $daily),
            'recent' => $recent->all(),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, daily: array<int, array<string, mixed>>, recent: array<int, array<string, mixed>>}
     */
    public function prayerFor(Student $student, string $start, string $end): array
    {
        $settings = $student->school
            ? PrayerSettings::for($student->school)
            : null;

        $rows = PrayerAttendance::where('student_id', $student->id)
            ->whereBetween('prayer_date', [$start, $end])
            ->orderByDesc('prayer_date')
            ->get();

        $hadir = $rows->count();
        $effectiveDays = $this->effectiveDays($student, $start, $end);
        $tidakHadir = max(0, $effectiveDays - $hadir);

        $byDate = $rows->keyBy(fn (PrayerAttendance $row) => $row->prayer_date->toDateString());

        $daily = [];
        foreach (CarbonPeriod::create($start, $end) as $date) {
            if ($date->isWeekend()) {
                continue;
            }

            $daily[] = [
                'date' => $date->format('d M'),
                'hadir' => isset($byDate[$date->toDateString()]) ? 1 : 0,
                'tidak_hadir' => isset($byDate[$date->toDateString()]) ? 0 : 1,
            ];
        }

        return [
            'enabled' => $settings?->enabled ?? false,
            'covered' => $settings?->covers($student) ?? false,
            // null = ikut aturan sekolah; true/false = dioverride per siswa
            'opt_in' => $student->prayer_opt_in,
            'school_includes_all' => $settings?->allReligions ?? false,
            'religion_label' => $student->religion?->label(),
            'window' => $settings ? $settings->displayStart().' – '.$settings->displayEnd() : null,
            'summary' => [
                'hadir' => $hadir,
                'tidak_hadir' => $tidakHadir,
                'effective_days' => $effectiveDays,
                'rate' => $effectiveDays > 0 ? round($hadir / $effectiveDays * 100, 1) : 0.0,
            ],
            'daily' => $daily,
            'recent' => $rows->take(30)->map(fn (PrayerAttendance $row) => [
                'id' => $row->id,
                'date' => $row->prayer_date->format('d M Y'),
                'status' => $row->status->value,
                'status_label' => $row->status->label(),
                'time' => $row->recorded_at?->format('H:i'),
                'device_id' => $row->device_id,
            ])->all(),
        ];
    }

    /**
     * Hari efektif = hari yang punya catatan absensi di sekolah siswa ini.
     * Sama seperti LaporanController, ini pengganti kalender hari libur.
     */
    private function effectiveDays(Student $student, string $start, string $end): int
    {
        return Attendance::where('school_id', $student->school_id)
            ->whereBetween('attendance_date', [$start, $end])
            ->distinct('attendance_date')
            ->count('attendance_date');
    }

    /**
     * Isi hari kosong dengan nol supaya garis chart tidak melompat.
     *
     * @param  Collection<int, Attendance>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function fillDailySeries(string $start, string $end, $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $key = Carbon::parse($row->attendance_date)->toDateString();
            $indexed[$key][$row->status->value] = true;
        }

        $result = [];
        foreach (CarbonPeriod::create($start, $end) as $date) {
            if ($date->isWeekend()) {
                continue;
            }

            $statuses = $indexed[$date->toDateString()] ?? [];

            $result[] = [
                'date' => $date->format('d M'),
                'hadir' => isset($statuses[AttendanceStatus::Hadir->value]) ? 1 : 0,
                'terlambat' => isset($statuses[AttendanceStatus::Terlambat->value]) ? 1 : 0,
                'tidak_hadir' => $statuses === [] ? 1 : 0,
            ];
        }

        return $result;
    }

    /**
     * Dipakai controller export untuk judul file dan header dokumen.
     */
    public function label(Student $student): string
    {
        return trim(($student->nis ?? $student->id).' - '.$student->full_name);
    }
}
