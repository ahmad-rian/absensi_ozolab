<?php

namespace App\Services\Notification;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Telegram Bot API for the self-service connect flow:
 * resolving the bot username, registering the webhook, sending replies, and
 * building the deep-link QR that parents scan.
 */
class TelegramConnect
{
    public function __construct(
        private readonly int $timeout = 10,
    ) {}

    /**
     * Resolve the bot username via getMe. Returns null on failure.
     */
    public function resolveUsername(string $botToken): ?string
    {
        try {
            $response = Http::timeout($this->timeout)->get($this->endpoint($botToken, 'getMe'));
            $data = $response->json();

            if (($data['ok'] ?? false) === true) {
                return $data['result']['username'] ?? null;
            }
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Telegram getMe failed.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Register the webhook URL for this bot. Returns true on success.
     */
    public function setWebhook(string $botToken, string $url, string $secretToken): bool
    {
        try {
            $response = Http::timeout($this->timeout)->post($this->endpoint($botToken, 'setWebhook'), [
                'url' => $url,
                'secret_token' => $secretToken,
                'allowed_updates' => ['message'],
            ]);

            return ($response->json()['ok'] ?? false) === true;
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Telegram setWebhook failed.', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Send a message, optionally with a reply markup (keyboard).
     *
     * @param  array<string, mixed>|null  $replyMarkup
     */
    public function sendMessage(string $botToken, int|string $chatId, string $text, ?array $replyMarkup = null): bool
    {
        try {
            $payload = ['chat_id' => $chatId, 'text' => $text];

            if ($replyMarkup !== null) {
                $payload['reply_markup'] = $replyMarkup;
            }

            $response = Http::timeout($this->timeout)->post($this->endpoint($botToken, 'sendMessage'), $payload);

            return ($response->json()['ok'] ?? false) === true;
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Telegram sendMessage failed.', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Build the parent-facing deep link that opens the bot and triggers /start.
     */
    public function deepLink(string $username): string
    {
        return 'https://t.me/'.ltrim($username, '@').'?start=connect';
    }

    /**
     * Render a deep-link URL as an inline SVG QR code.
     */
    public function qrSvg(string $url, int $size = 260): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($url);
    }

    private function endpoint(string $botToken, string $method): string
    {
        $base = rtrim((string) config('telegram.api_base'), '/');

        return "{$base}/bot{$botToken}/{$method}";
    }
}
