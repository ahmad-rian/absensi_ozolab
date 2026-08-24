<?php

namespace App\Services\Notification;

use App\Enums\AttendanceAlertKind;
use App\Enums\AttendanceType;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\SchoolChannelType;
use App\Models\Attendance;
use App\Models\AttendanceAlert;
use App\Models\NotificationLog;
use App\Models\ParentProfile;
use App\Models\PrayerAbsenceAlert;
use App\Models\School;
use App\Models\SchoolNotificationChannel;
use App\Support\WhatsAppQuota;
use Illuminate\Support\Collection;

class NotificationDispatcher
{
    public function __construct(
        private readonly WhatsAppGateway $whatsApp,
        private readonly TelegramGateway $telegram,
        private readonly EmailGateway $email,
    ) {}

    /**
     * Kirim notifikasi absensi ke semua channel aktif sekolah.
     * Idempotent: channel yang sudah berstatus Sent dilewati saat retry.
     *
     * `$skipChannels` dipakai jalur per-scan ketika sekolah menyalakan
     * `wa_alert_only`: Email dan Telegram tetap dikirim tiap scan, sementara
     * WhatsApp diserahkan sepenuhnya ke sapuan `attendance:notify-absence`.
     * Menyaringnya di sini, bukan di listener, supaya satu jalur saja yang tahu
     * kanal mana yang punya log dan korelasi apa.
     *
     * @param  array<int, NotificationChannel>  $skipChannels
     * @return bool true jika tidak ada channel yang gagal pada percobaan ini.
     */
    public function dispatchAttendance(Attendance $attendance, int $attempt = 1, array $skipChannels = []): bool
    {
        $student = $attendance->student;
        $parentProfile = $student?->parentProfile;

        if (! $student || ! $parentProfile) {
            return true;
        }

        $school = $student->school;
        $schoolId = $student->school_id;
        $activeTypes = $this->activeChannelTypes($schoolId);
        $variables = $this->buildVariables($attendance);
        $correlation = ['attendance_id' => $attendance->id];

        $allSucceeded = true;

        // WhatsApp: cukup salah satu channel WA aktif (Fonnte/Ozolab).
        $waActive = $activeTypes->contains(SchoolChannelType::FonnteWa)
            || $activeTypes->contains(SchoolChannelType::OzolabWa);

        if ($waActive && ! $this->skips($skipChannels, NotificationChannel::Whatsapp) && ! empty($parentProfile->whatsapp_number)) {
            $sent = $this->deliverWhatsApp(
                $correlation,
                $attendance->student_id,
                $school,
                $parentProfile,
                $variables,
                'attendance_notify',
                $attempt,
            );
            $allSucceeded = $allSucceeded && $sent;
        }

        // Telegram: channel aktif + ortu punya chat_id.
        if ($activeTypes->contains(SchoolChannelType::Telegram) && ! $this->skips($skipChannels, NotificationChannel::Telegram) && ! empty($parentProfile->telegram_chat_id)) {
            $sent = $this->deliver(
                $correlation,
                $attendance->student_id,
                $schoolId,
                $parentProfile,
                NotificationChannel::Telegram,
                $parentProfile->telegram_chat_id,
                $variables,
                'attendance_notify',
                $attempt,
                fn () => $this->telegram->sendTemplate($parentProfile->telegram_chat_id, 'attendance_notify', $variables, $schoolId),
            );
            $allSucceeded = $allSucceeded && $sent;
        }

        // Email: channel aktif + ortu punya alamat email.
        if ($activeTypes->contains(SchoolChannelType::Email) && ! $this->skips($skipChannels, NotificationChannel::Email) && ! empty($parentProfile->email)) {
            $sent = $this->deliver(
                $correlation,
                $attendance->student_id,
                $schoolId,
                $parentProfile,
                NotificationChannel::Email,
                $parentProfile->email,
                $variables,
                'attendance_notify',
                $attempt,
                fn () => $this->email->sendTemplate($parentProfile->email, 'attendance_notify', $variables, $schoolId),
            );
            $allSucceeded = $allSucceeded && $sent;
        }

        return $allSucceeded;
    }

    /**
     * Kirim peringatan "terlambat" / "tidak hadir" ke orang tua.
     *
     * WhatsApp saja, dan itu disengaja: hanya kebijakan WA yang dibalik. Email
     * dan Telegram sudah mengabarkan tiap scan lewat dispatchAttendance(), jadi
     * mengirimkannya lagi di sini berarti orang tua menerima dua email untuk
     * satu keterlambatan yang sama.
     *
     * @return bool true jika tidak ada channel yang gagal pada percobaan ini.
     */
    public function dispatchAttendanceAlert(AttendanceAlert $alert, int $attempt = 1): bool
    {
        $student = $alert->student;
        $parentProfile = $student?->parentProfile;

        if (! $student || ! $parentProfile || empty($parentProfile->whatsapp_number)) {
            return true;
        }

        $school = $student->school;
        $activeTypes = $this->activeChannelTypes($student->school_id);

        $waActive = $activeTypes->contains(SchoolChannelType::FonnteWa)
            || $activeTypes->contains(SchoolChannelType::OzolabWa);

        if (! $waActive) {
            return true;
        }

        $succeeded = $this->deliverWhatsApp(
            ['attendance_alert_id' => $alert->id],
            $student->id,
            $school,
            $parentProfile,
            $this->buildAlertVariables($alert),
            'attendance_alert_notify',
            $attempt,
        );

        if ($succeeded && $alert->delivered_at === null) {
            $alert->forceFill(['delivered_at' => now()])->save();
        }

        return $succeeded;
    }

    /**
     * Kirim ke satu channel dengan logging idempotent.
     *
     * `$correlation` adalah kolom yang mengunci satu log ke satu kejadian —
     * `['attendance_id' => ...]` untuk notifikasi absensi,
     * `['prayer_absence_alert_id' => ...]` untuk peringatan alpa sholat,
     * `['attendance_alert_id' => ...]` untuk peringatan terlambat/tidak hadir.
     * Sebelumnya kolomnya di-hardcode ke `attendance_id`, sehingga notifikasi
     * tanpa absensi menghasilkan `WHERE attendance_id = NULL` yang tidak pernah
     * cocok dengan apa pun dan mengirim ulang pesan di setiap retry.
     *
     * @param  array<string, string>  $correlation
     * @param  array<string, string>  $variables
     * @param  callable(): bool  $send
     */
    private function deliver(
        array $correlation,
        string $studentId,
        ?string $schoolId,
        ParentProfile $parentProfile,
        NotificationChannel $channel,
        string $destination,
        array $variables,
        string $templateKey,
        int $attempt,
        callable $send,
    ): bool {
        $existing = $this->existingLog($correlation, $channel);

        if ($existing && $existing->status === NotificationStatus::Sent) {
            return true;
        }

        $log = $this->upsertLog(
            $existing,
            $correlation,
            $studentId,
            $schoolId,
            $parentProfile,
            $channel,
            $destination,
            $variables,
            $templateKey,
            $attempt,
        );

        $success = $send();

        $log->update([
            'status' => $success ? NotificationStatus::Sent : NotificationStatus::Failed,
            'sent_at' => $success ? now() : null,
            'error_message' => $success ? null : 'Send failed on attempt '.$attempt,
            'attempt_count' => $attempt,
        ]);

        return $success;
    }

    /**
     * Kirim lewat WhatsApp, dengan kuota harian sekolah ditegakkan lebih dulu.
     *
     * Satu-satunya pintu WhatsApp di kelas ini, dan itu disengaja: menaruh
     * penjaganya di gateway melewatkan penulisan log (admin tidak akan pernah
     * tahu pesan mana yang tertahan), sementara menaruhnya di command bocor
     * lewat jalur alpa sholat yang tidak melewati command itu.
     *
     * @param  array<string, string>  $correlation
     * @param  array<string, string>  $variables
     */
    private function deliverWhatsApp(
        array $correlation,
        string $studentId,
        ?School $school,
        ParentProfile $parentProfile,
        array $variables,
        string $templateKey,
        int $attempt,
    ): bool {
        $schoolId = $school?->id;

        if ($school && ! WhatsAppQuota::allows($school)) {
            $this->logThrottled(
                $correlation,
                $studentId,
                $school,
                $parentProfile,
                $variables,
                $templateKey,
                $attempt,
            );

            // Sengaja true. Kuota habis bukan kegagalan sementara: mengembalikan
            // false membuat job di-release() lalu mencoba lagi tiga kali untuk
            // hasil yang sudah pasti sama, dan menahan antrean yang dipakai
            // bersama seluruh tenant.
            return true;
        }

        $destination = $parentProfile->whatsapp_number;

        return $this->deliver(
            $correlation,
            $studentId,
            $schoolId,
            $parentProfile,
            NotificationChannel::Whatsapp,
            $destination,
            $variables,
            $templateKey,
            $attempt,
            fn () => $this->whatsApp->sendTemplate($destination, $templateKey, $variables, $schoolId),
        );
    }

    /**
     * Catat pesan yang tertahan kuota supaya terlihat di Inbox Notifikasi.
     *
     * Baris log yang sudah ada dipakai ulang, bukan dibuat baru, supaya satu
     * kejadian tidak menumpuk tiga baris ketika job-nya sempat dicoba ulang.
     *
     * @param  array<string, string>  $correlation
     * @param  array<string, string>  $variables
     */
    private function logThrottled(
        array $correlation,
        string $studentId,
        School $school,
        ParentProfile $parentProfile,
        array $variables,
        string $templateKey,
        int $attempt,
    ): void {
        $limit = WhatsAppQuota::limitFor($school);

        $log = $this->upsertLog(
            $this->existingLog($correlation, NotificationChannel::Whatsapp),
            $correlation,
            $studentId,
            $school->id,
            $parentProfile,
            NotificationChannel::Whatsapp,
            $parentProfile->whatsapp_number,
            $variables,
            $templateKey,
            $attempt,
        );

        $log->update([
            'status' => NotificationStatus::Throttled,
            'sent_at' => null,
            'error_message' => "Kuota WhatsApp harian sekolah ({$limit} pesan) sudah habis.",
            'attempt_count' => $attempt,
        ]);
    }

    /**
     * @param  array<string, string>  $correlation
     */
    private function existingLog(array $correlation, NotificationChannel $channel): ?NotificationLog
    {
        return NotificationLog::withoutGlobalScope('school')
            ->where($correlation)
            ->where('channel', $channel->value)
            ->first();
    }

    /**
     * @param  array<string, string>  $correlation
     * @param  array<string, string>  $variables
     */
    private function upsertLog(
        ?NotificationLog $existing,
        array $correlation,
        string $studentId,
        ?string $schoolId,
        ParentProfile $parentProfile,
        NotificationChannel $channel,
        string $destination,
        array $variables,
        string $templateKey,
        int $attempt,
    ): NotificationLog {
        $log = $existing ?? new NotificationLog;

        $log->fill([
            ...$correlation,
            'school_id' => $schoolId,
            'student_id' => $studentId,
            'parent_profile_id' => $parentProfile->id,
            'channel' => $channel,
            'whatsapp_number' => $destination,
            'template_key' => $templateKey,
            'payload' => $variables,
            'status' => NotificationStatus::Pending,
            'attempt_count' => $attempt,
        ]);
        $log->save();

        return $log;
    }

    /**
     * @param  array<int, NotificationChannel>  $skipChannels
     */
    private function skips(array $skipChannels, NotificationChannel $channel): bool
    {
        return in_array($channel, $skipChannels, true);
    }

    /**
     * Kirim peringatan "N hari berturut-turut tidak sholat" ke orang tua.
     *
     * Pemilihan kanal SENGAJA asimetris terhadap dispatchAttendance(): email
     * dikirim kapan pun orang tua punya alamat, tanpa mensyaratkan baris
     * SchoolNotificationChannel bertipe EMAIL. Itulah arti "default lewat
     * email" — DefaultEmailGateway sudah jatuh ke mailer global kalau sekolah
     * belum mengatur SMTP sendiri. Jangan "dirapikan" menyamai absensi.
     *
     * @return bool true jika tidak ada channel yang gagal pada percobaan ini.
     */
    public function dispatchPrayerAbsence(PrayerAbsenceAlert $alert, int $attempt = 1): bool
    {
        $student = $alert->student;
        $parentProfile = $student?->parentProfile;

        if (! $student || ! $parentProfile) {
            return true;
        }

        $school = $student->school;
        $schoolId = $student->school_id;
        $activeTypes = $this->activeChannelTypes($schoolId);
        $variables = $this->buildPrayerAbsenceVariables($alert);
        $correlation = ['prayer_absence_alert_id' => $alert->id];
        $templateKey = 'prayer_absence_notify';

        $allSucceeded = true;

        if (! empty($parentProfile->email)) {
            $allSucceeded = $this->deliver(
                $correlation,
                $student->id,
                $schoolId,
                $parentProfile,
                NotificationChannel::Email,
                $parentProfile->email,
                $variables,
                $templateKey,
                $attempt,
                fn () => $this->email->sendTemplate($parentProfile->email, $templateKey, $variables, $schoolId),
            ) && $allSucceeded;
        }

        $waActive = $activeTypes->contains(SchoolChannelType::FonnteWa)
            || $activeTypes->contains(SchoolChannelType::OzolabWa);

        if ($waActive && ! empty($parentProfile->whatsapp_number)) {
            $allSucceeded = $this->deliverWhatsApp(
                $correlation,
                $student->id,
                $school,
                $parentProfile,
                $variables,
                $templateKey,
                $attempt,
            ) && $allSucceeded;
        }

        if ($activeTypes->contains(SchoolChannelType::Telegram) && ! empty($parentProfile->telegram_chat_id)) {
            $allSucceeded = $this->deliver(
                $correlation,
                $student->id,
                $schoolId,
                $parentProfile,
                NotificationChannel::Telegram,
                $parentProfile->telegram_chat_id,
                $variables,
                $templateKey,
                $attempt,
                fn () => $this->telegram->sendTemplate($parentProfile->telegram_chat_id, $templateKey, $variables, $schoolId),
            ) && $allSucceeded;
        }

        if ($allSucceeded && $alert->delivered_at === null) {
            $alert->forceFill(['delivered_at' => now()])->save();
        }

        return $allSucceeded;
    }

    /**
     * @return Collection<int, SchoolChannelType>
     */
    private function activeChannelTypes(string $schoolId): Collection
    {
        return SchoolNotificationChannel::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->get()
            ->map(fn (SchoolNotificationChannel $c) => $c->channel);
    }

    /**
     * @return array<string, string>
     */
    private function buildVariables(Attendance $attendance): array
    {
        $student = $attendance->student;
        $isCheckOut = $attendance->type === AttendanceType::CheckOut;

        return [
            'nama_siswa' => $student->full_name,
            'kelas' => $student->classroom?->name ?? '-',
            'waktu' => $attendance->recorded_at?->format('H:i') ?? '-',
            'tanggal' => $attendance->attendance_date->translatedFormat('d F Y'),
            'status' => $attendance->status->label(),
            'jenis' => $isCheckOut ? 'Pulang' : 'Masuk',
            'aktivitas' => $isCheckOut ? 'kepulangan' : 'kehadiran',
            'nama_sekolah' => $student->school?->name ?? 'Sekolah',
        ];
    }

    /**
     * Bernama sama dengan buildVariables() supaya admin yang sudah terbiasa
     * menulis template kehadiran tidak perlu menghafal daftar kedua.
     *
     * `{waktu}` diisi `-` untuk yang tidak hadir: tidak ada jam scan untuk
     * dilaporkan, dan mengosongkannya membuat template bawaan berisi baris
     * "Waktu : , 24 Agustus 2026".
     *
     * @return array<string, string>
     */
    private function buildAlertVariables(AttendanceAlert $alert): array
    {
        $student = $alert->student;

        return [
            'nama_siswa' => $student->full_name,
            'kelas' => $student->classroom?->name ?? '-',
            'waktu' => $alert->kind === AttendanceAlertKind::Terlambat
                ? ($alert->attendance?->recorded_at?->format('H:i') ?? '-')
                : '-',
            'tanggal' => $alert->alert_date->translatedFormat('d F Y'),
            'status' => $alert->kind->label(),
            'nama_sekolah' => $student->school?->name ?? 'Sekolah',
        ];
    }

    /**
     * Tiga variabel pertama sengaja bernama sama dengan buildVariables(),
     * supaya kebiasaan admin menulis template tidak berubah.
     *
     * @return array<string, string>
     */
    private function buildPrayerAbsenceVariables(PrayerAbsenceAlert $alert): array
    {
        $student = $alert->student;
        $school = $student->school;

        // combined_types diisi command ketika dua jenis sholat alpa bersamaan;
        // kalau kosong, cukup jenis alert ini sendiri.
        $labels = $alert->combined_types
            ?: [$alert->prayer_type->shortLabel()];

        return [
            'nama_siswa' => $student->full_name,
            'kelas' => $student->classroom?->name ?? '-',
            'nama_sekolah' => $school?->name ?? 'Sekolah',
            'jenis_sholat' => $this->joinIndonesian($labels),
            'jumlah_hari' => (string) $alert->streak_length,
            'ambang' => (string) ($school?->getSetting('prayer_absence_threshold') ?? 3),
            'tanggal_mulai' => $alert->streak_start_date->translatedFormat('d F Y'),
            'tanggal_terakhir' => $alert->streak_last_date->translatedFormat('d F Y'),
            'daftar_tanggal' => $alert->streak_start_date->translatedFormat('d F Y')
                .' s/d '.$alert->streak_last_date->translatedFormat('d F Y'),
        ];
    }

    /**
     * @param  array<int, string>  $items
     */
    private function joinIndonesian(array $items): string
    {
        if (count($items) <= 1) {
            return $items[0] ?? '-';
        }

        $last = array_pop($items);

        return implode(', ', $items).' dan '.$last;
    }
}
