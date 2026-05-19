<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('slug', 'smp-nusantara')->firstOrFail();

        AcademicYear::create([
            'school_id' => $school->id,
            'name' => '2024/2025',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-30',
            'is_active' => false,
        ]);

        AcademicYear::create([
            'school_id' => $school->id,
            'name' => '2025/2026',
            'start_date' => '2025-07-14',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);
    }
}
