<?php

namespace App\Services\Notification;

use App\Enums\SchoolChannelType;
use App\Mail\AttendanceNotificationMail;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DefaultEmailGateway implements EmailGateway
{
    /**
     * Kirim notifikasi kehadiran via email HTML profesional. Kredensial SMTP
     * dan email pengirim diatur per sekolah (1 sekolah 1 email pengirim) dari
     * halaman gateway notifikasi; fallback ke mailer global aplikasi.
     *
     * @param  array<string, string>  $variables
     */
    public function sendTemplate(string $email, string $templateKey, array $variables, ?string $schoolId = null): bool
    {
        if (empty($email)) {
            return false;
        }

        [$mailerName, $senderEmail, $senderName] = $this->resolveMailer($schoolId);

        try {
            $this->mailer($mailerName)->to($email)->send(new AttendanceNotificationMail($variables, $senderEmail, $senderName));

            Log::channel('whatsapp')->info('Email: sent.', ['email' => $email, 'school_id' => $schoolId]);

            return true;
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Email exception.', ['email' => $email, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function sendText(string $email, string $message, ?string $schoolId = null): bool
    {
        if (empty($email)) {
            return false;
        }

        [$mailerName, $senderEmail, $senderName] = $this->resolveMailer($schoolId);

        try {
            $this->mailer($mailerName)->raw($message, function ($mail) use ($email, $senderEmail, $senderName) {
                $mail->to($email)->subject('Notifikasi Absensi');

                if ($senderEmail) {
                    $mail->from($senderEmail, $senderName);
                }
            });

            Log::channel('whatsapp')->info('Email: test sent.', ['email' => $email, 'school_id' => $schoolId]);

            return true;
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Email exception.', ['email' => $email, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Pilih mailer: bila sekolah punya kredensial SMTP sendiri, daftarkan
     * transport runtime; jika tidak, pakai mailer default (null).
     */
    private function mailer(?string $mailerName): Mailer
    {
        return $mailerName ? Mail::mailer($mailerName) : Mail::mailer();
    }

    /**
     * Resolusi konfigurasi email per sekolah dari settings channel.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string} [mailerName, senderEmail, senderName]
     */
    private function resolveMailer(?string $schoolId): array
    {
        if (! $schoolId) {
            return [null, null, null];
        }

        $channel = SchoolNotificationChannel::where('school_id', $schoolId)
            ->where('channel', SchoolChannelType::Email->value)
            ->where('is_active', true)
            ->first();

        if (! $channel) {
            return [null, null, null];
        }

        $senderEmail = $channel->setting('sender_email') ?: null;
        $senderName = $channel->setting('sender_name') ?: School::find($schoolId)?->name;

        $host = $channel->setting('smtp_host');

        if (empty($host)) {
            return [null, $senderEmail, $senderName];
        }

        $mailerName = 'school_smtp_'.$schoolId;

        config(['mail.mailers.'.$mailerName => [
            'transport' => 'smtp',
            'host' => $host,
            'port' => (int) ($channel->setting('smtp_port') ?: 587),
            'username' => $channel->setting('smtp_username'),
            'password' => $channel->setting('smtp_password'),
            'encryption' => $channel->setting('smtp_encryption') ?: 'tls',
            'timeout' => null,
        ]]);

        return [$mailerName, $senderEmail, $senderName];
    }
}
