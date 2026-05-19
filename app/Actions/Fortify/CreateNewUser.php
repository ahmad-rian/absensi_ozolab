<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Gender;
use App\Enums\ParentRelation;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'phone' => ['required', 'string', 'max:20'],
            'relation' => ['required', Rule::enum(ParentRelation::class)],
            'school_id' => ['nullable', 'exists:schools,id'],

            'students' => ['required', 'array', 'min:1'],
            'students.*.full_name' => ['required', 'string', 'max:255'],
            'students.*.gender' => ['required', Rule::enum(Gender::class)],
            'students.*.classroom_id' => ['required', 'exists:classrooms,id'],
            'students.*.nis' => ['nullable', 'string', 'max:20'],
            'students.*.birth_place' => ['nullable', 'string', 'max:255'],
            'students.*.birth_date' => ['nullable', 'date'],
            'students.*.address' => ['nullable', 'string', 'max:1000'],
        ], [
            'students.required' => 'Minimal satu data siswa harus diisi.',
            'students.min' => 'Minimal satu data siswa harus diisi.',
            'students.*.full_name.required' => 'Nama lengkap siswa wajib diisi.',
            'students.*.gender.required' => 'Jenis kelamin siswa wajib dipilih.',
            'students.*.classroom_id.required' => 'Kelas siswa wajib dipilih.',
            'students.*.classroom_id.exists' => 'Kelas yang dipilih tidak valid.',
            'phone.required' => 'Nomor WhatsApp wajib diisi.',
            'relation.required' => 'Hubungan dengan siswa wajib dipilih.',
        ])->validate();

        return DB::transaction(function () use ($input) {
            $schoolId = $input['school_id'] ?? null;

            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'phone' => $input['phone'],
                'school_id' => $schoolId,
            ]);

            $user->assignRole(UserRole::OrangTua->value);

            $parentProfile = $user->parentProfile()->create([
                'whatsapp_number' => $input['phone'],
                'relation' => $input['relation'],
                'school_id' => $schoolId,
            ]);

            foreach ($input['students'] as $studentData) {
                $parentProfile->students()->create([
                    'full_name' => $studentData['full_name'],
                    'gender' => $studentData['gender'],
                    'classroom_id' => $studentData['classroom_id'],
                    'school_id' => $schoolId,
                    'nis' => $studentData['nis'] ?: $this->generateNis($studentData['classroom_id']),
                    'birth_place' => $studentData['birth_place'] ?? null,
                    'birth_date' => $studentData['birth_date'] ?? null,
                    'address' => $studentData['address'] ?? null,
                    'qr_token' => Str::random(64),
                    'qr_issued_at' => now(),
                    'is_active' => true,
                ]);
            }

            return $user;
        });
    }

    private function generateNis(int $classroomId): string
    {
        $classroom = \App\Models\Classroom::find($classroomId);
        $year = date('Y');
        $grade = $classroom?->grade_level ?? 7;
        $count = \App\Models\Student::where('classroom_id', $classroomId)->count() + 1;

        return sprintf('%04d%d%03d', $year, $grade, $count);
    }
}
