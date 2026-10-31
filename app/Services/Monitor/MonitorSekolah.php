<?php

namespace App\Services\Monitor;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\School;
use App\Models\Student;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Pantauan lintas sekolah untuk super admin: sekolah mana yang hari itu tidak
 * ada absen sama sekali. Semua sekolah aktif otomatis ikut peringkat.
 *
 * Seluruh angka diambil dengan beberapa query agregat (group by school_id),
 * bukan satu query per sekolah.
 */
class MonitorSekolah
{
    /** Status yang menandakan siswa benar-benar datang. */
    private const STATUS_DATANG = [AttendanceStatus::Hadir->value, AttendanceStatus::Terlambat->value];

    /** Urutan keadaan pada peringkat; yang paling butuh perhatian di atas. */
    private const URUTAN = ['tanpa_absen' => 0, 'belum_mulai' => 1, 'berjalan' => 2, 'libur' => 3];

    /**
     * @return array{
     *     tanggal: string,
     *     sekolah: list<array<string, mixed>>,
     *     ringkasan: array{total: int, tanpa_absen: int, berjalan: int, libur: int}
     * }
     */
    public function untuk(Carbon $tanggal): array
    {
        $tgl = $tanggal->toDateString();
        $sekolah = School::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $ids = $sekolah->pluck('id');

        $siswa = Student::withoutGlobalScopes()
            ->whereIn('school_id', $ids)
            ->where('is_active', true)
            ->selectRaw('school_id, count(*) as jumlah')
            ->groupBy('school_id')
            ->pluck('jumlah', 'school_id');

        $masuk = Attendance::withoutGlobalScopes()
            ->whereIn('school_id', $ids)
            ->whereDate('attendance_date', $tgl)
            ->where('type', AttendanceType::CheckIn->value)
            ->selectRaw('school_id, status, count(*) as jumlah, max(recorded_at) as terakhir')
            ->groupBy('school_id', 'status')
            ->get()
            ->groupBy('school_id');

        // Absen terakhir sampai tanggal dinilai, supaya terlihat sudah berapa
        // hari sekolah itu tidak absen.
        $terakhir = Attendance::withoutGlobalScopes()
            ->whereIn('school_id', $ids)
            ->where('type', AttendanceType::CheckIn->value)
            ->whereIn('status', self::STATUS_DATANG)
            ->whereDate('attendance_date', '<=', $tgl)
            ->selectRaw('school_id, max(attendance_date) as tanggal')
            ->groupBy('school_id')
            ->pluck('tanggal', 'school_id');

        $jadwal = AttendanceSchedule::withoutGlobalScopes()
            ->whereIn('school_id', $ids)
            ->where('day_of_week', $tanggal->dayOfWeekIso)
            ->where('is_active', true)
            ->get(['school_id', 'check_in_start'])
            ->groupBy('school_id');

        $baris = $sekolah->map(fn (School $s): array => $this->baris(
            $s,
            $tanggal,
            (int) ($siswa[$s->id] ?? 0),
            $masuk->get($s->id, collect()),
            $terakhir[$s->id] ?? null,
            $jadwal->get($s->id, collect()),
        ));

        $peringkat = $baris
            ->sortBy([
                fn (array $a, array $b) => self::URUTAN[$a['keadaan']] <=> self::URUTAN[$b['keadaan']],
                fn (array $a, array $b) => $a['persen'] <=> $b['persen'],
                fn (array $a, array $b) => strcasecmp($a['nama'], $b['nama']),
            ])
            ->values()
            ->map(fn (array $b, int $i) => [...$b, 'peringkat' => $i + 1]);

        return [
            'tanggal' => $tgl,
            'sekolah' => $peringkat->all(),
            'ringkasan' => [
                'total' => $peringkat->count(),
                'tanpa_absen' => $peringkat->where('keadaan', 'tanpa_absen')->count(),
                'berjalan' => $peringkat->where('keadaan', 'berjalan')->count(),
                'libur' => $peringkat->where('keadaan', 'libur')->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, Attendance>  $masuk  baris agregat per status
     * @param  Collection<int, AttendanceSchedule>  $jadwal
     * @return array<string, mixed>
     */
    private function baris(School $s, Carbon $tanggal, int $siswa, Collection $masuk, ?string $terakhir, Collection $jadwal): array
    {
        $hitung = fn (array $status): int => (int) $masuk->filter(fn ($r) => in_array($this->nilaiStatus($r->status), $status, true))->sum('jumlah');
        $datang = $hitung(self::STATUS_DATANG);
        $scanTerakhir = $masuk->max('terakhir');

        return [
            'id' => $s->id,
            'nama' => $s->name,
            'siswa' => $siswa,
            'hadir' => $datang,
            'terlambat' => $hitung([AttendanceStatus::Terlambat->value]),
            'izin_sakit' => $hitung([AttendanceStatus::Izin->value, AttendanceStatus::Sakit->value]),
            'persen' => $siswa > 0 ? round($datang / $siswa * 100, 1) : 0.0,
            'scan_terakhir' => $scanTerakhir ? Carbon::parse($scanTerakhir)->format('H:i') : null,
            'absen_terakhir' => $terakhir ? Carbon::parse($terakhir)->toDateString() : null,
            'keadaan' => $this->keadaan($tanggal, (int) $masuk->sum('jumlah'), $jadwal),
        ];
    }

    private function nilaiStatus(mixed $status): string
    {
        return $status instanceof AttendanceStatus ? $status->value : (string) $status;
    }

    /**
     * @param  Collection<int, AttendanceSchedule>  $jadwal
     */
    private function keadaan(Carbon $tanggal, int $tercatat, Collection $jadwal): string
    {
        if ($tercatat > 0) {
            return 'berjalan';
        }

        if ($jadwal->isEmpty()) {
            return 'libur';
        }

        // Hari ini sebelum jam masuk paling awal: belum waktunya dinilai.
        if ($tanggal->isSameDay(SchoolTime::today())) {
            $mulai = $jadwal->map(fn (AttendanceSchedule $j) => $j->momentOn('check_in_start', $tanggal))->min();

            if (SchoolTime::now()->lessThan($mulai)) {
                return 'belum_mulai';
            }
        }

        return 'tanpa_absen';
    }
}
