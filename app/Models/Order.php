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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A customer's order for one service.
 *
 * `reference`, `user_id`, `quantity`, `status`, `total_amount` and the quote columns are deliberately NOT
 * mass-assignable: ownership and the amount are set server-side, and status only
 * changes through App\Actions\Orders\ChangeOrderStatus (or the payment webhook).
 *
 * @property int $id
 * @property string $reference Customer-facing: "ME-XXXXXX", or the old order number ("12") for orders placed before random references.
 * @property int|null $user_id Null for guest orders (guest_* columns filled instead).
 * @property Carbon|null $claimed_at When the account owner added this guest order to their account.
 * @property int $service_id
 * @property int $quantity Units ordered; above 1 only for result checkers.
 * @property OrderStatus $status
 * @property int $total_amount Amount in KOBO (service price × quantity).
 * @property string|null $guest_name
 * @property string|null $guest_email
 * @property string|null $guest_phone
 * @property array<string, mixed>|null $field_answers Keyed by Service::$customer_fields[*]['key'].
 * @property Carbon|null $quoted_at When staff set the price of a quote order.
 * @property int|null $quoted_by
 * @property-read string $customer_name
 * @property-read string|null $customer_email
 * @property-read string $display_reference "ME-XXXXXX", or "#12" for old numeric references.
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
        'quantity' => 1,
    ];

    /**
     * @var string
     */
    public const REFERENCE_PREFIX = 'ME-';

    /**
     * Crockford base32: no I, L, O or U, so a reference read aloud or typed
     * from a screenshot is hard to get wrong (see normaliseReference()).
     *
     * @var string
     */
    public const REFERENCE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * 32^6 ≈ 1.07 billion possible codes.
     *
     * @var int
     */
    public const REFERENCE_LENGTH = 6;

    /**
     * Give every new order a random reference.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (blank($order->reference)) {
                $order->reference = self::generateReference();
            }
        });
    }

    /**
     * A random, unused reference such as "ME-7K3Q9A".
     *
     * Business rule: references are not sequential, so knowing your own doesn't
     * reveal anyone else's. The unique index is the final guarantee.
     *
     * @return string
     */
    public static function generateReference(): string
    {
        do {
            $code = '';

            for ($position = 0; $position < self::REFERENCE_LENGTH; $position++) {
                $code .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
            }

            $reference = self::REFERENCE_PREFIX.$code;
        } while (self::query()->withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Turn what a customer typed into the stored form.
     *
     * "me 7k3q9a", "ME7K3Q9A" and "#ME-7K3Q9A" all become "ME-7K3Q9A"; the
     * look-alike letters O, I and L are read as 0, 1 and 1 (Crockford). Old
     * numeric references ("#12") become "12".
     *
     * @param  string  $input
     * @return string
     */
    public static function normaliseReference(string $input): string
    {
        $reference = strtoupper((string) preg_replace('/[\s#]+/', '', $input));

        if (preg_match('/^ME-?([0-9A-Z]{'.self::REFERENCE_LENGTH.'})$/', $reference, $match)) {
            return self::REFERENCE_PREFIX.strtr($match[1], ['O' => '0', 'I' => '1', 'L' => '1']);
        }

        return mb_substr($reference, 0, 20);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'quantity' => 'integer',
            'status' => OrderStatus::class,
            'total_amount' => 'integer',
            'field_answers' => 'array',
            'quoted_at' => 'datetime',
            'claimed_at' => 'datetime',
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
     * Whether this is a result checker order (instant fulfilment: PINs from stock).
     *
     * @return bool
     */
    public function isResultChecker(): bool
    {
        return $this->service?->fulfilment_type === FulfilmentType::Instant;
    }

    /**
     * Whether this is a consultation booking order (see consultationBooking()).
     *
     * @return bool
     */
    public function isConsultation(): bool
    {
        return $this->service?->fulfilment_type === FulfilmentType::Consultation;
    }

    /**
     * Whether PINs may be allocated to this order now: a paid (or in-progress)
     * result checker order that hasn't been deleted.
     *
     * @return bool
     */
    public function canReceivePins(): bool
    {
        return $this->isResultChecker()
            && ! $this->trashed()
            && in_array($this->status, [OrderStatus::Paid, OrderStatus::InProgress], true);
    }

    /**
     * PINs paid for but not yet allocated (0 for anything that isn't a paid result checker order).
     *
     * Business rule (PIN stock fallback): when stock runs out at payment time
     * the order stays paid and this number is what staff still owe the
     * customer ("Deliver PIN manually" on the order screen).
     *
     * @return int
     */
    public function undeliveredPinCount(): int
    {
        if (! $this->canReceivePins()) {
            return 0;
        }

        return max(0, $this->quantity - $this->pins()->count());
    }

    /**
     * Paid result checker orders still owed PINs (the query form of
     * undeliveredPinCount() > 0), e.g. to serve them after a stock upload.
     *
     * @param  Builder<Order>  $query
     * @return void
     */
    #[Scope]
    protected function waitingForPins(Builder $query): void
    {
        $query->whereIn('status', [OrderStatus::Paid->value, OrderStatus::InProgress->value])
            ->whereHas('service', fn (Builder $service_query) => $service_query->where('fulfilment_type', FulfilmentType::Instant->value))
            ->where('quantity', '>', fn ($pin_count_query) => $pin_count_query
                ->selectRaw('count(*)')
                ->from('result_checker_pins')
                ->whereColumn('result_checker_pins.order_id', 'orders.id'));
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
     * Business rules:
     *  - A quote order whose total hasn't been set yet may only be cancelled;
     *    it can't be worked on or completed at an unknown price.
     *  - An order still owing money (awaitingPayment()) can't be worked on or
     *    completed. It moves on once the verified Paystack webhook marks it paid,
     *    or straight away if its total is ₦0. Needs action and cancel stay available.
     *  - A result checker order can't be marked completed while PINs are still
     *    owed; it completes itself once every PIN has been delivered.
     *  - A consultation order is completed or cancelled only through its
     *    booking (Consultations → Bookings: completed, no-show, cancel), so the
     *    order and booking never disagree and the slot is freed correctly.
     *
     * @param  OrderStatus  $next_status
     * @return bool
     */
    public function canTransitionTo(OrderStatus $next_status): bool
    {
        if (! $this->status->canTransitionTo($next_status)) {
            return false;
        }

        if ($this->isConsultation() && in_array($next_status, [OrderStatus::Completed, OrderStatus::Cancelled], true)) {
            return false;
        }

        if ($this->awaitsQuote()) {
            return $next_status === OrderStatus::Cancelled;
        }

        if ($next_status === OrderStatus::Completed && $this->isResultChecker() && $this->pins()->count() < $this->quantity) {
            return false;
        }

        $progresses_work = in_array($next_status, [OrderStatus::InProgress, OrderStatus::Completed], true);

        return ! ($progresses_work && $this->awaitingPayment());
    }

    /**
     * Whether the order still owes money: a non-zero total and not yet paid.
     *
     * Covers pending_payment, and needs_action entered from pending_payment
     * (otherwise needs_action -> in_progress would skip payment).
     *
     * @return bool
     */
    public function awaitingPayment(): bool
    {
        if ($this->total_amount <= 0) {
            return false;
        }

        return match ($this->status) {
            OrderStatus::PendingPayment => true,
            OrderStatus::NeedsAction => $this->statusBeforeNeedsAction() === OrderStatus::PendingPayment,
            default => false,
        };
    }

    /**
     * Why the customer can't pay right now, or null if they can.
     *
     * Business rule: only a pending_payment order with a total above zero can
     * be paid, and a quote order only after an admin has used "Set Total".
     *
     * @return string|null
     */
    public function paymentBlocker(): ?string
    {
        return match (true) {
            $this->trashed() => 'This request is no longer available.',
            $this->status === OrderStatus::Paid || $this->receipt()->exists() => 'This request has already been paid.',
            $this->status !== OrderStatus::PendingPayment => "This request can't be paid while it is \"{$this->status->getLabel()}\".",
            $this->awaitsQuote() => "We're still preparing the price for this request. You'll be able to pay once we've confirmed it; we'll email you.",
            $this->total_amount <= 0 => 'There is nothing to pay for this request.',
            default => null,
        };
    }

    /**
     * Whether "Pay now" should be offered.
     *
     * @return bool
     */
    public function canBePaid(): bool
    {
        return $this->paymentBlocker() === null;
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
     * Reference as shown to people: "ME-7K3Q9A", or "#12" for old numeric ones.
     *
     * @return Attribute<string, never>
     */
    protected function displayReference(): Attribute
    {
        return Attribute::get(fn (): string => ctype_digit((string) $this->reference) ? '#'.$this->reference : (string) $this->reference);
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
     * Result checker PINs allocated to this order, in allocation order.
     *
     * @return HasMany<ResultCheckerPin, $this>
     */
    public function pins(): HasMany
    {
        return $this->hasMany(ResultCheckerPin::class)->orderBy('allocated_at')->orderBy('id');
    }

    /**
     * The consultation booking made with this order (consultation orders only).
     *
     * @return HasOne<ConsultationBooking, $this>
     */
    public function consultationBooking(): HasOne
    {
        return $this->hasOne(ConsultationBooking::class);
    }

    /**
     * Service name with the quantity, e.g. "WAEC Result Checker × 3".
     *
     * @return string
     */
    public function serviceLabel(): string
    {
        $name = (string) $this->service?->name;

        return $this->quantity > 1 ? "{$name} × {$this->quantity}" : $name;
    }

    /**
     * Paystack payment attempts, newest first.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('created_at')->latest('id');
    }

    /**
     * The receipt issued when a verified payment marked this order paid.
     *
     * @return HasOneThrough<Receipt, Payment, $this>
     */
    public function receipt(): HasOneThrough
    {
        return $this->hasOneThrough(Receipt::class, Payment::class);
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
