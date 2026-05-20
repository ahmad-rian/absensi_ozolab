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
                'school_name' => 'SMP Nusantara',
                'default_check_in_time' => '07:00',
                'late_threshold_time' => '07:15',
                'default_check_out_time' => '14:30',
                'timezone' => 'Asia/Jakarta',
                'whatsapp_enabled' => true,
                'notify_on_check_in' => true,
                'notify_on_check_out' => false,
                'whatsapp_template_attendance' => 'Halo Bapak/Ibu Wali, ananda {nama_siswa} ({kelas}) telah {status} di {nama_sekolah} pada {tanggal} pukul {waktu}. Terima kasih.',
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
                'school_name' => 'SD Mentari',
                'default_check_in_time' => '06:45',
                'late_threshold_time' => '07:00',
                'default_check_out_time' => '12:30',
                'timezone' => 'Asia/Jakarta',
                'whatsapp_enabled' => true,
                'notify_on_check_in' => true,
                'notify_on_check_out' => false,
                'whatsapp_template_attendance' => 'Halo Bapak/Ibu Wali, ananda {nama_siswa} ({kelas}) telah {status} di {nama_sekolah} pada {tanggal} pukul {waktu}. Terima kasih.',
            ],
        ]);
    }
}
