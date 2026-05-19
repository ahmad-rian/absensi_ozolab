<?php

namespace App\Jobs;

use App\Models\Student;
use App\Services\Attendance\QrTokenGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateStudentQrToken implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Student $student,
    ) {}

    public function handle(QrTokenGenerator $generator): void
    {
        if (empty($this->student->qr_token)) {
            $generator->generate($this->student);
        }
    }
}
