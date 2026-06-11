<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SchoolChannelType;
use App\Http\Controllers\Controller;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use App\Services\Notification\EmailGateway;
use App\Services\Notification\TelegramConnect;
use App\Services\Notification\TelegramGateway;
use App\Services\Notification\WhatsAppGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NotificationGatewayController extends Controller
{
    public function index(Request $request): Response
    {
        $schools = School::query()->orderBy('name')->get(['id', 'name']);

        $selectedId = $request->query('school', $schools->first()?->id);
        $selected = $selectedId ? School::find($selectedId) : null;

        return Inertia::render('admin/notification-gateways/index', [
            'schools' => $schools->map(fn (School $s) => ['id' => $s->id, 'name' => $s->name]),
            'selectedSchoolId' => $selected?->id,
            'channels' => $selected ? $this->channelsPayload($selected) : null,
        ]);
    }

    public function update(Request $request, School $school, TelegramConnect $telegramConnect): RedirectResponse
    {
        $validated = $request->validate([
            'channels.OZOLAB_WA.is_active' => ['boolean'],
            'channels.FONNTE_WA.is_active' => ['boolean'],
            'channels.FONNTE_WA.fonnte_token' => ['nullable', 'string', 'min:10'],
            'channels.FONNTE_WA.display_phone' => ['nullable', 'string', 'max:20'],
            'channels.TELEGRAM.is_active' => ['boolean'],
            'channels.TELEGRAM.bot_token' => ['nullable', 'string', 'min:20'],
            'channels.EMAIL.is_active' => ['boolean'],
            'channels.EMAIL.sender_email' => ['nullable', 'email', 'max:255'],
            'channels.EMAIL.sender_name' => ['nullable', 'string', 'max:255'],
            'channels.EMAIL.smtp_host' => ['nullable', 'string', 'max:255'],
            'channels.EMAIL.smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'channels.EMAIL.smtp_username' => ['nullable', 'string', 'max:255'],
            'channels.EMAIL.smtp_password' => ['nullable', 'string', 'max:255'],
            'channels.EMAIL.smtp_encryption' => ['nullable', 'in:tls,ssl,none'],
        ]);

        $input = $validated['channels'] ?? [];

        // Ozolab WA — tanpa kredensial.
        $this->saveChannel($school, SchoolChannelType::OzolabWa, (bool) ($input['OZOLAB_WA']['is_active'] ?? false));

        // Fonnte WA — token + nomor (token hanya diupdate jika diisi).
        $this->saveChannel(
            $school,
            SchoolChannelType::FonnteWa,
            (bool) ($input['FONNTE_WA']['is_active'] ?? false),
            array_filter([
                'fonnte_token' => $input['FONNTE_WA']['fonnte_token'] ?? null,
                'display_phone' => $input['FONNTE_WA']['display_phone'] ?? null,
            ], fn ($v) => $v !== null),
        );

        // Telegram — bot token (hanya diupdate jika diisi).
        $this->saveChannel(
            $school,
            SchoolChannelType::Telegram,
            (bool) ($input['TELEGRAM']['is_active'] ?? false),
            array_filter([
                'bot_token' => $input['TELEGRAM']['bot_token'] ?? null,
            ], fn ($v) => $v !== null),
        );

        // Email — kredensial SMTP + pengirim per sekolah (password hanya
        // di-update jika diisi, field lain ikut tersimpan walau dikosongkan).
        $email = $input['EMAIL'] ?? [];
        $emailSettings = [
            'sender_email' => $email['sender_email'] ?? '',
            'sender_name' => $email['sender_name'] ?? '',
            'smtp_host' => $email['smtp_host'] ?? '',
            'smtp_port' => $email['smtp_port'] ?? '',
            'smtp_username' => $email['smtp_username'] ?? '',
            'smtp_encryption' => $email['smtp_encryption'] ?? 'tls',
        ];

        if (! empty($email['smtp_password'])) {
            $emailSettings['smtp_password'] = $email['smtp_password'];
        }

        $this->saveChannel($school, SchoolChannelType::Email, (bool) ($email['is_active'] ?? false), $emailSettings);

        // When Telegram is active with a token, resolve the bot username and
        // (re)register the webhook so parents can self-connect via QR.
        $warning = $this->syncTelegramConnection($school, $telegramConnect);

        if ($warning) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $warning]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Konfigurasi gateway notifikasi berhasil disimpan.']);
        }

        return to_route('admin.notification-gateways', ['school' => $school->id]);
    }

    /**
     * Resolve the bot username and register the webhook for the active Telegram
     * channel. Returns a warning message on failure, or null on success/skip.
     */
    private function syncTelegramConnection(School $school, TelegramConnect $telegramConnect): ?string
    {
        $channel = SchoolNotificationChannel::where('school_id', $school->id)
            ->where('channel', SchoolChannelType::Telegram->value)
            ->first();

        if (! $channel || ! $channel->is_active) {
            return null;
        }

        $token = (string) $channel->setting('bot_token');

        if ($token === '') {
            return 'Telegram aktif tetapi Bot Token belum diisi.';
        }

        $username = $telegramConnect->resolveUsername($token);

        if (! $username) {
            return 'Bot Token Telegram tidak valid atau gagal menghubungi Telegram. Periksa token.';
        }

        $secret = (string) ($channel->setting('webhook_secret') ?: Str::random(40));
        $webhookUrl = route('telegram.webhook', ['school' => $school->id]);

        $registered = $telegramConnect->setWebhook($token, $webhookUrl, $secret);

        $channel->settings = array_merge($channel->settings ?? [], [
            'bot_username' => $username,
            'webhook_secret' => $secret,
        ]);
        $channel->save();

        if (! $registered) {
            return 'Bot terhubung tetapi gagal mendaftarkan webhook. Coba simpan ulang.';
        }

        return null;
    }

    public function destroy(School $school): RedirectResponse
    {
        SchoolNotificationChannel::where('school_id', $school->id)->delete();

        // Sisakan Ozolab WA aktif sebagai default.
        SchoolNotificationChannel::create([
            'school_id' => $school->id,
            'channel' => SchoolChannelType::OzolabWa,
            'is_active' => true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Konfigurasi gateway direset ke default (Ozolab ID).']);

        return to_route('admin.notification-gateways', ['school' => $school->id]);
    }

    public function test(Request $request, School $school, WhatsAppGateway $whatsApp, TelegramGateway $telegram, EmailGateway $email): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'in:OZOLAB_WA,FONNTE_WA,TELEGRAM,EMAIL'],
            'destination' => ['required', 'string', 'min:5'],
        ]);

        $message = 'Test notifikasi dari sistem absensi '.$school->name.'. Jika Anda menerima pesan ini, konfigurasi berhasil!';
        $type = SchoolChannelType::from($validated['channel']);

        $success = match ($type) {
            SchoolChannelType::Telegram => $telegram->sendText($validated['destination'], $message, $school->id),
            SchoolChannelType::Email => $email->sendText($validated['destination'], $message, $school->id),
            default => $whatsApp->sendText($validated['destination'], $message, $school->id),
        };

        return response()->json([
            'success' => $success,
            'message' => $success
                ? 'Pesan test berhasil dikirim!'
                : 'Gagal mengirim. Periksa kredensial dan pastikan channel aktif.',
        ]);
    }

    /**
     * @param  array<string, string>  $settings
     */
    private function saveChannel(School $school, SchoolChannelType $type, bool $isActive, array $settings = []): void
    {
        $channel = SchoolNotificationChannel::firstOrNew([
            'school_id' => $school->id,
            'channel' => $type->value,
        ]);

        $channel->is_active = $isActive;

        if ($settings !== []) {
            $channel->settings = array_merge($channel->settings ?? [], $settings);
        }

        $channel->save();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function channelsPayload(School $school): array
    {
        $channels = $school->notificationChannels()->get()->keyBy(fn (SchoolNotificationChannel $c) => $c->channel->value);

        $fonnte = $channels->get(SchoolChannelType::FonnteWa->value);
        $telegram = $channels->get(SchoolChannelType::Telegram->value);
        $emailChannel = $channels->get(SchoolChannelType::Email->value);

        $connect = app(TelegramConnect::class);
        $botUsername = $telegram?->setting('bot_username');
        $deepLink = $botUsername ? $connect->deepLink($botUsername) : null;

        return [
            'OZOLAB_WA' => [
                'is_active' => (bool) $channels->get(SchoolChannelType::OzolabWa->value)?->is_active,
            ],
            'FONNTE_WA' => [
                'is_active' => (bool) $fonnte?->is_active,
                'display_phone' => $fonnte?->setting('display_phone') ?? '',
                'has_token' => ! empty($fonnte?->setting('fonnte_token')),
            ],
            'TELEGRAM' => [
                'is_active' => (bool) $telegram?->is_active,
                'has_token' => ! empty($telegram?->setting('bot_token')),
                'bot_username' => $botUsername,
                'deep_link' => $deepLink,
                'qr_svg' => $deepLink ? $connect->qrSvg($deepLink) : null,
                'connected_count' => ParentProfile::where('school_id', $school->id)->whereNotNull('telegram_chat_id')->count(),
                'total_parents' => ParentProfile::where('school_id', $school->id)->count(),
            ],
            'EMAIL' => [
                'is_active' => (bool) $emailChannel?->is_active,
                'sender_email' => $emailChannel?->setting('sender_email') ?? '',
                'sender_name' => $emailChannel?->setting('sender_name') ?? '',
                'smtp_host' => $emailChannel?->setting('smtp_host') ?? '',
                'smtp_port' => $emailChannel?->setting('smtp_port') ?? '',
                'smtp_username' => $emailChannel?->setting('smtp_username') ?? '',
                'smtp_encryption' => $emailChannel?->setting('smtp_encryption') ?? 'tls',
                'has_smtp_password' => ! empty($emailChannel?->setting('smtp_password')),
                'connected_count' => ParentProfile::where('school_id', $school->id)->whereNotNull('email')->where('email', '!=', '')->count(),
                'total_parents' => ParentProfile::where('school_id', $school->id)->count(),
            ],
        ];
    }
}
