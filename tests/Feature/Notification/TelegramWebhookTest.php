<?php

use App\Enums\SchoolChannelType;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use App\Models\Student;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\postJson;

function telegramBotSchool(string $secret = 'secret-token'): School
{
    $school = School::factory()->create();
    SchoolNotificationChannel::create([
        'school_id' => $school->id,
        'channel' => SchoolChannelType::Telegram,
        'is_active' => true,
        'settings' => ['bot_token' => '12345:ABCDEF_token_long', 'bot_username' => 'sekolahbot', 'webhook_secret' => $secret],
    ]);

    return $school;
}

function webhook(School $school, array $payload, string $secret = 'secret-token')
{
    return postJson(route('telegram.webhook', ['school' => $school->id]), $payload, [
        'X-Telegram-Bot-Api-Secret-Token' => $secret,
    ]);
}

test('shared contact auto-links the matching parent', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $school = telegramBotSchool();
    $parent = ParentProfile::factory()->create(['school_id' => $school->id, 'whatsapp_number' => '081234567890']);
    Student::factory()->create(['school_id' => $school->id, 'parent_profile_id' => $parent->id]);

    webhook($school, [
        'message' => [
            'chat' => ['id' => 999],
            'from' => ['id' => 999],
            'contact' => ['phone_number' => '6281234567890', 'user_id' => 999],
        ],
    ])->assertOk();

    expect($parent->fresh()->telegram_chat_id)->toBe('999');
});

test('wrong secret is rejected and links nothing', function () {
    Http::fake();
    $school = telegramBotSchool();
    $parent = ParentProfile::factory()->create(['school_id' => $school->id, 'whatsapp_number' => '081234567890']);

    webhook($school, [
        'message' => ['chat' => ['id' => 999], 'from' => ['id' => 999], 'contact' => ['phone_number' => '081234567890', 'user_id' => 999]],
    ], 'wrong-secret')->assertStatus(403);

    expect($parent->fresh()->telegram_chat_id)->toBeNull();
});

test('start command replies with a share-contact keyboard', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $school = telegramBotSchool();

    webhook($school, [
        'message' => ['chat' => ['id' => 5], 'from' => ['id' => 5], 'text' => '/start connect'],
    ])->assertOk();

    Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage')
        && str_contains(json_encode($r->data()), 'request_contact'));
});

test('forwarded contact of another user is rejected', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $school = telegramBotSchool();
    $parent = ParentProfile::factory()->create(['school_id' => $school->id, 'whatsapp_number' => '081234567890']);

    webhook($school, [
        'message' => [
            'chat' => ['id' => 999],
            'from' => ['id' => 999],
            'contact' => ['phone_number' => '081234567890', 'user_id' => 111],
        ],
    ])->assertOk();

    expect($parent->fresh()->telegram_chat_id)->toBeNull();
});

test('unknown number is not linked', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $school = telegramBotSchool();
    $parent = ParentProfile::factory()->create(['school_id' => $school->id, 'whatsapp_number' => '081234567890']);

    webhook($school, [
        'message' => [
            'chat' => ['id' => 999],
            'from' => ['id' => 999],
            'contact' => ['phone_number' => '089999999999', 'user_id' => 999],
        ],
    ])->assertOk();

    expect($parent->fresh()->telegram_chat_id)->toBeNull();
});
