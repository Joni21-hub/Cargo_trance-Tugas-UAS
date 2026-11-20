<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Iso6346Rule implements ValidationRule
{
    /**
     * ISO 6346 letter values.
     * Note: Multiples of 11 (11, 22, 33) are omitted according to ISO 6346 standard.
     */
    protected const CHAR_VALUES = [
        'A' => 10, 'B' => 12, 'C' => 13, 'D' => 14, 'E' => 15, 'F' => 16, 'G' => 17, 'H' => 18, 'I' => 19,
        'J' => 20, 'K' => 21, 'L' => 23, 'M' => 24, 'N' => 25, 'O' => 26, 'P' => 27, 'Q' => 28, 'R' => 29,
        'S' => 30, 'T' => 31, 'U' => 32, 'V' => 34, 'W' => 35, 'X' => 36, 'Y' => 37, 'Z' => 38,
        '0' => 0,  '1' => 1,  '2' => 2,  '3' => 3,  '4' => 4,  '5' => 5,  '6' => 6,  '7' => 7,  '8' => 8,  '9' => 9,
    ];

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail("The {$attribute} must be a valid string.");
            return;
        }

        $cleanValue = strtoupper(trim(str_replace([' ', '-'], '', $value)));

        // ISO 6346 container number consists of 4 letters + 6 digits serial + 1 check digit = 11 characters
        if (!preg_match('/^[A-Z]{4}\d{7}$/', $cleanValue)) {
            $fail("The {$attribute} does not match the ISO 6346 format (e.g. CSQU3054383: 4 letters followed by 7 digits).");
            return;
        }

        $expectedCheckDigit = self::calculateCheckDigit(substr($cleanValue, 0, 10));
        $actualCheckDigit = (int) $cleanValue[10];

        if ($expectedCheckDigit !== $actualCheckDigit) {
            $fail("The {$attribute} has an invalid ISO 6346 check digit. Expected {$expectedCheckDigit}, got {$actualCheckDigit}.");
        }
    }

    /**
     * Calculate ISO 6346 check digit for the first 10 characters.
     */
    public static function calculateCheckDigit(string $first10Chars): int
    {
        $first10Chars = strtoupper($first10Chars);
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $char = $first10Chars[$i];
            $charValue = self::CHAR_VALUES[$char] ?? 0;
            $weight = 1 << $i; // 2^i
            $sum += $charValue * $weight;
        }

        $remainder = $sum % 11;

        // If remainder is 10, check digit is 0
        return $remainder % 10;
    }

    /**
     * Validate an ISO 6346 string directly.
     */
    public static function isValid(string $containerNumber): bool
    {
        $clean = strtoupper(trim(str_replace([' ', '-'], '', $containerNumber)));
        if (!preg_match('/^[A-Z]{4}\d{7}$/', $clean)) {
            return false;
        }

        return self::calculateCheckDigit(substr($clean, 0, 10)) === (int) $clean[10];
    }
}
