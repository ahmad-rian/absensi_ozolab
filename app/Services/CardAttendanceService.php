<?php

namespace App\Services;

use App\Models\CardAttendance;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Support\SchoolTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CardAttendanceService
{
    public const QR_SOURCE = '__attendance';

    /** Mode satu-link: scan pertama masuk, scan berikutnya pulang. */
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

    /** @return array{attendance: CardAttendance, participant: CardFormSubmission, duplicate: bool, mode: string} */
    public function scan(CardForm $form, string $token, string $mode): array
    {
        abort_unless($form->is_active, 403, 'Layout ini sedang dinonaktifkan.');
        $mentah = $token;
        $token = $this->normalizeToken($token);
        if ($token === null) {
            $pemilik = $this->pemilikKartuLama($form, $mentah);

            throw ValidationException::withMessages(['token' => $pemilik
                ? 'Kartu lama milik '.$this->participantName($pemilik).' — belum dicetak ulang. Ambil kartu baru dari panitia.'
                : 'QR di kartu ini berisi data peserta, bukan QR absensi. Generate ulang kartu dari menu Absensi peserta lalu cetak ulang.']);
        }

        return DB::transaction(function () use ($form, $token, $mode): array {
            $participant = CardFormSubmission::where('card_form_id', $form->id)->whereKey(substr($token, 3, 26))->lockForUpdate()->first();
            if (! $participant || ! hash_equals($this->qrToken($participant), $token)) {
                throw ValidationException::withMessages(['token' => 'Kartu tidak terdaftar pada layout ini.']);
            }
            $record = CardAttendance::firstOrNew(['card_form_submission_id' => $participant->id, 'attendance_date' => SchoolTime::todayString()]);
            if ($record->exists && $record->status !== 'hadir') {
                throw ValidationException::withMessages(['token' => 'Status peserta sudah dicatat admin. Minta admin memperbaikinya terlebih dahulu.']);
            }
            if ($mode === self::MODE_OTOMATIS) {
                $mode = $this->modeOtomatis($record);
            }
            $column = $mode === 'pulang' ? 'check_out' : 'check_in';
            if ($mode === 'pulang' && ! $record->check_in) {
                throw ValidationException::withMessages(['token' => 'Peserta belum scan masuk hari ini. Pilih mode Masuk terlebih dahulu.']);
            }
            $duplicate = (bool) $record->{$column};
            if (! $duplicate) {
                $record->status = 'hadir';
                $record->{$column} = SchoolTime::now()->format('H:i:s');
                $record->save();
            }

            return ['attendance' => $record, 'participant' => $participant, 'duplicate' => $duplicate, 'mode' => $mode];
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

    /**
     * Masuk kalau belum ada scan masuk hari ini; pulang kalau sudah dan
     * jedanya cukup. Di dalam jeda, scan dianggap pengulangan scan masuk.
     */
    private function modeOtomatis(CardAttendance $record): string
    {
        if (! $record->check_in || $record->check_out) {
            return $record->check_out ? 'pulang' : 'masuk';
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
