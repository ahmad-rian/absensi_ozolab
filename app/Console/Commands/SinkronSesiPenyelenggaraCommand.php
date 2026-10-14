<?php

namespace App\Console\Commands;

use App\Models\Program;
use App\Services\Penyelenggara\SesiGenerator;
use Illuminate\Console\Command;

/**
 * Pastikan sesi mendatang semua program aktif sesuai polanya. Aman diulang;
 * tidak pernah mengubah sesi yang sudah lewat (spec 003).
 */
class SinkronSesiPenyelenggaraCommand extends Command
{
    protected $signature = 'penyelenggara:sesi-sinkron {--program= : Hanya program dengan id ini}';

    protected $description = 'Sinkronkan sesi mendatang program penyelenggara dengan polanya';

    public function handle(SesiGenerator $generator): int
    {
        $programs = Program::acrossPenyelenggara()
            ->whereNull('archived_at')
            ->whereDate('ends_on', '>=', now()->subDay()->toDateString())
            ->when($this->option('program'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        foreach ($programs as $program) {
            $generator->sinkronProgram($program);
        }

        $this->info("{$programs->count()} program disinkronkan.");

        return self::SUCCESS;
    }
}
