<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Naira <-> kobo conversion for UI inputs. Storage is always integer kobo.
 *
 * Conversion is done on the decimal string, not via floats, so ₦0.29 is
 * exactly 29 kobo.
 */
class Money
{
    /**
     * Validation regex for a naira amount typed by a user: digits, optional 1–2 decimals.
     *
     * @var string
     */
    public const NAIRA_INPUT_REGEX = '/^\d{1,9}(\.\d{1,2})?$/';

    /**
     * Convert a naira amount (e.g. "6500.50") to integer kobo (650050).
     *
     * @param  string|int|float|null  $naira
     * @return int
     *
     * @throws InvalidArgumentException  If the value is not a valid non-negative amount.
     */
    public static function naira_to_kobo(string|int|float|null $naira): int
    {
        if ($naira === null || $naira === '') {
            return 0;
        }

        $normalised = is_float($naira) ? number_format($naira, 2, '.', '') : trim((string) $naira);

        if (preg_match(self::NAIRA_INPUT_REGEX, $normalised) !== 1) {
            throw new InvalidArgumentException("Invalid naira amount [{$normalised}].");
        }

        [$whole, $fraction] = array_pad(explode('.', $normalised, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    /**
     * Convert integer kobo to a naira string for form inputs (650050 -> "6500.50", 650000 -> "6500").
     *
     * @param  int|null  $kobo
     * @return string|null
     */
    public static function kobo_to_naira(?int $kobo): ?string
    {
        if ($kobo === null) {
            return null;
        }

        $naira = intdiv($kobo, 100);
        $remainder = $kobo % 100;

        return $remainder === 0 ? (string) $naira : sprintf('%d.%02d', $naira, $remainder);
    }

    /**
     * Format kobo for display, e.g. 650000 -> "₦6,500.00" (or "₦6,500" with $hide_zero_kobo).
     *
     * @param  int|null  $kobo
     * @param  bool  $hide_zero_kobo  Drop ".00" for whole-naira amounts (public pages).
     * @return string
     */
    public static function format(?int $kobo, bool $hide_zero_kobo = false): string
    {
        $kobo ??= 0;
        $naira = '₦'.number_format(intdiv($kobo, 100));

        if ($hide_zero_kobo && $kobo % 100 === 0) {
            return $naira;
        }

        return $naira.'.'.sprintf('%02d', $kobo % 100);
    }
}
