<?php

namespace App\Services\Perizinan;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\AttendanceSchedule;
use App\Models\PengajuanIzin;
use App\Models\Student;
use App\Models\User;
use App\Services\ImageConverter;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur pengajuan izin/sakit siswa: simpan pengajuan, lalu setujui (mengisi
 * absensi) atau tolak. Dipakai portal orang tua, form publik, dan halaman
 * Data Perizinan sekolah.
 */
class PerizinanService
{
    /** Hari Senin–Jumat dipakai bila sekolah belum punya jadwal sama sekali. */
    private const HARI_BAWAAN = [1, 2, 3, 4, 5];

    public function __construct(private ImageConverter $converter) {}

    /**
     * @param  array{jenis: string, tanggal_mulai: string, tanggal_selesai: string, alasan: string, pengirim_nama?: ?string, pengirim_telepon?: ?string}  $data
     */
    public function ajukan(Student $siswa, array $data, string $sumber, ?UploadedFile $bukti = null, ?string $parentProfileId = null): PengajuanIzin
    {
        // Bukti foto disimpan privat (data kesehatan) dan diperkecil ke webp.
        $buktiPath = $bukti ? $this->converter->storeAsWebp($bukti, "perizinan/{$siswa->school_id}", 'local', 80, 1600) : null;

        return PengajuanIzin::withoutGlobalScope('school')->create([
            'school_id' => $siswa->school_id,
            'student_id' => $siswa->id,
            'parent_profile_id' => $parentProfileId,
            'sumber' => $sumber,
            'jenis' => $data['jenis'],
            'tanggal_mulai' => substr($data['tanggal_mulai'], 0, 10),
            'tanggal_selesai' => substr($data['tanggal_selesai'], 0, 10),
            'alasan' => trim($data['alasan']),
            'bukti_path' => $buktiPath,
            'pengirim_nama' => $data['pengirim_nama'] ?? null,
            'pengirim_telepon' => $data['pengirim_telepon'] ?? null,
            'status' => PengajuanIzin::MENUNGGU,
        ]);
    }

    /**
     * Setujui dan isi absensi Izin/Sakit di setiap hari sekolah dalam rentang.
     * Hari yang sudah Hadir/Terlambat tidak ditimpa; Alpa diganti.
     *
     * @return int jumlah hari yang dicatat
     */
    public function setujui(PengajuanIzin $izin, User $oleh): int
    {
        $this->pastikanMenunggu($izin);

        return DB::transaction(function () use ($izin, $oleh) {
            $siswa = Student::withoutGlobalScope('school')->findOrFail($izin->student_id);
            $status = AttendanceStatus::from($izin->jenis);
            $catatan = ($status === AttendanceStatus::Sakit ? 'Sakit' : 'Izin').' (pengajuan disetujui): '.$izin->alasan;
            $hariSekolah = $this->hariSekolah($siswa);
            $dicatat = 0;

            foreach (CarbonPeriod::create($izin->tanggal_mulai, $izin->tanggal_selesai) as $tanggal) {
                if (! in_array($tanggal->dayOfWeekIso, $hariSekolah, true)) {
                    continue;
                }

                $ada = Attendance::withoutGlobalScope('school')
                    ->where('student_id', $siswa->id)
                    ->whereDate('attendance_date', $tanggal->toDateString())
                    ->where('type', AttendanceType::CheckIn)
                    ->first();

                if ($ada && in_array($ada->status, [AttendanceStatus::Hadir, AttendanceStatus::Terlambat], true)) {
                    continue;
                }

                if ($ada) {
                    $ada->forceFill(['status' => $status, 'notes' => $catatan, 'recorded_by' => $oleh->id])->save();
                } else {
                    Attendance::withoutGlobalScope('school')->create([
                        'school_id' => $siswa->school_id,
                        'student_id' => $siswa->id,
                        'attendance_date' => $tanggal->toDateString(),
                        'type' => AttendanceType::CheckIn,
                        'status' => $status,
                        'recorded_at' => $tanggal->toDateString().' 07:00:00',
                        'recorded_by' => $oleh->id,
                        'device_id' => 'IZIN',
                        'notes' => $catatan,
                    ]);
                }
                $dicatat++;
            }

            $izin->forceFill([
                'status' => PengajuanIzin::DISETUJUI,
                'reviewed_by' => $oleh->id,
                'reviewed_at' => now(),
                'hari_dicatat' => $dicatat,
            ])->save();

            return $dicatat;
        });
    }

    public function tolak(PengajuanIzin $izin, User $oleh, string $catatan): void
    {
        $this->pastikanMenunggu($izin);

        $izin->forceFill([
            'status' => PengajuanIzin::DITOLAK,
            'catatan_review' => $catatan,
            'reviewed_by' => $oleh->id,
            'reviewed_at' => now(),
        ])->save();
    }

    /**
     * Hari (ISO 1=Senin) yang punya jadwal absensi aktif untuk kelas siswa
     * atau jadwal umum sekolah.
     *
     * @return array<int, int>
     */
    public function hariSekolah(Student $siswa): array
    {
        $hari = AttendanceSchedule::withoutGlobalScope('school')
            ->where('school_id', $siswa->school_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('classroom_id')->orWhere('classroom_id', $siswa->classroom_id))
            ->pluck('day_of_week')
            ->map(fn ($h) => (int) $h)
            ->unique()
            ->values()
            ->all();

        return $hari ?: self::HARI_BAWAAN;
    }

    /**
     * Aturan validasi bersama portal dan form publik.
     *
     * @return array<string, mixed>
     */
    public static function aturan(): array
    {
        $hariIni = CarbonImmutable::now('Asia/Jakarta')->toDateString();

        return [
            'jenis' => ['required', 'in:IZIN,SAKIT'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.CarbonImmutable::parse($hariIni)->subDays(7)->toDateString()],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai', function ($attr, $nilai, $gagal) {
                $mulai = request('tanggal_mulai');
                if (is_string($mulai) && strtotime($mulai) && CarbonImmutable::parse($mulai)->diffInDays(CarbonImmutable::parse($nilai)) >= PengajuanIzin::MAKS_HARI) {
                    $gagal('Rentang izin maksimal '.PengajuanIzin::MAKS_HARI.' hari. Ajukan lagi untuk hari berikutnya.');
                }
            }],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'bukti' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function pesan(): array
    {
        return [
            'jenis.required' => 'Pilih Izin atau Sakit.',
            'tanggal_mulai.after_or_equal' => 'Tanggal mulai paling lama 7 hari ke belakang.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'alasan.required' => 'Tuliskan alasan singkat.',
            'bukti.image' => 'Bukti harus berupa foto.',
            'bukti.max' => 'Ukuran foto bukti maksimal 8 MB.',
        ];
    }

    private function pastikanMenunggu(PengajuanIzin $izin): void
    {
        if (! $izin->isMenunggu()) {
            throw ValidationException::withMessages(['status' => 'Pengajuan ini sudah diproses sebelumnya.']);
        }
    }
}
