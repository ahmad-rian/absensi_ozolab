<?php

use App\Models\Classroom;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\AlamatLoginOrangTua;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

function accountSheetStudent(User $admin, Classroom $classroom, string $email): Student
{
    $user = User::factory()->create(['school_id' => $admin->school_id, 'email' => $email]);
    $parent = ParentProfile::factory()->create(['school_id' => $admin->school_id, 'user_id' => $user->id]);

    return Student::factory()->create(['school_id' => $admin->school_id, 'classroom_id' => $classroom->id, 'parent_profile_id' => $parent->id]);
}

test('account exports use the selected class and accept slug accounts as ready', function () {
    $admin = createAdminUser();
    $kelas = Classroom::factory()->create(['school_id' => $admin->school_id]);
    $other = Classroom::factory()->create(['school_id' => $admin->school_id]);
    accountSheetStudent($admin, $kelas, 'ibu-sari@tyas.app');
    accountSheetStudent($admin, $other, 'old@internal.app');
    $data = null;
    Event::listen('composing: pdf.akun-orang-tua', function ($view) use (&$data): void {
        $data = $view->getData();
    });

    $this->actingAs($admin)->get(route('admin.orang-tua.export-pdf', ['classroom_id' => $kelas->id]))->assertOk();
    expect($data['rows'])->toHaveCount(1);
    expect($data['rows'][0]['email'])->toBe('ibu-sari@tyas.app');
    expect($data['belumSiap'])->toBe(0);
    expect($data['classroomName'])->toBe($kelas->name);
    $this->get(route('admin.orang-tua.index'))->assertInertia(fn (Assert $page) => $page->has('classrooms', 2));
});

test('account exports reject unknown or foreign classrooms', function () {
    $admin = createAdminUser();
    $foreign = Classroom::factory()->create(['school_id' => School::factory()->create()->id]);
    foreach ([$foreign->id, 'tidak-ada'] as $id) {
        $this->actingAs($admin)->getJson(route('admin.orang-tua.export-pdf', ['classroom_id' => $id]))->assertUnprocessable()->assertJsonValidationErrors('classroom_id');
    }
});

test('portal validates the child selector even without a parent profile', function () {
    $user = User::factory()->create();
    $user->assignRole('ORANG_TUA');
    $this->actingAs($user)->getJson('/orangtua?anak[]=invalid')->assertUnprocessable()->assertJsonValidationErrors('anak');
    $this->get('/orangtua?anak=foreign')->assertForbidden();
    $this->get('/orangtua')->assertOk()->assertInertia(fn (Assert $page) => $page->where('student', null)->has('daftarAnak', 0));
});

test('slug simulation reserves duplicate names without changing accounts', function () {
    $school = School::factory()->create();
    foreach (['one', 'two'] as $number) {
        $user = User::factory()->create(['school_id' => $school->id, 'name' => 'Ibu Sari', 'email' => $number.'@internal.app']);
        $user->assignRole('ORANG_TUA');
    }
    $this->artisan('ortu:email-slug', ['--sekolah' => $school->id, '--dry-run' => true])
        ->expectsTable(['Sebelum', 'Sesudah'], [['one@internal.app', 'ibu-sari@tyas.app'], ['two@internal.app', 'ibu-sari2@tyas.app']])->assertSuccessful();
    expect(User::where('school_id', $school->id)->where('email', 'like', '%@internal.app')->count())->toBe(2);
    $this->artisan('ortu:email-slug', ['--sekolah' => $school->id])->assertSuccessful();
    expect(User::where('school_id', $school->id)->orderBy('email')->pluck('email')->all())->toBe(['ibu-sari2@tyas.app', 'ibu-sari@tyas.app']);
});

test('slug fallback uses child names and reserves soft deleted addresses', function () {
    $child = Student::factory()->create(['full_name' => 'Rani Putri']);
    $old = User::factory()->create(['email' => 'rani-putri@tyas.app']);
    $old->delete();
    expect(AlamatLoginOrangTua::untuk('08123456789', $child))->toBe('rani-putri2@tyas.app');
});

test('login works without client captcha and still throttles failed attempts', function () {
    $parent = User::factory()->create();
    $parent->assignRole('ORANG_TUA');
    $this->post('/login', ['email' => $parent->email, 'password' => 'password'])->assertRedirect('/orangtua');
    $this->post('/logout');
    $parent = User::factory()->create();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/login', ['email' => $parent->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    }
    $this->post('/login', ['email' => $parent->email, 'password' => 'wrong'])->assertTooManyRequests();
});
