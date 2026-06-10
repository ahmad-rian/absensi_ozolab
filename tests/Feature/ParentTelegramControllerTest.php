<?php

use App\Enums\SchoolChannelType;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use App\Models\Student;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

/**
 * @return array{0: School, 1: Student, 2: ParentProfile}
 */
function telegramSchoolWithStudent(string $whatsapp = '081234567890'): array
{
    $school = School::factory()->create();
    SchoolNotificationChannel::create([
        'school_id' => $school->id,
        'channel' => SchoolChannelType::Telegram,
        'is_active' => true,
        'settings' => ['bot_token' => 'test-token'],
    ]);

    $parent = ParentProfile::factory()->create(['whatsapp_number' => $whatsapp]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'parent_profile_id' => $parent->id,
    ]);

    return [$school, $student, $parent];
}

test('the public telegram page only lists schools with active telegram', function () {
    [$school] = telegramSchoolWithStudent();
    $without = School::factory()->create();

    get(route('parent.telegram'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('parent-telegram')
            ->where('schools', fn ($schools) => collect($schools)->pluck('id')->contains($school->id)
                && ! collect($schools)->pluck('id')->contains($without->id)
            )
        );
});

test('parent can link telegram chat id with matching whatsapp number', function () {
    [$school, $student, $parent] = telegramSchoolWithStudent();

    postJson(route('parent.telegram.store'), [
        'school_id' => $school->id,
        'student_id' => $student->id,
        'whatsapp_number' => '081234567890',
        'telegram_chat_id' => '987654321',
    ])->assertOk()->assertJson(['success' => true]);

    expect($parent->fresh()->telegram_chat_id)->toBe('987654321');
});

test('whatsapp number is normalized against 62 country code', function () {
    [$school, $student, $parent] = telegramSchoolWithStudent('+6281234567890');

    postJson(route('parent.telegram.store'), [
        'school_id' => $school->id,
        'student_id' => $student->id,
        'whatsapp_number' => '081234567890',
        'telegram_chat_id' => '555',
    ])->assertOk()->assertJson(['success' => true]);

    expect($parent->fresh()->telegram_chat_id)->toBe('555');
});

test('whatsapp stored without leading zero still matches input with zero', function () {
    [$school, $student, $parent] = telegramSchoolWithStudent('82123479638');

    postJson(route('parent.telegram.store'), [
        'school_id' => $school->id,
        'student_id' => $student->id,
        'whatsapp_number' => '082123479638',
        'telegram_chat_id' => '777',
    ])->assertOk()->assertJson(['success' => true]);

    expect($parent->fresh()->telegram_chat_id)->toBe('777');
});

test('mismatched whatsapp number is rejected', function () {
    [$school, $student, $parent] = telegramSchoolWithStudent();

    postJson(route('parent.telegram.store'), [
        'school_id' => $school->id,
        'student_id' => $student->id,
        'whatsapp_number' => '089999999999',
        'telegram_chat_id' => '987654321',
    ])->assertStatus(422)->assertJson(['success' => false]);

    expect($parent->fresh()->telegram_chat_id)->toBeNull();
});

test('student from another school is rejected', function () {
    [$school] = telegramSchoolWithStudent();
    $otherParent = ParentProfile::factory()->create(['whatsapp_number' => '081234567890']);
    $otherStudent = Student::factory()->create(['parent_profile_id' => $otherParent->id]);

    postJson(route('parent.telegram.store'), [
        'school_id' => $school->id,
        'student_id' => $otherStudent->id,
        'whatsapp_number' => '081234567890',
        'telegram_chat_id' => '987654321',
    ])->assertStatus(422)->assertJson(['success' => false]);
});

test('school without active telegram is rejected', function () {
    $school = School::factory()->create();
    $parent = ParentProfile::factory()->create(['whatsapp_number' => '081234567890']);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'parent_profile_id' => $parent->id,
    ]);

    postJson(route('parent.telegram.store'), [
        'school_id' => $school->id,
        'student_id' => $student->id,
        'whatsapp_number' => '081234567890',
        'telegram_chat_id' => '987654321',
    ])->assertStatus(422)->assertJson(['success' => false]);
});

test('non numeric chat id is rejected', function () {
    [$school, $student] = telegramSchoolWithStudent();

    postJson(route('parent.telegram.store'), [
        'school_id' => $school->id,
        'student_id' => $student->id,
        'whatsapp_number' => '081234567890',
        'telegram_chat_id' => 'abc',
    ])->assertStatus(422);
});
