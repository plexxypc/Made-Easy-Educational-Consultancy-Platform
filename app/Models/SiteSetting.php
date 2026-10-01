<?php

namespace App\Models;

use App\Enums\SiteSettingKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One piece of editable site copy (admin: Site settings).
 *
 * Read fresh from the database on every request (no cache), so an admin's
 * change shows on the next page load without a deploy or cache clear.
 *
 * @property int $id
 * @property SiteSettingKey $key
 * @property string|null $value NULL = the default (SiteSettingKey::default()).
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['key', 'value'])]
class SiteSetting extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => SiteSettingKey::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The value in effect: what an admin saved, else the default.
     *
     * @param  SiteSettingKey  $key
     * @return string|null
     */
    public static function current(SiteSettingKey $key): ?string
    {
        $value = self::query()->where('key', $key->value)->value('value');

        return filled($value) ? (string) $value : $key->default();
    }

    /**
     * A long-text setting split into paragraphs at blank lines.
     *
     * @param  SiteSettingKey  $key
     * @return array<int, string>
     */
    public static function paragraphs(SiteSettingKey $key): array
    {
        return self::split_paragraphs((string) self::current($key));
    }

    /**
     * Plain text to paragraphs: blank lines separate them, single line
     * breaks are kept inside a paragraph.
     *
     * @param  string  $text
     * @return array<int, string>
     */
    public static function split_paragraphs(string $text): array
    {
        $paragraphs = preg_split('/\R\s*\R/u', trim(str_replace("\r\n", "\n", $text))) ?: [];

        return array_values(array_filter(array_map('trim', $paragraphs), fn (string $paragraph): bool => $paragraph !== ''));
    }

    /**
     * Make sure every known setting has a row, so the settings screen lists
     * them all (including ones added in later releases).
     *
     * @return void
     */
    public static function ensure_all(): void
    {
        foreach (SiteSettingKey::cases() as $key) {
            self::query()->firstOrCreate(['key' => $key->value]);
        }
    }

    /**
     * Value for the settings form: the saved value, else the default, so the
     * admin edits the text actually on the site.
     *
     * @return string|null
     */
    public function editableValue(): ?string
    {
        return filled($this->value) ? $this->value : $this->key->default();
    }
}
