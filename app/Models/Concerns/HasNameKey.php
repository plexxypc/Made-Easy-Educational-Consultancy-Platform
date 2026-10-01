<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Keeps a `name_key` column (unique) in step with `name`, so records are
 * matched by name regardless of case and spacing. Assumption: names that
 * differ only in case or spacing are the same institution/course; spelling
 * variants and abbreviations ("UNILAG") are NOT matched.
 *
 * @mixin Model
 */
trait HasNameKey
{
    /**
     * @return void
     */
    protected static function bootHasNameKey(): void
    {
        static::saving(function (Model $model): void {
            $model->setAttribute('name', Str::squish((string) $model->getAttribute('name')));
            $model->setAttribute('name_key', self::name_key((string) $model->getAttribute('name')));
        });
    }

    /**
     * Comparison key for a name: lowercased, spaces squeezed.
     *
     * @param  string  $name
     * @return string
     */
    public static function name_key(string $name): string
    {
        return mb_strtolower(Str::squish($name));
    }
}
