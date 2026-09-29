<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit row: a staff/admin user viewed an order document. Append-only.
 *
 * @property int $id
 * @property int $order_document_id
 * @property int $user_id
 * @property string|null $ip_address
 * @property \Illuminate\Support\Carbon $viewed_at
 */
#[Fillable(['order_document_id', 'user_id', 'ip_address'])]
class DocumentAccessLog extends Model
{
    /**
     * `viewed_at` doubles as created_at; rows are never updated.
     *
     * @var string
     */
    public const CREATED_AT = 'viewed_at';

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
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * The document that was viewed.
     *
     * @return BelongsTo<OrderDocument, $this>
     */
    public function orderDocument(): BelongsTo
    {
        return $this->belongsTo(OrderDocument::class);
    }

    /**
     * The staff/admin user who viewed it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
