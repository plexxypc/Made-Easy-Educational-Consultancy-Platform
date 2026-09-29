<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One Paystack payment attempt for an order.
 *
 * `amount`, `paid_amount`, `status`, `raw_response`, `review_reason` and
 * `paid_at` are deliberately NOT mass-assignable: they change only in
 * InitiatePayment and ConfirmPaystackCharge.
 *
 * @property int $id
 * @property int $order_id
 * @property string $paystack_reference Our reference, sent to Paystack; unique idempotency key.
 * @property int $amount KOBO we asked Paystack to charge (the order total at the time).
 * @property int|null $paid_amount KOBO Paystack reported as charged (verified webhook).
 * @property string $currency
 * @property PaymentStatus $status
 * @property array<string, mixed>|null $raw_response Paystack payloads, keyed by stage ("initialize", "webhook").
 * @property string|null $review_reason Why a verified charge wasn't applied automatically.
 * @property Carbon|null $paid_at
 */
#[Fillable(['order_id', 'paystack_reference', 'currency'])]
class Payment extends Model
{
    /**
     * @var string
     */
    public const REFERENCE_PREFIX = 'MEPAY-';

    /**
     * Default attribute values (mirrors the column defaults).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'initiated',
        'currency' => 'NGN',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_amount' => 'integer',
            'status' => PaymentStatus::class,
            'raw_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * A new unique Paystack reference, e.g. "MEPAY-01J9ZK6X2W4T5Q8R7M3N0B1C2D".
     *
     * @return string
     */
    public static function newReference(): string
    {
        return self::REFERENCE_PREFIX.Str::upper((string) Str::ulid());
    }

    /**
     * Whether a verified charge arrived that a human must look at.
     *
     * @return bool
     */
    public function needsReview(): bool
    {
        return $this->review_reason !== null;
    }

    /**
     * Whether this attempt has been settled (successful, or flagged for review).
     * Settled payments are never processed again (webhook idempotency).
     *
     * @return bool
     */
    public function isSettled(): bool
    {
        return $this->status === PaymentStatus::Successful || $this->needsReview();
    }

    /**
     * Store a Paystack payload under the given stage, keeping earlier ones.
     *
     * @param  string  $stage  e.g. "initialize", "initialize_error", "webhook".
     * @param  array<string, mixed>  $payload
     * @return void
     */
    public function recordResponse(string $stage, array $payload): void
    {
        $this->raw_response = [...($this->raw_response ?? []), $stage => $payload];
    }

    /**
     * The order being paid for.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    /**
     * Receipt issued for this payment (successful payments only).
     *
     * @return HasOne<Receipt, $this>
     */
    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}
