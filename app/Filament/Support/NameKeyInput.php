<?php

namespace App\Filament\Support;

use App\Models\Course;
use App\Models\Institution;
use Closure;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;

/**
 * Name input for models using HasNameKey: unique ignoring case and extra
 * spaces, the same way the CSV import matches names.
 */
class NameKeyInput
{
    /**
     * @param  class-string<Institution|Course>  $model
     * @return TextInput
     */
    public static function make(string $model): TextInput
    {
        return TextInput::make('name')
            ->required()
            ->maxLength(255)
            ->rule(fn (?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($model, $record): void {
                $taken = $model::query()
                    ->where('name_key', $model::name_key((string) $value))
                    ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                    ->exists();

                if ($taken) {
                    $fail('That name is already in the list (names are matched ignoring capital letters and spaces).');
                }
            });
    }
}
