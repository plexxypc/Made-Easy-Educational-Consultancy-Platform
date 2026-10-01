<?php

namespace App\Enums;

use App\Support\EnumInput;
use Filament\Support\Contracts\HasLabel;

/**
 * Kind of tertiary institution (course & cut-off search).
 */
enum InstitutionType: string implements HasLabel
{
    case University = 'university';
    case Polytechnic = 'polytechnic';
    case CollegeOfEducation = 'college_of_education';

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::University => 'University',
            self::Polytechnic => 'Polytechnic',
            self::CollegeOfEducation => 'College of Education',
        };
    }

    /**
     * Read a CSV cell: the stored value or the label, any case, spaces or
     * hyphens for underscores ("College of Education", "college-of-education").
     * "COE" is accepted as the common short form.
     *
     * @param  string  $input
     * @return self|null Null if not recognised.
     */
    public static function from_input(string $input): ?self
    {
        $key = EnumInput::key($input);

        return $key === 'coe' ? self::CollegeOfEducation : EnumInput::match(self::cases(), $key);
    }
}
