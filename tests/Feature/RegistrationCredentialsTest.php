<?php

use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\ParentProfileService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $school = School::factory()->create();
    $classroom = Classroom::factory()->create(['school_id' => $school->id]);
    $this->payload = [
        'school_id' => $school->id, 'classroom_id' => $classroom->id,
        'full_name' => 'Anak Pertama', 'no_absen' => '1', 'nisn' => '0011111111',
        'gender' => 'LAKI_LAKI', 'religion' => 'ISLAM', 'birth_place' => 'Jakarta',
        'birth_date' => '2013-04-01', 'address' => 'Jl. Melati',
        'parent_name' => 'Budi Santoso', 'parent_phone' => '081234567890', 'parent_relation' => 'AYAH',
        'parent_email' => 'budi@example.com', 'password' => 'SandiWali123!', 'password_confirmation' => 'SandiWali123!',
    ];
});

test('new registration creates working parent login using chosen password', function () {
    $this->postJson('/daftar', $this->payload)->assertOk();
    $parent = User::where('email', 'budi@example.com')->firstOrFail();
    expect(Hash::check('SandiWali123!', $parent->password))->toBeTrue();
    expect($parent->must_change_password)->toBeFalse();
    expect($parent->hasRole('ORANG_TUA'))->toBeTrue();
    expect(Student::first()->parent_profile_id)->toBe($parent->parentProfile->id);
    $this->post('/login', ['email' => 'budi@example.com', 'password' => 'SandiWali123!'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($parent);
    $this->get('/orangtua')->assertOk();
});

test('registration requires valid confirmed credentials', function (array $overrides, string $field) {
    $this->postJson('/daftar', [...$this->payload, ...$overrides])->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(Student::count())->toBe(0);
    expect(User::where('email', 'budi@example.com')->exists())->toBeFalse();
})->with([
    'email required' => [['parent_email' => ''], 'parent_email'],
    'invalid email' => [['parent_email' => 'not-email'], 'parent_email'],
    'password required' => [['password' => ''], 'password'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    // Panjangnya cukup, kekuatannya tidak. Ini yang lolos sebelum aturannya
    // dinaikkan — akun ini bisa melihat data kehadiran seorang anak.
    'lowercase only' => [['password' => 'sandiwali', 'password_confirmation' => 'sandiwali'], 'password'],
    'no number' => [['password' => 'SandiWali', 'password_confirmation' => 'SandiWali'], 'password'],
    'confirmation mismatch' => [['password_confirmation' => 'different'], 'password'],
]);

test('orang tua lama bersandi lemah tidak terkunci dari anak keduanya', function () {
    /*
        Kolom sandi di /daftar bermakna ganda: membuat sandi baru, atau
        mencocokkan sandi akun yang sudah ada. Aturan kekuatan hanya boleh
        mengikat yang pertama — ribuan akun hasil impor masih memegang sandi
        bawaan `password`, yang lolos aturan lama dan gagal aturan baru. Kalau
        aturannya dipasang polos, mereka ditolak validator sebelum sandinya
        sempat dicocokkan, dan anak keduanya tidak bisa didaftarkan sama sekali.
    */
    app(ParentProfileService::class)->findOrCreateFromRegistration(
        $this->payload['school_id'],
        'Budi Santoso',
        '081234567890',
        'AYAH',
        'budi@example.com',
        'password',
    );

    $hash = User::where('email', 'budi@example.com')->firstOrFail()->password;

    $this->postJson('/daftar', [
        ...$this->payload,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertOk();

    $student = Student::firstOrFail();

    expect($student->parent_profile_id)->not->toBeNull()
        ->and(User::where('email', 'budi@example.com')->firstOrFail()->password)->toBe($hash);
});

test('existing parent credentials allow another child without resetting password', function () {
    $this->postJson('/daftar', $this->payload)->assertOk();
    $parent = User::where('email', 'budi@example.com')->firstOrFail();
    $hash = $parent->password;
    $this->postJson('/daftar', [...$this->payload, 'full_name' => 'Anak Kedua', 'nisn' => '0022222222', 'no_absen' => '2'])->assertOk();
    expect(Student::count())->toBe(2);
    expect(Student::pluck('parent_profile_id')->unique())->toHaveCount(1);
    expect($parent->fresh()->password)->toBe($hash);
});

test('knowing a parent phone cannot overwrite credentials or attach another child', function (array $overrides) {
    $this->postJson('/daftar', $this->payload)->assertOk();
    $parent = User::where('email', 'budi@example.com')->firstOrFail();
    $hash = $parent->password;
    $this->postJson('/daftar', [...$this->payload, 'nisn' => '0022222222', ...$overrides])->assertUnprocessable()->assertJsonValidationErrors('password');
    expect(Student::count())->toBe(1);
    expect($parent->fresh()->password)->toBe($hash);
    expect($parent->fresh()->email)->toBe('budi@example.com');
})->with([
    'wrong password' => [['password' => 'OtherPassword123', 'password_confirmation' => 'OtherPassword123']],
    'different email' => [['parent_email' => 'someone@example.com']],
]);

test('another accounts email cannot be claimed in registration', function () {
    User::factory()->create(['email' => 'budi@example.com']);
    $this->postJson('/daftar', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('parent_email');
    expect(Student::count())->toBe(0);
});
