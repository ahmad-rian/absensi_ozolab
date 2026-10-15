<?php

namespace App\Console\Commands;

use App\Enums\SchoolFeature;
use App\Models\School;
use App\Services\Poin\SapuanPoin;
use App\Support\SchoolFeatures;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Catat poin otomatis terlambat / tidak hadir untuk sekolah yang menyalakan
 * fitur Poin Pelanggaran.
 *
 * Dijadwalkan tiap jam. Tiap putaran mencocokkan ulang hari ini plus beberapa
 * hari ke belakang, jadi koreksi absensi yang telat (izin disetujui besoknya)
 * tetap menghapus poinnya.
 */
class SapuPoinSiswaCommand extends Command
{
    /** Jumlah hari ke belakang yang ikut dicocokkan ulang. */
    public const HARI_KOREKSI = 7;

    protected $signature = 'poin:sapu
                            {--date= : Tanggal acuan (Y-m-d), default hari ini}
                            {--school= : Batasi ke satu school_id}
                            {--dry-run : Tampilkan saja, jangan menulis}';

    protected $description = 'Catat dan cocokkan poin pelanggaran otomatis (terlambat / tidak hadir)';

    public function handle(SapuanPoin $sapuan): int
    {
        $date = $this->option('date')
            ? Carbon::createFromFormat('Y-m-d', $this->option('date'), SchoolTime::timezone())->startOfDay()
            : SchoolTime::today();

        $dryRun = (bool) $this->option('dry-run');
        $prefix = $dryRun ? '[dry-run] ' : '';

        $schools = School::query()
            ->when($this->option('school'), fn ($q, $id) => $q->where('id', $id))
            ->where('is_active', true)
            ->get()
            ->filter(fn (School $school) => SchoolFeatures::for($school)->enabled(SchoolFeature::PoinPelanggaran));

        foreach ($schools as $school) {
            $awal = $sapuan->awalSapuan($school, $dryRun);
            $tambah = 0;
            $hapus = 0;

            for ($mundur = self::HARI_KOREKSI - 1; $mundur >= 0; $mundur--) {
                $hari = $date->copy()->subDays($mundur);

                if ($hari->toDateString() < $awal) {
                    continue;
                }

                $hasil = $sapuan->sapu($school, $hari, $dryRun);
                $tambah += $hasil['tambah'];
                $hapus += $hasil['hapus'];
            }

            $this->line("{$prefix}{$school->name}: +{$tambah} poin, -{$hapus} dihapus.");
        }

        $this->info("{$prefix}Sapuan poin {$date->toDateString()} selesai untuk {$schools->count()} sekolah.");

        return self::SUCCESS;
    }
}
