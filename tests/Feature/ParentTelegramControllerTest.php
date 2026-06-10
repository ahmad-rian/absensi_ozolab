<?php

use App\Models\ParentProfile;
use App\Models\Student;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

test('the public telegram page renders', function () {
    get(route('parent.telegram'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('parent-telegram'));
});

test('parent can link telegram chat id with matching whatsapp number', function () {
    $parent = ParentProfile::factory()->create(['whatsapp_number' => '081234567890']);
    $student = Student::factory()->create(['parent_profile_id' => $parent->id]);

    postJson(route('parent.telegram.store'), [
        'student_id' => $student->id,
        'whatsapp_number' => '081234567890',
        'telegram_chat_id' => '987654321',
    ])->assertOk()->assertJson(['success' => true]);

    expect($parent->fresh()->telegram_chat_id)->toBe('987654321');
});

test('whatsapp number is normalized against 62 country code', function () {
    $parent = ParentProfile::factory()->create(['whatsapp_number' => '+6281234567890']);
    $student = Student::factory()->create(['parent_profile_id' => $parent->id]);

    postJson(route('parent.telegram.store'), [
        'student_id' => $student->id,
        'whatsapp_number' => '081234567890',
        'telegram_chat_id' => '555',
    ])->assertOk()->assertJson(['success' => true]);

    expect($parent->fresh()->telegram_chat_id)->toBe('555');
});

test('mismatched whatsapp number is rejected', function () {
    $parent = ParentProfile::factory()->create(['whatsapp_number' => '081234567890']);
    $student = Student::factory()->create(['parent_profile_id' => $parent->id]);

    postJson(route('parent.telegram.store'), [
        'student_id' => $student->id,
        'whatsapp_number' => '089999999999',
        'telegram_chat_id' => '987654321',
    ])->assertStatus(422)->assertJson(['success' => false]);

    expect($parent->fresh()->telegram_chat_id)->toBeNull();
});

test('non numeric chat id is rejected', function () {
    $parent = ParentProfile::factory()->create(['whatsapp_number' => '081234567890']);
    $student = Student::factory()->create(['parent_profile_id' => $parent->id]);

    postJson(route('parent.telegram.store'), [
        'student_id' => $student->id,
        'whatsapp_number' => '081234567890',
        'telegram_chat_id' => 'abc',
    ])->assertStatus(422);
});
