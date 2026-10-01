<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The editable bits of site copy (admin: Site settings). Adding a case adds
 * a row to the settings screen automatically (SiteSetting::ensure_all()).
 * Contact details stay in .env (config('madeeasy.contact')).
 */
enum SiteSettingKey: string implements HasLabel
{
    case StudentsAssisted = 'students_assisted';
    case StudentsAssistedLabel = 'students_assisted_label';
    case AboutBody = 'about_body';

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::StudentsAssisted => 'Students assisted (homepage figure)',
            self::StudentsAssistedLabel => 'Students assisted (wording)',
            self::AboutBody => 'About Us page text',
        };
    }

    /**
     * Guidance shown under the field.
     *
     * @return string
     */
    public function help(): string
    {
        return match ($this) {
            self::StudentsAssisted => 'The trust figure on the homepage, e.g. "100+" or "1,250". Leave empty to hide it until you have a confirmed number.',
            self::StudentsAssistedLabel => 'Shown under the figure.',
            self::AboutBody => 'Plain text. Leave a blank line between paragraphs.',
        };
    }

    /**
     * Value used while the setting is empty. Null hides the item.
     *
     * The students-assisted figure has no default on purpose: a made-up
     * number on a live site would be a false claim.
     *
     * @return string|null
     */
    public function default(): ?string
    {
        return match ($this) {
            self::StudentsAssisted => null,
            self::StudentsAssistedLabel => 'Students Assisted With Admission',
            self::AboutBody => "Made Easy Educational Consultancy helps students and parents through every step of their educational journey, from exam registration and result checking to admission and documentation.\n\n"
                ."We handle WAEC, NECO, GCE and NABTEB registration, JAMB services, admission guidance for universities, polytechnics and colleges of education, and document services such as certificates, affidavits and verification.\n\n"
                ."Our aim is simple: clear prices, honest advice, and a team that follows your request through until it's done.",
        };
    }

    /**
     * Whether the field is a multi-line text box.
     *
     * @return bool
     */
    public function isLongText(): bool
    {
        return $this === self::AboutBody;
    }

    /**
     * Validation rules for the value (empty is always allowed).
     *
     * @return array<int, string>
     */
    public function rules(): array
    {
        return match ($this) {
            self::StudentsAssisted => ['max:12', 'regex:/^\d[\d,]*\+?$/'],
            self::StudentsAssistedLabel => ['max:60'],
            self::AboutBody => ['max:5000'],
        };
    }
}
