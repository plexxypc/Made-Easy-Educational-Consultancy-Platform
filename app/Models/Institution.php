<?php

namespace App\Models;

use App\Enums\InstitutionOwnership;
use App\Enums\InstitutionType;
use App\Enums\NigerianState;
use App\Models\Concerns\HasNameKey;
use Database\Factories\InstitutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A university, polytechnic or college of education (course & cut-off search).
 *
 * type, ownership and state may be empty for institutions created by the CSV
 * import from a name only; see isMissingDetails().
 *
 * @property int $id
 * @property string $name
 * @property string $name_key See HasNameKey.
 * @property array<int, string>|null $aliases Alternate names, matched by the CSV import only.
 * @property InstitutionType|null $type
 * @property InstitutionOwnership|null $ownership
 * @property NigerianState|null $state
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['name', 'aliases', 'type', 'ownership', 'state'])]
class Institution extends Model
{
    /** @use HasFactory<InstitutionFactory> */
    use HasFactory, HasNameKey;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'type' => InstitutionType::class,
            'ownership' => InstitutionOwnership::class,
            'state' => NigerianState::class,
        ];
    }

    /**
     * Tidy aliases on save: spaces squeezed, blanks, repeats and the
     * institution's own name dropped; an empty list is stored as null.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (Institution $institution): void {
            $own_key = self::name_key((string) $institution->name);

            $aliases = collect($institution->aliases ?? [])
                ->map(fn (mixed $alias): string => Str::squish((string) $alias))
                ->filter(fn (string $alias): bool => $alias !== '' && self::name_key($alias) !== $own_key)
                ->unique(fn (string $alias): string => self::name_key($alias))
                ->values()
                ->all();

            $institution->aliases = $aliases === [] ? null : $aliases;
        });
    }

    /**
     * Name keys of this institution's aliases.
     *
     * @return array<int, string>
     */
    public function aliasKeys(): array
    {
        return array_map(fn (string $alias): string => self::name_key($alias), $this->aliases ?? []);
    }

    /**
     * Institutions by alias key. Loaded in full and matched in PHP so it
     * works the same on SQLite and Postgres; the list is a few hundred rows.
     *
     * @return array<string, Institution>
     */
    public static function by_alias_key(): array
    {
        $by_alias = [];

        foreach (self::query()->whereNotNull('aliases')->get() as $institution) {
            foreach ($institution->aliasKeys() as $alias_key) {
                $by_alias[$alias_key] = $institution;
            }
        }

        return $by_alias;
    }

    /**
     * Another institution already using this name or alias, if any.
     *
     * @param  string  $name
     * @param  int|null  $ignore_id  The institution being edited.
     * @return Institution|null
     */
    public static function claiming_name(string $name, ?int $ignore_id = null): ?Institution
    {
        $key = self::name_key($name);

        $by_name = self::query()
            ->where('name_key', $key)
            ->when($ignore_id, fn ($query) => $query->whereKeyNot($ignore_id))
            ->first();

        if ($by_name !== null) {
            return $by_name;
        }

        $by_alias = self::by_alias_key()[$key] ?? null;

        return $by_alias !== null && $by_alias->id !== $ignore_id ? $by_alias : null;
    }

    /**
     * @return HasMany<AdmissionRequirement, $this>
     */
    public function admissionRequirements(): HasMany
    {
        return $this->hasMany(AdmissionRequirement::class);
    }

    /**
     * Whether type, ownership or state still needs filling in.
     *
     * @return bool
     */
    public function isMissingDetails(): bool
    {
        return $this->type === null || $this->ownership === null || $this->state === null;
    }

    /**
     * e.g. "Federal university · Lagos", leaving out anything not recorded.
     *
     * @return string
     */
    public function descriptionLabel(): string
    {
        $kind = trim(($this->ownership?->getLabel() ?? '').' '.($this->type ? mb_strtolower($this->type->getLabel()) : ''));

        return collect([ucfirst($kind), $this->state?->getLabel()])->filter()->implode(' · ');
    }
}
