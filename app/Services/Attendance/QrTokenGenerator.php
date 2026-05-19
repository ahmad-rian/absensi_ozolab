<?php

namespace App\Services\Attendance;

use App\Models\Student;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrTokenGenerator
{
    public function generate(Student $student): string
    {
        $secret = config('attendance.qr_token_secret');
        $payload = $student->id.'|'.now()->timestamp.'|'.bin2hex(random_bytes(16));
        $hmac = hash_hmac('sha256', $payload, $secret);

        $token = substr(base64_encode($hmac.':'.$payload), 0, 64);
        $token = str_replace(['+', '/', '='], ['-', '_', ''], $token);

        $student->update([
            'qr_token' => $token,
            'qr_issued_at' => now(),
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
