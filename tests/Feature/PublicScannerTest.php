<?php

use App\Enums\AttendanceType;
use App\Models\AttendanceSchedule;
use App\Models\School;
use App\Models\Student;
use App\Support\SchoolTime;
use Carbon\Carbon;

beforeEach(function () {
    // Dihitung sebelum travelTo supaya tetap menunjuk Senin yang sama
    // walaupun jam sistem sudah dipindahkan di dalam test.
    $this->monday = SchoolTime::now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();

    $this->travelTo(schoolMonday());
});

/**
 * Senin pukul 07:00 WIB — di dalam jendela masuk pada jadwal default.
 */
function schoolMonday(int $hour = 7, int $minute = 0): Carbon
{
    return test()->monday->copy()->setTime($hour, $minute);
}

/**
 * Buat sekolah + siswa + jadwal Senin-Jumat dengan jam default aplikasi.
 */
function makeScannableStudent(?School $school = null): array
{
    $school ??= School::factory()->create();

    $student = Student::factory()->create(['school_id' => $school->id]);

    foreach (range(1, 5) as $day) {
        AttendanceSchedule::factory()->create([
            'school_id' => $school->id,
            'classroom_id' => null,
            'day_of_week' => $day,
            'is_active' => true,
        ]);
    }

    return [$school, $student];
}

test('public scan page loads with valid token without auth', function () {
    $school = School::factory()->create();

    $this->get(route('public.scanner', $school->scanner_token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('scan/public')
            ->where('school.name', $school->name)
            ->where('scanToken', $school->scanner_token)
        );
});

test('invalid scanner token returns 404', function () {
    $this->get('/scan/token-ngawur-tidak-ada')->assertNotFound();
});

test('public scan route is not auth guarded', function () {
    $school = School::factory()->create();

    $this->get(route('public.scanner', $school->scanner_token))
        ->assertOk()
        ->assertDontSee(route('login'));
});

test('morning scan is check-in and a second morning scan is rejected, not turned into check-out', function () {
    [$school, $student] = makeScannableStudent();

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertOk()
        ->assertJson(['success' => true, 'student' => ['type' => 'CHECK_IN']]);

    // Tap kedua di jendela masuk: dulu langsung tercatat "Pulang".
    $this->travelTo(schoolMonday(7, 5));

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    expect($student->attendances()->where('type', AttendanceType::CheckOut)->count())->toBe(0);
});

test('afternoon scan records check-out and a repeat is rejected', function () {
    [$school, $student] = makeScannableStudent();

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertOk()
        ->assertJson(['student' => ['type' => 'CHECK_IN']]);

    $this->travelTo(schoolMonday(14, 0));

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertOk()
        ->assertJson(['success' => true, 'student' => ['type' => 'CHECK_OUT']]);

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    expect($student->attendances()->where('type', AttendanceType::CheckIn)->count())->toBe(1)
        ->and($student->attendances()->where('type', AttendanceType::CheckOut)->count())->toBe(1);
});

test('next morning is a fresh check-in even when yesterday never checked out', function () {
    [$school, $student] = makeScannableStudent();

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertOk()
        ->assertJson(['student' => ['type' => 'CHECK_IN']]);

    // Lupa scan pulang, lanjut ke Selasa pagi.
    $this->travelTo(schoolMonday()->addDay());

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertOk()
        ->assertJson(['success' => true, 'student' => ['type' => 'CHECK_IN']]);

    expect($student->attendances()->where('type', AttendanceType::CheckIn)->count())->toBe(2)
        ->and($student->attendances()->where('type', AttendanceType::CheckOut)->count())->toBe(0);
});

test('scan before the check-in window opens is rejected', function () {
    [$school, $student] = makeScannableStudent();

    $this->travelTo(schoolMonday(5, 30));

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    expect($student->attendances()->count())->toBe(0);
});

test('scan after the check-out window closes is rejected', function () {
    [$school, $student] = makeScannableStudent();

    $this->travelTo(schoolMonday(19, 0));

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    expect($student->attendances()->count())->toBe(0);
});

test('late morning scan is still a check-in but flagged terlambat', function () {
    [$school, $student] = makeScannableStudent();

    $this->travelTo(schoolMonday(9, 30));

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertOk()
        ->assertJson(['success' => true, 'student' => ['type' => 'CHECK_IN', 'status' => 'Terlambat']]);
});

test('a raw NIS is rejected — only the QR token is accepted', function () {
    [$school, $student] = makeScannableStudent();

    // NIS bisa ditebak (8 digit, rentang sempit); dulu ini cukup untuk
    // memalsukan kehadiran siswa mana pun tanpa akun.
    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->nis])
        ->assertStatus(404)
        ->assertJson(['success' => false]);

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->nisn])
        ->assertStatus(404);

    expect($student->attendances()->count())->toBe(0);
});

test('the scan response does not leak student PII', function () {
    [$school, $student] = makeScannableStudent();

    $payload = $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertOk()
        ->json('student');

    expect($payload)->toHaveKeys(['full_name', 'nis', 'no_absen', 'classroom', 'status', 'time'])
        ->and($payload)->not->toHaveKey('address')
        ->and($payload)->not->toHaveKey('birth_date')
        ->and($payload)->not->toHaveKey('birth_place')
        ->and($payload)->not->toHaveKey('religion')
        ->and($payload)->not->toHaveKey('nisn');
});

test('cannot scan a student from another school via wrong token', function () {
    [$schoolA] = makeScannableStudent();
    [, $studentB] = makeScannableStudent();

    $this->postJson(route('public.scanner.scan', $schoolA->scanner_token), ['token' => $studentB->qr_token])
        ->assertStatus(404)
        ->assertJson(['success' => false]);
});

test('inactive school rejects scans', function () {
    [$school, $student] = makeScannableStudent();
    $school->update(['is_active' => false]);

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertStatus(403)
        ->assertJson(['success' => false]);
});

test('super admin can regenerate scanner token and old link dies', function () {
    $admin = createSuperAdminUser();
    $school = School::factory()->create();
    $oldToken = $school->scanner_token;

    $this->actingAs($admin)
        ->post("/admin/schools/{$school->id}/scanner-token")
        ->assertRedirect();

    $school->refresh();

    expect($school->scanner_token)->not->toBe($oldToken);

    $this->get("/scan/{$oldToken}")->assertNotFound();
    $this->get(route('public.scanner', $school->scanner_token))->assertOk();
});
