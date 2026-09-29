<?php

namespace App\Models;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A customer's order for one service.
 *
 * `user_id`, `status`, `total_amount` and the quote columns are deliberately NOT
 * mass-assignable: ownership and the amount are set server-side, and status only
 * changes through App\Actions\Orders\ChangeOrderStatus (or the payment webhook).
 *
 * @property int $id
 * @property int|null $user_id Null for guest orders (guest_* columns filled instead).
 * @property int $service_id
 * @property OrderStatus $status
 * @property int $total_amount Amount in KOBO.
 * @property string|null $guest_name
 * @property string|null $guest_email
 * @property string|null $guest_phone
 * @property array<string, mixed>|null $field_answers Keyed by Service::$customer_fields[*]['key'].
 * @property Carbon|null $quoted_at When staff set the price of a quote order.
 * @property int|null $quoted_by
 * @property-read string $customer_name
 * @property-read string|null $customer_email
 */
#[Fillable(['service_id', 'guest_name', 'guest_email', 'guest_phone', 'field_answers'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Default attribute values (mirrors the column default).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending_payment',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'status' => OrderStatus::class,
            'total_amount' => 'integer',
            'field_answers' => 'array',
            'quoted_at' => 'datetime',
        ];
    }

    /**
     * Orders the given user may see: all for staff/admin, own orders for customers.
     * Use this for listings; use the OrderPolicy for single records.
     *
     * @param  Builder<Order>  $query
     * @param  User  $user
     * @return void
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->isStaffOrAdmin()) {
            $query->where('user_id', $user->id);
        }
    }

    /**
     * Whether the given user placed this order.
     *
     * @param  User  $user
     * @return bool
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id !== null && $this->user_id === $user->id;
    }

    /**
     * Whether this order's price is set by staff (quote fulfilment).
     *
     * @return bool
     */
    public function isQuote(): bool
    {
        return $this->service?->fulfilment_type === FulfilmentType::Quote;
    }

    /**
     * Whether a quote order still has no staff-determined total.
     *
     * @return bool
     */
    public function awaitsQuote(): bool
    {
        return $this->isQuote() && $this->quoted_at === null;
    }

    /**
     * Whether the order may move to the given status.
     *
     * Business rule: a quote order whose total hasn't been set yet may only be
     * cancelled; it can't be worked on or completed at an unknown price.
     *
     * @param  OrderStatus  $next_status
     * @return bool
     */
    public function canTransitionTo(OrderStatus $next_status): bool
    {
        if (! $this->status->canTransitionTo($next_status)) {
            return false;
        }

        return ! $this->awaitsQuote() || $next_status === OrderStatus::Cancelled;
    }

    /**
     * Status to return to once the customer responds to "needs action".
     *
     * Business rule: restore the status the order had when staff marked it
     * "needs action" (order_status_changes.from_status of the latest such change).
     * Only pending_payment, paid and in_progress are restorable; anything else,
     * or missing history, falls back to in_progress. Restoring "paid" never marks
     * anything newly paid: it only returns to a status the webhook already set.
     *
     * @return OrderStatus
     */
    public function statusBeforeNeedsAction(): OrderStatus
    {
        $previous_status = $this->statusChanges()
            ->where('to_status', OrderStatus::NeedsAction->value)
            ->where('by_customer', false)
            ->value('from_status');

        $previous_status = $previous_status instanceof OrderStatus ? $previous_status : OrderStatus::tryFrom((string) $previous_status);

        return in_array($previous_status, [OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::InProgress], true)
            ? $previous_status
            : OrderStatus::InProgress;
    }

    /**
     * The staff note from the most recent "needs action" change (what the customer must do).
     *
     * @return ?string
     */
    public function needsActionNote(): ?string
    {
        return $this->statusChanges()
            ->where('to_status', OrderStatus::NeedsAction->value)
            ->where('by_customer', false)
            ->value('note');
    }

    /**
     * Name of the customer: the account name, or the guest name for guest orders.
     *
     * @return Attribute<string, never>
     */
    protected function customerName(): Attribute
    {
        return Attribute::get(fn (): string => $this->user?->name ?? $this->guest_name ?? 'Unknown customer');
    }

    /**
     * Email to contact the customer on (account email, else guest email).
     *
     * @return Attribute<string|null, never>
     */
    protected function customerEmail(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->user?->email ?? $this->guest_email);
    }

    /**
     * Submitted answers keyed by the field LABEL, in the service's field order,
     * for display. Answers to fields no longer on the service are kept at the end.
     *
     * @return array<string, string>
     */
    public function labelledFieldAnswers(): array
    {
        $answers = $this->field_answers ?? [];
        $labelled_answers = [];

        foreach ($this->service?->customer_fields ?? [] as $field) {
            if (array_key_exists($field['key'], $answers)) {
                $labelled_answers[$field['label']] = self::stringify_answer($answers[$field['key']]);
                unset($answers[$field['key']]);
            }
        }

        foreach ($answers as $key => $value) {
            $labelled_answers[(string) str($key)->headline()] = self::stringify_answer($value);
        }

        return $labelled_answers;
    }

    /**
     * Render a stored answer as display text.
     *
     * @param  mixed  $value
     * @return string
     */
    private static function stringify_answer(mixed $value): string
    {
        return match (true) {
            is_array($value) => implode(', ', array_map('strval', $value)),
            is_bool($value) => $value ? 'Yes' : 'No',
            $value === null || $value === '' => '—',
            default => (string) $value,
        };
    }

    /**
     * The customer who placed the order (null for guest orders).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The service ordered.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Staff member who set the quote total.
     *
     * @return BelongsTo<User, $this>
     */
    public function quotedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quoted_by');
    }

    /**
     * Documents uploaded for this order.
     *
     * @return HasMany<OrderDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(OrderDocument::class);
    }

    /**
     * Status history, newest first.
     *
     * @return HasMany<OrderStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(OrderStatusChange::class)->latest('created_at')->latest('id');
    }
}
