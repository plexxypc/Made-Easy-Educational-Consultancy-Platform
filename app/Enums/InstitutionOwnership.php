<?php

namespace App\Enums;

use App\Support\EnumInput;
use Filament\Support\Contracts\HasLabel;

/**
 * Who owns an institution (course & cut-off search).
 */
enum InstitutionOwnership: string implements HasLabel
{
    case Federal = 'federal';
    case State = 'state';
    case Private = 'private';

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Federal => 'Federal',
            self::State => 'State',
            self::Private => 'Private',
        };
    }

    /**
     * Read a CSV cell ("Federal", "federal", "PRIVATE").
     *
     * @param  string  $input
     * @return self|null Null if not recognised.
     */
    public static function from_input(string $input): ?self
    {
        return EnumInput::match(self::cases(), EnumInput::key($input));
    }
}
