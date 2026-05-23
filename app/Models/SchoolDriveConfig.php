<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class SchoolDriveConfig extends Model
{
    use BelongsToSchool, HasUlids;

    protected $fillable = [
        'school_id',
        'service_account_json',
        'root_folder_id',
        'cards_folder_id',
        'albums_folder_id',
        'parents_folder_id',
        'is_active',
        'last_tested_at',
    ];

    protected function casts(): array
    {
        return [
            'service_account_json' => 'encrypted',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getServiceAccountCredentials(): ?array
    {
        if (! $this->service_account_json) {
            return null;
        }

        $decoded = json_decode($this->service_account_json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
