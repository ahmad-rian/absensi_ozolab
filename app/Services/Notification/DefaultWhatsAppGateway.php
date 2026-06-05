<?php

namespace App\Services\Notification;

use App\Models\School;
use App\Models\SchoolWaConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DefaultWhatsAppGateway implements WhatsAppGateway
{
    private const FONNTE_URL = 'https://api.fonnte.com/send';

    public function __construct(
        private readonly int $timeout = 10,
    ) {}

    /**
     * @param  array<string, string>  $variables
     */
    public function sendTemplate(string $to, string $templateKey, array $variables, ?string $schoolId = null): bool
    {
        $message = $this->buildMessage($templateKey, $variables, $schoolId);

        if (! $message) {
            return false;
        }

        return $this->sendViaFonnte($to, $message, $schoolId);
    }

    public function sendText(string $to, string $message, ?string $schoolId = null): bool
    {
        return $this->sendViaFonnte($to, $message, $schoolId);
    }

    /**
     * Send message via Fonnte API using per-school token.
     */
    private function sendViaFonnte(string $to, string $message, ?string $schoolId): bool
    {
        $config = $this->getConfig($schoolId);

        if (! $config) {
            Log::channel('whatsapp')->warning('No WA config for school, skipping.', [
                'school_id' => $schoolId,
                'to' => $to,
            ]);

            return false;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Authorization' => $config->fonnte_token])
                ->post(self::FONNTE_URL, [
                    'target' => $to,
                    'message' => $message,
                    'countryCode' => '62',
                ]);

            $data = $response->json();
            $success = ($data['status'] ?? false) === true;

            Log::channel('whatsapp')->info($success ? 'WA sent via Fonnte.' : 'Fonnte send failed.', [
                'to' => $to,
                'school_id' => $schoolId,
                'response' => $data,
            ]);

            return $success;
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Fonnte send exception.', [
                'to' => $to,
                'school_id' => $schoolId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function getConfig(?string $schoolId): ?SchoolWaConfig
    {
        if (! $schoolId) {
            return null;
        }

        return SchoolWaConfig::where('school_id', $schoolId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Build message from template by substituting variables.
     */
    private function buildMessage(string $templateKey, array $variables, ?string $schoolId): ?string
    {
        $school = $schoolId ? School::find($schoolId) : null;
        $template = $school?->getSetting('whatsapp_template_attendance')
            ?? 'Halo, {nama_siswa} ({kelas}) telah {status} di {nama_sekolah} pada {tanggal} pukul {waktu}. Terima kasih.';

        $message = $template;
        foreach ($variables as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }

        return $message;
    }
}
