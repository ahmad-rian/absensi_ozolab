<?php

use App\Enums\AttendanceType;
use App\Models\AttendanceSchedule;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Carbon;

/**
 * Buat sekolah + siswa + jadwal aktif untuk hari ini agar scan berhasil dicatat.
 */
function makeScannableStudent(?School $school = null): array
{
    $school ??= School::factory()->create();

    $student = Student::factory()->create(['school_id' => $school->id]);

    AttendanceSchedule::factory()->create([
        'school_id' => $school->id,
        'classroom_id' => null,
        'day_of_week' => Carbon::now('Asia/Jakarta')->dayOfWeekIso,
        'check_in_start' => '00:00',
        'check_in_end' => '23:59',
        'late_threshold' => '23:59',
        'check_out_start' => '00:00',
        'check_out_end' => '23:59',
        'is_active' => true,
    ]);

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

test('first scan records check-in, second records check-out, third is rejected', function () {
    [$school, $student] = makeScannableStudent();

    // Scan 1 → Masuk
    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertOk()
        ->assertJson(['success' => true, 'student' => ['type' => 'CHECK_IN']]);

    // Scan 2 → Pulang
    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertOk()
        ->assertJson(['success' => true, 'student' => ['type' => 'CHECK_OUT']]);

    // Scan 3 → ditolak
    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->qr_token])
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    expect($student->attendances()->where('type', AttendanceType::CheckIn)->count())->toBe(1)
        ->and($student->attendances()->where('type', AttendanceType::CheckOut)->count())->toBe(1);
});

test('scan works via NIS as well as qr token', function () {
    [$school, $student] = makeScannableStudent();

    $this->postJson(route('public.scanner.scan', $school->scanner_token), ['token' => $student->nis])
        ->assertOk()
        ->assertJson(['success' => true]);
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
