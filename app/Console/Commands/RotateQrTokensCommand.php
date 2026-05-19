<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\Attendance\QrTokenGenerator;
use Illuminate\Console\Command;

class RotateQrTokensCommand extends Command
{
    protected $signature = 'qr:rotate
                            {--student= : Rotate token for a specific student ID}
                            {--all : Rotate tokens for all active students}';

    protected $description = 'Rotate QR tokens for students';

    public function handle(QrTokenGenerator $generator): int
    {
        if ($studentId = $this->option('student')) {
            $student = Student::find($studentId);

            if (! $student) {
                $this->error("Student with ID {$studentId} not found.");

                return self::FAILURE;
            }

            $generator->rotate($student);
            $this->info("QR token rotated for {$student->full_name}.");

            return self::SUCCESS;
        }

        if ($this->option('all')) {
            $count = 0;
            Student::where('is_active', true)->chunk(100, function ($students) use ($generator, &$count) {
                foreach ($students as $student) {
                    $generator->rotate($student);
                    $count++;
                }
            });

            $this->info("QR tokens rotated for {$count} students.");

            return self::SUCCESS;
        }

        $this->error('Please specify --student=ID or --all.');

        return self::FAILURE;
    }
}
