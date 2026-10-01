<?php

namespace App\Support;

use BackedEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Forgiving matching of typed or imported text to an enum case, by value or
 * label: case, spaces, hyphens and full stops don't matter.
 */
final class EnumInput
{
    /**
     * Normalised comparison key, e.g. "Akwa-Ibom " -> "akwa_ibom".
     *
     * @param  string  $input
     * @return string
     */
    public static function key(string $input): string
    {
        $key = mb_strtolower(trim($input));
        $key = (string) preg_replace('/[.\']/u', '', $key);

        return trim((string) preg_replace('/[\s\-_\/]+/u', '_', $key), '_');
    }

    /**
     * The case whose value or label has this key.
     *
     * @template T of BackedEnum
     *
     * @param  array<int, T>  $cases
     * @param  string  $key  From EnumInput::key().
     * @return T|null
     */
    public static function match(array $cases, string $key): ?BackedEnum
    {
        if ($key === '') {
            return null;
        }

        foreach ($cases as $case) {
            $label = $case instanceof HasLabel ? (string) $case->getLabel() : '';

            if (self::key((string) $case->value) === $key || self::key($label) === $key) {
                return $case;
            }
        }

        return null;
    }
}
