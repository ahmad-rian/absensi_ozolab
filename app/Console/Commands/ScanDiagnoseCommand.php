<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\Student;
use App\Services\Attendance\StudentLookup;
use Illuminate\Console\Command;

/**
 * Menjawab "kenapa kartu ini ditolak di gerbang" dalam sekali jalan.
 *
 * Empat syarat harus benar semua agar sebuah kartu dikenali: sekolahnya cocok,
 * siswanya aktif, tidak di-soft-delete, dan tokennya masih yang sama seperti
 * saat kartu dicetak. Pesan di layar gerbang sengaja sama untuk keempatnya,
 * jadi tanpa perintah ini satu-satunya cara mengetahui bedanya adalah menyusun
 * query tinker satu per satu — yang sudah tiga kali harus dilakukan.
 *
 * BACA SAJA. Tidak menulis apa pun.
 */
class ScanDiagnoseCommand extends Command
{
    protected $signature = 'scan:diagnosa
        {--sekolah= : Slug atau scanner_token sekolah gerbangnya}
        {--nis= : Periksa satu siswa lewat NIS atau NISN}
        {--token= : Periksa token hasil bacaan (masuk riwayat shell — pakai --nis bila bisa)}';

    protected $description = 'Periksa kenapa sebuah kartu ditolak di gerbang sekolah';

    public function handle(): int
    {
        $school = $this->cariSekolah();

        if (! $school) {
            return self::FAILURE;
        }

        $this->line('');
        $this->info(sprintf('Sekolah: %s', $school->name));
        $this->line(sprintf('  aktif        : %s', $school->is_active ? 'ya' : 'TIDAK — semua scan ditolak'));
        $this->line(sprintf('  slug         : %s', $school->slug));

        if ($nis = $this->option('nis')) {
            return $this->periksaSiswa($school, (string) $nis);
        }

        if ($token = $this->option('token')) {
            return $this->periksaToken($school, (string) $token);
        }

        return $this->ringkasan($school);
    }

    private function cariSekolah(): ?School
    {
        $kunci = (string) $this->option('sekolah');

        if ($kunci === '') {
            $this->error('Wajib memberi --sekolah (slug atau scanner_token).');

            return null;
        }

        $school = School::where('slug', $kunci)
            ->orWhere('scanner_token', $kunci)
            ->orWhere('scan_short_code', mb_strtolower($kunci))
            ->first();

        if (! $school) {
            $this->error(sprintf('Sekolah "%s" tidak ditemukan.', $kunci));
        }

        return $school;
    }

    private function periksaSiswa(School $school, string $nis): int
    {
        // withoutGlobalScopes + withTrashed disengaja: siswa yang di-soft-delete
        // atau berada di sekolah lain justru yang paling sering jadi jawabannya,
        // dan query biasa akan menyembunyikan mereka.
        $siswa = Student::withoutGlobalScopes()
            ->withTrashed()
            ->where(fn ($q) => $q->where('nis', $nis)->orWhere('nisn', $nis))
            ->get();

        if ($siswa->isEmpty()) {
            $this->line('');
            $this->error(sprintf('Tidak ada siswa dengan NIS/NISN "%s" di sekolah mana pun.', $nis));

            return self::SUCCESS;
        }

        foreach ($siswa as $r) {
            $this->line('');
            $this->info(sprintf('Siswa: %s', $r->full_name));
            $this->line(sprintf('  sekolah      : %s', $r->school_id === $school->id
                ? 'COCOK'
                : 'BEDA — '.(School::find($r->school_id)?->name ?? $r->school_id)));
            $this->line(sprintf('  is_active    : %s', $r->is_active ? 'ya' : 'TIDAK — kartunya tidak akan dikenali'));
            $this->line(sprintf('  dihapus      : %s', $r->deleted_at ? 'YA pada '.$r->deleted_at : 'tidak'));
            $this->line(sprintf('  qr_token     : %s', $r->qr_token
                ? sprintf('ada, %d karakter, bentuk %s', strlen($r->qr_token), $this->bentuk($r->qr_token))
                : 'KOSONG — kartunya tidak pernah bisa dipindai'));
            $this->line(sprintf('  qr_issued_at : %s', $r->qr_issued_at ?: '-'));
            $this->line(sprintf('  qr_rotated_at: %s', $r->qr_rotated_at
                ? $r->qr_rotated_at.' — kartu yang dicetak SEBELUM ini sudah mati'
                : 'belum pernah diputar'));
            $this->line(sprintf('  rfid_uid     : %s', $r->rfid_uid ?: '-'));
        }

        return self::SUCCESS;
    }

    private function periksaToken(School $school, string $token): int
    {
        $token = trim($token);

        $this->line('');
        $this->info(sprintf('Token: %d karakter, bentuk %s', strlen($token), $this->bentuk($token)));

        $bersih = StudentLookup::extractQrToken($token);

        if ($bersih !== null && $bersih !== $token) {
            $this->warn(sprintf('  Bacaan kotor: token sahnya "%s" (%d karakter). Pembaca kartunya menyisipkan karakter sampah.', $bersih, strlen($bersih)));
            $token = $bersih;
        }

        $pemilik = Student::withoutGlobalScopes()->withTrashed()->where('qr_token', $token)->first();

        if (! $pemilik) {
            $uid = StudentLookup::normalizeRfidUid($token);
            $pemilik = $uid === '' ? null : Student::withoutGlobalScopes()->withTrashed()->where('rfid_uid', $uid)->first();

            if ($pemilik) {
                $this->line('  Cocok sebagai UID RFID, bukan token QR.');
            }
        }

        $this->line('');

        if (! $pemilik) {
            $this->error('  tidak_ada_di_mana_pun — token ini tidak ada di sekolah mana pun.');
            $this->line('  Bacaan terpotong, atau kartunya dicetak sebelum tokennya diganti.');

            return self::SUCCESS;
        }

        $this->info(sprintf('  Pemilik: %s', $pemilik->full_name));

        if ($pemilik->school_id !== $school->id) {
            $this->error('  ada_di_sekolah_lain — '.(School::find($pemilik->school_id)?->name ?? $pemilik->school_id));
        } elseif ($pemilik->deleted_at) {
            $this->error('  siswa_dihapus pada '.$pemilik->deleted_at);
        } elseif (! $pemilik->is_active) {
            $this->error('  siswa_nonaktif');
        } else {
            $this->info('  Keempat syarat lolos. Kartu ini SEHARUSNYA dikenali di gerbang sekolah ini.');
        }

        return self::SUCCESS;
    }

    private function ringkasan(School $school): int
    {
        $dasar = fn () => Student::withoutGlobalScopes()->withTrashed()->where('school_id', $school->id);

        $this->line('');
        $this->line(sprintf('  siswa total      : %d', $dasar()->count()));
        $this->line(sprintf('  aktif & hidup    : %d', $dasar()->whereNull('deleted_at')->where('is_active', true)->count()));
        $this->line(sprintf('  dihapus          : %d', $dasar()->whereNotNull('deleted_at')->count()));
        $this->line(sprintf('  nonaktif         : %d', $dasar()->whereNull('deleted_at')->where('is_active', false)->count()));
        $this->line(sprintf('  punya qr_token   : %d', $dasar()->whereNotNull('qr_token')->count()));

        $tanpaToken = $dasar()->whereNull('deleted_at')->where('is_active', true)->whereNull('qr_token')->count();

        $this->line(sprintf('  aktif TANPA token: %d', $tanpaToken));

        if ($tanpaToken > 0) {
            $this->warn('  Siswa aktif tanpa qr_token: kartunya tidak akan pernah dikenali di gerbang.');
        }
        $this->line(sprintf('  punya rfid_uid   : %d', $dasar()->whereNotNull('rfid_uid')->count()));
        $this->line('');
        $this->line('Tambahkan --nis=... atau --token=... untuk memeriksa satu kartu.');

        return self::SUCCESS;
    }

    private function bentuk(string $nilai): string
    {
        return StudentLookup::extractQrToken($nilai) === $nilai ? 'token QR sah' : 'BUKAN bentuk token QR';
    }
}
