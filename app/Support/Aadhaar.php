<?php

namespace App\Support;

/**
 * Aadhaar tokenization helper.
 *
 * Compliance requirement: raw Aadhaar numbers must NEVER be persisted.
 * We store a salted SHA-256 token + the last 4 digits for display.
 */
final class Aadhaar
{
    public static function isValid(string $aadhaar): bool
    {
        $aadhaar = preg_replace('/\s+/', '', $aadhaar) ?? '';

        return (bool) preg_match('/^\d{12}$/', $aadhaar);
    }

    public static function tokenize(string $aadhaar): string
    {
        $aadhaar = preg_replace('/\s+/', '', $aadhaar) ?? '';
        $salt = (string) config('app.aadhaar_salt', config('app.key', ''));

        return hash('sha256', $salt.'|'.$aadhaar);
    }

    public static function last4(string $aadhaar): string
    {
        $aadhaar = preg_replace('/\s+/', '', $aadhaar) ?? '';

        return substr($aadhaar, -4);
    }

    public static function mask(string $last4): string
    {
        return 'XXXX-XXXX-'.$last4;
    }
}
