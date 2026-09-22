<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use App\Support\AlamatLoginOrangTua;
use Illuminate\Console\Command;

/**
 * Gunakan email notifikasi yang tersedia untuk login; generate slug jika kosong.
 */
class SlugEmailOrangTua extends Command
{
    protected $signature = 'ortu:email-slug {--sekolah=} {--dry-run}';

    protected $description = 'Sinkronkan email login orang tua dengan email asli, atau gunakan slug nama';

    public function handle(): int
    {
        $query = User::role(UserRole::OrangTua)
            ->where(function ($query): void {
                $query->where('email', 'like', '%'.AlamatLoginOrangTua::DOMAIN_LAMA)
                    ->orWhere('email', 'like', '%@'.AlamatLoginOrangTua::DOMAIN);
            })
            ->whereDoesntHave('roles', fn ($roles) => $roles->whereIn('name', [UserRole::Admin->value, UserRole::Guru->value, UserRole::SuperAdmin->value]))
            ->when($this->option('sekolah'), fn ($query, $sekolah) => $query->where('school_id', $sekolah));

        $jumlah = (clone $query)->count();
        $simulasi = (bool) $this->option('dry-run');

        $this->info($jumlah.' akun akan diperiksa'.($simulasi ? ' (simulasi)' : ''));

        if ($jumlah === 0) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($jumlah);
        $contoh = [];
        $dipesan = [];
        $namaUmum = 0;
        $diubah = 0;
        $dilewati = 0;

        $query->with('parentProfile')->chunkById(200, function ($users) use ($simulasi, $bar, &$contoh, &$dipesan, &$namaUmum, &$diubah, &$dilewati): void {
            foreach ($users as $user) {
                $anak = $user->parentProfile
                    ? Student::withoutGlobalScope('school')->where('parent_profile_id', $user->parentProfile->id)->first()
                    : null;

                $emailAsli = trim((string) $user->parentProfile?->email);
                $baru = $user->email;
                if ($emailAsli !== '' && ! AlamatLoginOrangTua::bawaanSistem($emailAsli)) {
                    if (! filter_var($emailAsli, FILTER_VALIDATE_EMAIL)
                        || preg_match('/[\r\n]/', $emailAsli)
                        || isset($dipesan[strtolower($emailAsli)])
                        || User::withTrashed()->whereRaw('LOWER(email) = ?', [strtolower($emailAsli)])->whereKeyNot($user->id)->exists()) {
                        $dilewati++;
                        $this->newLine();
                        $this->warn('Akun '.$user->id.' dilewati: email tidak valid atau sudah digunakan.');
                        $bar->advance();

                        continue;
                    }
                    $baru = $emailAsli;
                } elseif (str_ends_with($user->email, AlamatLoginOrangTua::DOMAIN_LAMA)) {
                    $baru = AlamatLoginOrangTua::untuk($user->name, $anak, $user->id, $dipesan);
                }

                if ($baru === $user->email) {
                    $bar->advance();

                    continue;
                }
                $diubah++;
                $dipesan[strtolower($baru)] = true;
                if (preg_match('/^wali[0-9]*@tyas\.app$/', $baru)) {
                    $namaUmum++;
                }

                if (count($contoh) < 5) {
                    $contoh[] = [$user->email, $baru];
                }

                if (! $simulasi) {
                    $user->forceFill(['email' => $baru, 'email_verified_at' => null])->save();
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->table(['Sebelum', 'Sesudah'], $contoh);
        $this->info($diubah.' akun '.($simulasi ? 'akan diperbarui' : 'diperbarui').'; '.$dilewati.' akun perlu diperiksa.');
        $this->info($namaUmum.' akun memakai nama umum wali; periksa nama orang tua dan anak sebelum membagikan akun.');

        return self::SUCCESS;
    }
}
