<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use App\Support\AlamatLoginOrangTua;
use Illuminate\Console\Command;

/**
 * Mengganti alamat login bawaan sistem dengan slug nama.
 *
 * Hanya menyentuh alamat `@internal.app` — email sungguhan yang sudah diisi
 * admin tidak pernah diubah, karena itu alamat yang orang tuanya benar-benar
 * pakai dan mungkin sudah dibagikan.
 */
class SlugEmailOrangTua extends Command
{
    protected $signature = 'ortu:email-slug {--sekolah=} {--dry-run}';

    protected $description = 'Ubah email login orang tua dari bentuk acak menjadi slug nama';

    public function handle(): int
    {
        $query = User::role(UserRole::OrangTua)
            ->where('email', 'like', '%'.AlamatLoginOrangTua::DOMAIN_LAMA)
            ->when($this->option('sekolah'), fn ($query, $sekolah) => $query->where('school_id', $sekolah));

        $jumlah = (clone $query)->count();
        $simulasi = (bool) $this->option('dry-run');

        $this->info($jumlah.' akun akan diubah'.($simulasi ? ' (simulasi)' : ''));

        if ($jumlah === 0) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($jumlah);
        $contoh = [];
        $dipesan = [];
        $namaUmum = 0;

        $query->with('parentProfile')->chunkById(200, function ($users) use ($simulasi, $bar, &$contoh, &$dipesan, &$namaUmum): void {
            foreach ($users as $user) {
                $anak = $user->parentProfile
                    ? Student::withoutGlobalScope('school')->where('parent_profile_id', $user->parentProfile->id)->first()
                    : null;

                $baru = AlamatLoginOrangTua::untuk($user->name, $anak, $user->id, $dipesan);
                $dipesan[$baru] = true;
                if (preg_match('/^wali[0-9]*@tyas\.app$/', $baru)) {
                    $namaUmum++;
                }

                if (count($contoh) < 5) {
                    $contoh[] = [$user->email, $baru];
                }

                if (! $simulasi) {
                    $user->forceFill(['email' => $baru])->save();
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->table(['Sebelum', 'Sesudah'], $contoh);
        $this->info($namaUmum.' akun memakai nama umum wali; periksa nama orang tua dan anak sebelum membagikan akun.');

        return self::SUCCESS;
    }
}
