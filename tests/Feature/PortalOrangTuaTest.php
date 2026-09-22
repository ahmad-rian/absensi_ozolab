<?php

use App\Enums\AppModule;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\CardGenerationLog;
use App\Models\Classroom;
use App\Models\ParentProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolCardLayout;
use App\Models\Student;
use App\Models\User;
use App\Services\Import\StudentImportApplier;
use App\Services\Import\StudentImportParser;
use App\Services\ParentProfileService;
use App\Support\XlsxDownload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Reader\XLSX\Reader;

function portalFamily(array $settings = []): array
{
    $school = School::factory()->create(['settings' => $settings]);
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole('ORANG_TUA');
    $parent = ParentProfile::factory()->create(['school_id' => $school->id, 'user_id' => $user->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'parent_profile_id' => $parent->id]);

    return [$user, $student, $school];
}

test('parents login to their portal and admins keep their dashboard', function () {
    [$parent] = portalFamily();
    $this->post('/login', ['email' => $parent->email, 'password' => 'password'])->assertRedirect('/orangtua');
    $this->get('/orangtua')->assertOk()->assertInertia(fn (Assert $page) => $page->component('orangtua/index')->has('children', 1));
    $this->get('/')->assertRedirect('/orangtua');
    $this->get('/admin/dashboard')->assertRedirect('/orangtua');
    $this->post('/logout');
    $admin = createAdminUser();
    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin/dashboard');
});

test('parents cannot access another child on any portal endpoint', function (string $suffix) {
    [$parent, $own, $school] = portalFamily();
    $other = Student::factory()->create(['school_id' => $school->id]);
    $this->actingAs($parent)->get('/orangtua/anak/'.$other->id.$suffix)->assertForbidden();
    $this->get('/orangtua/anak/'.$own->id)->assertOk();
})->with(['', '/laporan', '/galeri', '/unduh/foto']);

test('password enforcement covers workspaces and settings and rejects the default password', function () {
    [$parent] = portalFamily();
    $parent->update(['must_change_password' => true]);
    foreach (['/orangtua', '/settings/profile', '/admin/dashboard', '/kartu-bebas'] as $url) {
        $this->actingAs($parent)->get($url)->assertRedirect('/ganti-password');
    }
    $this->get('/ganti-password')->assertOk();
    $this->put('/ganti-password', ['password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasErrors('password');
    $this->put('/ganti-password', ['password' => 'RahasiaBaru!2026', 'password_confirmation' => 'RahasiaBaru!2026'])->assertRedirect('/orangtua');
    expect($parent->fresh()->must_change_password)->toBeFalse();
    expect(Hash::check('RahasiaBaru!2026', $parent->fresh()->password))->toBeTrue();
    $this->get('/orangtua')->assertOk();
});

test('password command only changes parents in the selected school and dry run changes nothing', function () {
    [$parent, , $school] = portalFamily();
    [$other] = portalFamily();
    $admin = createAdminUser();
    $teacher = User::factory()->create(['school_id' => $school->id]);
    $teacher->assignRole('GURU');
    $super = createSuperAdminUser();
    $hashes = [$parent->password, $other->password, $admin->password, $teacher->password, $super->password];
    $this->artisan('ortu:setel-password', ['--sekolah' => $school->id, '--dry-run' => true])->assertSuccessful();
    expect($parent->fresh()->password)->toBe($hashes[0]);
    // Tanpa `--force` perintahnya bertanya lebih dulu, dan jawaban `tidak`
    // harus meninggalkan hash lama utuh.
    $this->artisan('ortu:setel-password', ['--sekolah' => $school->id])
        ->expectsConfirmation('Sandi lama akun di atas hilang permanen. Lanjutkan?', 'no')
        ->assertFailed();
    expect($parent->fresh()->password)->toBe($hashes[0]);

    $this->artisan('ortu:setel-password', ['--sekolah' => $school->id, '--force' => true])->assertSuccessful();
    expect($parent->fresh()->must_change_password)->toBeTrue();
    expect(Hash::check('password', $parent->fresh()->password))->toBeTrue();
    foreach ([$other, $admin, $teacher, $super] as $index => $user) {
        expect($user->fresh()->password)->toBe($hashes[$index + 1]);
    }
});

test('disabled prayer panels and downloads are unavailable', function () {
    [$parent, $student] = portalFamily(['prayer_dhuha_enabled' => false, 'prayer_enabled' => true]);
    $this->actingAs($parent)->get('/orangtua/anak/'.$student->id)->assertOk()->assertInertia(fn (Assert $page) => $page->missing('panels.dhuha')->has('panels.dzuhur'));
    $this->get('/orangtua/anak/'.$student->id.'/laporan?download=1&jenis=dhuha')->assertForbidden();
    $this->get('/orangtua/anak/'.$student->id.'/laporan?download=1&jenis=semuanya')->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get('/orangtua/anak/'.$student->id.'?start_date[]=bad')->assertSessionHasErrors('start_date');
});

test('renaming permissions preserves custom role and direct user grants', function () {
    $permission = Permission::where('name', 'beranda.access')->firstOrFail();
    $role = Role::create(['name' => 'CUSTOM', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $role->givePermissionTo($permission);
    $user->givePermissionTo($permission);
    $id = $permission->id;
    $permission->update(['name' => 'dashboard.access']);
    $migration = require glob(database_path('migrations/*rename_permissions_ke_bahasa_indonesia.php'))[0];
    $migration->up();
    $migration->up();
    expect(Permission::find($id)->name)->toBe('beranda.access');
    expect($role->fresh()->hasPermissionTo('beranda.access'))->toBeTrue();
    expect($user->fresh()->hasDirectPermission('beranda.access'))->toBeTrue();
});

test('all literal route permissions exist in the module registry', function () {
    foreach (['web.php', 'api.php'] as $file) {
        preg_match_all('/permission:([a-z-]+\.access)/', file_get_contents(base_path('routes/'.$file)), $matches);
        foreach ($matches[1] as $permission) {
            expect(AppModule::permissions())->toContain($permission);
        }
    }
});

test('real parent email upgrades placeholder account and conflicts have readable errors', function () {
    $school = School::factory()->create();
    $service = app(ParentProfileService::class);
    $profile = $service->findOrCreateFromRegistration($school->id, 'Ibu Sari', '628123456789');
    $same = $service->findOrCreateFromRegistration($school->id, 'Ibu Sari', '628123456789', email: 'sari@example.com');
    expect($same->id)->toBe($profile->id);
    expect($same->user->email)->toBe('sari@example.com');
    $otherSchool = School::factory()->create();
    expect(fn () => $service->findOrCreateFromRegistration($otherSchool->id, 'Ibu Sari', '628123456789', email: 'sari@example.com'))
        ->toThrow(ValidationException::class, 'Email sudah dipakai akun orang tua di sekolah lain.');
});

test('combined xlsx contains only enabled sheets', function (array $settings, array $names) {
    $admin = createAdminUser();
    $admin->school->update(['settings' => $settings]);
    Student::factory()->create(['school_id' => $admin->school_id]);
    $response = $this->actingAs($admin)->get('/admin/laporan/export?jenis=semuanya')->assertOk();
    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $actual = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        $actual[] = $sheet->getName();
    }
    $reader->close();
    expect($actual)->toBe($names);
})->with([
    [['prayer_enabled' => true, 'prayer_dhuha_enabled' => true], ['Ringkasan', 'Absensi Sekolah', 'Dhuha', 'Dzuhur']],
    [['prayer_enabled' => false, 'prayer_dhuha_enabled' => false], ['Ringkasan', 'Absensi Sekolah']],
]);

test('bulk cards use sidebar school and ignore stale query school', function () {
    $super = createSuperAdminUser();
    $active = School::factory()->create();
    Student::factory()->create(['school_id' => $active->id]);
    $this->actingAs($super)->withSession(['current_school_id' => $active->id])->get('/admin/generate-kartu?school_id='.$super->school_id)
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.school_id', $active->id)->where('ringkasan.total', 1));
});

test('prayer schedule saves times without changing flags and rejects overlapping windows', function () {
    $admin = createAdminUser();
    $admin->school->update(['settings' => ['prayer_enabled' => true, 'prayer_dhuha_enabled' => true]]);
    $times = ['prayer_dhuha_start' => '07:30', 'prayer_dhuha_end' => '09:00', 'prayer_start' => '11:00', 'prayer_end' => '13:00'];
    $this->actingAs($admin)->put('/admin/jadwal-absensi/sholat', $times)->assertSessionHasNoErrors();
    expect($admin->school->fresh()->getSetting('prayer_start'))->toBe('11:00');
    expect($admin->school->fresh()->getSetting('prayer_enabled'))->toBeTrue();
    $this->put('/admin/jadwal-absensi/sholat', [...$times, 'prayer_dhuha_end' => '11:00'])->assertSessionHasErrors('prayer_dhuha_end');
});

test('parent email import links accounts and rolls back rejected rows', function () {
    $school = School::factory()->create();
    Classroom::factory()->create(['school_id' => $school->id, 'name' => '7A']);
    $csv = UploadedFile::fake()->createWithContent('orangtua.csv', "NISN,NIS,Nama,Nama Orang Tua,No HP,Email Ortu,JK,Kelas\n9100000001,91001,Anak Sari,Ibu Sari,081234567899,sari@example.com,L,7A\n");
    $parsed = app(StudentImportParser::class)->parse($csv->getPathname(), 'csv', $school->id);
    expect($parsed['rows'][0]['data']['parent_email'])->toBe('sari@example.com');
    $applier = app(StudentImportApplier::class);
    $result = $applier->apply($parsed['rows'], $school->id);
    expect($result['created'])->toBe(1);
    $student = Student::where('nis', '91001')->firstOrFail();
    expect($student->parentProfile->user->email)->toBe('sari@example.com');
    expect($student->parentProfile->user->must_change_password)->toBeTrue();
    $other = School::factory()->create();
    $result = $applier->apply($parsed['rows'], $other->id);
    expect($result['failed'])->toBe(1);
    expect($result['errors'][0]['message'])->toContain('sekolah lain');
    expect(Student::where('school_id', $other->id)->count())->toBe(0);
    expect(User::where('school_id', $other->id)->count())->toBe(0);
});

test('gallery returns latest successful card per layout and protects downloads', function () {
    [$parent, $student, $school] = portalFamily();
    Storage::fake('public');
    $student->update(['photo_path' => 'photos/own.jpg']);
    Storage::disk('public')->put('photos/own.jpg', 'photo');
    $layout = SchoolCardLayout::create(['school_id' => $school->id, 'name' => 'OSIS depan', 'type' => 'osis', 'layout_config' => []]);
    $base = ['school_id' => $school->id, 'student_id' => $student->id, 'school_card_layout_id' => $layout->id, 'type' => 'card', 'status' => 'completed', 'file_path' => 'photos/own.jpg'];
    $old = CardGenerationLog::create($base);
    $old->forceFill(['created_at' => now()->subDays(2)])->save();
    $latest = CardGenerationLog::create($base);
    $latest->forceFill(['created_at' => now()->subDay()])->save();
    CardGenerationLog::create([...$base, 'status' => 'failed']);
    $this->actingAs($parent)->get('/orangtua/anak/'.$student->id.'/galeri')->assertOk()->assertInertia(fn (Assert $page) => $page->has('cards', 1)->where('cards.0.id', $latest->id));

    $this->get('/orangtua/anak/'.$student->id.'/unduh/foto')->assertDownload();
    $foreign = Student::factory()->create(['school_id' => $school->id]);
    $log = CardGenerationLog::create(['school_id' => $school->id, 'student_id' => $foreign->id, 'type' => 'card', 'status' => 'completed', 'file_path' => 'photos/own.jpg']);
    $this->get('/orangtua/anak/'.$student->id.'/unduh/'.$log->id)->assertNotFound();
});

test('xlsx sheet names are bounded and invalid characters are rejected', function () {
    $response = XlsxDownload::sheets('test.xlsx', [str_repeat('a', 40) => ['header' => ['Nama'], 'rows' => [['=1+1']]]]);
    $reader = new Reader;
    $reader->open($response->getFile()->getPathname());
    foreach ($reader->getSheetIterator() as $sheet) {
        expect(mb_strlen($sheet->getName()))->toBe(31);
        $rows = iterator_to_array($sheet->getRowIterator());
        expect(end($rows)->toArray())->toBe(['=1+1']);
    }
    $reader->close();
    expect(fn () => XlsxDownload::sheets('test.xlsx', ['bad/name' => ['header' => [], 'rows' => []]]))->toThrow(InvalidArgumentException::class);
});

test('parent list filters accounts without login email', function () {
    $admin = createAdminUser();
    foreach (['real@example.com', 'parent-123@internal.app'] as $email) {
        $user = User::factory()->create(['school_id' => $admin->school_id, 'email' => $email]);
        ParentProfile::factory()->create(['school_id' => $admin->school_id, 'user_id' => $user->id]);
    }
    $this->actingAs($admin)->get('/admin/orang-tua?belum_login=1')->assertOk()->assertInertia(fn (Assert $page) => $page->has('parents.data', 1)->where('parents.data.0.user.email', 'parent-123@internal.app'));
});

test('student exports contain all records beyond the thirty row screen limit', function () {
    $admin = createAdminUser();
    $student = Student::factory()->create(['school_id' => $admin->school_id]);
    for ($day = 1; $day <= 31; $day++) {
        Attendance::factory()->create([
            'school_id' => $admin->school_id, 'student_id' => $student->id,
            'attendance_date' => sprintf('2026-08-%02d', $day),
            'type' => AttendanceType::CheckIn,
            'status' => AttendanceStatus::Hadir,
        ]);
    }
    $response = $this->actingAs($admin)->get(route('admin.siswa.laporan.absensi.xlsx', [$student, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31']))->assertOk();
    $rows = collect(xlsxRows($response))->filter(fn ($row) => preg_match('/^\d{2} Aug 2026$/', $row[0] ?? ''));
    expect($rows)->toHaveCount(31);
    $this->get(route('admin.siswa.laporan.semuanya.pdf', $student))->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('password command preserves admin accounts with an additional parent role', function () {
    $admin = createAdminUser();
    $admin->assignRole('ORANG_TUA');
    $hash = $admin->password;
    $this->artisan('ortu:setel-password', ['--force' => true])->assertSuccessful();
    expect($admin->fresh()->password)->toBe($hash);
    expect($admin->fresh()->must_change_password)->toBeFalse();
});
