<?php

namespace App\Jobs;

use App\Models\PrayerAbsenceAlert;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPrayerAbsenceNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(
        public PrayerAbsenceAlert $alert,
    ) {}

    public function uniqueId(): string
    {
        return 'prayer-absence-'.$this->alert->id;
    }

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $allSucceeded = $dispatcher->dispatchPrayerAbsence($this->alert, $this->attempts());

        if (! $allSucceeded && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 600);
        }
    }
}
