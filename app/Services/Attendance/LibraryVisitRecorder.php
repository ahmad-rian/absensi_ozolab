<?php

namespace App\Services\Attendance;

use App\Enums\SchoolFeature;
use App\Models\LibraryVisit;
use App\Models\Student;
use App\Support\SchoolFeatures;
use App\Support\SchoolTime;
use Carbon\Carbon;

/**
 * Kunjungan perpustakaan — satu kunjungan punya jam masuk dan jam keluar, dan
 * siswa boleh keluar-masuk berkali-kali dalam sehari.
 *
 * Satu tap melakukan dua hal berbeda tergantung keadaan: membuka kunjungan baru,
 * atau menutup kunjungan yang masih terbuka. Petugas tidak perlu memilih mode,
 * sama seperti gerbang yang menentukan masuk/pulang dari jam.
 *
 * Tidak ada jendela jam buka-tutup: perpustakaan melayani sepanjang hari sekolah,
 * dan satu pengaturan lagi hanya menambah hal yang bisa lupa diisi. Yang menjaga
 * adalah keberadaan jadwal sekolah hari itu.
 *
 * Sengaja tidak menulis ke `attendances` dan tidak memancarkan event: statistik
 * kehadiran sekolah harus tetap bersih, dan orang tua tidak perlu dikirimi pesan
 * tiap anaknya masuk perpustakaan.
 */
class LibraryVisitRecorder
{
    public const ACTION_ENTER = 'masuk';

    public const ACTION_EXIT = 'keluar';

    /**
     * Tap dalam rentang ini setelah peristiwa terakhir dianggap tidak disengaja.
     *
     * Tanpa penjaga ini, kartu yang tersenggol dua kali akan membuka lalu langsung
     * menutup kunjungan dalam hitungan detik — dan laporan durasinya jadi sampah.
     */
    private const COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly ScheduleResolver $scheduleResolver,
    ) {}

    /**
     * @return array{success: bool, visit: ?LibraryVisit, message: string, action: ?string}
     */
    public function record(
        Student $student,
        ?string $deviceId = null,
        ?Carbon $timestamp = null,
    ): array {
        $timestamp = $timestamp ? SchoolTime::toLocal($timestamp) : SchoolTime::now();
        $date = $timestamp->toDateString();

        $school = $student->school;

        if (! $school) {
            return $this->fail('Siswa belum terhubung ke sekolah mana pun.');
        }

        // Guard fitur di recorder, bukan middleware: `abort(403)` menghasilkan
        // halaman HTML sedangkan konsol scan hanya membaca {success, message}.
        if (SchoolFeatures::for($school)->disabled(SchoolFeature::KunjunganPerpustakaan)) {
            return $this->fail('Kunjungan perpustakaan belum diaktifkan untuk sekolah ini.');
        }

        if (! $this->scheduleResolver->isSchoolDay($student, $timestamp)) {
            return $this->fail('Tidak ada jadwal aktif untuk hari ini.');
        }

        $open = $this->openVisit($student, $date);

        if ($tooSoon = $this->tooSoonAfterLastEvent($student, $date, $timestamp)) {
            return $this->fail($tooSoon);
        }

        if ($open) {
            $open->update(['exited_at' => $timestamp]);

            $duration = $open->durationMinutes();

            return [
                'success' => true,
                'visit' => $open->refresh(),
                'message' => 'Keluar perpustakaan tercatat'.($duration !== null ? ', lama kunjungan '.$duration.' menit' : '').'.',
                'action' => self::ACTION_EXIT,
            ];
        }

        $visit = LibraryVisit::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'visit_date' => $date,
            'entered_at' => $timestamp,
            'device_id' => $deviceId,
        ]);

        return [
            'success' => true,
            'visit' => $visit,
            'message' => 'Masuk perpustakaan tercatat.',
            'action' => self::ACTION_ENTER,
        ];
    }

    private function openVisit(Student $student, string $date): ?LibraryVisit
    {
        return LibraryVisit::where('student_id', $student->id)
            ->whereDate('visit_date', $date)
            ->stillInside()
            ->latest('entered_at')
            ->first();
    }

    /**
     * Pesan penolakan bila tap terlalu berdekatan dengan peristiwa terakhir,
     * atau null bila boleh diteruskan.
     */
    private function tooSoonAfterLastEvent(Student $student, string $date, Carbon $timestamp): ?string
    {
        $last = LibraryVisit::where('student_id', $student->id)
            ->whereDate('visit_date', $date)
            ->latest('entered_at')
            ->first();

        if (! $last) {
            return null;
        }

        $lastEvent = $last->exited_at ?? $last->entered_at;

        if ($lastEvent->diffInSeconds($timestamp, absolute: true) >= self::COOLDOWN_SECONDS) {
            return null;
        }

        return 'Baru saja tercatat pukul '.$lastEvent->format('H:i').'. Tunggu sebentar sebelum menempel lagi.';
    }

    /**
     * @return array{success: false, visit: null, message: string, action: null}
     */
    private function fail(string $message): array
    {
        return ['success' => false, 'visit' => null, 'message' => $message, 'action' => null];
    }
}
