<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit row: a staff/admin user viewed sensitive customer data. Append-only.
 *
 * Exactly one subject is set: an uploaded order document (LogDocumentAccess)
 * or a result checker PIN (LogPinAccess). The table keeps its original name.
 *
 * @property int $id
 * @property int|null $order_document_id
 * @property int|null $result_checker_pin_id
 * @property int $user_id
 * @property string|null $ip_address
 * @property Carbon $viewed_at
 */
#[Fillable(['order_document_id', 'result_checker_pin_id', 'user_id', 'ip_address'])]
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
     * The order the viewed document or PIN belongs to.
     *
     * @return Order|null
     */
    public function subjectOrder(): ?Order
    {
        return $this->orderDocument?->order ?? $this->resultCheckerPin?->order;
    }

    /**
     * What was viewed, for display: the document filename or "Result checker PIN (service)".
     *
     * @return string
     */
    public function subjectLabel(): string
    {
        return match (true) {
            $this->orderDocument !== null => $this->orderDocument->original_filename,
            $this->resultCheckerPin !== null => 'Result checker PIN ('.$this->resultCheckerPin->service?->name.')',
            default => '—',
        };
    }

    /**
     * The document that was viewed (null for PIN views).
     *
     * @return BelongsTo<OrderDocument, $this>
     */
    public function orderDocument(): BelongsTo
    {
        return $this->belongsTo(OrderDocument::class);
    }

    /**
     * The PIN that was viewed (null for document views).
     *
     * @return BelongsTo<ResultCheckerPin, $this>
     */
    public function resultCheckerPin(): BelongsTo
    {
        return $this->belongsTo(ResultCheckerPin::class);
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
