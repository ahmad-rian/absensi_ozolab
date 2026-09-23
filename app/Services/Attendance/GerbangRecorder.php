<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceType;
use App\Enums\SchoolFeature;
use App\Models\Attendance;
use App\Models\PrayerAttendance;
use App\Models\School;
use App\Models\Student;
use App\Support\PrayerSchedule;
use App\Support\SchoolFeatures;
use App\Support\SchoolTime;
use Carbon\Carbon;

/**
 * Satu gerbang untuk semua absen: datang, Dhuha, Dzuhur, pulang.
 *
 * Kelas ini TIDAK menyalin logika pencatatan. Ia cuma memutuskan siapa yang
 * dipanggil lebih dulu; `AttendanceRecorder` dan `PrayerAttendanceRecorder`
 * tetap pemilik tunggal aturannya masing-masing, termasuk penentuan
 * masuk/pulang, jenis sholat, kepesertaan, dan penolakan duplikat.
 *
 * ## Kenapa jam saja tidak cukup
 *
 * `AttendanceRecorder::resolveType()` menghitung scan sebagai MASUK sejak
 * `check_in_start` sampai `check_out_start` — dengan jadwal bawaan itu 06:00
 * sampai 13:00. Kedua jendela sholat berada seluruhnya di dalam rentang itu:
 *
 *     06:00 ─────────────────────────────────── 13:00 ────── 18:00
 *     │               masuk                          │ pulang  │
 *     │    ╔═════════╗        ╔═════════╗            │         │
 *     │    ║  Dhuha  ║        ║ Dzuhur  ║            │         │
 *     │    ╚═════════╝        ╚═════════╝            │         │
 *
 * Jadi pukul 07:45 satu tempelan kartu bisa berarti "datang" atau "Dhuha", dan
 * jamnya sendiri tidak bisa membedakan. Yang membedakan: apa yang BELUM
 * tercatat.
 *
 * ## Aturannya
 *
 * Satu tempelan mencatat satu hal yang belum tercatat, dalam urutan tetap:
 * datang → sholat → pulang. Dicoba berurutan, berhenti di yang pertama
 * berhasil. Percobaan yang gagal tidak meninggalkan apa pun, jadi mencoba
 * berurutan aman dan sekaligus membuat deteksi duplikat tidak perlu ditulis
 * ulang di sini.
 *
 * Datang menang atas sholat dengan sengaja: siswa yang baru tiba pukul 07:45
 * harus tercatat MASUK lebih dulu, kalau tidak ia dihitung alpa seharian dan
 * orang tuanya menerima peringatan. Sholat baru masuk hitungan sesudah
 * kehadirannya terekam — dan itu juga yang menjaga datanya jujur: tidak ada
 * catatan sholat untuk anak yang belum sampai di sekolah.
 *
 * Kalau fitur sholat mati, langkah sholat dilewati dan perilakunya persis
 * seperti sebelum kelas ini ada.
 */
class GerbangRecorder
{
    public function __construct(
        private readonly AttendanceRecorder $attendance,
        private readonly PrayerAttendanceRecorder $prayer,
    ) {}

    /**
     * Sekolahnya dioper, bukan diambil dari `$student->school`: pemanggilnya
     * sudah memegang objek itu, dan relasi yang dimuat ulang di sini berarti
     * satu query tambahan untuk setiap kartu yang ditempel sepanjang hari.
     *
     * @return array{success: bool, message: string, jenis: ?string, label: ?string, status: ?string}
     */
    public function record(
        School $sekolahSiswa,
        Student $student,
        ?string $deviceId = null,
        ?Carbon $timestamp = null,
    ): array {
        $waktu = $timestamp ? SchoolTime::toLocal($timestamp) : SchoolTime::now();

        // Fitur absensi sekolah yang dimatikan harus benar-benar berhenti
        // mencatat. Penjaga di controller kini meloloskan sekolah yang hanya
        // memakai absen sholat, jadi tanpa pemeriksaan di sini scan mereka akan
        // tetap menulis baris masuk/pulang yang tidak diminta siapa pun.
        $sekolah = SchoolFeatures::for($sekolahSiswa)->enabled(SchoolFeature::AbsensiSekolah)
            ? $this->attendance->record(
                student: $student,
                recordedBy: null,
                deviceId: $deviceId,
                timestamp: $waktu,
            )
            : ['success' => false, 'attendance' => null, 'message' => 'Absensi sekolah sedang dimatikan oleh admin.'];

        if ($sekolah['success']) {
            $type = $sekolah['attendance']->type;

            return [
                'success' => true,
                'message' => $sekolah['message'],
                'jenis' => $type->value,
                'label' => $type === AttendanceType::CheckIn ? 'Masuk' : 'Pulang',
                'status' => $sekolah['attendance']->status->label(),
            ];
        }

        if (! $this->sholatBerlaku($sekolahSiswa, $student, $waktu)) {
            // Sholat tidak bisa jadi jawaban untuk scan ini — entah fiturnya
            // mati, di luar jendela, atau siswanya memang tidak ikut. Pesan
            // absensi sekolah yang ditampilkan, bukan pesan sholat yang akan
            // membingungkan ("hanya untuk siswa beragama Islam" pada anak yang
            // sekadar absen dua kali).
            return $this->gagal($sekolah['message']);
        }

        $ibadah = $this->prayer->record(
            student: $student,
            recordedBy: null,
            deviceId: $deviceId,
            timestamp: $waktu,
        );

        if ($ibadah['success']) {
            $type = $ibadah['attendance']->prayer_type;

            return [
                'success' => true,
                'message' => $ibadah['message'],
                'jenis' => 'PRAYER',
                'label' => $type->label(),
                'status' => $ibadah['attendance']->status->label(),
            ];
        }

        return $this->gagal($this->pesanGabungan($student, $waktu, $sekolah['message'], $ibadah['message']));
    }

    /**
     * Apakah sholat bisa jadi jawaban untuk scan pada waktu ini.
     *
     * Diputuskan SEBELUM memanggil pencatatnya, supaya pilihan pesan tidak
     * bergantung pada pencocokan teks kegagalan.
     */
    private function sholatBerlaku(School $sekolah, Student $student, Carbon $waktu): bool
    {
        $jadwal = PrayerSchedule::for($sekolah);

        if (! $jadwal->anyEnabled()) {
            return false;
        }

        $jendela = $jadwal->resolveFor($waktu);

        return $jendela !== null && $jendela->covers($student);
    }

    /**
     * Pesan saat tidak ada lagi yang bisa dicatat.
     *
     * Kalau keduanya gagal karena sudah tercatat, sebutkan apa saja yang sudah
     * ada hari ini. "Sudah absen masuk" saja membuat operator mengira sholatnya
     * belum masuk dan menempelkan kartu berulang kali.
     */
    private function pesanGabungan(Student $student, Carbon $waktu, string $pesanSekolah, string $pesanSholat): string
    {
        $tanggal = $waktu->toDateString();

        $tercatat = Attendance::withoutGlobalScopes()
            ->where('student_id', $student->id)
            ->whereDate('attendance_date', $tanggal)
            ->get()
            ->map(fn (Attendance $baris): string => sprintf(
                '%s %s',
                $baris->type === AttendanceType::CheckIn ? 'masuk' : 'pulang',
                $baris->recorded_at?->format('H:i') ?? '',
            ))
            ->merge(
                PrayerAttendance::withoutGlobalScopes()
                    ->where('student_id', $student->id)
                    ->whereDate('prayer_date', $tanggal)
                    ->get()
                    ->map(fn (PrayerAttendance $baris): string => sprintf(
                        '%s %s',
                        $baris->prayer_type->label(),
                        $baris->recorded_at?->format('H:i') ?? '',
                    ))
            )
            ->map(fn (string $bagian): string => trim($bagian))
            ->filter()
            ->values();

        if ($tercatat->isEmpty()) {
            // Tidak ada yang tercatat sama sekali, jadi keduanya gagal karena
            // sebab lain — jendela, jadwal, atau kepesertaan. Pesan sholat yang
            // lebih spesifik untuk saat ini.
            return $pesanSholat;
        }

        return 'Sudah lengkap hari ini: '.$tercatat->join(', ').'.';
    }

    /**
     * @return array{success: false, message: string, jenis: null, label: null, status: null}
     */
    private function gagal(string $message): array
    {
        return ['success' => false, 'message' => $message, 'jenis' => null, 'label' => null, 'status' => null];
    }
}
