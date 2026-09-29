<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardAttendance extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['card_form_submission_id', 'attendance_date', 'status', 'check_in', 'check_out', 'note', 'recorded_by'];

    protected function casts(): array
    {
        return ['attendance_date' => 'date'];
    }

    protected function attendanceDate(): Attribute
    {
        return Attribute::make(
            set: fn (string|\DateTimeInterface $value): string => Carbon::parse($value)->format('Y-m-d'),
        );
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(CardFormSubmission::class, 'card_form_submission_id');
    }
}
