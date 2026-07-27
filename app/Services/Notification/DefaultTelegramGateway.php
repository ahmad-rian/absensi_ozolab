<?php

namespace App\Services\Notification;

use App\Enums\SchoolChannelType;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DefaultTelegramGateway implements TelegramGateway
{
    private const DEFAULT_TEMPLATE = "Assalamualaikum Bapak/Ibu,\n\nBerikut informasi kehadiran putra/putri Anda:\n\nNama  : {nama_siswa}\nKelas : {kelas}\nStatus : {status}\nWaktu : {tanggal}, {waktu}\nSekolah : {nama_sekolah}\n\nTerima kasih atas perhatiannya.\n\nPesan otomatis dari sistem absensi {nama_sekolah}";

    private const DEFAULT_PRAYER_ABSENCE_TEMPLATE = "Assalamualaikum Bapak/Ibu,\n\nKami sampaikan bahwa putra/putri Anda belum tercatat mengikuti sholat {jenis_sholat} di sekolah selama {jumlah_hari} hari sekolah berturut-turut.\n\nNama    : {nama_siswa}\nKelas   : {kelas}\nPeriode : {tanggal_mulai} s/d {tanggal_terakhir}\nSekolah : {nama_sekolah}\n\nMohon bantuan Bapak/Ibu untuk mengingatkan ananda. Bila ada alasan tertentu (sakit, izin, atau kendala lain), silakan menghubungi wali kelas.\n\nPesan otomatis dari sistem absensi {nama_sekolah}";

    public function __construct(
        private readonly int $timeout = 10,
    ) {}

    /**
     * @param  array<string, string>  $variables
     */
    public function sendTemplate(string $chatId, string $templateKey, array $variables, ?string $schoolId = null): bool
    {
        return $this->send($chatId, $this->buildMessage($variables, $schoolId, $templateKey), $schoolId);
    }

    public function sendText(string $chatId, string $message, ?string $schoolId = null): bool
    {
        return $this->send($chatId, $message, $schoolId);
    }

    private function send(string $chatId, string $message, ?string $schoolId): bool
    {
        $token = $this->botToken($schoolId);

        if (empty($token)) {
            Log::channel('whatsapp')->warning('Telegram bot not configured, skipping.', [
                'chat_id' => $chatId,
                'school_id' => $schoolId,
            ]);

            return false;
        }

        $baseUrl = rtrim((string) config('telegram.api_base'), '/');

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$baseUrl}/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                ]);

            $data = $response->json();
            $success = ($data['ok'] ?? false) === true;

            Log::channel('whatsapp')->info($success ? 'Telegram: sent.' : 'Telegram: failed.', [
                'chat_id' => $chatId,
                'response' => $data,
            ]);

            return $success;
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Telegram exception.', ['chat_id' => $chatId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function botToken(?string $schoolId): ?string
    {
        if (! $schoolId) {
            return null;
        }

        $channel = SchoolNotificationChannel::where('school_id', $schoolId)
            ->where('channel', SchoolChannelType::Telegram->value)
            ->where('is_active', true)
            ->first();

        return $channel?->setting('bot_token');
    }

    /**
     * Pilih template berdasarkan jenis notifikasi.
     *
     * `default =>` menjaga perilaku lama byte-identik: tanpa cabang ini,
     * peringatan alpa sholat akan terkirim memakai template kehadiran.
     *
     * @param  array<string, string>  $variables
     */
    private function buildMessage(array $variables, ?string $schoolId, string $templateKey = 'attendance_notify'): string
    {
        $school = $schoolId ? School::find($schoolId) : null;

        [$settingKey, $fallback] = match ($templateKey) {
            'prayer_absence_notify' => ['whatsapp_template_prayer_absence', self::DEFAULT_PRAYER_ABSENCE_TEMPLATE],
            default => ['whatsapp_template_attendance', self::DEFAULT_TEMPLATE],
        };

        $template = $this->normalizeTemplate((string) ($school?->getSetting($settingKey) ?: $fallback));

        $message = $template;
        foreach ($variables as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }

        return $message;
    }

    /**
     * Decode a template that was accidentally double JSON-encoded
     * (stored as "\"Halo Bapak\/Ibu...\"") back to plain text.
     */
    private function normalizeTemplate(string $template): string
    {
        $trimmed = trim($template);

        if (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"')) {
            $decoded = json_decode($trimmed);

            if (is_string($decoded)) {
                return $decoded;
            }
        }

        return $template;
    }
}
