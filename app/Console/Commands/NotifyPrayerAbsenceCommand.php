<?php

namespace App\Console\Commands;

use App\Enums\PrayerType;
use App\Enums\SchoolFeature;
use App\Jobs\SendPrayerAbsenceNotification;
use App\Models\PrayerAbsenceAlert;
use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\PrayerAbsenceScanner;
use App\Support\PrayerSettings;
use App\Support\SchoolFeatures;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class NotifyPrayerAbsenceCommand extends Command
{
    protected $signature = 'prayer:notify-absence
                            {--date= : Tanggal acuan (Y-m-d), default hari ini}
                            {--school= : Batasi ke satu school_id}
                            {--type= : Batasi ke satu jenis sholat (DHUHA/DZUHUR)}
                            {--dry-run : Tampilkan saja, jangan menulis atau mengantrekan}';

    protected $description = 'Kirim peringatan ke orang tua saat siswa berturut-turut tidak ikut sholat';

    public function handle(PrayerAbsenceScanner $scanner): int
    {
        $date = $this->option('date')
            ? Carbon::createFromFormat('Y-m-d', $this->option('date'), SchoolTime::timezone())->startOfDay()
            : SchoolTime::today();

        $dryRun = (bool) $this->option('dry-run');
        $onlyType = PrayerType::tryFrom(strtoupper((string) $this->option('type')));

        $schools = School::query()
            ->when($this->option('school'), fn ($q) => $q->where('id', $this->option('school')))
            ->where('is_active', true)
            ->get();

        $queued = 0;

        foreach ($schools as $school) {
            $features = SchoolFeatures::for($school);

            if ($features->disabled(SchoolFeature::NotifAlpaSholat)) {
                continue;
            }

            $threshold = (int) ($school->getSetting('prayer_absence_threshold') ?? 3);
            $requirePresent = (bool) ($school->getSetting('prayer_absence_require_present') ?? true);

            /** @var array<string, array{alerts: Collection<int, PrayerAbsenceAlert>, labels: array<int, string>}> */
            $perStudent = [];

            foreach (PrayerType::chronological() as $type) {
                if ($onlyType !== null && $type !== $onlyType) {
                    continue;
                }

                if (! $this->typeIsEvaluable($school, $type, $date, $features)) {
                    continue;
                }

                $hits = $scanner->scan($school, $type, $date, $threshold, $requirePresent);
                $hitIds = $hits->map(fn (array $row) => $row['student']->id)->all();

                $this->resolveClosedAlerts($school, $type, $hitIds, $dryRun);

                foreach ($hits as $row) {
                    $alert = $this->recordAlert($school, $type, $row, $dryRun);

                    if ($alert === null) {
                        continue;
                    }

                    $perStudent[$row['student']->id]['alerts'][] = $alert;
                    $perStudent[$row['student']->id]['labels'][] = $type->shortLabel();
                    $perStudent[$row['student']->id]['student'] = $row['student'];
                }
            }

            $queued += $this->queueNotifications($perStudent, $dryRun);
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Alpa sholat {$date->toDateString()}: {$queued} notifikasi diantrekan.");

        return self::SUCCESS;
    }

    /**
     * Hari berjalan baru boleh dinilai setelah jendela sholatnya benar-benar
     * tutup plus tenggang; kalau tidak, scan pukul 12:59 kalah cepat dari tick
     * penjadwal pukul 13:00.
     */
    private function typeIsEvaluable(School $school, PrayerType $type, Carbon $date, SchoolFeatures $features): bool
    {
        $feature = $type === PrayerType::Dhuha ? SchoolFeature::SholatDhuha : SchoolFeature::SholatDzuhur;

        if ($features->disabled($feature)) {
            return false;
        }

        if (! $date->isSameDay(SchoolTime::today())) {
            return true;
        }

        $settings = PrayerSettings::for($school, $type);
        $closesAt = $date->copy()->setTimeFromTimeString($settings->end)
            ->addMinutes((int) config('attendance.prayer.absence_grace_minutes', 30));

        return SchoolTime::now()->greaterThan($closesAt);
    }

    /**
     * Tutup alert terbuka milik siswa yang sudah tidak lagi punya rentetan —
     * inilah "reset begitu siswa sholat lagi".
     *
     * @param  array<int, string>  $stillFailing
     */
    private function resolveClosedAlerts(School $school, PrayerType $type, array $stillFailing, bool $dryRun): void
    {
        $open = PrayerAbsenceAlert::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->where('prayer_type', $type->value)
            ->whereNull('resolved_at')
            ->get();

        foreach ($open as $alert) {
            if (in_array($alert->student_id, $stillFailing, true)) {
                continue;
            }

            if ($dryRun) {
                continue;
            }

            $alert->forceFill(['resolved_at' => now()])->save();
        }
    }

    /**
     * @param  array{student: Student, streak: int, start: string, last: string}  $row
     */
    private function recordAlert(School $school, PrayerType $type, array $row, bool $dryRun): ?PrayerAbsenceAlert
    {
        $student = $row['student'];

        // Penjaga UTAMA — bukan unique key. Rentetan yang lebih panjang dari
        // jendela pindai menggeser streak_start_date tiap hari, jadi unique key
        // sendirian akan menghasilkan notifikasi duplikat setiap hari.
        $openAlert = PrayerAbsenceAlert::withoutGlobalScope('school')
            ->where('student_id', $student->id)
            ->where('prayer_type', $type->value)
            ->whereNull('resolved_at')
            ->whereNotNull('notified_at')
            ->exists();

        if ($openAlert) {
            return null;
        }

        // Tanpa satu pun tujuan kirim, membuat baris alert ber-notified_at
        // hanya akan membungkam peringatan yang tidak pernah terkirim.
        if (! $this->hasDestination($school, $student)) {
            return null;
        }

        if ($dryRun) {
            $this->line("  [dry-run] {$student->full_name} — {$type->shortLabel()} {$row['streak']} hari");

            return null;
        }

        $alert = PrayerAbsenceAlert::withoutGlobalScope('school')
            ->firstOrNew([
                'student_id' => $student->id,
                'prayer_type' => $type->value,
                'streak_start_date' => $row['start'],
            ]);

        if ($alert->exists && $alert->notified_at !== null) {
            return null;
        }

        $alert->fill([
            'school_id' => $school->id,
            'streak_last_date' => $row['last'],
            'streak_length' => $row['streak'],
        ])->save();

        return $alert;
    }

    private function hasDestination(School $school, Student $student): bool
    {
        $parent = $student->parentProfile;

        if (! $parent) {
            return false;
        }

        // Email cukup sendirian: dispatchPrayerAbsence mengirimnya tanpa
        // mensyaratkan kanal EMAIL terdaftar (mailer global jadi fallback).
        return ! empty($parent->email)
            || ! empty($parent->whatsapp_number)
            || ! empty($parent->telegram_chat_id);
    }

    /**
     * Dua pesan nyaris identik dalam satu detik terbaca sebagai spam, jadi
     * alert dengan rentetan terpanjang jadi primary dan membawa label yang lain.
     *
     * @param  array<string, array<string, mixed>>  $perStudent
     */
    private function queueNotifications(array $perStudent, bool $dryRun): int
    {
        if ($dryRun) {
            return 0;
        }

        $queued = 0;

        foreach ($perStudent as $entry) {
            /** @var array<int, PrayerAbsenceAlert> $alerts */
            $alerts = $entry['alerts'] ?? [];

            if ($alerts === []) {
                continue;
            }

            usort($alerts, fn (PrayerAbsenceAlert $a, PrayerAbsenceAlert $b) => $b->streak_length <=> $a->streak_length);

            $primary = $alerts[0];
            $primary->forceFill([
                'combined_types' => $entry['labels'],
                // notified_at diisi saat job DIANTREKAN, bukan saat sukses
                // kirim: lebih baik kurang-kirim daripada kirim-ganda ke HP
                // orang tua. Kegagalan tetap terlihat sebagai NotificationLog
                // berstatus FAILED di inbox admin.
                'notified_at' => now(),
            ])->save();

            foreach (array_slice($alerts, 1) as $secondary) {
                $secondary->forceFill(['notified_at' => now()])->save();
            }

            SendPrayerAbsenceNotification::dispatch($primary)
                ->onQueue(config('whatsapp.queue', 'whatsapp'));

            $queued++;
        }

        return $queued;
    }
}
