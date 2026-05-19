<?php

use App\Models\Student;
use App\Services\Attendance\QrTokenGenerator;

test('generates a unique 64-char token', function () {
    $student = Student::factory()->create(['qr_token' => null]);
    $generator = new QrTokenGenerator;

    $token = $generator->generate($student);

    expect($token)->toHaveLength(64);
    expect($student->fresh()->qr_token)->toBe($token);
    expect($student->fresh()->qr_issued_at)->not->toBeNull();
});

test('generates different tokens for different students', function () {
    $student1 = Student::factory()->create(['qr_token' => null]);
    $student2 = Student::factory()->create(['qr_token' => null]);
    $generator = new QrTokenGenerator;

    $token1 = $generator->generate($student1);
    $token2 = $generator->generate($student2);

    expect($token1)->not->toBe($token2);
});

test('verifies valid token returns student', function () {
    $student = Student::factory()->create();
    $generator = new QrTokenGenerator;

    $found = $generator->verify($student->qr_token);

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($student->id);
});

test('verifies invalid token returns null', function () {
    $generator = new QrTokenGenerator;

    expect($generator->verify('invalid-token-that-does-not-exist'))->toBeNull();
});

test('rotate generates new token and sets rotated_at', function () {
    $student = Student::factory()->create();
    $oldToken = $student->qr_token;
    $generator = new QrTokenGenerator;

    $newToken = $generator->rotate($student);

    expect($newToken)->not->toBe($oldToken);
    expect($student->fresh()->qr_rotated_at)->not->toBeNull();
});
