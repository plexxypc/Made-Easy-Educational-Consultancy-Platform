<?php

namespace App\Models;

use App\Models\Concerns\HasNameKey;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A course of study, e.g. "Computer Science" (course & cut-off search).
 *
 * @property int $id
 * @property string $name
 * @property string $name_key See HasNameKey.
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['name'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory, HasNameKey;

    /**
     * @return HasMany<AdmissionRequirement, $this>
     */
    public function admissionRequirements(): HasMany
    {
        return $this->hasMany(AdmissionRequirement::class);
    }
}
