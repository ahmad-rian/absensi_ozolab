<?php

namespace App\Events;

use App\Models\Attendance;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentCheckedIn
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Attendance $attendance,
    ) {}
}
