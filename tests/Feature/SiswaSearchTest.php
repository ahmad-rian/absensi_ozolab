<?php

use App\Models\Student;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->admin = createAdminUser();

    $this->siti = Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'full_name' => 'Siti Rahmawati',
        'nis' => '10021001',
        'nisn' => '0071234567',
    ]);

    $this->budi = Student::factory()->create([
        'school_id' => $this->admin->school_id,
        'full_name' => 'Budi Santoso',
        'nis' => '10021002',
        'nisn' => '0079876543',
    ]);
});

function searchStudents(string $term): TestResponse
{
    return test()->actingAs(test()->admin)->get(route('admin.siswa.index', ['search' => $term]));
}

test('students can be found by nisn', function () {
    searchStudents('0079876543')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('students.data', 1)
            ->where('students.data.0.full_name', 'BUDI SANTOSO')
        );
});

test('a partial nisn still matches', function () {
    searchStudents('98765')
        ->assertInertia(fn ($page) => $page
            ->has('students.data', 1)
            ->where('students.data.0.nisn', '0079876543')
        );
});

test('searching by name and nis keeps working', function () {
    searchStudents('Siti')->assertInertia(fn ($page) => $page->has('students.data', 1));
    searchStudents('10021001')->assertInertia(fn ($page) => $page->has('students.data', 1));
});

/**
 * Kolom NISN di tabel daftar siswa membaca `students.data.*.nisn` apa adanya.
 * Test ini menjaga kontrak datanya: kalau suatu saat controller diberi
 * `->select([...])` demi menghemat query, kolomnya akan kosong senyap tanpa
 * error apa pun. Bukan menguji JSX-nya.
 */
test('the listing carries nisn for the column to render', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.siswa.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('students.data', 2)
            // Diurutkan berdasarkan full_name, jadi Budi lebih dulu dari Siti.
            ->where('students.data.0.nisn', '0079876543')
            ->where('students.data.1.nisn', '0071234567')
        );
});

test('a nisn from another school is not reachable', function () {
    $outsider = Student::factory()->create(['nisn' => '0070000001']);

    searchStudents('0070000001')->assertInertia(fn ($page) => $page->has('students.data', 0));

    expect($outsider->school_id)->not->toBe($this->admin->school_id);
});
