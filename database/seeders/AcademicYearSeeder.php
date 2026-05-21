<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        $schools = School::all();

        foreach ($schools as $school) {
            for ($startYear = 2024; $startYear <= 2039; $startYear++) {
                $endYear = $startYear + 1;

                AcademicYear::firstOrCreate(
                    [
                        'school_id' => $school->id,
                        'name' => "{$startYear}/{$endYear}",
                    ],
                    [
                        'start_date' => "{$startYear}-07-15",
                        'end_date' => "{$endYear}-06-30",
                        'is_active' => $startYear === 2025,
                    ],
                );
            }
        }
    }
}
