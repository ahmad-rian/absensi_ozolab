<?php

namespace App\Rules;

use App\Enums\SchoolFeature;
use App\Models\School;
use App\Support\SchoolFeatures;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Menolak `school_id` yang fiturnya sedang dimatikan.
 *
 * Halaman publik seperti /daftar menampilkan banyak sekolah sekaligus sehingga
 * tidak bisa dijaga middleware `feature:` (yang butuh satu sekolah). Menyaring
 * daftar di UI saja tidak cukup — `school_id` masih bisa dikirim langsung.
 */
class SchoolFeatureEnabled implements ValidationRule
{
    public function __construct(
        private readonly SchoolFeature $feature,
        private readonly string $message = 'Sekolah ini sedang tidak menerima pendaftaran online.',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $school = School::find($value);

        if (! $school || SchoolFeatures::for($school)->disabled($this->feature)) {
            $fail($this->message);
        }
    }
}
