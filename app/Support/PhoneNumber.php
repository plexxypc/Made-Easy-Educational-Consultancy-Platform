<?php

namespace App\Support;

/**
 * Phone number comparison for order lookups.
 */
class PhoneNumber
{
    /**
     * Reduce a phone number to comparable digits.
     *
     * Spaces, dashes, brackets and "+" are dropped, and the Nigerian country
     * code is folded to the local form: "+234 803 123 4567" and "0803-123-4567"
     * both become "08031234567".
     *
     * @param  ?string  $phone
     * @return string
     */
    public static function normalise(?string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            return '0'.substr($digits, 3);
        }

        return $digits;
    }

    /**
     * Whether two phone numbers match after normalising (needs at least 7 digits).
     *
     * @param  ?string  $first
     * @param  ?string  $second
     * @return bool
     */
    public static function matches(?string $first, ?string $second): bool
    {
        $first_digits = self::normalise($first);

        return strlen($first_digits) >= 7 && hash_equals($first_digits, self::normalise($second));
    }
}
