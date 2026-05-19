<?php

namespace Database\Seeders;

use App\Models\School;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        School::create([
            'name' => 'SMP Nusantara',
            'slug' => 'smp-nusantara',
            'address' => 'Jl. Pendidikan No. 1',
            'city' => 'Jakarta',
            'phone' => '+6221234567',
            'email' => 'info@smpnusantara.sch.id',
            'is_active' => true,
            'settings' => [
                'check_in_start' => '06:30',
                'check_in_end' => '07:30',
                'late_threshold' => '07:00',
                'check_out_start' => '14:00',
                'check_out_end' => '16:00',
                'wa_template' => 'Yth. Bapak/Ibu :parent_name, anak Anda :student_name telah :status pada :datetime.',
            ],
        ]);

        School::create([
            'name' => 'SD Mentari',
            'slug' => 'sd-mentari',
            'address' => 'Jl. Ceria No. 10',
            'city' => 'Bandung',
            'phone' => '+6222345678',
            'email' => 'info@sdmentari.sch.id',
            'is_active' => true,
            'settings' => [
                'check_in_start' => '06:45',
                'check_in_end' => '07:15',
                'late_threshold' => '07:00',
                'check_out_start' => '12:30',
                'check_out_end' => '14:00',
                'wa_template' => 'Yth. Bapak/Ibu :parent_name, anak Anda :student_name telah :status pada :datetime.',
            ],
        ]);
    }
}
