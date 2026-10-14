<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Penyelenggara;
use App\Models\PenyelenggaraInvitation;
use App\Models\User;
use App\Notifications\UndanganPenyelenggara;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Satu-satunya jalur penerbitan undangan penyelenggara — dipakai super admin
 * (admin pertama) dan admin penyelenggara (tim), supaya aturan email dan
 * pencatatan log tidak bercabang.
 */
class PenyelenggaraUndangan
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah dipakai akun lain. Satu akun hanya untuk satu sekolah atau satu penyelenggara.',
        ];
    }

    public function kirim(Penyelenggara $penyelenggara, string $email, UserRole $role, ?User $by): PenyelenggaraInvitation
    {
        // Undangan lama yang masih menunggu untuk email yang sama dibatalkan,
        // supaya hanya tautan terbaru yang berlaku.
        PenyelenggaraInvitation::acrossPenyelenggara()
            ->where('penyelenggara_id', $penyelenggara->id)
            ->where('email', strtolower(trim($email)))
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        [$invitation, $token] = PenyelenggaraInvitation::issue($penyelenggara, $email, $role, $by);

        Notification::route('mail', $invitation->email)
            ->notify(new UndanganPenyelenggara($penyelenggara->name, $role->label(), $token));

        activity()
            ->performedOn($penyelenggara)
            ->causedBy($by)
            ->withProperties(['email' => $invitation->email, 'role' => $role->value])
            ->log('undangan dikirim');

        return $invitation;
    }
}
