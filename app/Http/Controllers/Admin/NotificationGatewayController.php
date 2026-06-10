<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SchoolChannelType;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use App\Services\Notification\TelegramGateway;
use App\Services\Notification\WhatsAppGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function update(Request $request, School $school): RedirectResponse
    {
        $validated = $request->validate([
            'channels.OZOLAB_WA.is_active' => ['boolean'],
            'channels.FONNTE_WA.is_active' => ['boolean'],
            'channels.FONNTE_WA.fonnte_token' => ['nullable', 'string', 'min:10'],
            'channels.FONNTE_WA.display_phone' => ['nullable', 'string', 'max:20'],
            'channels.TELEGRAM.is_active' => ['boolean'],
            'channels.TELEGRAM.bot_token' => ['nullable', 'string', 'min:20'],
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

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Konfigurasi gateway notifikasi berhasil disimpan.']);

        return to_route('admin.notification-gateways', ['school' => $school->id]);
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

    public function test(Request $request, School $school, WhatsAppGateway $whatsApp, TelegramGateway $telegram): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'in:OZOLAB_WA,FONNTE_WA,TELEGRAM'],
            'destination' => ['required', 'string', 'min:5'],
        ]);

        $message = 'Test notifikasi dari sistem absensi '.$school->name.'. Jika Anda menerima pesan ini, konfigurasi berhasil!';
        $type = SchoolChannelType::from($validated['channel']);

        $success = $type === SchoolChannelType::Telegram
            ? $telegram->sendText($validated['destination'], $message, $school->id)
            : $whatsApp->sendText($validated['destination'], $message, $school->id);

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
            ],
        ];
    }
}
