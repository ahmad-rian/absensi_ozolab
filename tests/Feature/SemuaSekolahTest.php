<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\CardGenerationLog;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Support\SchoolTime;

beforeEach(function () {
    $this->superAdmin = createSuperAdminUser();

    // Super admin membawa sekolahnya sendiri dengan nama acak dari factory, dan
    // seluruh tabel di halaman ini diurutkan berdasarkan nama — jadi namanya
    // dipatok supaya urutan barisnya bisa diuji.
    $this->superAdmin->school->update(['name' => 'AAA Sekolah Induk']);

    $this->satu = School::factory()->create(['name' => 'SMP Satu']);
    $this->dua = School::factory()->create(['name' => 'SMP Dua']);

    $this->siswaSatu = Student::factory()->create([
        'school_id' => $this->satu->id,
        'classroom_id' => Classroom::factory()->create(['school_id' => $this->satu->id])->id,
        'full_name' => 'Siswa Sekolah Satu',
        'nisn' => '0148651992',
    ]);

    $this->siswaDua = Student::factory()->create([
        'school_id' => $this->dua->id,
        'classroom_id' => Classroom::factory()->create(['school_id' => $this->dua->id])->id,
        'full_name' => 'Siswa Sekolah Dua',
    ]);
});

test('a school admin cannot reach the cross-school view even holding the permission', function () {
    $admin = createAdminUser();
    $admin->givePermissionTo('semua-sekolah.access');

    $this->actingAs($admin)
        ->get(route('admin.semua-sekolah'))
        ->assertForbidden();
});

test('the summary counts every school side by side', function () {
    Attendance::factory()->create([
        'school_id' => $this->satu->id,
        'student_id' => $this->siswaSatu->id,
        'attendance_date' => SchoolTime::now()->toDateString(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Terlambat,
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('admin.semua-sekolah'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/semua-sekolah/index')
            ->where('tab', 'ringkasan')
            ->where('totals.students', 2)
            ->has('summary', 3)
            ->where('summary.1.name', 'SMP Dua')
            ->where('summary.2.name', 'SMP Satu')
            ->where('summary.2.terlambat', 1)
            ->where('summary.2.students_count', 1)
        );
});

test('the student tab lists students from every school at once', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('admin.semua-sekolah', ['tab' => 'siswa']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('students.data', 2));
});

test('a search reaches a student of another school', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('admin.semua-sekolah', ['tab' => 'siswa', 'search' => '0148651992']))
        ->assertInertia(fn ($page) => $page
            ->has('students.data', 1)
            ->where('students.data.0.school', 'SMP Satu')
        );
});

/**
 * Tombol buka cepat perlu tahu ke sekolah mana konteks sesi dipindahkan. Kalau
 * `school_id` hilang dari payload, tombolnya tidak error — cuma diam saja.
 */
test('the listing carries school_id for the quick-open button', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('admin.semua-sekolah', ['tab' => 'siswa', 'search' => '0148651992']))
        ->assertInertia(fn ($page) => $page
            ->has('students.data', 1)
            ->where('students.data.0.school_id', $this->satu->id)
        );
});

test('the student tab can be narrowed to one school', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('admin.semua-sekolah', ['tab' => 'siswa', 'school_id' => $this->dua->id]))
        ->assertInertia(fn ($page) => $page
            ->has('students.data', 1)
            ->where('students.data.0.full_name', 'SISWA SEKOLAH DUA')
        );
});

test('the attendance tab recaps each school over the range', function () {
    Attendance::factory()->create([
        'school_id' => $this->dua->id,
        'student_id' => $this->siswaDua->id,
        'attendance_date' => SchoolTime::now()->toDateString(),
        'type' => AttendanceType::CheckIn,
        'status' => AttendanceStatus::Hadir,
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('admin.semua-sekolah', ['tab' => 'absensi']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('attendance', 3)
            ->where('attendance.1.name', 'SMP Dua')
            ->where('attendance.1.hadir', 1)
            ->where('attendance.1.total', 1)
            ->where('attendance.2.name', 'SMP Satu')
            ->where('attendance.2.total', 0)
        );
});

test('the card tab shows logs from every school', function () {
    CardGenerationLog::create([
        'school_id' => $this->dua->id,
        'student_id' => $this->siswaDua->id,
        'type' => 'card',
        'status' => 'completed',
        'drive_url' => 'https://drive.google.com/file/d/abc/view',
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('admin.semua-sekolah', ['tab' => 'kartu']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('cards.data', 1)
            ->where('cards.data.0.school', 'SMP Dua')
            ->where('cards.data.0.drive_url', 'https://drive.google.com/file/d/abc/view')
        );
});

test('an unknown tab falls back to the summary instead of erroring', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('admin.semua-sekolah', ['tab' => 'ngawur']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('tab', 'ringkasan'));
});

test('the view offers no way to change anything', function () {
    $writeRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'admin/semua-sekolah'))
        ->reject(fn ($route) => in_array('GET', $route->methods(), true))
        ->map(fn ($route) => $route->uri())
        ->all();

    expect($writeRoutes)->toBe([]);
});
