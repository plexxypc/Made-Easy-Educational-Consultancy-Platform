<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One order status transition. Append-only; written by ChangeOrderStatus
 * (staff) and RespondToNeedsAction (customer).
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $changed_by User who made the change (null for guest customers).
 * @property bool $by_customer True when the customer made it (a "needs action" response).
 * @property OrderStatus $from_status
 * @property OrderStatus $to_status
 * @property string|null $note Staff: message to the customer. Customer: their response.
 * @property Carbon $created_at
 */
#[Fillable(['order_id', 'changed_by', 'by_customer', 'from_status', 'to_status', 'note'])]
class OrderStatusChange extends Model
{
    /**
     * @var null
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'by_customer' => 'boolean',
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
        ];
    }

    /**
     * Who made the change, for display: "Customer", the staff name, or "Made Easy team".
     *
     * @return string
     */
    public function actorLabel(): string
    {
        if ($this->by_customer) {
            return 'Customer';
        }

        return $this->changedBy?->name ?? 'Made Easy team';
    }

    /**
     * The order that changed.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * User who made the change (staff/admin, or a signed-in customer responding).
     *
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
