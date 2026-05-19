<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'school_name',
                'value' => json_encode('SMP Nusantara'),
                'description' => 'Nama sekolah yang ditampilkan di aplikasi',
            ],
            [
                'key' => 'school_logo',
                'value' => json_encode(null),
                'description' => 'Path logo sekolah',
            ],
            [
                'key' => 'timezone',
                'value' => json_encode('Asia/Jakarta'),
                'description' => 'Timezone aplikasi',
            ],
            [
                'key' => 'default_check_in_time',
                'value' => json_encode('07:00'),
                'description' => 'Jam mulai check-in default',
            ],
            [
                'key' => 'late_threshold_time',
                'value' => json_encode('07:15'),
                'description' => 'Setelah jam ini status menjadi TERLAMBAT',
            ],
            [
                'key' => 'default_check_out_time',
                'value' => json_encode('14:30'),
                'description' => 'Jam pulang default',
            ],
            [
                'key' => 'whatsapp_template_attendance',
                'value' => json_encode('Halo Bapak/Ibu Wali, ananda {nama_siswa} ({kelas}) telah {status} di {nama_sekolah} pada {tanggal} pukul {waktu}. Terima kasih.'),
                'description' => 'Template pesan WhatsApp notifikasi absensi',
            ],
            [
                'key' => 'whatsapp_enabled',
                'value' => json_encode(true),
                'description' => 'Master switch notifikasi WhatsApp',
            ],
            [
                'key' => 'notify_on_check_in',
                'value' => json_encode(true),
                'description' => 'Kirim WA saat siswa check-in',
            ],
            [
                'key' => 'notify_on_check_out',
                'value' => json_encode(false),
                'description' => 'Kirim WA saat siswa check-out',
            ],
            [
                'key' => 'holiday_calendar',
                'value' => json_encode([]),
                'description' => 'Array tanggal libur nasional/sekolah',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }
    }
}
