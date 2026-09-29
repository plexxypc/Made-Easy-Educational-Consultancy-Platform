<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

/**
 * Name + slug input pair: the slug follows the name until an admin edits it by hand.
 */
class SlugField
{
    /**
     * Name input that keeps the slug in sync while the slug is still auto-generated.
     *
     * @param  string  $name_field
     * @param  string  $slug_field
     * @return TextInput
     */
    public static function name_input(string $name_field = 'name', string $slug_field = 'slug'): TextInput
    {
        return TextInput::make($name_field)
            ->required()
            ->maxLength(255)
            ->live(onBlur: true)
            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) use ($slug_field): void {
                $current_slug = (string) $get($slug_field);

                // Only overwrite a slug that still matches the previous name (i.e. never hand-edited).
                if ($current_slug !== '' && $current_slug !== Str::slug((string) $old)) {
                    return;
                }

                $set($slug_field, Str::slug((string) $state));
            });
    }

    /**
     * Slug input: lowercase, hyphenated, unique per table.
     *
     * @param  string  $slug_field
     * @return TextInput
     */
    public static function slug_input(string $slug_field = 'slug'): TextInput
    {
        return TextInput::make($slug_field)
            ->required()
            ->maxLength(255)
            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
            ->validationMessages(['regex' => 'Use lowercase letters, numbers and single hyphens only.'])
            ->unique(ignoreRecord: true)
            ->helperText('Used in URLs. Filled in from the name automatically; edit if needed.');
    }
}
