<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DefaultWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $senderId,
        private readonly int $timeout,
    ) {}

    /**
     * @param  array<string, string>  $variables
     */
    public function sendTemplate(string $to, string $templateKey, array $variables): bool
    {
        if (empty($this->baseUrl)) {
            Log::channel('whatsapp')->warning('WhatsApp base URL not configured, skipping send.', [
                'to' => $to,
                'template' => $templateKey,
            ]);

            return false;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/send-template", [
                    'sender_id' => $this->senderId,
                    'to' => $to,
                    'template_key' => $templateKey,
                    'variables' => $variables,
                ]);

            if ($response->successful()) {
                Log::channel('whatsapp')->info('WhatsApp template sent.', [
                    'to' => $to,
                    'template' => $templateKey,
                    'response' => $response->json(),
                ]);

                return true;
            }

            Log::channel('whatsapp')->error('WhatsApp send failed.', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('WhatsApp send exception.', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendText(string $to, string $message): bool
    {
        if (empty($this->baseUrl)) {
            return false;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/send-text", [
                    'sender_id' => $this->senderId,
                    'to' => $to,
                    'message' => $message,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('WhatsApp text send exception.', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
