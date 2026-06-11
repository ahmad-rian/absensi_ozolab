<?php

namespace App\Http\Controllers;

use App\Enums\SchoolChannelType;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use App\Services\Notification\TelegramConnect;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramConnect $telegram,
    ) {}

    /**
     * Receive Telegram updates for a school's bot and auto-link the parent
     * who scanned the QR by matching the WhatsApp number they share.
     */
    public function handle(Request $request, School $school): JsonResponse
    {
        $channel = $this->telegramChannel($school);

        // Validate the secret header Telegram echoes back from setWebhook.
        if (! $channel || $request->header('X-Telegram-Bot-Api-Secret-Token') !== $channel->setting('webhook_secret')) {
            return response()->json(['ok' => false], 403);
        }

        $token = (string) $channel->setting('bot_token');
        $message = $request->input('message');

        if (! is_array($message)) {
            return response()->json(['ok' => true]);
        }

        $chatId = $message['chat']['id'] ?? null;

        if ($chatId === null) {
            return response()->json(['ok' => true]);
        }

        // Step 2: parent shared their contact — match and link.
        if (isset($message['contact'])) {
            $this->linkContact($school, $token, $message);

            return response()->json(['ok' => true]);
        }

        // Step 1: /start — ask the parent to share their number.
        if (str_starts_with((string) ($message['text'] ?? ''), '/start')) {
            $this->telegram->sendMessage(
                $token,
                $chatId,
                "Selamat datang di notifikasi kehadiran {$school->name}.\n\nTekan tombol di bawah untuk menghubungkan akun Anda. Notifikasi hanya dikirim ke nomor orang tua yang terdaftar.",
                [
                    'keyboard' => [[['text' => '📱 Bagikan Nomor Saya', 'request_contact' => true]]],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ],
            );
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function linkContact(School $school, string $token, array $message): void
    {
        $contact = $message['contact'];
        $chatId = $message['chat']['id'];

        // Only accept the sender's own contact, not a forwarded one.
        if (($contact['user_id'] ?? null) !== ($message['from']['id'] ?? null)) {
            $this->telegram->sendMessage($token, $chatId, 'Mohon bagikan nomor Anda sendiri menggunakan tombol yang tersedia.');

            return;
        }

        $phone = PhoneNumber::normalize($contact['phone_number'] ?? '');

        $matches = ParentProfile::where('school_id', $school->id)
            ->get()
            ->filter(fn (ParentProfile $p) => PhoneNumber::normalize($p->whatsapp_number) === $phone && $phone !== '');

        if ($matches->isEmpty()) {
            $this->telegram->sendMessage(
                $token,
                $chatId,
                "Nomor Anda belum terdaftar di {$school->name}. Hubungi admin sekolah untuk memastikan nomor WhatsApp Anda sudah benar.",
            );

            return;
        }

        foreach ($matches as $parent) {
            $parent->update(['telegram_chat_id' => (string) $chatId]);
        }

        $children = $matches->flatMap(fn (ParentProfile $p) => $p->students->pluck('full_name'))->unique()->values();
        $names = $children->isNotEmpty() ? $children->implode(', ') : 'putra/putri Anda';

        $this->telegram->sendMessage(
            $token,
            $chatId,
            "✅ Berhasil terhubung!\n\nNotifikasi kehadiran untuk {$names} akan dikirim ke Telegram ini.",
            ['remove_keyboard' => true],
        );
    }

    private function telegramChannel(School $school): ?SchoolNotificationChannel
    {
        return SchoolNotificationChannel::where('school_id', $school->id)
            ->where('channel', SchoolChannelType::Telegram->value)
            ->where('is_active', true)
            ->first();
    }
}
