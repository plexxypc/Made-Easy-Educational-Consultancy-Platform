<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Receipt for a verified payment. A snapshot: its details are copied when the
 * payment is confirmed and never change afterwards.
 *
 * @property int $id
 * @property int $payment_id
 * @property string $order_reference Stored reference, e.g. "ME-7K3Q9A" (or "12" for old orders).
 * @property string $payment_reference Paystack reference.
 * @property string $customer_name
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property string $service_name
 * @property int $amount KOBO.
 * @property string $currency
 * @property string $payment_status
 * @property Carbon $paid_at
 * @property-read string $number e.g. "MER-000042".
 * @property-read string $amount_label e.g. "₦15,000.00".
 */
#[Fillable([
    'payment_id', 'order_reference', 'payment_reference', 'customer_name', 'customer_email',
    'customer_phone', 'service_name', 'amount', 'currency', 'payment_status', 'paid_at',
])]
class Receipt extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Receipt number from the id. Assumption: numbers are sequential for
     * bookkeeping; a rare gap is possible if a database transaction rolls back.
     *
     * @return Attribute<string, never>
     */
    protected function number(): Attribute
    {
        return Attribute::get(fn (): string => 'MER-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT));
    }

    /**
     * @return Attribute<string, never>
     */
    protected function amountLabel(): Attribute
    {
        return Attribute::get(fn (): string => Money::format($this->amount));
    }

    /**
     * Order reference as shown to people ("#12" for old numeric ones).
     *
     * @return string
     */
    public function displayOrderReference(): string
    {
        return ctype_digit($this->order_reference) ? '#'.$this->order_reference : $this->order_reference;
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
