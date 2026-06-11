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
     * Build the QR token as "<identity>.<signature>" where identity is the
     * student's NISN (fallback to NIS) so the NISN is readable from the QR,
     * and the signature is an HMAC keyed by a secret + random nonce. The NISN
     * is visible but the token cannot be forged without the secret, and every
     * call produces a fresh signature so rotation always invalidates old QRs.
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
