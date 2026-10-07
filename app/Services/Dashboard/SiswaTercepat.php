<?php

namespace App\Services\Dashboard;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\PrayerType;
use App\Enums\SchoolFeature;
use App\Models\School;
use App\Models\Student;
use App\Support\SchoolFeatures;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Peringkat siswa yang rata-rata datang paling awal: absen pagi, sholat
 * Dhuha, dan sholat Dzuhur, untuk 1, 2, dan 3 bulan terakhir.
 *
 * Jam diambil dari `recorded_at` apa adanya — kolom itu sudah berisi jam
 * dinding Jakarta, jadi tidak dikonversi zona lagi. Rata-rata dihitung di PHP,
 * bukan SQL: ekstraksi jam berbeda antara MySQL (produksi) dan SQLite (tes).
 *
 * Siswa harus hadir minimal separuh hari aktif periodenya (dan minimal
 * {@see self::MIN_HARI} hari). Tanpa syarat ini, siswa yang hanya sekali
 * datang pukul 05.50 akan menduduki peringkat pertama.
 */
class SiswaTercepat
{
    public const PERIODE_BULAN = [1, 2, 3];

    public const JUMLAH = 10;

    public const MIN_HARI = 3;

    /** Bagian minimal dari hari aktif periode yang harus dihadiri. */
    public const MIN_PORSI_HADIR = 0.5;

    private const CACHE_DETIK = 600;

    /**
     * @return list<array{kunci: string, label: string, periode: array<int, list<array<string, mixed>>>}>
     */
    public function untuk(School $school): array
    {
        $hari = SchoolTime::todayString();

        return Cache::remember(
            "dashboard:siswa-tercepat:{$school->id}:{$hari}",
            self::CACHE_DETIK,
            fn (): array => $this->hitung($school, SchoolTime::today()),
        );
    }

    /**
     * @return list<array{kunci: string, label: string, periode: array<int, list<array<string, mixed>>>}>
     */
    public function hitung(School $school, Carbon $hariIni): array
    {
        $mulai = $hariIni->copy()->subMonthsNoOverflow(max(self::PERIODE_BULAN))->addDay();
        $fitur = SchoolFeatures::for($school);

        $kategori = [['pagi', 'Absen pagi', $this->catatanPagi($school->id, $mulai, $hariIni)]];

        foreach ([[SchoolFeature::SholatDhuha, PrayerType::Dhuha], [SchoolFeature::SholatDzuhur, PrayerType::Dzuhur]] as [$saklar, $sholat]) {
            if ($fitur->enabled($saklar)) {
                $kategori[] = [$sholat->slug(), 'Sholat '.$sholat->label(), $this->catatanSholat($school->id, $sholat, $mulai, $hariIni)];
            }
        }

        $semuaId = collect($kategori)->flatMap(fn (array $k) => $k[2]->pluck('student_id'))->unique()->values();
        $siswa = Student::query()
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->whereIn('id', $semuaId)
            ->with('classroom:id,name')
            ->get(['id', 'full_name', 'classroom_id'])
            ->keyBy('id');

        return collect($kategori)->map(fn (array $k): array => [
            'kunci' => $k[0],
            'label' => $k[1],
            'periode' => collect(self::PERIODE_BULAN)->mapWithKeys(fn (int $bulan): array => [
                $bulan => $this->peringkat($k[2], $siswa, $hariIni->copy()->subMonthsNoOverflow($bulan)->addDay()->toDateString()),
            ])->all(),
        ])->all();
    }

    /**
     * @return Collection<int, object{student_id: string, tanggal: string, menit: int}>
     */
    private function catatanPagi(string $schoolId, Carbon $mulai, Carbon $akhir): Collection
    {
        $baris = DB::table('attendances')
            ->where('school_id', $schoolId)
            ->where('type', AttendanceType::CheckIn->value)
            ->whereIn('status', [AttendanceStatus::Hadir->value, AttendanceStatus::Terlambat->value])
            ->whereDate('attendance_date', '>=', $mulai->toDateString())
            ->whereDate('attendance_date', '<=', $akhir->toDateString())
            ->whereNotNull('recorded_at')
            ->get(['student_id', 'attendance_date as tanggal', 'recorded_at']);

        return $this->normalkan($baris);
    }

    /**
     * @return Collection<int, object{student_id: string, tanggal: string, menit: int}>
     */
    private function catatanSholat(string $schoolId, PrayerType $sholat, Carbon $mulai, Carbon $akhir): Collection
    {
        $baris = DB::table('prayer_attendances')
            ->where('school_id', $schoolId)
            ->where('prayer_type', $sholat->value)
            ->where('status', AttendanceStatus::Hadir->value)
            ->whereDate('prayer_date', '>=', $mulai->toDateString())
            ->whereDate('prayer_date', '<=', $akhir->toDateString())
            ->whereNotNull('recorded_at')
            ->get(['student_id', 'prayer_date as tanggal', 'recorded_at']);

        return $this->normalkan($baris);
    }

    /**
     * Tanggal dinormalkan di PHP: kolom `date` di SQLite ikut menyimpan jam.
     *
     * @param  Collection<int, object>  $baris
     * @return Collection<int, object{student_id: string, tanggal: string, menit: int}>
     */
    private function normalkan(Collection $baris): Collection
    {
        return $baris->map(fn (object $b): object => (object) [
            'student_id' => (string) $b->student_id,
            'tanggal' => substr((string) $b->tanggal, 0, 10),
            'menit' => ((int) substr((string) $b->recorded_at, 11, 2)) * 60 + (int) substr((string) $b->recorded_at, 14, 2),
        ]);
    }

    /**
     * @param  Collection<int, object{student_id: string, tanggal: string, menit: int}>  $catatan
     * @param  Collection<string, Student>  $siswa
     * @return list<array{peringkat: int, id: string, nama: string, kelas: ?string, rataRata: string, hari: int, inisial: string}>
     */
    private function peringkat(Collection $catatan, Collection $siswa, string $sejak): array
    {
        $periode = $catatan->filter(fn (object $c): bool => $c->tanggal >= $sejak);
        $hariAktif = $periode->pluck('tanggal')->unique()->count();
        $minHari = max(self::MIN_HARI, (int) ceil($hariAktif * self::MIN_PORSI_HADIR));

        return $periode
            ->groupBy('student_id')
            ->filter(fn (Collection $c, string $id): bool => $siswa->has($id) && $c->count() >= $minHari)
            ->map(fn (Collection $c, string $id): array => [
                'id' => $id,
                'menit' => $c->avg('menit'),
                'hari' => $c->count(),
                'nama' => $siswa[$id]->full_name,
            ])
            ->sort(fn (array $a, array $b): int => [$a['menit'], -$a['hari'], $a['nama']] <=> [$b['menit'], -$b['hari'], $b['nama']])
            ->take(self::JUMLAH)
            ->values()
            ->map(fn (array $r, int $i): array => [
                'peringkat' => $i + 1,
                'id' => $r['id'],
                'nama' => $r['nama'],
                'kelas' => $siswa[$r['id']]->classroom?->name,
                'rataRata' => sprintf('%02d.%02d', intdiv((int) round($r['menit']), 60), (int) round($r['menit']) % 60),
                'hari' => $r['hari'],
                'inisial' => $this->inisial($r['nama']),
            ])
            ->all();
    }

    private function inisial(string $nama): string
    {
        return collect(preg_split('/\s+/', trim($nama)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $kata): string => mb_strtoupper(mb_substr($kata, 0, 1)))
            ->implode('');
    }
}
