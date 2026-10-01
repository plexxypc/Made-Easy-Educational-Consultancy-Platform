<?php

namespace App\Models;

use App\Enums\TestimonialStatus;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Customer testimonial (PDF section 15).
 *
 * Public pages must only ever use approved(); pending and rejected ones
 * exist for moderation only. Customers submit through
 * SubmitTestimonial (completed orders only, one per order).
 *
 * @property int $id
 * @property int|null $order_id
 * @property string $customer_name As the customer wants it shown, e.g. "Ada O.".
 * @property string $body
 * @property int|null $rating 1 to 5 stars, optional.
 * @property TestimonialStatus $status
 * @property Carbon|null $moderated_at
 * @property int|null $moderated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['customer_name', 'body', 'rating'])]
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory;

    /**
     * @var int
     */
    public const MAX_RATING = 5;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => TestimonialStatus::class,
            'moderated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /**
     * The only testimonials the public may see, newest approval first.
     *
     * @param  Builder<Testimonial>  $query
     * @return void
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', TestimonialStatus::Approved->value)
            ->orderByDesc('moderated_at')
            ->orderByDesc('id');
    }

    /**
     * Record a moderation decision.
     *
     * @param  TestimonialStatus  $status
     * @param  User  $moderator
     * @return void
     */
    public function moderate(TestimonialStatus $status, User $moderator): void
    {
        $this->forceFill([
            'status' => $status,
            'moderated_at' => now(),
            'moderated_by' => $moderator->id,
        ])->save();
    }
}
