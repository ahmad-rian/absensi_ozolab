<?php

namespace App\Services;

use App\Models\CardAttendance;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Models\CardScan;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CardAttendanceService
{
    public const QR_SOURCE = '__attendance';

    /** Mode satu-link: scan pertama masuk, scan setelah jeda memperbarui pulang. */
    public const MODE_OTOMATIS = 'otomatis';

    /**
     * Jarak minimal sejak scan masuk sebelum scan berikutnya dianggap pulang.
     * Tanpa jeda ini, kartu yang tidak sengaja ditembak dua kali di antrean
     * langsung tercatat pulang.
     */
    public const JEDA_PULANG_MENIT = 30;

    public function qrToken(CardFormSubmission $participant): string
    {
        $identity = 'kb.'.$participant->id;
        $signature = hash_hmac('sha256', $identity.'|'.$participant->card_form_id, config('app.key'));

        return $identity.'.'.substr($signature, 0, 32);
    }

    public function participantName(CardFormSubmission $participant): string
    {
        $values = $participant->data ?? [];
        foreach (array_merge(['nama', 'name'], array_keys($values)) as $key) {
            if (is_scalar($values[$key] ?? null) && trim((string) $values[$key]) !== '') {
                return (string) $values[$key];
            }
        }

        return $participant->id;
    }

    /**
     * Setiap scan diterima dan dicatat ke riwayat (`card_scans`); rekap harian
     * menyimpan Masuk = scan pertama dan Pulang = scan terakhir.
     *
     * @return array{attendance: CardAttendance, participant: CardFormSubmission, duplicate: bool, mode: string, jenis: ?string, urutan: int}
     */
    public function scan(CardForm $form, string $token, string $mode): array
    {
        abort_unless($form->is_active, 403, 'Layout ini sedang dinonaktifkan.');
        $mentah = $token;
        $token = $this->normalizeToken($token);
        $kartuLama = null;
        if ($token === null) {
            $kartuLama = $this->pemilikKartuLama($form, $mentah);

            // Layout yang kartunya terlanjur dicetak massal boleh menerima
            // kartu lama (QR berisi No Porsi), asalkan pemiliknya tepat satu.
            if (! $kartuLama || ! $form->accept_legacy_qr) {
                throw ValidationException::withMessages(['token' => $kartuLama
                    ? 'Kartu lama milik '.$this->participantName($kartuLama).' — belum dicetak ulang. Ambil kartu baru dari panitia.'
                    : 'QR di kartu ini berisi data peserta, bukan QR absensi. Generate ulang kartu dari menu Absensi peserta lalu cetak ulang.']);
            }
        }

        return DB::transaction(function () use ($form, $token, $mode, $kartuLama): array {
            $participant = CardFormSubmission::where('card_form_id', $form->id)
                ->whereKey($kartuLama?->id ?? substr((string) $token, 3, 26))->lockForUpdate()->first();
            if (! $participant || (! $kartuLama && ! hash_equals($this->qrToken($participant), $token))) {
                throw ValidationException::withMessages(['token' => 'Kartu tidak terdaftar pada layout ini.']);
            }
            // Peserta program penyelenggara dicatat di `kehadirans` lewat link
            // scan programnya; tercatat di sini membuat riwayatnya terpecah.
            if ($participant->program_id !== null) {
                throw ValidationException::withMessages(['token' => 'Kartu ini milik program penyelenggara. Scan lewat link scan program tersebut.']);
            }
            $record = CardAttendance::firstOrNew(['card_form_submission_id' => $participant->id, 'attendance_date' => SchoolTime::todayString()]);
            if ($record->exists && $record->status !== 'hadir') {
                throw ValidationException::withMessages(['token' => 'Status peserta sudah dicatat admin. Minta admin memperbaikinya terlebih dahulu.']);
            }
            $sekarang = SchoolTime::now();
            $wall = $sekarang->format('Y-m-d H:i:s');
            // Scanner dobel / kartu ditempel dua kali: diabaikan tanpa menulis apa pun.
            if (CardScan::baruSaja($participant->id, $wall)) {
                return ['attendance' => $record, 'participant' => $participant, 'duplicate' => true,
                    'mode' => $record->check_out ? 'pulang' : 'masuk', 'jenis' => null, 'urutan' => $this->urutanHariIni($participant)];
            }
            if ($mode === self::MODE_OTOMATIS) {
                $mode = $this->modeOtomatis($record);
            }
            if ($mode === 'pulang' && ! $record->check_in) {
                throw ValidationException::withMessages(['token' => 'Peserta belum scan masuk hari ini. Pilih mode Masuk terlebih dahulu.']);
            }
            // Masuk = scan pertama (tidak pernah ditimpa); Pulang = scan terakhir
            // (selalu diperbarui); scan di antaranya hanya masuk riwayat.
            $jenis = match (true) {
                $mode === 'pulang' => CardScan::JENIS_PULANG,
                ! $record->check_in => CardScan::JENIS_MASUK,
                default => CardScan::JENIS_SCAN,
            };
            if ($jenis !== CardScan::JENIS_SCAN) {
                $record->status = 'hadir';
                $record->{$jenis === CardScan::JENIS_PULANG ? 'check_out' : 'check_in'} = $sekarang->format('H:i:s');
                $record->save();
            }
            CardScan::create([
                'card_form_submission_id' => $participant->id,
                'card_form_id' => $form->id,
                'jenis' => $jenis,
                'scanned_at' => $wall,
            ]);

            return ['attendance' => $record, 'participant' => $participant, 'duplicate' => false,
                'mode' => $mode, 'jenis' => $jenis, 'urutan' => $this->urutanHariIni($participant)];
        }, 3);
    }

    /**
     * Nama peserta yang kartu LAMA-nya baru saja di-scan, untuk daftar cetak ulang.
     *
     * Kartu sebelum Okt 2026 menaruh kolom data (No Porsi) di QR-nya. Kartu itu
     * tetap tidak dicatat hadir — QR absensi harus bertanda tangan — tapi
     * operator perlu tahu kartu siapa yang masih lama.
     *
     * @return list<string>
     */
    public function kartuLamaHariIni(CardForm $form): array
    {
        $id = Cache::get(self::kunciKartuLama($form), []);

        return CardFormSubmission::where('card_form_id', $form->id)->whereKey($id)->get()
            ->map(fn (CardFormSubmission $p): string => $this->participantName($p))->sort()->values()->all();
    }

    /** Pemilik tunggal kartu lama di layout ini, atau null kalau tidak ada / ambigu. */
    private function pemilikKartuLama(CardForm $form, string $isi): ?CardFormSubmission
    {
        $isi = mb_strtolower(trim($isi));
        if ($isi === '' || mb_strlen($isi) > 64) {
            return null;
        }

        $cocok = CardFormSubmission::where('card_form_id', $form->id)->get(['id', 'card_form_id', 'data'])
            ->filter(fn (CardFormSubmission $p): bool => collect($p->data ?? [])
                ->contains(fn ($nilai): bool => is_scalar($nilai) && mb_strtolower(trim((string) $nilai)) === $isi));

        if ($cocok->count() !== 1) {
            return null;
        }

        $pemilik = $cocok->first();
        $kunci = self::kunciKartuLama($form);
        Cache::put($kunci, array_values(array_unique([...Cache::get($kunci, []), $pemilik->id])), now()->addDays(2));

        return $pemilik;
    }

    private static function kunciKartuLama(CardForm $form): string
    {
        return 'kartu-lama:'.$form->id.':'.SchoolTime::todayString();
    }

    /** Scan ke berapa peserta ini hari ini (termasuk yang barusan). */
    private function urutanHariIni(CardFormSubmission $participant): int
    {
        return CardScan::query()
            ->where('card_form_submission_id', $participant->id)
            ->where('scanned_at', '>=', SchoolTime::todayString().' 00:00:00')
            ->count();
    }

    /**
     * Masuk kalau belum ada scan masuk hari ini; pulang kalau jedanya sejak
     * masuk sudah cukup (scan berikutnya memperbarui jam pulang). Di dalam
     * jeda, scan hanya masuk riwayat.
     */
    private function modeOtomatis(CardAttendance $record): string
    {
        if (! $record->check_in) {
            return 'masuk';
        }

        $masuk = Carbon::parse(SchoolTime::todayString().' '.$record->check_in, SchoolTime::now()->getTimezone());

        return $masuk->diffInMinutes(SchoolTime::now()) >= self::JEDA_PULANG_MENIT ? 'pulang' : 'masuk';
    }

    /**
     * Bentuk baku token hasil scan, atau null kalau bukan QR absensi.
     *
     * Barcode gun dengan Caps Lock atau setelan huruf besar mengirim token
     * yang sama dengan huruf berbeda. ULID peserta disimpan huruf kecil oleh
     * `HasUlids` dan tanda tangannya heksadesimal kecil, jadi seluruh token
     * aman dikecilkan tanpa melemahkan pemeriksaan tanda tangan.
     */
    private function normalizeToken(string $token): ?string
    {
        $token = strtolower(trim($token));

        return preg_match('/\Akb\.[0-9a-hjkmnp-tv-z]{26}\.[a-f0-9]{32}\z/', $token) ? $token : null;
    }
}
