<?php

namespace App\Notifications;

use App\Models\PenyelenggaraInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email undangan akun penyelenggara. Token mentah hanya hidup di sini.
 */
class UndanganPenyelenggara extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $penyelenggaraName,
        public string $roleLabel,
        public string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Undangan bergabung dengan {$this->penyelenggaraName}")
            ->greeting('Assalamualaikum,')
            ->line("Anda diundang sebagai {$this->roleLabel} di {$this->penyelenggaraName} pada aplikasi absensi Tyas.")
            ->action('Terima undangan', route('undangan.show', $this->token))
            ->line('Tautan ini berlaku '.PenyelenggaraInvitation::BERLAKU_HARI.' hari dan hanya bisa dipakai sekali.')
            ->line('Bila Anda tidak merasa diundang, abaikan email ini.');
    }
}
