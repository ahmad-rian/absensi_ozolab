<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Peringatan "N hari berturut-turut tidak sholat" untuk orang tua.
 *
 * Bentuknya sengaja sama persis dengan AttendanceNotificationMail (array
 * variabel + pengirim per sekolah) supaya DefaultEmailGateway cukup bercabang
 * pada satu baris.
 */
class PrayerAbsenceNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $variables
     */
    public function __construct(
        public array $variables,
        public ?string $senderEmail = null,
        public ?string $senderName = null,
    ) {}

    public function envelope(): Envelope
    {
        $studentName = $this->variables['nama_siswa'] ?? '';
        $schoolName = $this->variables['nama_sekolah'] ?? 'Sekolah';

        return new Envelope(
            from: $this->senderEmail ? new Address($this->senderEmail, $this->senderName ?: $schoolName) : null,
            subject: 'Pemberitahuan Absen Sholat'.($studentName !== '' ? ' - '.$studentName : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.prayer-absence',
            with: ['vars' => $this->variables],
        );
    }
}
