<?php

namespace App\Models;

use App\Enums\NigerianState;
use Database\Factories\AdmissionRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What an institution asks for a course: the answer to "can I get in with
 * my score?" (PDF section 11). Informational only.
 *
 * @property int $id
 * @property int $institution_id
 * @property int $course_id
 * @property int $cut_off_mark JAMB UTME score, 0–400.
 * @property int $olevel_credits_required O'Level credits, 1–9.
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['institution_id', 'course_id', 'cut_off_mark', 'olevel_credits_required'])]
class AdmissionRequirement extends Model
{
    /** @use HasFactory<AdmissionRequirementFactory> */
    use HasFactory;

    /**
     * Highest possible UTME score.
     *
     * @var int
     */
    public const MAX_SCORE = 400;

    /**
     * Most O'Level subjects that count (one sitting has at most 9).
     *
     * @var int
     */
    public const MAX_CREDITS = 9;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cut_off_mark' => 'integer',
            'olevel_credits_required' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Requirements a student meets. Every argument is optional; a null one
     * is simply not checked, so fewer answers mean more results.
     *
     * Matching rules (see the search page for how they are explained):
     *  - score: cut_off_mark <= score (meeting the cut-off exactly counts);
     *  - credits: olevel_credits_required <= credits;
     *  - course: the course name contains the text, any case
     *    ("computer" finds Computer Science and Computer Engineering);
     *  - state: the institution is in that state (institutions with no
     *    recorded state are left out when a state is chosen).
     *
     * @param  Builder<AdmissionRequirement>  $query
     * @param  int|null  $score  JAMB UTME score.
     * @param  int|null  $credits  O'Level credits the student has.
     * @param  string|null  $course_text  Part of a course name.
     * @param  NigerianState|null  $state
     * @return void
     */
    #[Scope]
    protected function matching(Builder $query, ?int $score, ?int $credits, ?string $course_text, ?NigerianState $state): void
    {
        // LIKE wildcards are dropped rather than escaped (SQLite has no default escape character).
        $course_key = Course::name_key(str_replace(['%', '_', '\\'], ' ', (string) $course_text));

        $query
            ->when($score !== null, fn (Builder $query) => $query->where('cut_off_mark', '<=', $score))
            ->when($credits !== null, fn (Builder $query) => $query->where('olevel_credits_required', '<=', $credits))
            ->when($course_key !== '', fn (Builder $query) => $query->whereHas(
                'course',
                fn (Builder $course_query) => $course_query->where('name_key', 'like', '%'.$course_key.'%'),
            ))
            ->when($state !== null, fn (Builder $query) => $query->whereHas(
                'institution',
                fn (Builder $institution_query) => $institution_query->where('state', $state->value),
            ));
    }
}
