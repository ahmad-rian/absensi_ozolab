<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\Attendance\ScheduleProvisioner;
use Illuminate\Console\Command;

class SyncAttendanceSchedulesCommand extends Command
{
    protected $signature = 'attendance:sync-schedules
                            {--school= : Batasi ke satu school_id}
                            {--force : Timpa jam pada jadwal yang sudah ada, termasuk jadwal per kelas}';

    protected $description = 'Pastikan setiap sekolah punya jadwal absensi lengkap dengan jam default';

    public function handle(ScheduleProvisioner $provisioner): int
    {
        $times = $provisioner->defaultTimes();

        $this->line(sprintf(
            'Jam default: masuk %s (telat > %s) — pulang %s s/d %s',
            $times['check_in_start'],
            $times['late_threshold'],
            $times['check_out_start'],
            $times['check_out_end'],
        ));

        $schools = School::query()
            ->when($this->option('school'), fn ($q) => $q->where('id', $this->option('school')))
            ->get();

        if ($schools->isEmpty()) {
            $this->warn('Tidak ada sekolah yang cocok.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');

        foreach ($schools as $school) {
            $result = $provisioner->provision($school->id, $force);

            $this->info(sprintf(
                '%s — %d jadwal dibuat, %d baris jamnya diseragamkan.',
                $school->name,
                $result['created'],
                $result['updated'],
            ));
        }

        if (! $force) {
            $this->comment('Jalankan ulang dengan --force untuk menimpa jam pada jadwal lama.');
        }

        return self::SUCCESS;
    }
}
