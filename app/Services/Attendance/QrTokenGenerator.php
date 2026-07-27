<?php

namespace App\Services\Attendance;

use App\Models\Student;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrTokenGenerator
{
    /**
     * Bentuk token QR: "<identity>.<signature>", identity = NISN (fallback NIS)
     * supaya terbaca dari QR, signature = potongan HMAC ber-nonce acak.
     *
     * Catatan penting: verifikasi TIDAK menghitung ulang HMAC — `verify()`
     * hanya melakukan lookup kolom `qr_token`. Jadi ketahanan token terhadap
     * pemalsuan sepenuhnya berasal dari nonce 64 bit CSPRNG, bukan dari
     * rahasianya. Jangan mengandalkan `attendance.qr_token_secret` sebagai
     * kontrol keamanan; merotasinya tidak berefek apa pun.
     */
    public function generate(Student $student): string
    {
        $identity = $student->nisn ?: $student->nis;
        $issuedAt = now();
        $secret = config('attendance.qr_token_secret');
        $nonce = bin2hex(random_bytes(8));

        $signature = substr(hash_hmac('sha256', $identity.'|'.$issuedAt->timestamp.'|'.$nonce, $secret), 0, 24);
        $token = substr($identity.'.'.$signature, 0, 64);

        $student->update([
            'qr_token' => $token,
            'qr_issued_at' => $issuedAt,
        ]);

        return $token;
    }

    public function verify(string $token): ?Student
    {
        return Student::where('qr_token', $token)
            ->where('is_active', true)
            ->first();
    }

    public function renderSvg(Student $student): string
    {
        if (! $student->qr_token) {
            $this->generate($student);
            $student->refresh();
        }

        $renderer = new ImageRenderer(
            new RendererStyle(300, 2),
            new SvgImageBackEnd,
        );

        $writer = new Writer($renderer);

        return $writer->writeString($student->qr_token);
    }

    public function rotate(Student $student): string
    {
        $newToken = $this->generate($student);

        $student->update(['qr_rotated_at' => now()]);

        return $newToken;
    }
}
