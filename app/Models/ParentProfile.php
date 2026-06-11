<?php

namespace App\Models;

use App\Enums\ParentRelation;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ParentProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParentProfile extends Model
{
    /** @use HasFactory<ParentProfileFactory> */
    use BelongsToSchool, HasFactory, HasUlids;

    protected $fillable = [
        'school_id',
        'user_id',
        'nik',
        'whatsapp_number',
        'telegram_chat_id',
        'email',
        'relation',
        'occupation',
        'address',
        'city',
    ];

    protected function casts(): array
    {
        return [
            'relation' => ParentRelation::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
