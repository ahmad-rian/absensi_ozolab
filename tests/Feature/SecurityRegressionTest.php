<?php

use App\Enums\AppModule;
use App\Enums\SchoolChannelType;
use App\Enums\SchoolFeature;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use App\Models\Student;
use App\Models\User;

/**
 * Satu test per temuan audit yang punya jalur serangan konkret.
 * Rujukannya ada di SECURITY-AUDIT.md.
 */
beforeEach(function () {
    $this->admin = createAdminUser();
    $this->otherSchool = School::factory()->create();
});

// ---------------------------------------------------------------- C-1

test('C-1: a school admin cannot grant themselves system permissions', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => 'ADMIN',
            'extra_permissions' => ['schools.access'],
        ])
        ->assertForbidden();

    expect($this->admin->fresh()->can('schools.access'))->toBeFalse();
});

test('C-1: a school admin cannot grant system permissions to a colleague either', function () {
    $colleague = User::factory()->create(['school_id' => $this->admin->school_id]);
    $colleague->assignRole('GURU');

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $colleague), [
            'name' => $colleague->name,
            'email' => $colleague->email,
            'role' => 'GURU',
            'extra_permissions' => ['schools.access', 'impersonate.access'],
        ])
        ->assertSessionHasErrors('extra_permissions.0');

    expect($colleague->fresh()->can('schools.access'))->toBeFalse();
});

test('C-1: a super admin may still grant anything', function () {
    $superAdmin = createSuperAdminUser();
    $target = User::factory()->create(['school_id' => $superAdmin->school_id]);
    $target->assignRole('ADMIN');

    $this->actingAs($superAdmin)
        ->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'ADMIN',
            'extra_permissions' => ['schools.access'],
        ])
        ->assertSessionHasNoErrors();

    expect($target->fresh()->can('schools.access'))->toBeTrue();
});

// ---------------------------------------------------------------- H-1, H-4, M-1

test('H-1/H-4/M-1: system modules are super-admin only even with the permission', function () {
    $this->admin->givePermissionTo(['schools.access', 'notification-gateways.access', 'roles.access']);

    $this->actingAs($this->admin)->get(route('admin.schools.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.notification-gateways'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.roles'))->assertForbidden();
    $this->actingAs($this->admin)
        ->post(route('admin.schools.regenerate-scanner', $this->otherSchool))
        ->assertForbidden();

    expect($this->otherSchool->fresh()->scanner_token)->toBe($this->otherSchool->scanner_token);
});

// ---------------------------------------------------------------- H-2

test('H-2: a role without siswa.access cannot read the student API', function () {
    $parent = User::factory()->create(['school_id' => $this->admin->school_id]);
    $parent->assignRole('ORANG_TUA');

    Student::factory()->create(['school_id' => $this->admin->school_id]);

    $this->actingAs($parent)->getJson('/api/students')->assertForbidden();
    $this->actingAs($parent)->getJson('/api/schools')->assertForbidden();

    // Yang berhak tetap bisa.
    $this->actingAs($this->admin)->getJson('/api/students')->assertOk();
});

// ---------------------------------------------------------------- H-3

test('H-3: impersonation is refused for anyone but a super admin', function () {
    $this->admin->givePermissionTo('impersonate.access');

    $victim = User::factory()->create(['school_id' => $this->otherSchool->id]);
    $victim->assignRole('GURU');

    $this->actingAs($this->admin)
        ->post(route('admin.users.impersonate', $victim))
        ->assertForbidden();

    expect(auth()->id())->toBe($this->admin->id);
});

// ---------------------------------------------------------------- H-5

test('H-5: the telegram webhook rejects a request when no secret is stored', function () {
    SchoolNotificationChannel::create([
        'school_id' => $this->otherSchool->id,
        'channel' => SchoolChannelType::Telegram,
        'is_active' => true,
        'settings' => ['bot_token' => 'x'],
    ]);

    // Tanpa header sama sekali — dulu lolos karena `null !== null` bernilai false.
    $this->postJson(route('telegram.webhook', $this->otherSchool), [
        'message' => ['chat' => ['id' => '999'], 'from' => ['id' => 1]],
    ])->assertForbidden();

    $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => ''])
        ->postJson(route('telegram.webhook', $this->otherSchool), ['message' => []])
        ->assertForbidden();
});

test('H-5: the webhook accepts the correct secret', function () {
    SchoolNotificationChannel::create([
        'school_id' => $this->otherSchool->id,
        'channel' => SchoolChannelType::Telegram,
        'is_active' => true,
        'settings' => ['bot_token' => 'x', 'webhook_secret' => 'rahasia-benar'],
    ]);

    $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'rahasia-benar'])
        ->postJson(route('telegram.webhook', $this->otherSchool), ['message' => []])
        ->assertOk();
});

// ---------------------------------------------------------------- M-4

/**
 * Ekspor kini XLSX, dan formatnya TIDAK memberi kekebalan gratis:
 * `OpenSpout\Common\Entity\Cell::fromValue()` mengubah string berawalan `=`
 * menjadi `FormulaCell`. `XlsxDownload` sengaja membangun `StringCell` sendiri.
 *
 * Elemen `<f>` di sheet XML adalah bukti pastinya — pembaca OpenSpout hanya
 * mengembalikan nilai sel, jadi memeriksa nilainya saja tidak akan menangkap
 * kalau sel itu ternyata rumus.
 */
test('M-4: a formula-looking name never becomes a formula cell in the export', function () {
    $student = Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'full_name' => '=HYPERLINK("https://evil.tld","Klik")',
        'nis' => '20250001',
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.siswa.laporan.absensi.xlsx', $student));

    $response->assertOk();

    expect(xlsxSheetXml($response))->not->toContain('<f>')
        ->and(collect(xlsxRows($response))->flatten()->all())
        ->toContain('=HYPERLINK("HTTPS://EVIL.TLD","KLIK")');
});

// ---------------------------------------------------------------- Hardening

test('security headers are present on every web response', function () {
    $response = $this->get('/daftar');

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});

test('the public registration form only accepts a safe name', function () {
    $school = School::factory()->create(['is_active' => true]);

    $this->post('/daftar', [
        'school_id' => $school->id,
        'full_name' => '=HYPERLINK("https://evil.tld","klik")',
        'gender' => 'LAKI_LAKI',
    ])->assertSessionHasErrors('full_name');
});

// ---------------------------------------------------------------- Arsitektur

test('every system-module route is guarded by the super-admin middleware', function () {
    $systemPermissions = [
        'schools.access',
        'roles.access',
        'notification-gateways.access',
        'card-forms.access',
        'kartu-bebas.access',
        'impersonate.access',
        'semua-sekolah.access',
    ];

    $unguarded = collect(app('router')->getRoutes()->getRoutes())
        ->filter(function ($route) use ($systemPermissions) {
            $middleware = $route->gatherMiddleware();

            $usesSystemPermission = collect($middleware)->contains(
                fn ($m) => is_string($m) && str_starts_with($m, 'permission:')
                    && in_array(substr($m, strlen('permission:')), $systemPermissions, true),
            );

            // `gatherMiddleware()` mengembalikan alias apa adanya, bukan
            // nama kelasnya — cek keduanya supaya tidak rapuh.
            $guarded = in_array('super-admin', $middleware, true)
                || in_array(EnsureSuperAdmin::class, $middleware, true);

            return $usesSystemPermission && ! $guarded;
        })
        ->map(fn ($route) => $route->uri())
        ->values()
        ->all();

    expect($unguarded)->toBe([]);
});

test('every route of a feature-owned module also carries the feature middleware', function () {
    // Cermin dari test di atas: rute modul baru yang lupa dipasangi `feature:`
    // akan diam-diam mengabaikan saklar di halaman Pengaturan.
    $expected = [];

    foreach (AppModule::cases() as $module) {
        $feature = SchoolFeature::forModule($module);

        if ($feature !== null) {
            $expected['permission:'.$module->permission()] = 'feature:'.$feature->value;
        }
    }

    $unguarded = collect(app('router')->getRoutes()->getRoutes())
        ->filter(function ($route) use ($expected) {
            $middleware = collect($route->gatherMiddleware())->filter(fn ($m) => is_string($m));

            $needed = $middleware
                ->map(fn ($m) => $expected[$m] ?? null)
                ->filter()
                ->values();

            return $needed->isNotEmpty()
                && $needed->contains(fn ($feature) => ! $middleware->contains($feature));
        })
        ->map(fn ($route) => $route->uri())
        ->values()
        ->all();

    expect($unguarded)->toBe([]);
});
