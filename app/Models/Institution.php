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

/**
 * A university, polytechnic or college of education (course & cut-off search).
 *
 * type, ownership and state may be empty for institutions created by the CSV
 * import from a name only; see isMissingDetails().
 *
 * @property int $id
 * @property string $name
 * @property string $name_key See HasNameKey.
 * @property InstitutionType|null $type
 * @property InstitutionOwnership|null $ownership
 * @property NigerianState|null $state
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['name', 'type', 'ownership', 'state'])]
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
            'type' => InstitutionType::class,
            'ownership' => InstitutionOwnership::class,
            'state' => NigerianState::class,
        ];
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
