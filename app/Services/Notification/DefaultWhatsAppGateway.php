<?php

namespace App\Services\Notification;

use App\Models\School;
use App\Models\SchoolWaConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DefaultWhatsAppGateway implements WhatsAppGateway
{
    private const FONNTE_URL = 'https://api.fonnte.com/send';

    private const DEFAULT_TEMPLATE = "Assalamualaikum Bapak/Ibu,\n\nBerikut informasi kehadiran putra/putri Anda:\n\nNama  : {nama_siswa}\nKelas : {kelas}\nStatus : {status}\nWaktu : {tanggal}, {waktu}\nSekolah : {nama_sekolah}\n\nTerima kasih atas perhatiannya.\n\n_Pesan otomatis dari sistem absensi {nama_sekolah}_";

    public function __construct(
        private readonly int $timeout = 10,
    ) {}

    /**
     * @param  array<string, string>  $variables
     */
    public function sendTemplate(string $to, string $templateKey, array $variables, ?string $schoolId = null): bool
    {
        $message = $this->buildMessage($variables, $schoolId);

        return $this->send($to, $message, $schoolId);
    }

    public function sendText(string $to, string $message, ?string $schoolId = null): bool
    {
        return $this->send($to, $message, $schoolId);
    }

    /**
     * Send via best available gateway: Fonnte (per-school) → Ozolab (global fallback).
     */
    private function send(string $to, string $message, ?string $schoolId): bool
    {
        // Priority 1: Fonnte per-school config
        $fonnteConfig = $schoolId ? $this->getFonnteConfig($schoolId) : null;

        if ($fonnteConfig) {
            return $this->sendViaFonnte($to, $message, $fonnteConfig);
        }

        // Priority 2: Ozolab gateway from .env
        return $this->sendViaOzolab($to, $message);
    }

    /**
     * Send via Fonnte API (per-school token).
     */
    private function sendViaFonnte(string $to, string $message, SchoolWaConfig $config): bool
    {
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

            Log::channel('whatsapp')->info($success ? 'Fonnte: sent.' : 'Fonnte: failed.', [
                'to' => $to,
                'response' => $data,
            ]);

            return $success;
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Fonnte exception.', ['to' => $to, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Send via Ozolab WA Gateway (global .env config).
     */
    private function sendViaOzolab(string $to, string $message): bool
    {
        $baseUrl = config('whatsapp.base_url');
        $apiKey = config('whatsapp.api_key');
        $sender = config('whatsapp.sender');

        if (empty($apiKey) || empty($sender)) {
            Log::channel('whatsapp')->warning('Ozolab WA gateway not configured, skipping.', ['to' => $to]);

            return false;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$baseUrl}/send-message", [
                    'api_key' => $apiKey,
                    'sender' => $sender,
                    'number' => $to,
                    'message' => $message,
                ]);

            $data = $response->json();
            $success = ($data['status'] ?? false) === true;

            Log::channel('whatsapp')->info($success ? 'Ozolab: sent.' : 'Ozolab: failed.', [
                'to' => $to,
                'response' => $data,
            ]);

            return $success;
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Ozolab exception.', ['to' => $to, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function getFonnteConfig(?string $schoolId): ?SchoolWaConfig
    {
        if (! $schoolId) {
            return null;
        }

        return SchoolWaConfig::where('school_id', $schoolId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Build safe attendance message from template.
     */
    private function buildMessage(array $variables, ?string $schoolId): string
    {
        $school = $schoolId ? School::find($schoolId) : null;
        $template = $school?->getSetting('whatsapp_template_attendance') ?? self::DEFAULT_TEMPLATE;

        $message = $template;
        foreach ($variables as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }

        return $message;
    }
}
