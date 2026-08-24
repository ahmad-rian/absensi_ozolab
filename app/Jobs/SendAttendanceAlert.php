<?php

namespace App\Jobs;

use App\Models\AttendanceAlert;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAttendanceAlert implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(
        public AttendanceAlert $alert,
    ) {}

    public function uniqueId(): string
    {
        return 'attendance-alert-'.$this->alert->id;
    }

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $succeeded = $dispatcher->dispatchAttendanceAlert($this->alert, $this->attempts());

        if (! $succeeded && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 600);
        }
    }
}
