<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\ParentRelation;
use App\Enums\Religion;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ParentAndStudentSeeder extends Seeder
{
    /** @var list<string> */
    private array $maleNames = [
        'Bagus', 'Rizky', 'Fajar', 'Dimas', 'Andi', 'Arif', 'Bima', 'Cahyo',
        'Deni', 'Eko', 'Fauzan', 'Galih', 'Hendra', 'Irfan', 'Joko', 'Kurnia',
        'Lukman', 'Muhammad', 'Naufal', 'Oscar', 'Prasetyo', 'Rafi', 'Surya',
        'Teguh', 'Umar', 'Vino', 'Wahyu', 'Yoga', 'Zaki', 'Alif',
    ];

    /** @var list<string> */
    private array $femaleNames = [
        'Citra', 'Dewi', 'Putri', 'Sari', 'Anisa', 'Bunga', 'Dian', 'Eka',
        'Fitri', 'Gita', 'Hani', 'Indah', 'Jasmine', 'Kartika', 'Laras',
        'Melati', 'Nadia', 'Oktavia', 'Puspita', 'Ratna', 'Sinta', 'Tiara',
        'Umi', 'Vina', 'Wulan', 'Yuni', 'Zahra', 'Aulia', 'Bella', 'Cantika',
    ];

    /** @var list<string> */
    private array $lastNames = [
        'Pratama', 'Wijaya', 'Saputra', 'Hidayat', 'Nugroho', 'Santoso',
        'Kusuma', 'Ramadhan', 'Putra', 'Permana', 'Suherman', 'Wibowo',
        'Gunawan', 'Setiawan', 'Rahmawati', 'Lestari', 'Handayani', 'Purnama',
        'Suryadi', 'Hartono', 'Firmansyah', 'Septian', 'Maulana', 'Fitriani',
    ];

    public function run(): void
    {
        $school = School::where('slug', 'smp-nusantara')->firstOrFail();
        $classrooms = Classroom::all();
        $password = Hash::make('password');
        $studentCount = 0;
        $parentIndex = 0;

        // Target: 200 orang tua → ~300 siswa
        $totalParents = 200;
        $parentsData = [];

        for ($i = 0; $i < $totalParents; $i++) {
            $parentIndex++;
            $relation = fake()->randomElement(ParentRelation::cases());
            $isFather = $relation === ParentRelation::Ayah;
            $parentFirstName = $isFather
                ? $this->maleNames[array_rand($this->maleNames)]
                : $this->femaleNames[array_rand($this->femaleNames)];
            $parentLastName = $this->lastNames[array_rand($this->lastNames)];
            $fullName = "{$parentFirstName} {$parentLastName}";

            $user = User::create([
                'name' => $fullName,
                'email' => "orangtua{$parentIndex}@sekolah.test",
                'email_verified_at' => now(),
                'password' => $password,
                'phone' => '+628'.fake()->numerify('##########'),
                'is_active' => true,
                'school_id' => $school->id,
            ]);
            $user->assignRole(UserRole::OrangTua->value);

            $parentProfile = ParentProfile::create([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'nik' => fake()->numerify('################'),
                'whatsapp_number' => '+628'.fake()->numerify('##########'),
                'relation' => $relation,
                'occupation' => fake('id_ID')->jobTitle(),
                'address' => fake('id_ID')->address(),
                'city' => fake('id_ID')->city(),
            ]);

            $parentsData[] = $parentProfile;
        }

        // Distribusi ~300 siswa: 50% orang tua mendapat 2 anak, 50% mendapat 1
        $classroomIds = $classrooms->pluck('id')->toArray();

        foreach ($parentsData as $index => $parent) {
            $childCount = $index < 100 ? 2 : 1; // 100 orang tua pertama mendapat 2 anak

            for ($c = 0; $c < $childCount; $c++) {
                $studentCount++;
                $gender = fake()->randomElement(Gender::cases());
                $firstName = $gender === Gender::LakiLaki
                    ? $this->maleNames[array_rand($this->maleNames)]
                    : $this->femaleNames[array_rand($this->femaleNames)];
                $lastName = $this->lastNames[array_rand($this->lastNames)];

                $classroomId = $classroomIds[array_rand($classroomIds)];
                $classroom = $classrooms->find($classroomId);
                $gradeDigit = $classroom->grade_level;
                $nis = sprintf('%04d%d%03d', 2025, $gradeDigit, $studentCount);

                Student::create([
                    'school_id' => $school->id,
                    'parent_profile_id' => $parent->id,
                    'classroom_id' => $classroomId,
                    'nis' => $nis,
                    'nisn' => fake()->unique()->numerify('##########'),
                    'full_name' => "{$firstName} {$lastName}",
                    'gender' => $gender,
                    'religion' => fake()->randomElement(Religion::cases()),
                    'birth_place' => fake('id_ID')->city(),
                    'birth_date' => fake()->dateTimeBetween('-15 years', '-12 years'),
                    'address' => fake('id_ID')->address(),
                    'qr_token' => Str::random(64),
                    'qr_issued_at' => now(),
                    'is_active' => true,
                ]);
            }
        }
    }
}
