<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetelPasswordOrangTua extends Command
{
    protected $signature = 'ortu:setel-password {--sekolah=} {--dry-run} {--force}';

    protected $description = 'Setel password awal orang tua dan wajibkan penggantian password';

    public function handle(): int
    {
        $query = User::role(UserRole::OrangTua)
            ->whereDoesntHave('roles', fn ($roles) => $roles->whereIn('name', [UserRole::Admin->value, UserRole::Guru->value, UserRole::SuperAdmin->value]))
            ->when($this->option('sekolah'), fn ($query, $school) => $query->where('school_id', $school));
        $jumlah = $query->count();
        $this->info($jumlah.' akun orang tua'.($this->option('dry-run') ? ' (simulasi)' : ''));
        if ($jumlah === 0) {
            $this->info('Tidak ada akun yang perlu diproses.');

            return self::SUCCESS;
        }
        if (! $this->option('dry-run')) {
            /*
                Hash lama ditimpa dan tidak bisa dikembalikan. Tanpa `--sekolah`
                sasarannya SETIAP orang tua di SETIAP sekolah sekaligus, dan
                sampai masing-masing login untuk menggantinya, semuanya memakai
                satu kata sandi yang sama.
            */
            if (! $this->option('force') && ! $this->confirm('Sandi lama akun di atas hilang permanen. Lanjutkan?', false)) {
                $this->warn('Dibatalkan, tidak ada yang diubah.');

                return self::FAILURE;
            }

            $selesai = 0;
            $bar = $this->output->createProgressBar($jumlah);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Waktu: %elapsed:6s% | Sisa: %remaining:6s%');
            $bar->start();

            $query->chunkById(100, function ($users) use ($bar, &$selesai): void {
                foreach ($users as $user) {
                    $user->forceFill(['password' => Hash::make('11111111'), 'must_change_password' => true, 'remember_token' => null])->save();
                    $selesai++;
                    $bar->advance();
                }
            });
            $bar->finish();
            $this->newLine(2);
            $this->info($selesai.' akun berhasil diperbarui. Pengguna wajib mengganti kata sandi setelah masuk.');
        }

        return self::SUCCESS;
    }
}
