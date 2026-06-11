<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Reduce a phone number to its bare national form so different formats
     * (0812…, +62812…, 62812…, 812…) all compare equal.
     */
    public static function normalize(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($digits, '62')) {
            return substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return ltrim($digits, '0');
        }

        return $digits;
    }
}
