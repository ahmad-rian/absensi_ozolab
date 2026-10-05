<?php

namespace App\Services;

use App\Models\CardAttendance;
use App\Models\CardForm;
use App\Models\CardFormSubmission;
use App\Support\SchoolTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CardAttendanceService
{
    public const QR_SOURCE = '__attendance';

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

    /** @return array{attendance: CardAttendance, participant: CardFormSubmission, duplicate: bool} */
    public function scan(CardForm $form, string $token, string $mode): array
    {
        abort_unless($form->is_active, 403, 'Layout ini sedang dinonaktifkan.');
        $token = $this->normalizeToken($token);
        if ($token === null) {
            throw ValidationException::withMessages(['token' => 'QR di kartu ini berisi data peserta, bukan QR absensi. Generate ulang kartu dari menu Absensi peserta lalu cetak ulang.']);
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

            return ['attendance' => $record, 'participant' => $participant, 'duplicate' => $duplicate];
        }, 3);
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
