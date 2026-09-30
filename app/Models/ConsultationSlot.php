<?php

namespace App\Models;

use App\Enums\ConsultationType;
use Carbon\CarbonImmutable;
use Database\Factories\ConsultationSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A consultation time customers can book. Created by admins
 * (CreateConsultationSlots); nothing is mass-assignable.
 *
 * Times are wall-clock times in config('madeeasy.consultations.timezone');
 * use startsAt()/endsAt() rather than reading `date` and `start_time` directly.
 *
 * @property int $id
 * @property Carbon $date
 * @property string $start_time "HH:MM:SS"
 * @property int $duration_minutes
 * @property ConsultationType $consultation_type
 * @property int $capacity Bookings the slot takes.
 * @property int|null $created_by
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property-read int|null $occupied_count When loaded with withOccupiedCount().
 */
class ConsultationSlot extends Model
{
    /** @use HasFactory<ConsultationSlotFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'capacity' => 1,
        'duration_minutes' => 60,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'duration_minutes' => 'integer',
            'consultation_type' => ConsultationType::class,
            'capacity' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * The timezone slot times are written in.
     *
     * @return string
     */
    public static function timezone(): string
    {
        return (string) config('madeeasy.consultations.timezone');
    }

    /**
     * When the consultation starts (in the consultation timezone).
     *
     * @return CarbonImmutable
     */
    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date->format('Y-m-d').' '.substr((string) $this->start_time, 0, 8), self::timezone());
    }

    /**
     * When the consultation ends.
     *
     * @return CarbonImmutable
     */
    public function endsAt(): CarbonImmutable
    {
        return $this->startsAt()->addMinutes($this->duration_minutes);
    }

    /**
     * "Tuesday 6 October 2026".
     *
     * @return string
     */
    public function dateLabel(): string
    {
        return $this->startsAt()->format('l j F Y');
    }

    /**
     * "10:00am – 11:00am WAT".
     *
     * @return string
     */
    public function timeLabel(): string
    {
        return $this->startsAt()->format('g:ia').' – '.$this->endsAt()->format('g:ia T');
    }

    /**
     * "Tuesday 6 October 2026, 10:00am – 11:00am WAT".
     *
     * @return string
     */
    public function label(): string
    {
        return $this->dateLabel().', '.$this->timeLabel();
    }

    /**
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Whether the start time has passed.
     *
     * @return bool
     */
    public function hasStarted(): bool
    {
        return $this->startsAt()->lessThanOrEqualTo(now());
    }

    /**
     * Whether a customer may still pick this slot on the booking page, given
     * the minimum notice (ignores capacity; see ReserveConsultationSlot).
     *
     * @return bool
     */
    public function isOpenForBooking(): bool
    {
        return ! $this->isCancelled()
            && $this->startsAt()->greaterThan(now()->addMinutes((int) config('madeeasy.consultations.min_notice_minutes')));
    }

    /**
     * Bookings currently holding a place in this slot.
     *
     * @return int
     */
    public function occupiedCount(): int
    {
        return $this->occupied_count ?? $this->bookings()->occupying()->count();
    }

    /**
     * Places left.
     *
     * @return int
     */
    public function remainingCapacity(): int
    {
        return max(0, $this->capacity - $this->occupiedCount());
    }

    /**
     * Add `occupied_count` (bookings holding a place).
     *
     * @param  Builder<ConsultationSlot>  $query
     * @return void
     */
    #[Scope]
    protected function withOccupiedCount(Builder $query): void
    {
        $query->withCount(['bookings as occupied_count' => fn (Builder $bookings) => $bookings->occupying()]);
    }

    /**
     * Not cancelled, from today (consultation timezone) up to the booking
     * window. Times earlier today and full slots are filtered by the caller.
     *
     * @param  Builder<ConsultationSlot>  $query
     * @return void
     */
    #[Scope]
    protected function upcomingForBooking(Builder $query): void
    {
        $today = now(self::timezone())->startOfDay();

        $query->whereNull('cancelled_at')
            ->whereDate('date', '>=', $today->toDateString())
            ->whereDate('date', '<=', $today->addDays((int) config('madeeasy.consultations.booking_window_days'))->toDateString());
    }

    /**
     * All bookings of this slot, any status.
     *
     * @return HasMany<ConsultationBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(ConsultationBooking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
