<?php

namespace App\Services\Student;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\PrayerType;
use App\Models\Attendance;
use App\Models\PrayerAttendance;
use App\Models\Student;
use App\Support\JamDinding;
use App\Support\PrayerSchedule;
use App\Support\PrayerSettings;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/**
 * Riwayat absensi satu siswa di halaman detail, dipaginasi di server.
 *
 * Absensi sekolah dipaginasi PER HARI, bukan per baris: masuk dan pulang
 * adalah dua baris di tabel, tetapi admin membaca "tanggal ini datang jam
 * berapa, pulang jam berapa". Satu query mengambil tanggal halaman ini, satu
 * query lagi mengambil seluruh barisnya; jumlah query tetap berapa pun isinya.
 *
 * Jam dibaca apa adanya karena `recorded_at` sudah jam dinding sekolah
 * (lihat {@see JamDinding}).
 */
class RiwayatAbsensi
{
    public const PER_HALAMAN = 15;

    public const PARAM_SEKOLAH = 'riwayat_page';

    public const PARAM_SHOLAT = 'riwayat_sholat_page';

    private const HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    /** Status yang berarti siswa benar-benar tiba; selainnya jam catat bukan jam datang. */
    private const STATUS_DATANG = [AttendanceStatus::Hadir, AttendanceStatus::Terlambat];

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function sekolah(Student $student, string $start, string $end, int $halaman): LengthAwarePaginator
    {
        $tanggal = $this->absensiSiswa($student, $start, $end)
            // groupBy, bukan distinct(): hitungan total paginator untuk query
            // ber-GROUP BY dibungkus subquery, sehingga total = jumlah hari.
            // select() eksplisit wajib: tanpa itu subquery hitungannya menjadi
            // `select *` dan MySQL (ONLY_FULL_GROUP_BY) menolaknya. SQLite di
            // tes tidak menegakkan aturan itu.
            ->select('attendance_date')
            ->groupBy('attendance_date')
            ->orderByDesc('attendance_date')
            ->paginate(self::PER_HALAMAN, ['attendance_date'], self::PARAM_SEKOLAH, $halaman);

        $hariIni = $tanggal->getCollection()
            ->map(fn (Attendance $row) => $row->attendance_date->toDateString())
            ->unique()
            ->values();

        if ($hariIni->isEmpty()) {
            return $tanggal->setCollection(collect());
        }

        $baris = $this->absensiSiswa($student, $hariIni->last(), $hariIni->first())
            ->get(['id', 'attendance_date', 'type', 'status', 'recorded_at', 'recorded_by', 'device_id', 'notes'])
            ->groupBy(fn (Attendance $row) => $row->attendance_date->toDateString());

        return $tanggal->setCollection(
            $hariIni->map(fn (string $hari) => $this->barisHari($hari, $baris->get($hari, collect()))),
        );
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function sholat(Student $student, string $start, string $end, PrayerType $jenis, int $halaman): LengthAwarePaginator
    {
        // Jenis yang dimatikan sekolah tidak punya riwayat untuk dilaporkan,
        // sama seperti StudentStatsBuilder::prayerFor().
        if (! $this->sholatAktif($student, $jenis)) {
            return new Paginator([], 0, self::PER_HALAMAN, $halaman, ['pageName' => self::PARAM_SHOLAT]);
        }

        return PrayerAttendance::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $student->school_id)
            ->where('student_id', $student->id)
            ->where('prayer_type', $jenis->value)
            ->whereDate('prayer_date', '>=', $start)
            ->whereDate('prayer_date', '<=', $end)
            ->orderByDesc('prayer_date')
            ->orderByDesc('recorded_at')
            ->paginate(self::PER_HALAMAN, ['id', 'prayer_date', 'status', 'recorded_at', 'recorded_by', 'device_id', 'notes'], self::PARAM_SHOLAT, $halaman)
            ->through(fn (PrayerAttendance $row) => [
                'id' => $row->id,
                'tanggal' => $row->prayer_date->toDateString(),
                'hari' => self::HARI[$row->prayer_date->dayOfWeekIso],
                'jam' => $row->recorded_at?->format('H:i'),
                'status' => $row->status->value,
                'status_label' => $row->status->label(),
                'keterangan' => $row->notes ?: null,
                'sumber' => $this->sumber($row->device_id, $row->recorded_by),
            ]);
    }

    /**
     * Global scope `school` mati di konteks console, jadi school_id difilter
     * eksplisit, sama seperti StudentStatsBuilder. Index unik
     * (student_id, attendance_date, type) menopang filter ini.
     *
     * @return Builder<Attendance>
     */
    private function absensiSiswa(Student $student, string $start, string $end)
    {
        return Attendance::query()
            ->withoutGlobalScope('school')
            ->where('school_id', $student->school_id)
            ->where('student_id', $student->id)
            // whereDate: kolom `date` di SQLite ikut menyimpan jam.
            ->whereDate('attendance_date', '>=', $start)
            ->whereDate('attendance_date', '<=', $end);
    }

    /**
     * @param  Collection<int, Attendance>  $baris
     * @return array<string, mixed>
     */
    private function barisHari(string $tanggal, Collection $baris): array
    {
        $masuk = $baris->first(fn (Attendance $row) => $row->type === AttendanceType::CheckIn);
        $pulang = $baris->first(fn (Attendance $row) => $row->type === AttendanceType::CheckOut);
        $datang = $masuk && in_array($masuk->status, self::STATUS_DATANG, true);

        return [
            'id' => $masuk?->id ?? $pulang?->id,
            'tanggal' => $tanggal,
            'hari' => self::HARI[Carbon::parse($tanggal)->dayOfWeekIso],
            'status' => $masuk?->status->value,
            'status_label' => $masuk?->status->label() ?? 'Tanpa catatan masuk',
            'jam_masuk' => $datang ? $masuk->recorded_at?->format('H:i') : null,
            'jam_pulang' => $pulang?->recorded_at?->format('H:i'),
            'pulang_otomatis' => $pulang?->device_id === 'AUTO',
            'keterangan' => collect([$masuk?->notes, $pulang?->notes])->filter()->implode(' · ') ?: null,
            'sumber' => $this->sumber($masuk?->device_id ?? $pulang?->device_id, $masuk?->recorded_by ?? $pulang?->recorded_by),
        ];
    }

    private function sholatAktif(Student $student, PrayerType $jenis): bool
    {
        if (! $student->school) {
            return false;
        }

        return collect(PrayerSchedule::for($student->school)->enabled())
            ->contains(fn (PrayerSettings $settings) => $settings->type === $jenis);
    }

    /**
     * Asal catatan dalam bahasa operator. Id perangkat yang tidak dikenal
     * ditampilkan apa adanya supaya tetap bisa ditelusuri.
     */
    private function sumber(?string $deviceId, ?string $recordedBy): string
    {
        return match (true) {
            $deviceId === 'PUBLIC-SCAN' => 'Scan gerbang',
            $deviceId === 'AUTO' => 'Otomatis sistem',
            in_array($deviceId, [PrayerType::Dhuha->deviceId(), PrayerType::Dzuhur->deviceId()], true) => 'Scan sholat',
            $deviceId === null && $recordedBy !== null => 'Input manual',
            $deviceId === null => '-',
            default => $deviceId,
        };
    }
}
