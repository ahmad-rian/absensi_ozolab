<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\School;
use App\Models\Student;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Aturan "siapa yang tidak hadir hari itu" yang dipakai bersama oleh sapuan
 * peringatan WA dan sapuan poin pelanggaran. Disatukan supaya keduanya tidak
 * bisa menilai hari libur atau jendela tutup secara berbeda.
 *
 * Semua query melewati global scope sekolah karena dipanggil dari console.
 */
class DaftarKetidakhadiran
{
    /**
     * Jadwal aktif sekolah untuk hari yang dinilai. Kosong = bukan hari sekolah.
     *
     * @return Collection<int, AttendanceSchedule>
     */
    public function jadwal(School $school, Carbon $date): Collection
    {
        return AttendanceSchedule::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->where('day_of_week', $date->dayOfWeekIso)
            ->where('is_active', true)
            ->get();
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
    public function jendelaTutup(Collection $schedules, string $column, Carbon $date): bool
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
     * terdaftar di jadwal — bukan seluruh siswa membolos bersamaan.
     */
    public function adaCheckIn(School $school, Carbon $date): bool
    {
        return Attendance::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->whereDate('attendance_date', $date->toDateString())
            ->where('type', AttendanceType::CheckIn->value)
            ->exists();
    }

    /**
     * @return Collection<int, Attendance>
     */
    public function terlambat(School $school, Carbon $date): Collection
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
     * Siswa yang tidak punya absen masuk hari itu.
     *
     * `IZIN` dan `SAKIT` adalah ketidakhadiran berizin dan tidak pernah jadi
     * sasaran. `ALPA` yang diisi admin manual justru ikut, karena itu memang
     * pernyataan eksplisit bahwa siswanya tidak datang.
     *
     * @param  Collection<int, AttendanceSchedule>  $schedules
     * @return Collection<int, Student>
     */
    public function tidakHadir(School $school, Collection $schedules, Carbon $date): Collection
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
}
