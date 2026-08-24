<?php

namespace App\Console\Commands;

use App\Enums\AttendanceAlertKind;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\SchoolFeature;
use App\Jobs\SendAttendanceAlert;
use App\Models\Attendance;
use App\Models\AttendanceAlert;
use App\Models\AttendanceSchedule;
use App\Models\School;
use App\Models\Student;
use App\Support\SchoolFeatures;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Kirim kabar terlambat / tidak hadir ke orang tua lewat WhatsApp.
 *
 * Kebijakan WA dibalik: yang hadir tepat waktu tidak lagi dikabarkan sama
 * sekali, dan yang dikirim justru dikumpulkan dalam satu sapuan — bukan satu
 * pesan per scan. Email dan Telegram tidak lewat sini; keduanya tetap mengabari
 * tiap scan lewat DispatchAttendanceNotifications.
 *
 * Dijadwalkan tiap jam. Command sendiri yang memutuskan apakah jendelanya sudah
 * benar-benar tutup, karena tiap sekolah punya jamnya masing-masing.
 */
class NotifyAttendanceAbsenceCommand extends Command
{
    protected $signature = 'attendance:notify-absence
                            {--date= : Tanggal acuan (Y-m-d), default hari ini}
                            {--school= : Batasi ke satu school_id}
                            {--dry-run : Tampilkan saja, jangan menulis atau mengantrekan}';

    protected $description = 'Kirim peringatan WhatsApp untuk siswa yang terlambat atau tidak hadir';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::createFromFormat('Y-m-d', $this->option('date'), SchoolTime::timezone())->startOfDay()
            : SchoolTime::today();

        $dryRun = (bool) $this->option('dry-run');
        $prefix = $dryRun ? '[dry-run] ' : '';

        $schools = School::query()
            ->when($this->option('school'), fn ($q, $id) => $q->where('id', $id))
            ->where('is_active', true)
            ->get();

        $queued = 0;

        foreach ($schools as $school) {
            $queued += $this->sweepSchool($school, $date, $dryRun, $prefix);
        }

        $this->info("{$prefix}Peringatan absensi {$date->toDateString()}: {$queued} notifikasi diantrekan.");

        return self::SUCCESS;
    }

    private function sweepSchool(School $school, Carbon $date, bool $dryRun, string $prefix): int
    {
        // Sekolah yang belum membalik kebijakan WA-nya tetap memakai jalur lama
        // (kabar tiap scan), jadi sapuan ini tidak boleh menyentuhnya sama sekali.
        if (! (bool) ($school->getSetting('wa_alert_only') ?? false)) {
            return 0;
        }

        if (SchoolFeatures::for($school)->disabled(SchoolFeature::NotifAbsensi)) {
            return 0;
        }

        $schedules = $this->schedulesFor($school, $date);

        // Tidak ada jadwal aktif hari ini = bukan hari sekolah. Ini sekaligus
        // penjaga akhir pekan, tanpa perlu tabel hari libur.
        if ($schedules->isEmpty()) {
            return 0;
        }

        $targets = [];

        if ($this->wants($school, AttendanceAlertKind::Terlambat)
            && $this->windowClosed($schedules, 'late_threshold', $date)) {
            foreach ($this->lateAttendances($school, $date) as $attendance) {
                $targets[] = [
                    'student' => $attendance->student,
                    'kind' => AttendanceAlertKind::Terlambat,
                    'attendance_id' => $attendance->id,
                    'waktu' => $attendance->recorded_at?->format('H:i') ?? '-',
                ];
            }
        }

        // Ketidakhadiran hanya boleh dinilai setelah jendela masuk BENAR-BENAR
        // habis. Batasnya `check_out_start`, bukan `check_in_end`: yang
        // menentukan scan masih dihitung masuk atau sudah dihitung pulang adalah
        // `AttendanceRecorder::resolveType()`, dan ia memakai `check_out_start`.
        // Memakai `check_in_end` (bawaannya 08:00) akan menyatakan siswa yang
        // datang pukul 10:00 sebagai tidak hadir, padahal ia masih bisa absen.
        if ($this->wants($school, AttendanceAlertKind::Alpa)
            && $this->windowClosed($schedules, 'check_out_start', $date)
            && $this->hasAnyCheckIn($school, $date, $prefix)) {
            foreach ($this->absentStudents($school, $schedules, $date) as $student) {
                $targets[] = [
                    'student' => $student,
                    'kind' => AttendanceAlertKind::Alpa,
                    'attendance_id' => null,
                    // Tidak ada jam scan untuk dilaporkan.
                    'waktu' => '-',
                ];
            }
        }

        return $this->queue($school, $targets, $date, $dryRun, $prefix);
    }

    /**
     * Jadwal aktif sekolah untuk hari yang dinilai.
     *
     * @return Collection<int, AttendanceSchedule>
     */
    private function schedulesFor(School $school, Carbon $date): Collection
    {
        return AttendanceSchedule::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->where('day_of_week', $date->dayOfWeekIso)
            ->where('is_active', true)
            ->get();
    }

    private function wants(School $school, AttendanceAlertKind $kind): bool
    {
        return (bool) ($school->getSetting($kind->settingKey()) ?? true);
    }

    /**
     * Jam terakhir dari kolom yang diminta, di seluruh jadwal hari itu, sudah
     * lewat plus tenggang?
     *
     * Dipakai jam TERBESAR supaya kelas yang jendelanya paling panjang tidak
     * dinilai lebih dulu dari waktunya. Tanggal lampau selalu dianggap tutup.
     *
     * @param  Collection<int, AttendanceSchedule>  $schedules
     */
    private function windowClosed(Collection $schedules, string $column, Carbon $date): bool
    {
        if (! $date->isSameDay(SchoolTime::today())) {
            return true;
        }

        $latest = $schedules
            ->map(fn (AttendanceSchedule $schedule) => $schedule->momentOn($column, $date))
            ->max();

        $grace = (int) config('attendance.absence_grace_minutes', 30);

        return SchoolTime::now()->greaterThan($latest->copy()->addMinutes($grace));
    }

    /**
     * Penjaga hari libur.
     *
     * Nol check-in sepanjang hari hampir pasti berarti libur yang tidak
     * terdaftar di jadwal — bukan seluruh siswa membolos bersamaan. Tanpa
     * penjaga ini, satu libur nasional mengirimi SETIAP orang tua pesan "anak
     * Anda tidak hadir".
     */
    private function hasAnyCheckIn(School $school, Carbon $date, string $prefix): bool
    {
        $any = Attendance::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->whereDate('attendance_date', $date->toDateString())
            ->where('type', AttendanceType::CheckIn->value)
            ->exists();

        if (! $any) {
            $this->line("{$prefix}<comment>{$school->name}</comment>: nol check-in hari ini, dianggap libur.");
        }

        return $any;
    }

    /**
     * @return Collection<int, Attendance>
     */
    private function lateAttendances(School $school, Carbon $date): Collection
    {
        return Attendance::withoutGlobalScope('school')
            ->with(['student.parentProfile', 'student.classroom'])
            ->where('school_id', $school->id)
            ->whereDate('attendance_date', $date->toDateString())
            ->where('type', AttendanceType::CheckIn->value)
            ->where('status', AttendanceStatus::Terlambat->value)
            ->get();
    }

    /**
     * Siswa yang tidak punya absen masuk hari ini.
     *
     * `IZIN` dan `SAKIT` adalah ketidakhadiran berizin dan tidak pernah jadi
     * sasaran. `ALPA` yang diisi admin manual justru ikut, karena itu memang
     * pernyataan eksplisit bahwa siswanya tidak datang.
     *
     * @param  Collection<int, AttendanceSchedule>  $schedules
     * @return Collection<int, Student>
     */
    private function absentStudents(School $school, Collection $schedules, Carbon $date): Collection
    {
        $global = $schedules->contains(fn (AttendanceSchedule $s) => $s->classroom_id === null);
        $classroomIds = $schedules->pluck('classroom_id')->filter()->all();

        return Student::withoutGlobalScope('school')
            ->with(['parentProfile', 'classroom'])
            ->where('school_id', $school->id)
            ->where('is_active', true)
            // Jadwal khusus kelas hanya menjangkau kelas itu. Kalau ada jadwal
            // global (classroom_id null), seluruh siswa masuk hitungan.
            ->when(! $global, fn ($q) => $q->whereIn('classroom_id', $classroomIds))
            ->whereDoesntHave('attendances', fn ($q) => $q
                ->whereDate('attendance_date', $date->toDateString())
                ->where('type', AttendanceType::CheckIn->value)
                ->where('status', '!=', AttendanceStatus::Alpa->value))
            ->get();
    }

    /**
     * Satu pesan per NOMOR, bukan per anak.
     *
     * Satu orang tua bisa punya beberapa anak di sekolah yang sama — di data
     * prod ditemukan satu nomor dengan enam. Tanpa penggabungan, nomor itu
     * menerima enam WhatsApp nyaris identik dalam satu detik: terbaca spam oleh
     * penerimanya maupun oleh WhatsApp sendiri, dan kuota harian tidak
     * menahannya karena ia menghitung pesan, bukan penerima.
     *
     * @param  array<int, array{student: ?Student, kind: AttendanceAlertKind, attendance_id: ?string, waktu: string}>  $targets
     */
    private function queue(School $school, array $targets, Carbon $date, bool $dryRun, string $prefix): int
    {
        /** @var array<string, array<int, array<string, mixed>>> */
        $perNomor = [];

        foreach ($targets as $target) {
            $student = $target['student'];
            $nomor = $student?->parentProfile?->whatsapp_number;

            // Jalur ini WhatsApp saja. Tanpa nomor, membuat baris alert
            // ber-notified_at hanya akan membungkam peringatan yang tidak pernah
            // terkirim — pelajaran dari NotifyPrayerAbsenceCommand.
            if (! $student || empty($nomor)) {
                continue;
            }

            $alert = $this->pendingAlert($school, $student, $target, $date);

            if ($alert === null) {
                continue;
            }

            $perNomor[self::nomorSeragam($nomor)][] = [
                'student' => $student,
                'kind' => $target['kind'],
                'waktu' => $target['waktu'],
                'alert' => $alert,
            ];
        }

        $queued = 0;

        foreach ($perNomor as $nomor => $rombongan) {
            // Urut nama supaya primary-nya tidak berubah-ubah antar eksekusi
            // dan daftar anaknya terbaca konsisten.
            usort($rombongan, fn (array $a, array $b) => strcasecmp($a['student']->full_name, $b['student']->full_name));

            if ($dryRun) {
                $nama = implode(', ', array_map(
                    fn (array $e) => "{$e['student']->full_name} ({$e['kind']->label()})",
                    $rombongan,
                ));
                $this->line("{$prefix}{$school->name} / {$nomor}: {$nama}");

                continue;
            }

            $this->persist($school, $rombongan);

            SendAttendanceAlert::dispatch($rombongan[0]['alert'])
                ->onQueue(config('whatsapp.queue', 'whatsapp'));

            $queued++;
        }

        return $queued;
    }

    /**
     * Baris alert yang masih perlu dikabarkan, atau null kalau sudah.
     *
     * @param  array{kind: AttendanceAlertKind, attendance_id: ?string}  $target
     */
    private function pendingAlert(School $school, Student $student, array $target, Carbon $date): ?AttendanceAlert
    {
        // `whereDate`, bukan `firstOrNew(['alert_date' => ...])`. Cast `date`
        // menyimpan kolomnya sebagai "Y-m-d H:i:s", jadi pencocokan persis
        // terhadap "Y-m-d" tidak pernah ketemu — barisnya dianggap belum ada
        // lalu insert-nya menabrak unique key.
        $alert = AttendanceAlert::withoutGlobalScope('school')
            ->where('student_id', $student->id)
            ->whereDate('alert_date', $date->toDateString())
            ->where('kind', $target['kind']->value)
            ->first();

        // Sapuan berjalan tiap jam. Tanpa penjaga ini satu keterlambatan
        // dikabarkan ulang setiap jam sampai tengah malam. Anak yang sudah
        // dikabarkan pagi tadi juga tidak ikut digabung ke pesan sore.
        if ($alert && $alert->notified_at !== null) {
            return null;
        }

        $alert ??= new AttendanceAlert([
            'student_id' => $student->id,
            'alert_date' => $date->toDateString(),
            'kind' => $target['kind']->value,
        ]);

        return $alert->fill([
            'school_id' => $school->id,
            'attendance_id' => $target['attendance_id'],
        ]);
    }

    /**
     * Simpan seluruh alert satu rombongan; yang pertama membawa daftar anaknya.
     *
     * @param  array<int, array<string, mixed>>  $rombongan
     */
    private function persist(School $school, array $rombongan): void
    {
        $daftar = array_map(fn (array $entry) => [
            'nama' => $entry['student']->full_name,
            'kelas' => $entry['student']->classroom?->name ?? '-',
            'status' => $entry['kind']->label(),
            'waktu' => $entry['waktu'],
        ], $rombongan);

        foreach ($rombongan as $index => $entry) {
            /** @var AttendanceAlert $alert */
            $alert = $entry['alert'];

            $alert->fill([
                // Hanya primary yang membawa daftarnya; sisanya tetap ditulis
                // supaya sapuan berikutnya tahu mereka sudah dikabarkan.
                'combined_children' => $index === 0 ? $daftar : null,
                // notified_at diisi saat job DIANTREKAN, bukan saat sukses
                // kirim: lebih baik kurang-kirim daripada kirim-ganda ke HP
                // orang tua. Kegagalan tetap terlihat sebagai NotificationLog
                // berstatus FAILED di inbox admin.
                'notified_at' => now(),
            ])->save();
        }
    }

    /**
     * Bentuk nomor yang bisa dibandingkan.
     *
     * `081391444323` dan `81391444323` adalah orang yang sama, dan keduanya ada
     * di data prod untuk satu keluarga. Tanpa penyeragaman, keluarga itu tetap
     * menerima dua pesan.
     *
     * Public dan statis supaya bisa diuji tanpa menyentuh database sama sekali.
     */
    public static function nomorSeragam(string $nomor): string
    {
        $angka = preg_replace('/\D+/', '', $nomor) ?? '';

        if (str_starts_with($angka, '62')) {
            return substr($angka, 2);
        }

        return ltrim($angka, '0');
    }
}
