<?php

namespace App\Jobs;

use App\Models\Attendance;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAttendanceNotifications implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(
        public Attendance $attendance,
    ) {}

    public function uniqueId(): string
    {
        return 'attendance-notify-'.$this->attendance->id;
    }

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $allSucceeded = $dispatcher->dispatchAttendance($this->attendance, $this->attempts());

        if (! $allSucceeded && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 600);
        }
    }
}
