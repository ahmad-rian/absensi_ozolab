<?php

namespace App\Services\Penyelenggara;

use App\Enums\UserRole;
use App\Models\CardFormSubmission;
use App\Models\Grup;
use App\Models\Penyelenggara;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aturan bisnis peserta penyelenggara: kuota, grup + riwayat, cakupan pendamping.
 */
class PesertaService
{
    public function jumlahAktif(Penyelenggara $penyelenggara): int
    {
        // Program yang sudah diarsip selesai berjalan; pesertanya tidak lagi memakan kuota.
        return CardFormSubmission::acrossPenyelenggara()
            ->where('penyelenggara_id', $penyelenggara->id)
            ->whereNull('archived_at')
            ->whereNotIn('program_id', Program::acrossPenyelenggara()
                ->where('penyelenggara_id', $penyelenggara->id)
                ->whereNotNull('archived_at')
                ->select('id'))
            ->count();
    }

    /**
     * Tolak penambahan bila melampaui kuota (FR-013).
     *
     * @throws ValidationException
     */
    public function pastikanKuota(Penyelenggara $penyelenggara, int $tambahan): void
    {
        if ($penyelenggara->participant_quota === null) {
            return;
        }

        $sisa = max(0, $penyelenggara->participant_quota - $this->jumlahAktif($penyelenggara));

        if ($tambahan > $sisa) {
            throw ValidationException::withMessages([
                'kuota' => "Kuota peserta tidak cukup: sisa {$sisa}, yang akan ditambahkan {$tambahan}. Hubungi Tyas untuk menambah kuota.",
            ]);
        }
    }

    /**
     * Samakan keanggotaan grup peserta dan catat riwayatnya (FR-009).
     *
     * @param  array<int, string>  $grupIds
     */
    public function sinkronGrup(CardFormSubmission $peserta, array $grupIds, ?User $oleh): void
    {
        $valid = Grup::acrossPenyelenggara()
            ->where('program_id', $peserta->program_id)
            ->whereIn('id', $grupIds)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($peserta, $valid, $oleh) {
            $lama = $peserta->grups()->pluck('grups.id')->all();
            $keluar = array_values(array_diff($lama, $valid));
            $masuk = array_values(array_diff($valid, $lama));

            if (! $keluar && ! $masuk) {
                return;
            }

            $peserta->grups()->sync($valid);

            $catat = fn (?string $dari, ?string $ke, string $aksi) => DB::table('grup_peserta_riwayats')->insert([
                'id' => (string) str()->ulid(),
                'penyelenggara_id' => $peserta->penyelenggara_id,
                'card_form_submission_id' => $peserta->id,
                'dari_grup_id' => $dari,
                'ke_grup_id' => $ke,
                'aksi' => $aksi,
                'oleh' => $oleh?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (count($keluar) === 1 && count($masuk) === 1) {
                $catat($keluar[0], $masuk[0], 'pindah');

                return;
            }

            foreach ($keluar as $id) {
                $catat($id, null, 'keluar');
            }
            foreach ($masuk as $id) {
                $catat(null, $id, 'masuk');
            }
        });
    }

    /**
     * Grup yang boleh dilihat user di program ini. `null` = semua (admin/super admin).
     *
     * @return array<int, string>|null
     */
    public function grupTerlihat(User $user, Program $program): ?array
    {
        if ($user->isSuperAdmin() || ! $user->hasRole(UserRole::Pendamping->value)) {
            return null;
        }

        $tugas = Grup::where('program_id', $program->id)
            ->whereHas('pendamping', fn (Builder $q) => $q->where('users.id', $user->id))
            ->pluck('id')
            ->all();

        return $tugas ? Grup::denganTurunan($program->id, $tugas) : [];
    }

    /**
     * Batasi query peserta ke cakupan user (pendamping: grupnya + turunan).
     *
     * @param  Builder<CardFormSubmission>  $query
     * @return Builder<CardFormSubmission>
     */
    public function batasi(Builder $query, User $user, Program $program): Builder
    {
        $grupIds = $this->grupTerlihat($user, $program);

        if ($grupIds === null) {
            return $query;
        }

        return $query->whereHas('grups', fn (Builder $q) => $q->whereIn('grups.id', $grupIds ?: ['-']));
    }

    public function bolehLihat(User $user, CardFormSubmission $peserta): bool
    {
        $program = Program::findOrFail($peserta->program_id);

        return $this->batasi(CardFormSubmission::whereKey($peserta->id), $user, $program)->exists();
    }
}
